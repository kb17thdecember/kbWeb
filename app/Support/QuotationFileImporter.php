<?php

namespace App\Support;

use App\Models\KflexPrice;
use App\Models\Product;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class QuotationFileImporter
{
    /**
     * Import an exported quotation file (.xlsx, .json) and return its structured state payload.
     *
     * @return array<string, mixed>
     */
    public function import(string $filePath, string $originalName = ''): array
    {
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $filePath, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            $raw = (string) file_get_contents($filePath);
            $decoded = json_decode($raw, true);
            if (! is_array($decoded) || ! in_array($decoded['activeQuotationType'] ?? '', ['ariston', 'second'], true)) {
                throw new RuntimeException('File JSON không đúng định dạng báo giá.');
            }

            return $decoded;
        }

        return $this->importXlsx($filePath);
    }

    /**
     * @return array<string, mixed>
     */
    private function importXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Cần cài đặt PHP zip extension để đọc file Excel (.xlsx).');
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('File không phải là file báo giá Excel (.xlsx) hợp lệ.');
        }

        $embeddedState = null;
        $sheetXml = false;
        $sharedStringsXml = null;
        $drawingXml = null;
        $drawingRelsXml = null;
        $rowImages = [];

        try {
            $embeddedJson = $archive->getFromName('xl/customData/quotation.json');
            if (is_string($embeddedJson) && $embeddedJson !== '') {
                $decoded = json_decode($embeddedJson, true);
                if (is_array($decoded)) {
                    $embeddedState = $decoded;
                }
            }

            $sheetXml = $archive->getFromName($this->firstSheetPath($archive));
            $sharedStringsXml = $archive->getFromName('xl/sharedStrings.xml') ?: null;
            $drawingXml = $archive->getFromName('xl/drawings/drawing1.xml') ?: null;
            $drawingRelsXml = $archive->getFromName('xl/drawings/_rels/drawing1.xml.rels') ?: null;

            if ($drawingXml !== null && $drawingRelsXml !== null) {
                $rowImages = $this->extractRowImages($archive, $drawingXml, $drawingRelsXml);
            }
        } finally {
            $archive->close();
        }

        if ($sheetXml === false) {
            if ($embeddedState !== null) {
                return $embeddedState;
            }
            throw new RuntimeException('Không đọc được trang tính đầu tiên của file Excel báo giá.');
        }

        $sharedStrings = $this->sharedStrings($sharedStringsXml);
        [$grid, $merges, $maxRow] = $this->parseWorksheet($sheetXml, $sharedStrings);

        if ($grid === []) {
            if ($embeddedState !== null) {
                return $embeddedState;
            }
            throw new RuntimeException('File Excel báo giá không có dữ liệu.');
        }

        $isKflex = false;
        for ($r = 1; $r <= min($maxRow, 20); $r++) {
            $c1 = mb_strtoupper($this->cellText($grid, $r, 1), 'UTF-8');
            $c2 = mb_strtoupper($this->cellText($grid, $r, 2), 'UTF-8');
            if (
                str_contains($c1, 'K-FLEX')
                || str_contains($c1, 'BẢO ÔN')
                || ($c1 === 'TT' && str_contains($c2, 'TÊN VẬT TƯ'))
            ) {
                $isKflex = true;
                break;
            }
        }

        $todayIso = now()->format('Y-m-d');

        if ($isKflex) {
            return $this->parseKflexWorksheet($grid, $merges, $maxRow, $embeddedState, $todayIso);
        }

        return $this->parseAristonWorksheet($grid, $maxRow, $rowImages, $embeddedState, $todayIso);
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @param  array<string, string>  $rowImages
     * @param  array<string, mixed>|null  $embeddedState
     * @return array<string, mixed>
     */
    private function parseAristonWorksheet(
        array $grid,
        int $maxRow,
        array $rowImages,
        ?array $embeddedState,
        string $todayIso,
    ): array {
        $baseForm = is_array($embeddedState['quoteForm'] ?? null) ? $embeddedState['quoteForm'] : [];
        $quoteForm = [
            'documentTitle' => $baseForm['documentTitle'] ?? ($this->cellText($grid, 5, 1) ?: 'BÁO GIÁ BƠM NHIỆT ARISTON'),
            'recipientCompany' => $baseForm['recipientCompany'] ?? '',
            'recipientPerson' => $baseForm['recipientPerson'] ?? '',
            'contactPhone' => '',
            'quoteDate' => $baseForm['quoteDate'] ?? $todayIso,
            'validUntilDate' => $baseForm['validUntilDate'] ?? now()->addMonths(2)->format('Y-m-d'),
            'projectName' => $baseForm['projectName'] ?? '',
            'vatPercent' => (int) ($baseForm['vatPercent'] ?? 8),
            'signerName' => $baseForm['signerName'] ?? '',
            'signerPhone' => $baseForm['signerPhone'] ?? '',
            'signerEmail' => $baseForm['signerEmail'] ?? '',
        ];

        $titleCell = $this->cellText($grid, 5, 1);
        if ($titleCell !== '') {
            $quoteForm['documentTitle'] = $titleCell;
        }

        $tableHeaderRow = 0;
        for ($r = 5; $r <= min($maxRow, 20); $r++) {
            $c1 = $this->cellText($grid, $r, 1);
            $c2 = $this->cellText($grid, $r, 2) ?: $this->cellText($grid, $r, 3);
            $c1Lower = mb_strtolower($c1, 'UTF-8');

            if (str_starts_with($c1Lower, 'kính gửi')) {
                $quoteForm['recipientCompany'] = $c2;
            } elseif (str_starts_with($c1Lower, 'người nhận')) {
                $quoteForm['recipientPerson'] = $c2;
            } elseif (str_starts_with($c1Lower, 'thông tin liên lạc')) {
                $quoteForm['contactPhone'] = $c2;
            } elseif (str_starts_with($c1Lower, 'thời gian')) {
                $quoteForm['quoteDate'] = $this->parseSlashDateToIso($c2, $todayIso);
            } elseif (str_starts_with($c1Lower, 'project')) {
                $quoteForm['projectName'] = $c2;
            } elseif (
                str_contains($c1Lower, 'sản phẩm')
                && str_contains(mb_strtolower($this->cellText($grid, $r, 2), 'UTF-8'), 'mã sản phẩm')
            ) {
                $tableHeaderRow = $r;
                break;
            }
        }

        $aristonProducts = Product::query()->with('images')->get();
        $items = [];
        $summaryStartRow = $maxRow + 1;

        if ($tableHeaderRow > 0) {
            $embeddedIdx = 0;
            for ($r = $tableHeaderRow + 1; $r <= $maxRow; $r++) {
                $c1 = $this->cellText($grid, $r, 1);
                if (str_starts_with(mb_strtoupper($c1, 'UTF-8'), 'TỔNG CỘNG')) {
                    $summaryStartRow = $r;
                    break;
                }

                $c2 = $this->cellText($grid, $r, 2);
                $c3 = $this->cellText($grid, $r, 3);
                $c5 = $this->cellText($grid, $r, 5);
                $c6 = $this->cellText($grid, $r, 6);
                $c7 = $this->cellText($grid, $r, 7);
                $c8 = $this->cellText($grid, $r, 8);

                if ($c1 === '' && $c2 === '' && $c6 === '' && $c7 === '') {
                    continue;
                }

                $matchedProd = $c2 !== ''
                    ? $aristonProducts->first(fn (Product $p): bool => mb_strtolower(trim($p->name), 'UTF-8') === mb_strtolower(trim($c2), 'UTF-8'))
                    : null;

                $embeddedItem = is_array($embeddedState['items'][$embeddedIdx] ?? null)
                    ? $embeddedState['items'][$embeddedIdx]
                    : [];
                $embeddedIdx++;

                $imageUrl = '';
                if ($matchedProd !== null && ! empty($matchedProd->image[0])) {
                    $imageUrl = $matchedProd->image[0];
                } elseif (! empty($embeddedItem['imageUrl'])) {
                    $imageUrl = (string) $embeddedItem['imageUrl'];
                } elseif (isset($rowImages["{$r}:4"])) {
                    $imageUrl = $rowImages["{$r}:4"];
                }

                $items[] = [
                    'uid' => 'imp-ariston-'.$r.'-'.uniqid(),
                    'productId' => $matchedProd?->id ?? ($embeddedItem['productId'] ?? null),
                    'productLabel' => $c1 !== '' ? $c1 : ($embeddedItem['productLabel'] ?? 'Máy nước nóng gián tiếp Ariston'),
                    'modelCode' => $c2,
                    'origin' => $c3 !== '' ? $c3 : ($embeddedItem['origin'] ?? 'Ý-Việt Nam'),
                    'imageUrl' => $imageUrl,
                    'unit' => $c5 !== '' ? $c5 : ($embeddedItem['unit'] ?? 'Máy'),
                    'specifications' => $c6,
                    'unitPrice' => max(0, (int) round($this->parseFormattedNumber($c7, (float) ($embeddedItem['unitPrice'] ?? 0)))),
                    'quantity' => max(1, (int) round($this->parseFormattedNumber($c8, (float) ($embeddedItem['quantity'] ?? 1)))),
                ];
            }
        }

        $afterThanks = false;
        $signerNameFound = false;

        for ($r = $summaryStartRow; $r <= $maxRow; $r++) {
            $c1 = $this->cellText($grid, $r, 1);
            if ($c1 === '') {
                continue;
            }
            $c1Lower = mb_strtolower($c1, 'UTF-8');

            if (str_starts_with($c1Lower, 'thuế vat')) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $c1, $m) === 1) {
                    $quoteForm['vatPercent'] = (int) $m[1];
                }
            } elseif (str_contains($c1Lower, 'báo giá có hiệu lực đến ngày')) {
                $parts = explode(':', $c1, 2);
                $datePart = trim($parts[1] ?? '');
                if ($datePart !== '') {
                    $quoteForm['validUntilDate'] = $this->parseSlashDateToIso($datePart, $quoteForm['validUntilDate']);
                }
            } elseif (str_contains($c1Lower, 'many thanks')) {
                $afterThanks = true;
                $quoteForm['signerName'] = '';
                $quoteForm['signerPhone'] = '';
                $quoteForm['signerEmail'] = '';
            } elseif ($afterThanks) {
                if (preg_match('/^hp\s*:\s*(.*)$/iu', $c1, $m) === 1) {
                    $quoteForm['signerPhone'] = trim($m[1]);
                } elseif (preg_match('/^email\s*:\s*(.*)$/iu', $c1, $m) === 1) {
                    $quoteForm['signerEmail'] = trim($m[1]);
                } elseif (! $signerNameFound) {
                    $quoteForm['signerName'] = $c1;
                    $signerNameFound = true;
                }
            }
        }

        return [
            'version' => 1,
            'activeQuotationType' => 'ariston',
            'quoteForm' => $quoteForm,
            'items' => $items !== [] ? $items : ($embeddedState['items'] ?? []),
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @param  array<int, array{r1: int, c1: int, r2: int, c2: int}>  $merges
     * @param  array<string, mixed>|null  $embeddedState
     * @return array<string, mixed>
     */
    private function parseKflexWorksheet(
        array $grid,
        array $merges,
        int $maxRow,
        ?array $embeddedState,
        string $todayIso,
    ): array {
        foreach ($merges as $m) {
            if ($m['c1'] === 14 && $m['c2'] === 14 && $m['r2'] > $m['r1']) {
                $topNote = $this->cellText($grid, $m['r1'], 14);
                if ($topNote !== '') {
                    for ($rr = $m['r1'] + 1; $rr <= $m['r2']; $rr++) {
                        if (($grid[$rr][14] ?? '') === '') {
                            $grid[$rr][14] = $topNote;
                        }
                    }
                }
            }
        }

        $baseKf = is_array($embeddedState['kflexQuoteForm'] ?? null) ? $embeddedState['kflexQuoteForm'] : [];
        $kflexQuoteForm = [
            'documentTitle' => $baseKf['documentTitle'] ?? ($this->cellText($grid, 5, 1) ?: 'BÁO GIÁ BẢO ÔN K-FLEX'),
            'recipientCompany' => $baseKf['recipientCompany'] ?? '',
            'recipientPerson' => $baseKf['recipientPerson'] ?? '',
            'projectName' => $baseKf['projectName'] ?? '',
            'quoteDate' => $baseKf['quoteDate'] ?? $todayIso,
            'validUntilDate' => $baseKf['validUntilDate'] ?? now()->addMonths(2)->format('Y-m-d'),
            'vatPercent' => (int) ($baseKf['vatPercent'] ?? 8),
            'introText' => $baseKf['introText'] ?? 'Công ty Star Light là đơn vị phân phối mảng dự án sản phẩm bảo ôn K-FLEX',
            'paymentTerms' => $baseKf['paymentTerms'] ?? 'Thỏa thuận',
            'deliveryMethod' => $baseKf['deliveryMethod'] ?? 'Giao hàng tại chân công trình',
            'deliveryTime' => $baseKf['deliveryTime'] ?? 'tối thiểu 40 ngày (đối với hàng nhập khẩu) kể từ ngày đơn đặt hàng được xác nhận, Hiện đơn hàng đang có sẵn',
            'contactName' => $baseKf['contactName'] ?? 'Mr.Kiệt',
            'contactHp' => $baseKf['contactHp'] ?? '0979.807.234',
            'contactEmail' => $baseKf['contactEmail'] ?? 'tuankiet@starlightgroup.vn',
        ];

        $titleCell = $this->cellText($grid, 5, 1);
        if ($titleCell !== '') {
            $kflexQuoteForm['documentTitle'] = $titleCell;
        }

        $tableHeaderStartRow = 0;
        for ($r = 5; $r <= min($maxRow, 20); $r++) {
            $c1 = $this->cellText($grid, $r, 1);
            $cVal = $this->cellText($grid, $r, 3) ?: $this->cellText($grid, $r, 2);
            $c1Lower = mb_strtolower($c1, 'UTF-8');

            if (str_starts_with($c1Lower, 'kính gửi')) {
                $kflexQuoteForm['recipientCompany'] = $cVal;
            } elseif (str_starts_with($c1Lower, 'người nhận')) {
                $kflexQuoteForm['recipientPerson'] = $cVal;
            } elseif (str_starts_with($c1Lower, 'project')) {
                $kflexQuoteForm['projectName'] = $cVal;
            } elseif (str_starts_with($c1Lower, 'thời gian')) {
                $kflexQuoteForm['quoteDate'] = $this->parseSlashDateToIso($cVal, $todayIso);
            } elseif ($c1 === 'TT' && str_contains(mb_strtolower($this->cellText($grid, $r, 2), 'UTF-8'), 'tên vật tư')) {
                $tableHeaderStartRow = $r;
                $prevLine = $this->cellText($grid, $r - 1, 1);
                if ($prevLine !== '' && ! str_starts_with(mb_strtolower($prevLine, 'UTF-8'), 'thời gian')) {
                    $kflexQuoteForm['introText'] = $prevLine;
                }
                break;
            }
        }

        $kflexPrices = KflexPrice::query()->get();
        $kflexGroups = [];
        $detectedLines = [];
        $summaryStartRow = $maxRow + 1;

        if ($tableHeaderStartRow > 0) {
            $dataStartRow = $tableHeaderStartRow + 2;
            $currentGroupIdx = null;

            for ($r = $dataStartRow; $r <= $maxRow; $r++) {
                $c1 = $this->cellText($grid, $r, 1);
                $c2 = $this->cellText($grid, $r, 2);
                $c3 = $this->cellText($grid, $r, 3);
                $c4 = $this->cellText($grid, $r, 4);
                $c5 = $this->cellText($grid, $r, 5);
                $c6 = $this->cellText($grid, $r, 6);
                $c7 = $this->cellText($grid, $r, 7);
                $c8 = $this->cellText($grid, $r, 8);
                $c9 = $this->cellText($grid, $r, 9);
                $c10 = $this->cellText($grid, $r, 10);
                $c11 = $this->cellText($grid, $r, 11);
                $c12 = $this->cellText($grid, $r, 12);
                $c14 = $this->cellText($grid, $r, 14);

                if (str_starts_with(mb_strtoupper($c1, 'UTF-8'), 'TỔNG CỘNG')) {
                    $summaryStartRow = $r;
                    break;
                }

                $isMergedSection = false;
                foreach ($merges as $m) {
                    if ($m['r1'] === $r && $m['r2'] === $r && $m['c1'] === 2 && $m['c2'] >= 13) {
                        $isMergedSection = true;
                        break;
                    }
                }

                $isSectionRow = $isMergedSection || ($c1 === '' && $c2 !== '' && $c3 === '' && $c6 === '' && $c10 === '' && $c11 === '' && $c12 === '');

                if ($isSectionRow) {
                    $kflexGroups[] = [
                        'uid' => 'imp-kf-grp-'.$r.'-'.uniqid(),
                        'hasTitle' => true,
                        'title' => $c2,
                        'items' => [],
                    ];
                    $currentGroupIdx = count($kflexGroups) - 1;

                    continue;
                }

                if ($c1 === '' && $c2 === '' && $c3 === '' && $c6 === '' && $c12 === '') {
                    continue;
                }

                if ($currentGroupIdx === null) {
                    $kflexGroups[] = [
                        'uid' => 'imp-kf-grp-'.$r.'-'.uniqid(),
                        'hasTitle' => false,
                        'title' => '',
                        'items' => [],
                    ];
                    $currentGroupIdx = count($kflexGroups) - 1;
                }

                $brandAndName = mb_strtolower("{$c3} {$c2}", 'UTF-8');
                $productLine = 'st';
                if (str_contains($brandAndName, 'protect')) {
                    $productLine = 'k_protect';
                } elseif (str_contains($brandAndName, 'm-flex') || str_contains($brandAndName, 'mflex')) {
                    $productLine = 'm_flex';
                }
                if (! in_array($productLine, $detectedLines, true)) {
                    $detectedLines[] = $productLine;
                }

                $qtyM = $c10 !== '' ? $this->parseFormattedNumber($c10, 0) : null;
                $qtyM2 = $c11 !== '' ? $this->parseFormattedNumber($c11, 0) : null;

                $isSheet = ($qtyM2 !== null && $qtyM === null)
                    || $c8 !== ''
                    || str_starts_with(mb_strtolower($c2, 'UTF-8'), 'tấm');
                $formType = $isSheet ? 'sheet' : 'tube';

                $typeLower = mb_strtolower($c5, 'UTF-8');
                if ($formType === 'tube') {
                    $variantType = str_contains($typeLower, 'nhôm') ? 'alu' : 'foam';
                } else {
                    if (str_contains($typeLower, 'cả keo và nhôm')) {
                        $variantType = 'alu_and_glue';
                    } elseif (str_contains($typeLower, 'nhôm') || str_contains($typeLower, 'keo')) {
                        $variantType = 'alu_or_glue';
                    } else {
                        $variantType = 'foam';
                    }
                }

                $matchedPrice = $kflexPrices->first(function (KflexPrice $p) use ($productLine, $formType, $variantType, $c6, $c7): bool {
                    if ($p->product_line !== $productLine || $p->form_type !== $formType || $p->variant_type !== $variantType) {
                        return false;
                    }
                    if ((int) $p->thickness !== (int) $c6) {
                        return false;
                    }
                    if ($formType === 'tube') {
                        return (int) ($p->diameter ?? 0) === (int) $c7;
                    }

                    return true;
                });

                $kflexGroups[$currentGroupIdx]['items'][] = [
                    'uid' => 'imp-kf-item-'.$r.'-'.uniqid(),
                    'kflexPriceId' => $matchedPrice?->id,
                    'itemName' => $c2,
                    'brandLabel' => $c3 !== '' ? $c3 : ($productLine === 'k_protect' ? 'K-Flex K-Protect' : ($productLine === 'm_flex' ? 'K-Flex M-Flex' : 'K-Flex ST')),
                    'originLabel' => $c4 !== '' ? $c4 : ($productLine === 'm_flex' ? 'Ý - VN' : 'Ý - Malaysia'),
                    'productTypeLabel' => $c5,
                    'productLine' => $productLine,
                    'formType' => $formType,
                    'variantType' => $variantType,
                    'thickness' => $c6,
                    'innerDiameter' => $c7,
                    'widthM' => $c8,
                    'lengthM' => $c9,
                    'qtyM' => $qtyM,
                    'qtyM2' => $qtyM2,
                    'unitPrice' => max(0, (int) round($this->parseFormattedNumber($c12, (float) ($matchedPrice?->price ?? 0)))),
                    'note' => $c14,
                ];
            }
        }

        $afterThanks = false;
        for ($r = $summaryStartRow; $r <= $maxRow; $r++) {
            $c1 = $this->cellText($grid, $r, 1);
            $c2 = $this->cellText($grid, $r, 2);
            $c1Lower = mb_strtolower($c1, 'UTF-8');

            if (str_starts_with($c1Lower, 'thuế vat')) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $c1, $m) === 1) {
                    $kflexQuoteForm['vatPercent'] = (int) $m[1];
                }
            } elseif (str_contains($c1Lower, '1. báo giá có hiệu lực đến ngày')) {
                $parts = explode(':', $c1, 2);
                $datePart = trim($parts[1] ?? '');
                if ($datePart !== '') {
                    $kflexQuoteForm['validUntilDate'] = $this->parseSlashDateToIso($datePart, $kflexQuoteForm['validUntilDate']);
                }
            } elseif (str_contains($c1Lower, '2. phương thức thanh toán')) {
                $parts = explode(':', $c1, 2);
                $kflexQuoteForm['paymentTerms'] = trim($parts[1] ?? '');
            } elseif (str_contains($c1Lower, '3. phương thức giao hàng')) {
                $parts = explode(':', $c1, 2);
                $kflexQuoteForm['deliveryMethod'] = trim($parts[1] ?? '');
            } elseif (str_contains($c1Lower, '4. thời gian giao hàng')) {
                $parts = explode(':', $c1, 2);
                $kflexQuoteForm['deliveryTime'] = trim($parts[1] ?? '');
            } elseif (str_contains($c1Lower, 'many thanks') || str_contains(mb_strtolower($c2, 'UTF-8'), 'many thanks')) {
                $afterThanks = true;
            } elseif ($afterThanks) {
                if ($c1Lower === 'hp' || str_starts_with($c1Lower, 'hp:')) {
                    $kflexQuoteForm['contactHp'] = $c2 !== '' ? $c2 : trim((string) preg_replace('/^hp\s*:\s*/iu', '', $c1));
                } elseif (str_starts_with($c1Lower, 'email')) {
                    $kflexQuoteForm['contactEmail'] = $c2 !== '' ? $c2 : trim((string) preg_replace('/^email\s*:\s*/iu', '', $c1));
                } elseif ($c1 !== '') {
                    $kflexQuoteForm['contactName'] = $c1;
                }
            }
        }

        $nonEmptyGroups = array_values(array_filter(
            $kflexGroups,
            fn (array $g): bool => count($g['items']) > 0,
        ));

        return [
            'version' => 1,
            'activeQuotationType' => 'second',
            'kflexQuoteForm' => $kflexQuoteForm,
            'kflexSelectedProductLines' => $detectedLines !== [] ? $detectedLines : ($embeddedState['kflexSelectedProductLines'] ?? ['st']),
            'kflexGroups' => $nonEmptyGroups !== [] ? $nonEmptyGroups : ($embeddedState['kflexGroups'] ?? []),
        ];
    }

    private function firstSheetPath(ZipArchive $archive): string
    {
        $workbook = $archive->getFromName('xl/workbook.xml');
        $relationships = $archive->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relationships === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheet = $this->parseXml($workbook)->sheets->sheet[0] ?? null;
        $relationId = $sheet?->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? null;

        foreach ($this->parseXml($relationships)->Relationship as $relationship) {
            if ($relationId !== null && (string) $relationship['Id'] === (string) $relationId) {
                return 'xl/'.ltrim((string) $relationship['Target'], '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(?string $xml): array
    {
        if ($xml === null) {
            return [];
        }

        $strings = [];
        foreach ($this->parseXml($xml)->si as $item) {
            $text = '';
            foreach ($item->xpath('.//*[local-name()="t"][not(ancestor::*[local-name()="rPh"])]') ?: [] as $part) {
                $text .= (string) $part;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array{0: array<int, array<int, string>>, 1: array<int, array{r1: int, c1: int, r2: int, c2: int}>, 2: int}
     */
    private function parseWorksheet(string $sheetXml, array $sharedStrings): array
    {
        $sheet = $this->parseXml($sheetXml);
        $grid = [];
        $maxRow = 0;

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $rowNumber = (int) $row['r'];
            if ($rowNumber > $maxRow) {
                $maxRow = $rowNumber;
            }

            foreach ($row->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $grid[$rowNumber][$column] = $this->cellValue($cell, $sharedStrings);
            }
        }

        $merges = [];
        foreach ($sheet->mergeCells->mergeCell ?? [] as $mergeCell) {
            $ref = (string) $mergeCell['ref'];
            $parts = explode(':', $ref);
            if (count($parts) === 2) {
                [$r1, $c1] = $this->parseCellRef($parts[0]);
                [$r2, $c2] = $this->parseCellRef($parts[1]);
                if ($r1 > 0 && $c1 > 0 && $r2 > 0 && $c2 > 0) {
                    $merges[] = [
                        'r1' => min($r1, $r2),
                        'c1' => min($c1, $c2),
                        'r2' => max($r1, $r2),
                        'c2' => max($c1, $c2),
                    ];
                }
            }
        }

        return [$grid, $merges, $maxRow];
    }

    /**
     * @return array<string, string>
     */
    private function extractRowImages(ZipArchive $archive, string $drawingXml, string $drawingRelsXml): array
    {
        $result = [];
        $rels = $this->parseXml($drawingRelsXml);
        $relMap = [];
        foreach ($rels->Relationship ?? [] as $rel) {
            $id = (string) $rel['Id'];
            $target = (string) $rel['Target'];
            if ($id !== '' && $target !== '') {
                $clean = 'xl/'.ltrim((string) preg_replace('/^(\.\.\/)+/', '', $target), '/');
                $relMap[$id] = $clean;
            }
        }

        $drawing = $this->parseXml($drawingXml);
        $anchors = $drawing->xpath('//*[local-name()="oneCellAnchor" or local-name()="twoCellAnchor"]') ?: [];
        foreach ($anchors as $anchor) {
            $fromNodes = $anchor->xpath('./*[local-name()="from"]') ?: [];
            if ($fromNodes === []) {
                continue;
            }
            $from = $fromNodes[0];
            $colNodes = $from->xpath('./*[local-name()="col"]') ?: [];
            $rowNodes = $from->xpath('./*[local-name()="row"]') ?: [];
            $blipNodes = $anchor->xpath('.//*[local-name()="blip"]') ?: [];
            if ($colNodes === [] || $rowNodes === [] || $blipNodes === []) {
                continue;
            }

            $col1 = ((int) $colNodes[0]) + 1;
            $row1 = ((int) $rowNodes[0]) + 1;
            $blip = $blipNodes[0];
            $embedAttr = $blip->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['embed'] ?? null;
            $embedId = (string) $embedAttr;
            if ($embedId === '' || ! isset($relMap[$embedId])) {
                continue;
            }

            $mediaBytes = $archive->getFromName($relMap[$embedId]);
            if (is_string($mediaBytes) && $mediaBytes !== '') {
                $mime = str_ends_with(strtolower($relMap[$embedId]), '.jpg') || str_ends_with(strtolower($relMap[$embedId]), '.jpeg')
                    ? 'image/jpeg'
                    : 'image/png';
                $result["{$row1}:{$col1}"] = 'data:'.$mime.';base64,'.base64_encode($mediaBytes);
            }
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $text = '';
            foreach ($cell->is->xpath('.//*[local-name()="t"]') ?: [] as $part) {
                $text .= (string) $part;
            }

            return $text;
        }

        $value = (string) $cell->v;

        return match ($type) {
            's' => $sharedStrings[(int) $value] ?? '',
            'b' => $value === '1' ? 'TRUE' : 'FALSE',
            default => $value,
        };
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     */
    private function cellText(array $grid, int $row, int $col): string
    {
        return trim((string) ($grid[$row][$col] ?? ''));
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            if ($letter !== '') {
                $index = $index * 26 + (ord($letter) - 64);
            }
        }

        return $index;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseCellRef(string $ref): array
    {
        if (preg_match('/^([A-Za-z]+)(\d+)$/', trim($ref), $m) !== 1) {
            return [0, 0];
        }

        return [(int) $m[2], $this->columnIndex($m[1])];
    }

    private function parseFormattedNumber(string $raw, float $fallback = 0): float
    {
        $cleaned = preg_replace('/[^\d.,-]/', '', trim($raw)) ?? '';
        if ($cleaned === '') {
            return $fallback;
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $cleaned) === 1) {
            return (float) str_replace('.', '', $cleaned);
        }
        if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $cleaned) === 1) {
            return (float) str_replace(',', '', $cleaned);
        }

        $dotCount = substr_count($cleaned, '.');
        $commaCount = substr_count($cleaned, ',');
        if ($dotCount > 1 && $commaCount === 0) {
            return (float) str_replace('.', '', $cleaned);
        }
        if ($commaCount > 1 && $dotCount === 0) {
            return (float) str_replace(',', '', $cleaned);
        }
        if ($commaCount === 1 && $dotCount === 0) {
            $after = explode(',', $cleaned)[1] ?? '';
            if (strlen($after) === 3) {
                return (float) str_replace(',', '', $cleaned);
            }

            return (float) str_replace(',', '.', $cleaned);
        }
        if ($dotCount === 1 && $commaCount === 0) {
            $parts = explode('.', $cleaned);
            if (strlen($parts[1] ?? '') === 3 && (int) $parts[0] > 0) {
                return (float) str_replace('.', '', $cleaned);
            }
        }

        return (float) str_replace(',', '', $cleaned);
    }

    private function parseSlashDateToIso(string $raw, string $fallbackIso): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return $fallbackIso;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) === 1) {
            return $trimmed;
        }
        if (preg_match('/(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})/', $trimmed, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $fallbackIso;
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($element === false) {
            throw new RuntimeException('File Excel báo giá bị lỗi hoặc không đúng định dạng.');
        }

        return $element;
    }
}
