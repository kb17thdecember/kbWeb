<?php

namespace App\Support;

use App\Models\KflexPrice;
use App\Models\Product;
use RuntimeException;
use SimpleXMLElement;

class QuotationFileImporter
{
    /**
     * Import an exported quotation file (.xlsx, .pdf, .json, .html) and return its structured state payload.
     *
     * @return array<string, mixed>
     */
    public function import(string $filePath, string $originalName = ''): array
    {
        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $filePath, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            $raw = (string) file_get_contents($filePath);
            $decoded = json_decode($raw, true);
            $normalized = $this->normalizePayload(is_array($decoded) ? $decoded : null);
            if ($normalized === null) {
                throw new RuntimeException('File JSON không đúng định dạng báo giá.');
            }

            return $normalized;
        }

        if ($ext === 'html' || $ext === 'htm') {
            $raw = (string) file_get_contents($filePath);
            if (preg_match('/KBQDATA:([A-Za-z0-9+\/=\s]+):ENDKBQDATA/', $raw, $m) === 1) {
                $decoded = $this->decodeBase64Payload($m[1]);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
            throw new RuntimeException('File HTML không chứa dữ liệu báo giá hợp lệ.');
        }

        if ($ext === 'pdf') {
            return $this->importPdf($filePath);
        }

        return $this->importXlsx($filePath);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function normalizePayload(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $qType = $payload['quotationType'] ?? $payload['activeQuotationType'] ?? null;
        if (! in_array($qType, ['ariston', 'second'], true)) {
            if (! empty($payload['kflexGroups']) && empty($payload['items'])) {
                $qType = 'second';
            } elseif (! empty($payload['items']) || ! empty($payload['quoteForm'])) {
                $qType = 'ariston';
            } else {
                return null;
            }
        }

        $payload['version'] = $payload['version'] ?? 1;
        $payload['quotationType'] = $qType;
        $payload['activeQuotationType'] = $qType;

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeBase64Payload(string $base64): ?array
    {
        $clean = preg_replace('/[^A-Za-z0-9+\/=]/', '', $base64) ?? '';
        if ($clean === '') {
            return null;
        }

        $rawJson = base64_decode($clean, true);
        if (! is_string($rawJson) || $rawJson === '') {
            return null;
        }

        $decoded = json_decode($rawJson, true);

        return $this->normalizePayload(is_array($decoded) ? $decoded : null);
    }

    /**
     * @return array<string, mixed>
     */
    private function importPdf(string $path): array
    {
        $rawPdf = (string) file_get_contents($path);
        if ($rawPdf === '') {
            throw new RuntimeException('File PDF trống hoặc không thể đọc.');
        }

        if (preg_match('/KBQDATA:([A-Za-z0-9+\/=\s]+):ENDKBQDATA/', $rawPdf, $m) === 1) {
            $decoded = $this->decodeBase64Payload($m[1]);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        $headers = [];
        $streams = [];
        if (preg_match_all('/(\d+)\s+\d+\s+obj\b([\s\S]*?)endobj/', $rawPdf, $objMatches, PREG_SET_ORDER)) {
            foreach ($objMatches as $om) {
                $objId = (int) $om[1];
                $body = $om[2];
                $streamPos = strpos($body, 'stream');
                if ($streamPos === false) {
                    $headers[$objId] = $body;

                    continue;
                }
                $headers[$objId] = substr($body, 0, $streamPos);
                if (preg_match('/stream\r?\n([\s\S]*?)\r?\n?endstream/', $body, $sm) === 1) {
                    $rawStream = $sm[1];
                    $inflated = @gzuncompress($rawStream);
                    if ($inflated === false) {
                        $inflated = @gzinflate($rawStream);
                    }
                    $streamContent = is_string($inflated) ? $inflated : $rawStream;
                    $streams[$objId] = $streamContent;

                    if (preg_match('/KBQDATA:([A-Za-z0-9+\/=\s]+):ENDKBQDATA/', $streamContent, $m) === 1) {
                        $decoded = $this->decodeBase64Payload($m[1]);
                        if ($decoded !== null) {
                            return $decoded;
                        }
                    }
                }
            }
        }

        $cmapsByObj = [];
        $fallbackCMap = [];
        foreach ($streams as $objId => $s) {
            if (str_contains($s, 'beginbfchar') || str_contains($s, 'beginbfrange')) {
                $cmap = $this->parsePdfCMap($s);
                $cmapsByObj[$objId] = $cmap;
                foreach ($cmap as $k => $v) {
                    if (! isset($fallbackCMap[$k])) {
                        $fallbackCMap[$k] = $v;
                    }
                }
            }
        }

        $fontObjToCMap = [];
        foreach ($headers as $objId => $h) {
            if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $h, $tm) === 1) {
                $cid = (int) $tm[1];
                if (isset($cmapsByObj[$cid])) {
                    $fontObjToCMap[$objId] = $cmapsByObj[$cid];
                }
            }
        }

        $fontNameToCMap = [];
        foreach ($headers as $h) {
            if (preg_match_all('/\/([A-Za-z0-9_.-]+)\s+(\d+)\s+\d+\s+R/', $h, $fm, PREG_SET_ORDER)) {
                foreach ($fm as $ref) {
                    $fname = $ref[1];
                    $fobj = (int) $ref[2];
                    if (isset($fontObjToCMap[$fobj])) {
                        $fontNameToCMap[$fname] = $fontObjToCMap[$fobj];
                    }
                }
            }
        }

        $runs = [];
        $rawVLines = [];
        $streamOrderChunks = [];
        $pageIdx = 0;
        $opRegex = '/\/([A-Za-z0-9_.-]+)\s+([\d.-]+)\s+Tf|([\d.-]+)\s+([\d.-]+)\s+Td|([\d.-]+)\s+([\d.-]+)\s+TD|([\d.-]+)\s+[\d.-]+\s+[\d.-]+\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+Tm|\[(?:<[0-9A-Fa-f\s]+>|\((?:\\\\.|[^\\\\()])*\)|[\s\d.-])*\]\s*TJ|<([0-9A-Fa-f\s]+)>\s*Tj|\((?:\\\\.|[^\\\\()])*\)\s*Tj/';

        foreach ($streams as $s) {
            if (! str_contains($s, 'BT') || ! str_contains($s, 'ET')) {
                continue;
            }
            $pageIdx++;
            if (preg_match_all('/([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+re\b/', $s, $reM, PREG_SET_ORDER)) {
                foreach ($reM as $rm) {
                    $rx = (float) $rm[1];
                    $rw = (float) $rm[3];
                    $rh = (float) $rm[4];
                    if (abs($rw) < 2.0 && abs($rh) > 15.0) {
                        $rawVLines[] = round($rx, 2);
                    }
                }
            }
            $activeCMap = $fallbackCMap;
            $fontSize = 6.0;
            $tmA = 1.0;
            $tmD = 1.0;
            $curX = 0.0;
            $curY = 0.0;

            if (preg_match_all($opRegex, $s, $ops, PREG_SET_ORDER)) {
                foreach ($ops as $op) {
                    $full = $op[0];
                    if (! empty($op[1])) {
                        $activeCMap = $fontNameToCMap[$op[1]] ?? $fallbackCMap;
                        $rawSize = (float) ($op[2] ?? 6.0);
                        $fontSize = $rawSize > 0 ? $rawSize : 6.0;
                    } elseif (isset($op[3]) && $op[3] !== '') {
                        $curX += (float) $op[3] * abs($tmA);
                        $curY += (float) $op[4] * $tmD;
                    } elseif (isset($op[5]) && $op[5] !== '') {
                        $curX += (float) $op[5] * abs($tmA);
                        $curY += (float) $op[6] * $tmD;
                    } elseif (isset($op[7]) && $op[7] !== '') {
                        $tmA = (float) $op[7] ?: 1.0;
                        $tmD = (float) $op[8] ?: 1.0;
                        $curX = (float) $op[9];
                        $curY = (float) $op[10];
                    } else {
                        $effFontSize = max(3.5, $fontSize * (abs($tmD) > 1.5 && $fontSize <= 1.5 ? abs($tmD) : 1.0));
                        $txt = '';
                        if (preg_match_all('/<([0-9A-Fa-f\s]+)>|\((?:\\\\.|[^\\\\()])*\)|(-?\d+(?:\.\d+)?)/', $full, $tks, PREG_SET_ORDER)) {
                            foreach ($tks as $tk) {
                                if (isset($tk[2]) && $tk[2] !== '') {
                                    $kern = (float) $tk[2];
                                    if ($kern < -180 && ! str_ends_with($txt, ' ')) {
                                        $txt .= ' ';
                                    }
                                } elseif (! empty($tk[1])) {
                                    $hex = strtoupper((string) preg_replace('/\s+/', '', $tk[1]));
                                    $step = (isset($activeCMap[substr($hex, 0, 4)]) || strlen($hex) % 4 === 0) ? 4 : 2;
                                    for ($i = 0; $i < strlen($hex); $i += $step) {
                                        $code = substr($hex, $i, $step);
                                        if (isset($activeCMap[$code])) {
                                            $txt .= $activeCMap[$code];
                                        } elseif (isset($fallbackCMap[$code])) {
                                            $txt .= $fallbackCMap[$code];
                                        } else {
                                            $cc = hexdec($code);
                                            if ($cc >= 32 && $cc <= 126) {
                                                $txt .= chr((int) $cc);
                                            }
                                        }
                                    }
                                } elseif (str_starts_with($tk[0], '(')) {
                                    $txt .= stripcslashes(substr($tk[0], 1, -1));
                                }
                            }
                        }
                        if ($txt !== '') {
                            if (class_exists(\Normalizer::class)) {
                                $txt = \Normalizer::normalize($txt, \Normalizer::FORM_C) ?: $txt;
                            }
                            $streamOrderChunks[] = $txt;
                            $estW = $this->estimatePdfRunWidth($txt, $effFontSize);
                            $runs[] = [
                                'page' => $pageIdx,
                                'x' => $curX,
                                'endX' => $curX + $estW,
                                'y' => $curY,
                                'fontSize' => $effFontSize,
                                'text' => $txt,
                            ];
                        }
                    }
                }
            }
        }

        // Check stream-order text first so wrapped KBQDATA lines are never scrambled by (x, y) sorting
        $streamOrderCompact = preg_replace('/\s+/', '', implode('', $streamOrderChunks)) ?? '';
        if (preg_match('/KBQDATA:([A-Za-z0-9+\/=]+):ENDKBQDATA/', $streamOrderCompact, $sm) === 1) {
            $decoded = $this->decodeBase64Payload($sm[1]);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Auto-detect flipped Y coordinate space (top-down generators where header Y < summary Y)
        $topMarkerY = null;
        $bottomMarkerY = null;
        foreach ($runs as $r) {
            if ($r['page'] !== 1) {
                continue;
            }
            $u = mb_strtoupper($r['text'], 'UTF-8');
            if ($topMarkerY === null && (str_contains($u, 'BÁO GIÁ') || str_contains($u, 'STAR LIGHT') || str_contains($u, 'KÍNH GỬI'))) {
                $topMarkerY = $r['y'];
            }
            if (str_contains($u, 'TỔNG CỘNG') || str_contains($u, 'MANY THANKS') || str_contains($u, 'ĐIỀU KIỆN')) {
                $bottomMarkerY = $r['y'];
            }
        }
        if ($topMarkerY !== null && $bottomMarkerY !== null && $topMarkerY < $bottomMarkerY) {
            foreach ($runs as &$r) {
                $r['y'] = -$r['y'];
            }
            unset($r);
        }

        $visualRows = [];
        foreach ($runs as $run) {
            $found = false;
            foreach ($visualRows as &$vr) {
                if ($vr['page'] === $run['page'] && abs($vr['y'] - $run['y']) <= 2.5) {
                    $vr['runs'][] = $run;
                    $found = true;
                    break;
                }
            }
            unset($vr);
            if (! $found) {
                $visualRows[] = [
                    'page' => $run['page'],
                    'y' => $run['y'],
                    'runs' => [$run],
                ];
            }
        }

        usort($visualRows, fn (array $a, array $b): int => $a['page'] !== $b['page'] ? ($a['page'] <=> $b['page']) : ($b['y'] <=> $a['y']));

        sort($rawVLines);
        $vLines = [];
        foreach ($rawVLines as $vx) {
            if ($vLines === [] || abs($vx - $vLines[count($vLines) - 1]) > 2.0) {
                $vLines[] = $vx;
            }
        }

        $structuredRows = [];
        $lines = [];
        foreach ($visualRows as $vr) {
            $rowRuns = $vr['runs'];
            usort($rowRuns, fn (array $a, array $b): int => $a['x'] <=> $b['x']);
            $mergedCells = [];
            foreach ($rowRuns as $run) {
                if ($mergedCells === []) {
                    $mergedCells[] = $run;

                    continue;
                }
                $lastIdx = count($mergedCells) - 1;
                $prev = $mergedCells[$lastIdx];
                $gap = $run['x'] - $prev['endX'];
                $crossesVLine = false;
                foreach ($vLines as $vl) {
                    if ($prev['x'] + 1.5 < $vl && $run['x'] >= $vl - 1.5) {
                        $crossesVLine = true;
                        break;
                    }
                }
                if (! $crossesVLine && $gap < 5.5) {
                    $needSpace = $this->shouldInsertPdfSpace($prev['text'], $run['text'], $gap);
                    $mergedCells[$lastIdx]['text'] .= ($needSpace ? ' ' : '').$run['text'];
                    $mergedCells[$lastIdx]['endX'] = max($prev['endX'], $run['endX']);
                } else {
                    $mergedCells[] = $run;
                }
            }
            $cells = [];
            $cellRuns = [];
            foreach ($mergedCells as $mc) {
                $clean = trim((string) preg_replace('/\s+/', ' ', $mc['text']));
                if ($clean !== '') {
                    $cells[] = $clean;
                    $cellRuns[] = [
                        'x' => (float) $mc['x'],
                        'endX' => (float) $mc['endX'],
                        'centerX' => ((float) $mc['x'] + (float) $mc['endX']) / 2.0,
                        'text' => $clean,
                    ];
                }
            }
            if ($cells !== []) {
                $line = implode(' ', $cells);
                $structuredRows[] = [
                    'page' => (int) $vr['page'],
                    'y' => (float) $vr['y'],
                    'cellRuns' => $cellRuns,
                    'cells' => $cells,
                    'line' => $line,
                ];
                $lines[] = $line;
            }
        }

        $combined = implode("\n", $lines);
        $compact = preg_replace('/\s+/', '', $combined) ?? '';
        if (
            preg_match('/KBQDATA:([A-Za-z0-9+\/=\s]+):ENDKBQDATA/', $combined, $m) === 1
            || preg_match('/KBQDATA:([A-Za-z0-9+\/=]+):ENDKBQDATA/', $compact, $m) === 1
        ) {
            $decoded = $this->decodeBase64Payload($m[1]);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        if (trim($combined) === '') {
            throw new RuntimeException('Không thể đọc nội dung từ file PDF này.');
        }

        $todayIso = now()->format('Y-m-d');
        $upper = mb_strtoupper($combined, 'UTF-8');
        if (str_contains($upper, 'K-FLEX') || str_contains($upper, 'BẢO ÔN')) {
            return $this->parseKflexPdfStructured($lines, $structuredRows, $combined, $todayIso, $vLines);
        }

        return $this->parseAristonPdfStructured($lines, $structuredRows, $combined, $todayIso, $vLines);
    }

    /**
     * @return array<string, string>
     */
    private function parsePdfCMap(string $cmapText): array
    {
        $map = [];
        if (preg_match_all('/beginbfchar([\s\S]*?)endbfchar/', $cmapText, $bfChars)) {
            foreach ($bfChars[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    foreach ($pairs as $p) {
                        $src = strtoupper($p[1]);
                        $dst = $this->decodeHexUtf16($p[2]);
                        if ($dst !== '') {
                            $map[$src] = $dst;
                        }
                    }
                }
            }
        }
        if (preg_match_all('/beginbfrange([\s\S]*?)endbfrange/', $cmapText, $bfRanges)) {
            foreach ($bfRanges[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[([\s\S]*?)\]/', $block, $arMatches, PREG_SET_ORDER)) {
                    foreach ($arMatches as $ar) {
                        $start = hexdec($ar[1]);
                        $end = hexdec($ar[2]);
                        $padLen = strlen($ar[1]);
                        if (preg_match_all('/<([0-9A-Fa-f]+)>/', $ar[3], $tMatches)) {
                            $targets = $tMatches[1];
                            for ($code = $start, $idx = 0; $code <= $end && $idx < count($targets); $code++, $idx++) {
                                $key = str_pad(strtoupper(dechex((int) $code)), $padLen, '0', STR_PAD_LEFT);
                                $dst = $this->decodeHexUtf16($targets[$idx]);
                                if ($dst !== '') {
                                    $map[$key] = $dst;
                                }
                            }
                        }
                    }
                }
                $blockWithoutArrays = preg_replace('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[[\s\S]*?\]/', '', $block) ?? $block;
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $blockWithoutArrays, $rMatches, PREG_SET_ORDER)) {
                    foreach ($rMatches as $r) {
                        $start = hexdec($r[1]);
                        $end = hexdec($r[2]);
                        $padLen = strlen($r[1]);
                        if ($start === $end && strlen($r[3]) > 4) {
                            $key = str_pad(strtoupper(dechex((int) $start)), $padLen, '0', STR_PAD_LEFT);
                            $dst = $this->decodeHexUtf16($r[3]);
                            if ($dst !== '') {
                                $map[$key] = $dst;
                            }

                            continue;
                        }
                        $target = hexdec($r[3]);
                        for ($code = $start; $code <= $end && ($code - $start) < 1024; $code++) {
                            $key = str_pad(strtoupper(dechex((int) $code)), $padLen, '0', STR_PAD_LEFT);
                            $map[$key] = mb_chr((int) $target, 'UTF-8');
                            $target++;
                        }
                    }
                }
            }
        }

        return $map;
    }

    private function shouldInsertPdfSpace(string $prevText, string $nextText, float $gap): bool
    {
        if ($gap <= 2.4 || str_ends_with($prevText, ' ') || str_starts_with($nextText, ' ')) {
            return false;
        }
        if (preg_match('/^[.,:;)\]]/', $nextText) === 1 || str_ends_with($prevText, '(') || str_ends_with($prevText, '-')) {
            return false;
        }
        if (
            $gap < 6.5
            && preg_match('/\p{L}$/u', $prevText) === 1
            && preg_match('/^[àáạảãăắằẳẵặâấầẩẫậèéẹẻẽêếềểễệìíịỉĩòóọỏõôốồổỗộơớờởỡợùúụủũưứừửữựỳýỵỷỹ]/iu', $nextText) === 1
        ) {
            return false;
        }

        return true;
    }

    private function estimatePdfRunWidth(string $txt, float $fontSize): float
    {
        $len = mb_strlen($txt, 'UTF-8');
        $w = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($txt, $i, 1, 'UTF-8');
            if (in_array($ch, [' ', 'i', 'l', 'I', '.', ',', ':'], true)) {
                $w += $fontSize * 0.28;
            } elseif (mb_strtoupper($ch, 'UTF-8') === $ch && mb_strtolower($ch, 'UTF-8') !== $ch) {
                $w += $fontSize * 0.65;
            } else {
                $w += $fontSize * 0.50;
            }
        }

        return $w;
    }

    /**
     * @param  array<int, array{page?: int, y?: float, cellRuns?: array<int, array{x: float, endX: float, centerX: float, text: string}>, cells: array<int, string>, line: string}>  $headerRows
     * @param  array<int, float>  $proportions
     * @param  array<int, callable(string): bool>  $colMatchers
     * @param  array<int, float>  $vLines
     * @return array<int, float>
     */
    private function buildPdfColumnDividers(array $headerRows, float $tableMinX, float $tableMaxX, array $proportions, array $colMatchers, array $vLines = []): array
    {
        $colCount = count($proportions);
        $tableVLines = array_values(array_filter(
            $vLines,
            fn (float $vl): bool => $vl >= $tableMinX - 25.0 && $vl <= $tableMaxX + 35.0
        ));
        if (count($tableVLines) === $colCount + 1) {
            return array_slice($tableVLines, 1, $colCount - 1);
        }

        $span = max(200.0, $tableMaxX - $tableMinX);
        $defaultMin = [];
        $defaultMax = [];
        $acc = 0.0;
        for ($c = 0; $c < $colCount; $c++) {
            $defaultMin[$c] = $tableMinX + $acc * $span;
            $acc += $proportions[$c];
            $defaultMax[$c] = $tableMinX + $acc * $span;
        }

        $detectedMin = array_fill(0, $colCount, null);
        $detectedMax = array_fill(0, $colCount, null);

        foreach ($headerRows as $hr) {
            foreach ($hr['cellRuns'] ?? [] as $cr) {
                $txtLower = mb_strtolower(trim($cr['text']), 'UTF-8');
                if ($txtLower === '' || str_contains($txtLower, 'kích thước') || str_contains($txtLower, 'khối lượng')) {
                    continue;
                }
                for ($c = 0; $c < $colCount; $c++) {
                    if (($colMatchers[$c])($txtLower)) {
                        $detectedMin[$c] = $detectedMin[$c] === null ? $cr['x'] : min($detectedMin[$c], $cr['x']);
                        $detectedMax[$c] = $detectedMax[$c] === null ? $cr['endX'] : max($detectedMax[$c], $cr['endX']);
                        break;
                    }
                }
            }
        }

        $dividers = [];
        for ($c = 0; $c < $colCount - 1; $c++) {
            if ($detectedMax[$c] !== null && $detectedMin[$c + 1] !== null && $detectedMin[$c + 1] > $detectedMax[$c]) {
                $dividers[$c] = ($detectedMax[$c] + $detectedMin[$c + 1]) / 2.0;
            } else {
                $dividers[$c] = $defaultMax[$c];
            }
        }

        return $dividers;
    }

    /**
     * @param  array<int, float>  $dividers
     * @param  array<int, int>  $leftAlignedCols
     */
    private function resolvePdfColumnIndex(float $x, float $centerX, array $dividers, array $leftAlignedCols = []): int
    {
        $colCount = count($dividers) + 1;
        foreach ($leftAlignedCols as $lc) {
            $leftBound = $lc === 0 ? -1e9 : ($dividers[$lc - 1] - 4.0);
            $rightBound = $lc < count($dividers) ? $dividers[$lc] : 1e9;
            if ($x >= $leftBound && $x < $rightBound && $centerX < $rightBound + 8.0) {
                return $lc;
            }
        }
        for ($c = 0; $c < count($dividers); $c++) {
            if ($centerX < $dividers[$c]) {
                return $c;
            }
        }

        return $colCount - 1;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, array{page?: int, y?: float, cellRuns?: array<int, array{x: float, endX: float, centerX: float, text: string}>, cells: array<int, string>, line: string}>  $structuredRows
     * @param  array<int, float>  $vLines
     * @return array<string, mixed>
     */
    private function parseKflexPdfStructured(array $lines, array $structuredRows, string $combined, string $todayIso, array $vLines = []): array
    {
        $kflexQuoteForm = [
            'documentTitle' => 'BÁO GIÁ BẢO ÔN K-FLEX',
            'recipientCompany' => '',
            'recipientPerson' => '',
            'projectName' => '',
            'quoteDate' => $todayIso,
            'validUntilDate' => now()->addMonths(2)->format('Y-m-d'),
            'vatPercent' => 8,
            'introText' => 'Công ty Star Light là đơn vị phân phối mảng dự án sản phẩm bảo ôn K-FLEX',
            'paymentTerms' => 'Thỏa thuận',
            'deliveryMethod' => 'Giao hàng tại chân công trình',
            'deliveryTime' => 'tối thiểu 40 ngày (đối với hàng nhập khẩu) kể từ ngày đơn đặt hàng được xác nhận, Hiện đơn hàng đang có sẵn',
            'contactName' => 'Mr.Kiệt',
            'contactHp' => '0979.807.234',
            'contactEmail' => 'tuankiet@starlightgroup.vn',
        ];

        $afterThanks = false;
        $contactFound = false;
        for ($i = 0; $i < count($lines); $i++) {
            $trim = trim($lines[$i]);
            $lower = mb_strtolower($trim, 'UTF-8');
            if (str_starts_with($lower, 'báo giá') && ! str_contains($lower, 'có hiệu lực')) {
                $kflexQuoteForm['documentTitle'] = (string) preg_replace('/b\s+ảo/iu', 'BẢO', $trim);
            } elseif (str_starts_with($lower, 'kính gửi')) {
                $val = trim((string) preg_replace('/^kính gửi\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $kflexQuoteForm['recipientCompany'] = $val;
            } elseif (str_starts_with($lower, 'người nhận')) {
                $val = trim((string) preg_replace('/^người nhận\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $kflexQuoteForm['recipientPerson'] = $val;
            } elseif (str_starts_with($lower, 'project')) {
                $val = trim((string) preg_replace('/^project\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $kflexQuoteForm['projectName'] = $val;
            } elseif (str_starts_with($lower, 'thời gian') && ! str_contains($lower, 'giao hàng')) {
                $dStr = trim((string) preg_replace('/^thời gian\s*:?\s*/iu', '', $trim));
                if ($dStr === '' && isset($lines[$i + 1])) {
                    $dStr = trim($lines[$i + 1]);
                }
                if ($dStr !== '') {
                    $kflexQuoteForm['quoteDate'] = $this->parseSlashDateToIso($dStr, $todayIso);
                }
            } elseif (str_contains($lower, 'đơn vị phân phối')) {
                $kflexQuoteForm['introText'] = $trim;
            } elseif (str_contains($lower, 'thuế vat')) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $trim, $vm) === 1) {
                    $kflexQuoteForm['vatPercent'] = (int) $vm[1];
                }
            } elseif (str_contains($lower, '1. báo giá có hiệu lực đến ngày')) {
                if (preg_match('/(\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})/', $trim, $dm) === 1) {
                    $kflexQuoteForm['validUntilDate'] = $this->parseSlashDateToIso($dm[1], $kflexQuoteForm['validUntilDate']);
                }
            } elseif (str_contains($lower, '2. phương thức thanh toán')) {
                $parts = explode(':', $trim, 2);
                if (isset($parts[1]) && trim($parts[1]) !== '') {
                    $kflexQuoteForm['paymentTerms'] = trim($parts[1]);
                }
            } elseif (str_contains($lower, '3. phương thức giao hàng')) {
                $parts = explode(':', $trim, 2);
                if (isset($parts[1]) && trim($parts[1]) !== '') {
                    $kflexQuoteForm['deliveryMethod'] = trim($parts[1]);
                }
            } elseif (str_contains($lower, '4. thời gian giao hàng')) {
                $parts = explode(':', $trim, 2);
                if (isset($parts[1]) && trim($parts[1]) !== '') {
                    $dtVal = trim($parts[1]);
                    if (isset($lines[$i + 1])) {
                        $nextLine = trim($lines[$i + 1]);
                        if ($nextLine !== '' && ! preg_match('/^([0-9]+\.|rất mong|many thanks)/iu', $nextLine)) {
                            $dtVal .= ' '.$nextLine;
                        }
                    }
                    $kflexQuoteForm['deliveryTime'] = $dtVal;
                }
            } elseif (str_contains($lower, 'many thanks')) {
                $afterThanks = true;
            } elseif ($afterThanks) {
                if (preg_match('/^hp\b/i', $trim) === 1) {
                    $kflexQuoteForm['contactHp'] = trim((string) preg_replace('/^hp\s*:?\s*/i', '', $trim));
                } elseif (preg_match('/^email\b/i', $trim) === 1) {
                    $kflexQuoteForm['contactEmail'] = trim((string) preg_replace('/^email\s*:?\s*/i', '', $trim));
                } elseif (! $contactFound && mb_strlen($trim, 'UTF-8') > 1 && ! str_contains($trim, ':')) {
                    $kflexQuoteForm['contactName'] = $trim;
                    $contactFound = true;
                }
            }
        }

        $kflexPrices = $this->safeKflexPrices();
        $kflexGroups = [];
        $detectedLines = [];
        $currentGroupIdx = null;

        $headerRows = [];
        $bodyRows = [];
        $inTable = false;
        $tableMinX = 1e9;
        $tableMaxX = 0.0;

        foreach ($structuredRows as $row) {
            $lineUpper = mb_strtoupper($row['line'], 'UTF-8');
            if (str_starts_with($lineUpper, 'TỔNG CỘNG')) {
                break;
            }
            $isHeaderLine = (($row['cells'][0] ?? '') === 'TT' && str_contains($lineUpper, 'TÊN VẬT TƯ'))
                || str_contains($lineUpper, 'UNIT PRICE')
                || str_contains($lineUpper, 'CHIỀU DÀY')
                || str_contains($lineUpper, 'ID (MM)')
                || preg_match('/^\(MM\)/i', trim($row['line'])) === 1;
            if ($isHeaderLine) {
                $inTable = true;
                $headerRows[] = $row;
                foreach ($row['cellRuns'] ?? [] as $cr) {
                    $tableMinX = min($tableMinX, $cr['x']);
                    $tableMaxX = max($tableMaxX, $cr['endX']);
                }

                continue;
            }
            if (! $inTable) {
                continue;
            }
            $bodyRows[] = $row;
            foreach ($row['cellRuns'] ?? [] as $cr) {
                $tableMinX = min($tableMinX, $cr['x']);
                $tableMaxX = max($tableMaxX, $cr['endX']);
            }
        }

        if ($tableMinX > $tableMaxX) {
            $tableMinX = 50.0;
            $tableMaxX = 720.0;
        }

        $proportions = [0.038, 0.160, 0.068, 0.084, 0.070, 0.042, 0.040, 0.042, 0.042, 0.048, 0.048, 0.092, 0.112, 0.114];
        $matchers = [
            fn (string $s): bool => $s === 'tt',
            fn (string $s): bool => str_contains($s, 'tên vật tư'),
            fn (string $s): bool => str_contains($s, 'nhãn hiệu'),
            fn (string $s): bool => str_contains($s, 'thương hiệu') || str_contains($s, 'xuất xứ'),
            fn (string $s): bool => str_contains($s, 'loại sản') || $s === 'phẩm',
            fn (string $s): bool => str_contains($s, 'chiều dày'),
            fn (string $s): bool => str_contains($s, 'id (mm)') || $s === 'id',
            fn (string $s): bool => str_contains($s, 'chiều rộng'),
            fn (string $s): bool => str_contains($s, 'chiều dài'),
            fn (string $s): bool => $s === 'm',
            fn (string $s): bool => $s === 'm2',
            fn (string $s): bool => str_contains($s, 'đơn giá') || str_contains($s, 'unit price'),
            fn (string $s): bool => str_contains($s, 'tổng') || str_contains($s, 'total'),
            fn (string $s): bool => str_contains($s, 'ghi chú'),
        ];
        $dividers = $this->buildPdfColumnDividers($headerRows, $tableMinX, $tableMaxX, $proportions, $matchers, $vLines);
        if (count($vLines) < 14) {
            // Adjust Col 0|1 and Col 1|2 dividers so left-aligned Tên vật tư never spills into TT or Nhãn hiệu
            foreach ($headerRows as $hr) {
                foreach ($hr['cellRuns'] ?? [] as $cr) {
                    $t = mb_strtolower($cr['text'], 'UTF-8');
                    if ($t === 'tt') {
                        $dividers[0] = min($dividers[0], $cr['endX'] + 12.0);
                    } elseif (str_contains($t, 'nhãn hiệu')) {
                        $dividers[1] = max($dividers[0] + 40.0, $cr['x'] - 8.0);
                    }
                }
            }
        }

        // Map each bodyRow's cellRuns into 14 columns and classify rows into Section, ItemAnchor, or WrappedContinuation
        $mappedBody = [];
        foreach ($bodyRows as $idx => $row) {
            $colRuns = array_fill(0, 14, []);
            $runsList = $row['cellRuns'] ?? [];
            foreach ($runsList as $cr) {
                $cIdx = $this->resolvePdfColumnIndex($cr['x'], $cr['centerX'], $dividers, [1]);
                $colRuns[$cIdx][] = [
                    'y' => $row['y'] ?? (float) (1000 - $idx * 10),
                    'x' => $cr['x'],
                    'text' => $cr['text'],
                ];
            }

            $colTexts = array_map(
                fn (array $arr): string => trim(implode(' ', array_column($arr, 'text'))),
                $colRuns
            );

            $nonEmptyCols = array_keys(array_filter($colTexts, fn (string $v): bool => $v !== ''));
            $hasFormattedPrice = preg_match('/\d{1,3}[.,]\d{3}/', $row['line']) === 1;
            $hasTt = preg_match('/^\d{1,3}$/', $colTexts[0]) === 1 || preg_match('/^\d{1,3}$/', $row['cells'][0] ?? '') === 1;
            $hasNumericDims = ($colTexts[5] !== '' && is_numeric(str_replace(',', '.', $colTexts[5])))
                || ($colTexts[9] !== '' && is_numeric(str_replace(',', '.', $colTexts[9])))
                || ($colTexts[10] !== '' && is_numeric(str_replace(',', '.', $colTexts[10])));

            $lineTrim = trim($row['line']);
            $lineUpper = mb_strtoupper($lineTrim, 'UTF-8');
            $isSectionRow = count($row['cells']) === 1
                && ! $hasFormattedPrice
                && ! $hasTt
                && (
                    str_contains($lineUpper, 'BẢO ÔN')
                    || str_contains($lineUpper, 'HẠNG MỤC')
                    || str_contains($lineUpper, 'PHẦN ')
                    || ($lineUpper === $lineTrim && mb_strlen($lineTrim, 'UTF-8') >= 6 && preg_match('/[A-ZÀÁẠẢÃĂẮẰẲẴẶÂẤẦẨẪẬÈÉẸẺẼÊẾỀỂỄỆÌÍỊỈĨÒÓỌỎÕÔỐỒỔỖỘƠỚỜỞỠỢÙÚỤỦŨƯỨỪỬỮỰỲÝỴỶỸ]/u', $lineTrim) === 1 && preg_match('/^(DN\d+|K-FLEX|ST\b|M-FLEX|TUBE|SHEET)/i', $lineTrim) !== 1)
                );

            $isItemAnchor = ! $isSectionRow && ($hasFormattedPrice || ($hasTt && ($hasNumericDims || count($row['cells']) >= 5)));

            $mappedBody[] = [
                'type' => $isSectionRow ? 'section' : ($isItemAnchor ? 'item' : 'wrap'),
                'page' => $row['page'] ?? 1,
                'y' => $row['y'] ?? (float) (1000 - $idx * 10),
                'colRuns' => $colRuns,
                'cells' => $row['cells'],
                'line' => $row['line'],
            ];
        }

        // Attach 'wrap' rows (multi-line wrapped text of Tên vật tư, Nhãn hiệu, Xuất xứ, Loại SP, Ghi chú) to nearest 'item' row on same page
        $itemIndices = [];
        foreach ($mappedBody as $idx => $mb) {
            if ($mb['type'] === 'item') {
                $itemIndices[] = $idx;
            }
        }

        foreach ($mappedBody as $idx => $mb) {
            if ($mb['type'] !== 'wrap' || $itemIndices === []) {
                continue;
            }
            $bestItemIdx = null;
            $bestDist = 1e9;
            foreach ($itemIndices as $iIdx) {
                if ($mappedBody[$iIdx]['page'] !== $mb['page']) {
                    continue;
                }
                $dist = abs($mappedBody[$iIdx]['y'] - $mb['y']);
                if ($dist < $bestDist && $dist <= 36.0) {
                    $bestDist = $dist;
                    $bestItemIdx = $iIdx;
                }
            }
            if ($bestItemIdx !== null) {
                for ($c = 0; $c < 14; $c++) {
                    foreach ($mb['colRuns'][$c] as $cr) {
                        $mappedBody[$bestItemIdx]['colRuns'][$c][] = $cr;
                    }
                }
            }
        }

        foreach ($mappedBody as $mb) {
            if ($mb['type'] === 'section') {
                $kflexGroups[] = [
                    'uid' => 'imp-kf-grp-pdf-'.uniqid(),
                    'hasTitle' => true,
                    'title' => $mb['cells'][0] ?? trim($mb['line']),
                    'items' => [],
                ];
                $currentGroupIdx = count($kflexGroups) - 1;

                continue;
            }

            if ($mb['type'] !== 'item') {
                continue;
            }

            $cols = [];
            for ($c = 0; $c < 14; $c++) {
                $cRuns = $mb['colRuns'][$c];
                usort($cRuns, fn (array $a, array $b): int => abs($a['y'] - $b['y']) > 2.0 ? ($b['y'] <=> $a['y']) : ($a['x'] <=> $b['x']));
                $cols[$c] = trim((string) preg_replace('/\s+/', ' ', implode(' ', array_column($cRuns, 'text'))));
            }

            // Use 2D column-mapped values when available, falling back to sequential cells if columns collapsed
            $nonEmptyColCount = count(array_filter($cols, fn (string $v): bool => $v !== ''));
            if ($nonEmptyColCount >= 6 && ($cols[1] !== '' || $cols[2] !== '')) {
                $itemName = $cols[1];
                $brandLabel = $cols[2] !== '' ? $cols[2] : 'K-Flex ST';
                $originLabel = $cols[3] !== '' ? $cols[3] : 'Ý - Malaysia';
                $productTypeLabel = $cols[4] !== '' ? $cols[4] : 'Chỉ Foam';
                $thickness = $cols[5] !== '' ? $cols[5] : '13';
                $innerDiameter = $cols[6];
                $widthM = $cols[7];
                $lengthM = $cols[8];
                $qtyMStr = $cols[9];
                $qtyM2Str = $cols[10];
                $unitPriceStr = $cols[11];
                $totalStr = $cols[12];
                $note = $cols[13];

                $brandAndName = mb_strtolower($brandLabel.' '.$itemName, 'UTF-8');
                $productLine = 'st';
                if (str_contains($brandAndName, 'protect')) {
                    $productLine = 'k_protect';
                } elseif (str_contains($brandAndName, 'm-flex') || str_contains($brandAndName, 'mflex')) {
                    $productLine = 'm_flex';
                }
                if (! in_array($productLine, $detectedLines, true)) {
                    $detectedLines[] = $productLine;
                }

                $isSheet = ($qtyM2Str !== '' && $qtyMStr === '')
                    || ($widthM !== '' && $innerDiameter === '')
                    || str_contains($brandAndName, 'sheet')
                    || str_contains($brandAndName, 'tấm');
                $formType = $isSheet ? 'sheet' : 'tube';

                $typeLower = mb_strtolower($productTypeLabel, 'UTF-8');
                $variantType = ($formType === 'tube')
                    ? ((str_contains($typeLower, 'không nhôm') || str_contains($typeLower, 'chỉ foam')) ? 'foam' : (str_contains($typeLower, 'nhôm') ? 'alu' : 'foam'))
                    : (str_contains($typeLower, 'cả keo và nhôm') ? 'alu_and_glue' : ((str_contains($typeLower, 'nhôm') || str_contains($typeLower, 'keo')) ? 'alu_or_glue' : 'foam'));

                if ($lengthM === '') {
                    $lengthM = $formType === 'tube' ? ($productLine === 'm_flex' ? '1.83' : '2') : '8';
                }
                if ($formType === 'sheet' && $widthM === '') {
                    $widthM = '1.2';
                }

                $qtyVal = $formType === 'sheet'
                    ? max(0, $this->parseFormattedNumber($qtyM2Str !== '' ? $qtyM2Str : $qtyMStr, 1))
                    : max(0, $this->parseFormattedNumber($qtyMStr !== '' ? $qtyMStr : $qtyM2Str, 1));
                $unitPriceVal = max(0, (int) round($this->parseFormattedNumber($unitPriceStr !== '' ? $unitPriceStr : $totalStr, 0)));
            } else {
                $cells = $mb['cells'];
                $hasTt = preg_match('/^\d{1,3}$/', $cells[0] ?? '') === 1;
                $offset = $hasTt ? 1 : 0;
                $itemName = $cells[$offset] ?? '';
                $brandLabel = $cells[$offset + 1] ?? 'K-Flex ST';
                $originLabel = $cells[$offset + 2] ?? 'Ý - Malaysia';
                $productTypeLabel = $cells[$offset + 3] ?? 'Chỉ Foam';
                $rest = array_slice($cells, $offset + 4);
                if ($itemName === '' || count($rest) < 3) {
                    continue;
                }

                $brandAndName = mb_strtolower($brandLabel.' '.$itemName, 'UTF-8');
                $productLine = 'st';
                if (str_contains($brandAndName, 'protect')) {
                    $productLine = 'k_protect';
                } elseif (str_contains($brandAndName, 'm-flex') || str_contains($brandAndName, 'mflex')) {
                    $productLine = 'm_flex';
                }
                if (! in_array($productLine, $detectedLines, true)) {
                    $detectedLines[] = $productLine;
                }

                $isSheet = str_contains($brandAndName, 'sheet') || str_contains($brandAndName, 'tấm');
                $formType = $isSheet ? 'sheet' : 'tube';
                $typeLower = mb_strtolower($productTypeLabel, 'UTF-8');
                $variantType = ($formType === 'tube')
                    ? ((str_contains($typeLower, 'không nhôm') || str_contains($typeLower, 'chỉ foam')) ? 'foam' : (str_contains($typeLower, 'nhôm') ? 'alu' : 'foam'))
                    : (str_contains($typeLower, 'cả keo và nhôm') ? 'alu_and_glue' : ((str_contains($typeLower, 'nhôm') || str_contains($typeLower, 'keo')) ? 'alu_or_glue' : 'foam'));

                $note = '';
                if ($rest !== [] && preg_match('/^[\d.,\s-]+$/', (string) end($rest)) !== 1) {
                    $note = (string) array_pop($rest);
                }

                $thickness = $rest[0] ?? '13';
                $secondDim = $rest[1] ?? '';
                $lengthM = count($rest) >= 5 ? ($rest[2] ?? '') : ($productLine === 'm_flex' ? '1.83' : '2');
                $innerDiameter = $formType === 'tube' ? $secondDim : '';
                $widthM = $formType === 'sheet' ? ($secondDim ?: '1.2') : '';
                $qtyStr = count($rest) >= 3 ? ($rest[count($rest) - 3] ?? '1') : '1';
                $unitPriceStr = count($rest) >= 2 ? ($rest[count($rest) - 2] ?? '0') : '0';

                $qtyVal = max(0, $this->parseFormattedNumber($qtyStr, 1));
                $unitPriceVal = max(0, (int) round($this->parseFormattedNumber($unitPriceStr, 0)));
            }

            $matchedPrice = $kflexPrices->first(function (KflexPrice $p) use ($productLine, $formType, $variantType, $thickness, $innerDiameter): bool {
                if ($p->product_line !== $productLine || $p->form_type !== $formType || $p->variant_type !== $variantType) {
                    return false;
                }
                if ((int) $p->thickness !== (int) $thickness) {
                    return false;
                }
                if ($formType === 'tube') {
                    return (int) ($p->diameter ?? 0) === (int) $innerDiameter;
                }

                return true;
            });

            if ($currentGroupIdx === null) {
                $kflexGroups[] = [
                    'uid' => 'imp-kf-grp-pdf-'.uniqid(),
                    'hasTitle' => false,
                    'title' => '',
                    'items' => [],
                ];
                $currentGroupIdx = count($kflexGroups) - 1;
            }

            $kflexGroups[$currentGroupIdx]['items'][] = [
                'uid' => 'imp-kf-pdf-'.uniqid(),
                'kflexPriceId' => $matchedPrice?->id,
                'itemName' => $itemName,
                'brandLabel' => $brandLabel,
                'originLabel' => $originLabel,
                'productTypeLabel' => $productTypeLabel,
                'productLine' => $productLine,
                'formType' => $formType,
                'variantType' => $variantType,
                'thickness' => (string) $thickness,
                'innerDiameter' => (string) $innerDiameter,
                'widthM' => (string) $widthM,
                'lengthM' => (string) $lengthM,
                'qtyM' => $formType === 'tube' ? $qtyVal : null,
                'qtyM2' => $formType === 'sheet' ? $qtyVal : null,
                'unitPrice' => $unitPriceVal > 0 ? $unitPriceVal : (int) ($matchedPrice?->price ?? 0),
                'note' => $note,
            ];
        }

        $nonEmptyGroups = array_values(array_filter($kflexGroups, fn (array $g): bool => $g['items'] !== []));

        if ($nonEmptyGroups === []) {
            $matchedItems = [];
            foreach ($kflexPrices as $p) {
                if ($p->product_name !== '' && str_contains($combined, $p->product_name)) {
                    $matchedItems[] = [
                        'uid' => 'imp-kf-pdf-'.$p->id.'-'.uniqid(),
                        'kflexPriceId' => $p->id,
                        'itemName' => $p->product_name,
                        'brandLabel' => $p->product_line === 'k_protect' ? 'K-Flex K-Protect' : ($p->product_line === 'm_flex' ? 'K-Flex M-Flex' : 'K-Flex ST'),
                        'originLabel' => ($p->brand ?: 'Ý').' - '.($p->origin ?: 'Malaysia'),
                        'productTypeLabel' => $p->variant_type === 'alu' ? 'Có nhôm' : 'Chỉ Foam',
                        'productLine' => $p->product_line,
                        'formType' => $p->form_type,
                        'variantType' => $p->variant_type,
                        'thickness' => (string) $p->thickness,
                        'innerDiameter' => (string) ($p->diameter ?? ''),
                        'widthM' => $p->form_type === 'sheet' ? '1.2' : '',
                        'lengthM' => $p->form_type === 'tube' ? '2' : '8',
                        'qtyM' => $p->form_type === 'tube' ? 1 : null,
                        'qtyM2' => $p->form_type === 'sheet' ? 1 : null,
                        'unitPrice' => (int) $p->price,
                        'note' => '',
                    ];
                }
            }
            if ($matchedItems !== []) {
                $hasSecInText = str_contains(mb_strtoupper($combined, 'UTF-8'), 'BẢO ÔN CHO');
                $nonEmptyGroups[] = [
                    'uid' => 'imp-kf-grp-pdf-'.uniqid(),
                    'hasTitle' => $hasSecInText,
                    'title' => $hasSecInText ? 'BẢO ÔN CHO ĐƯỜNG THÉP' : '',
                    'items' => $matchedItems,
                ];
            }
        }

        return [
            'version' => 1,
            'quotationType' => 'second',
            'activeQuotationType' => 'second',
            'kflexQuoteForm' => $kflexQuoteForm,
            'kflexSelectedProductLines' => $detectedLines !== [] ? $detectedLines : ['st'],
            'kflexGroups' => $nonEmptyGroups,
        ];
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<int, array{page?: int, y?: float, cellRuns?: array<int, array{x: float, endX: float, centerX: float, text: string}>, cells: array<int, string>, line: string}>  $structuredRows
     * @param  array<int, float>  $vLines
     * @return array<string, mixed>
     */
    private function parseAristonPdfStructured(array $lines, array $structuredRows, string $combined, string $todayIso, array $vLines = []): array
    {
        $quoteForm = [
            'documentTitle' => 'BÁO GIÁ BƠM NHIỆT ARISTON',
            'recipientCompany' => '',
            'recipientPerson' => '',
            'contactPhone' => '',
            'quoteDate' => $todayIso,
            'validUntilDate' => now()->addMonths(2)->format('Y-m-d'),
            'projectName' => '',
            'vatPercent' => 8,
            'signerName' => 'Nguyễn Tuấn Kiệt',
            'signerPhone' => '0979 807 234',
            'signerEmail' => 'tuankiet@starlightgroup.vn',
        ];

        $afterThanks = false;
        $signerFound = false;
        for ($i = 0; $i < count($lines); $i++) {
            $trim = trim($lines[$i]);
            $lower = mb_strtolower($trim, 'UTF-8');
            if (str_starts_with($lower, 'báo giá') && ! str_contains($lower, 'có hiệu lực')) {
                $quoteForm['documentTitle'] = $trim;
            } elseif (str_starts_with($lower, 'kính gửi')) {
                $val = trim((string) preg_replace('/^kính gửi\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $quoteForm['recipientCompany'] = $val;
            } elseif (str_starts_with($lower, 'người nhận')) {
                $val = trim((string) preg_replace('/^người nhận\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $quoteForm['recipientPerson'] = $val;
            } elseif (str_starts_with($lower, 'thông tin liên lạc')) {
                $val = trim((string) preg_replace('/^thông tin liên lạc\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $quoteForm['contactPhone'] = $val;
            } elseif (str_starts_with($lower, 'thời gian') && ! str_contains($lower, 'giao hàng')) {
                $dStr = trim((string) preg_replace('/^thời gian\s*:?\s*/iu', '', $trim));
                if ($dStr === '' && isset($lines[$i + 1])) {
                    $dStr = trim($lines[$i + 1]);
                }
                if ($dStr !== '') {
                    $quoteForm['quoteDate'] = $this->parseSlashDateToIso($dStr, $todayIso);
                }
            } elseif (str_starts_with($lower, 'project')) {
                $val = trim((string) preg_replace('/^project\s*:?\s*/iu', '', $trim));
                if ($val === '' && isset($lines[$i + 1])) {
                    $val = trim($lines[$i + 1]);
                }
                $quoteForm['projectName'] = $val;
            } elseif (str_contains($lower, 'thuế vat')) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*%/', $trim, $vm) === 1) {
                    $quoteForm['vatPercent'] = (int) $vm[1];
                }
            } elseif (str_contains($lower, 'báo giá có hiệu lực đến ngày')) {
                if (preg_match('/(\d{1,2}[\/-]\d{1,2}[\/-]\d{4}|\d{4}-\d{2}-\d{2})/', $trim, $dm) === 1) {
                    $quoteForm['validUntilDate'] = $this->parseSlashDateToIso($dm[1], $quoteForm['validUntilDate']);
                }
            } elseif (str_contains($lower, 'many thanks')) {
                $afterThanks = true;
            } elseif ($afterThanks) {
                if (preg_match('/^hp\b/i', $trim) === 1) {
                    $quoteForm['signerPhone'] = trim((string) preg_replace('/^hp\s*:?\s*/i', '', $trim));
                } elseif (preg_match('/^email\b/i', $trim) === 1) {
                    $quoteForm['signerEmail'] = trim((string) preg_replace('/^email\s*:?\s*/i', '', $trim));
                } elseif (! $signerFound && mb_strlen($trim, 'UTF-8') > 1 && ! str_contains($trim, ':')) {
                    $quoteForm['signerName'] = $trim;
                    $signerFound = true;
                }
            }
        }

        $aristonProducts = $this->safeAristonProducts();
        $items = [];

        $headerRows = [];
        $bodyRows = [];
        $inTable = false;
        $tableMinX = 1e9;
        $tableMaxX = 0.0;

        foreach ($structuredRows as $row) {
            $upperLine = mb_strtoupper($row['line'], 'UTF-8');
            if (str_starts_with($upperLine, 'TỔNG CỘNG')) {
                break;
            }
            $isHeader = str_contains($upperLine, 'MÃ SẢN PHẨM')
                || str_contains($upperLine, 'GIÁ BÁN DỰ ÁN')
                || str_contains($upperLine, 'ĐẶC TÍNH KỸ THUẬT')
                || str_contains($upperLine, 'SELLING PRICE')
                || str_contains($upperLine, 'SPECIFICATIONS')
                || (str_contains($upperLine, 'PRODUCT') && str_contains($upperLine, 'MODEL'))
                || trim($upperLine) === '(VNĐ)'
                || trim($upperLine) === '(CHIẾC)';
            if ($isHeader) {
                $inTable = true;
                $headerRows[] = $row;
                foreach ($row['cellRuns'] ?? [] as $cr) {
                    $tableMinX = min($tableMinX, $cr['x']);
                    $tableMaxX = max($tableMaxX, $cr['endX']);
                }

                continue;
            }
            if (! $inTable) {
                continue;
            }
            $bodyRows[] = $row;
            foreach ($row['cellRuns'] ?? [] as $cr) {
                $tableMinX = min($tableMinX, $cr['x']);
                $tableMaxX = max($tableMaxX, $cr['endX']);
            }
        }

        if ($tableMinX > $tableMaxX) {
            $tableMinX = 40.0;
            $tableMaxX = 780.0;
        }

        $proportions = [0.090, 0.085, 0.055, 0.175, 0.045, 0.265, 0.105, 0.060, 0.120];
        $matchers = [
            fn (string $s): bool => ($s === 'sản phẩm' || $s === 'product' || str_starts_with($s, 'sản phẩm')),
            fn (string $s): bool => str_contains($s, 'mã sản phẩm') || $s === 'model',
            fn (string $s): bool => str_contains($s, 'xuất xứ') || $s === 'origin',
            fn (string $s): bool => str_contains($s, 'hình ảnh') || $s === 'image',
            fn (string $s): bool => str_contains($s, 'đơn vị'),
            fn (string $s): bool => str_contains($s, 'đặc tính') || str_contains($s, 'specifications'),
            fn (string $s): bool => str_contains($s, 'giá bán') || str_contains($s, 'selling price'),
            fn (string $s): bool => str_contains($s, 'số lượng') || str_contains($s, 'quantity') || str_contains($s, '(chiếc)'),
            fn (string $s): bool => str_contains($s, 'thành tiền') || str_contains($s, 'amount'),
        ];
        $dividers = $this->buildPdfColumnDividers($headerRows, $tableMinX, $tableMaxX, $proportions, $matchers, $vLines);

        // Identify anchor rows in tbody (rows containing unitPrice and/or rowAmount)
        $anchorIndices = [];
        foreach ($bodyRows as $idx => $row) {
            if (preg_match_all('/\b\d{1,3}(?:[.,]\d{3})+\b/', $row['line'], $pm) && count($pm[0]) >= 1) {
                $maxPriceOnRow = 0.0;
                foreach ($pm[0] as $pStr) {
                    $val = $this->parseFormattedNumber($pStr, 0);
                    if ($val > $maxPriceOnRow) {
                        $maxPriceOnRow = $val;
                    }
                }
                if ($maxPriceOnRow >= 10000) {
                    $anchorIndices[] = $idx;
                }
            }
        }

        if ($anchorIndices !== []) {
            $itemBuckets = array_fill(0, count($anchorIndices), array_fill(0, 9, []));

            foreach ($bodyRows as $rIdx => $row) {
                $rowY = $row['y'] ?? (float) (1000 - $rIdx * 10);
                $rowPage = $row['page'] ?? 1;

                // Find which anchor row band this bodyRow belongs to (midpoint between consecutive anchors on same page)
                $targetBucket = 0;
                $bestDist = 1e9;
                foreach ($anchorIndices as $bIdx => $aRowIdx) {
                    $aPage = $bodyRows[$aRowIdx]['page'] ?? 1;
                    $aY = $bodyRows[$aRowIdx]['y'] ?? (float) (1000 - $aRowIdx * 10);
                    $dist = ($aPage === $rowPage ? 0.0 : 10000.0) + abs($rowY - $aY);
                    if ($dist < $bestDist) {
                        $bestDist = $dist;
                        $targetBucket = $bIdx;
                    }
                }

                $runsList = $row['cellRuns'] ?? [];
                if (count($runsList) >= 2 && ($tableMaxX - $tableMinX) > 120.0) {
                    foreach ($runsList as $cr) {
                        $cIdx = $this->resolvePdfColumnIndex($cr['x'], $cr['centerX'], $dividers, [5]);
                        $itemBuckets[$targetBucket][$cIdx][] = [
                            'y' => $rowY,
                            'x' => $cr['x'],
                            'text' => $cr['text'],
                        ];
                    }
                } elseif ($rIdx === $anchorIndices[$targetBucket] && count($row['cells']) >= 4) {
                    $itemBuckets[$targetBucket][0][] = ['y' => $rowY, 'x' => 0.0, 'text' => $row['cells'][0] ?? ''];
                    $itemBuckets[$targetBucket][1][] = ['y' => $rowY, 'x' => 1.0, 'text' => $row['cells'][1] ?? ''];
                    $itemBuckets[$targetBucket][2][] = ['y' => $rowY, 'x' => 2.0, 'text' => $row['cells'][2] ?? ''];
                }
            }

            foreach ($anchorIndices as $bIdx => $aRowIdx) {
                $bucket = $itemBuckets[$bIdx];
                $colJoinedSpace = [];
                $colJoinedNewline = [];
                for ($c = 0; $c < 9; $c++) {
                    $cRuns = $bucket[$c];
                    usort($cRuns, fn (array $a, array $b): int => abs($a['y'] - $b['y']) > 2.0 ? ($b['y'] <=> $a['y']) : ($a['x'] <=> $b['x']));
                    $colJoinedSpace[$c] = trim((string) preg_replace('/\s+/', ' ', implode(' ', array_column($cRuns, 'text'))));
                    // Group runs on same Y into single spec line, different Y into newline
                    $specLines = [];
                    $curSpecY = null;
                    foreach ($cRuns as $sr) {
                        if ($curSpecY === null || abs($sr['y'] - $curSpecY) > 2.5) {
                            $specLines[] = $sr['text'];
                            $curSpecY = $sr['y'];
                        } else {
                            $specLines[count($specLines) - 1] .= ' '.$sr['text'];
                        }
                    }
                    $colJoinedNewline[$c] = trim(implode("\n", $specLines));
                }

                $anchorRow = $bodyRows[$aRowIdx];
                $uPrice = (int) round($this->parseFormattedNumber($colJoinedSpace[6], 0));
                $totalAmt = (int) round($this->parseFormattedNumber($colJoinedSpace[8], 0));
                $qty = (int) round($this->parseFormattedNumber($colJoinedSpace[7], 0));

                if ($uPrice < 1000 && preg_match_all('/\b\d{1,3}(?:[.,]\d{3})+\b/', $anchorRow['line'], $pm) && count($pm[0]) >= 1) {
                    $uPrice = (int) round($this->parseFormattedNumber($pm[0][0], 0));
                    if (count($pm[0]) >= 2) {
                        $totalAmt = (int) round($this->parseFormattedNumber((string) end($pm[0]), 0));
                    }
                }
                if ($qty <= 0) {
                    $qty = ($uPrice > 0 && $totalAmt >= $uPrice) ? max(1, (int) round($totalAmt / $uPrice)) : 1;
                }

                $modelCode = $colJoinedSpace[1];
                $productLabel = $colJoinedSpace[0];
                $normModel = strtolower((string) preg_replace('/[\s-]+/', '', $modelCode));

                $matchedProd = $aristonProducts->first(function (Product $p) use ($normModel, $modelCode, $productLabel): bool {
                    if ($p->name === '') {
                        return false;
                    }
                    $pNorm = strtolower((string) preg_replace('/[\s-]+/', '', $p->name));
                    if ($normModel !== '' && ($pNorm === $normModel || str_contains($normModel, $pNorm) || str_contains($pNorm, $normModel))) {
                        return true;
                    }

                    return $modelCode === '' && $productLabel !== '' && str_contains(mb_strtolower($productLabel, 'UTF-8'), mb_strtolower($p->name, 'UTF-8'));
                });

                $origin = $colJoinedSpace[2] !== ''
                    ? (string) preg_replace('/\s*-\s*/u', '-', $colJoinedSpace[2])
                    : ($matchedProd?->origin ? 'Ý-'.$matchedProd->origin : 'Ý-Việt Nam');

                $items[] = [
                    'uid' => 'imp-ariston-pdf-row-'.uniqid(),
                    'productId' => $matchedProd?->id,
                    'productLabel' => $productLabel !== '' ? $productLabel : ($matchedProd?->product_title ?: 'Máy nước nóng gián tiếp Ariston'),
                    'modelCode' => $modelCode !== '' ? $modelCode : ($matchedProd?->name ?? ''),
                    'origin' => $origin,
                    'imageUrl' => $matchedProd->image[0] ?? '',
                    'unit' => $colJoinedSpace[4] !== '' ? $colJoinedSpace[4] : 'Máy',
                    'specifications' => $colJoinedNewline[5] !== '' ? $colJoinedNewline[5] : ($matchedProd?->description ?? ''),
                    'unitPrice' => $uPrice > 0 ? $uPrice : (int) ($matchedProd?->price ?? 2386000),
                    'quantity' => $qty,
                ];
            }
        }

        if ($items === []) {
            foreach ($aristonProducts as $prod) {
                if ($prod->name !== '' && str_contains($combined, $prod->name)) {
                    $items[] = [
                        'uid' => 'imp-ariston-pdf-'.$prod->id.'-'.uniqid(),
                        'productId' => $prod->id,
                        'productLabel' => $prod->product_title ?: 'Máy nước nóng gián tiếp Ariston',
                        'modelCode' => $prod->name,
                        'origin' => $prod->origin ? 'Ý-'.$prod->origin : 'Ý-Việt Nam',
                        'imageUrl' => $prod->image[0] ?? '',
                        'unit' => 'Máy',
                        'specifications' => $prod->description ?? '',
                        'unitPrice' => (int) ($prod->price > 0 ? $prod->price : 2386000),
                        'quantity' => 1,
                    ];
                }
            }
        }

        return [
            'version' => 1,
            'quotationType' => 'ariston',
            'activeQuotationType' => 'ariston',
            'quoteForm' => $quoteForm,
            'items' => $items,
        ];
    }

    private function decodeHexUtf16(string $hex): string
    {
        $clean = preg_replace('/[^0-9A-Fa-f]/', '', $hex) ?? '';
        if ($clean === '') {
            return '';
        }
        $out = '';
        for ($i = 0; $i + 4 <= strlen($clean); $i += 4) {
            $code = hexdec(substr($clean, $i, 4));
            if ($code > 0) {
                $out .= mb_chr((int) $code, 'UTF-8');
            }
        }

        return $out;
    }

    /**
     * Pure-PHP ZIP reader fallback so .xlsx files work even when PHP's ZipArchive extension is not enabled.
     *
     * @return array<string, string>
     */
    private function readZipEntriesPurePhp(string $path): array
    {
        $bytes = (string) file_get_contents($path);
        $len = strlen($bytes);
        if ($len < 22) {
            throw new RuntimeException('File không phải là file báo giá Excel (.xlsx) hợp lệ.');
        }

        $eocdPos = -1;
        $minSearch = max(0, $len - 65557);
        for ($i = $len - 22; $i >= $minSearch; $i--) {
            if (substr($bytes, $i, 4) === "PK\x05\x06") {
                $eocdPos = $i;
                break;
            }
        }

        if ($eocdPos === -1) {
            throw new RuntimeException('File không phải là file báo giá Excel (.xlsx) hợp lệ.');
        }

        $eocd = unpack('vtotalEntries/VcdSize/VcdOffset', substr($bytes, $eocdPos + 10, 10));
        if (! is_array($eocd)) {
            throw new RuntimeException('Không thể đọc cấu trúc ZIP của file Excel.');
        }

        $totalEntries = (int) $eocd['totalEntries'];
        $cdPos = (int) $eocd['cdOffset'];
        $entries = [];

        for ($idx = 0; $idx < $totalEntries; $idx++) {
            if ($cdPos + 46 > $len || substr($bytes, $cdPos, 4) !== "PK\x01\x02") {
                break;
            }
            $cd = unpack('vmethod/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdisk/vintAttr/VextAttr/VlocalOffset', substr($bytes, $cdPos + 10, 36));
            if (! is_array($cd)) {
                break;
            }
            $nameLen = (int) $cd['nameLen'];
            $extraLen = (int) $cd['extraLen'];
            $commentLen = (int) $cd['commentLen'];
            $compSize = (int) $cd['compSize'];
            $method = (int) $cd['method'];
            $localOffset = (int) $cd['localOffset'];

            $fileName = ltrim(str_replace('\\', '/', substr($bytes, $cdPos + 46, $nameLen)), '/');
            $cdPos += 46 + $nameLen + $extraLen + $commentLen;

            if ($localOffset + 30 > $len || substr($bytes, $localOffset, 4) !== "PK\x03\x04") {
                continue;
            }
            $localHdr = unpack('vnameLen/vextraLen', substr($bytes, $localOffset + 26, 4));
            if (! is_array($localHdr)) {
                continue;
            }
            $dataStart = $localOffset + 30 + (int) $localHdr['nameLen'] + (int) $localHdr['extraLen'];
            $rawData = substr($bytes, $dataStart, $compSize);

            if ($method === 0) {
                $entries[$fileName] = $rawData;
            } elseif ($method === 8) {
                $inflated = @gzinflate($rawData);
                if (is_string($inflated)) {
                    $entries[$fileName] = $inflated;
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function importXlsx(string $path): array
    {
        $entries = $this->readZipEntriesPurePhp($path);

        $embeddedState = null;
        $embeddedJson = $entries['xl/customData/quotation.json'] ?? null;
        if (is_string($embeddedJson) && $embeddedJson !== '') {
            $decoded = json_decode($embeddedJson, true);
            if (is_array($decoded)) {
                $embeddedState = $this->normalizePayload($decoded);
            }
        }

        $firstSheet = $this->firstSheetPathFromEntries($entries);
        $sheetXml = $entries[$firstSheet] ?? $entries['xl/worksheets/sheet1.xml'] ?? false;
        $sharedStringsXml = $entries['xl/sharedStrings.xml'] ?? null;
        $drawingXml = $entries['xl/drawings/drawing1.xml'] ?? null;
        $drawingRelsXml = $entries['xl/drawings/_rels/drawing1.xml.rels'] ?? null;
        $rowImages = [];

        if ($drawingXml !== null && $drawingRelsXml !== null) {
            $rowImages = $this->extractRowImagesFromEntries($entries, $drawingXml, $drawingRelsXml);
        }

        if (! is_string($sheetXml) || $sheetXml === '') {
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
        for ($r = 1; $r <= min($maxRow, 25); $r++) {
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
            'contactPhone' => $baseForm['contactPhone'] ?? '',
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
        for ($r = 5; $r <= min($maxRow, 25); $r++) {
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

        $aristonProducts = $this->safeAristonProducts();
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
            'quotationType' => 'ariston',
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
        for ($r = 5; $r <= min($maxRow, 25); $r++) {
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

        $kflexPrices = $this->safeKflexPrices();
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
            'quotationType' => 'second',
            'activeQuotationType' => 'second',
            'kflexQuoteForm' => $kflexQuoteForm,
            'kflexSelectedProductLines' => $detectedLines !== [] ? $detectedLines : ($embeddedState['kflexSelectedProductLines'] ?? ['st']),
            'kflexGroups' => $nonEmptyGroups !== [] ? $nonEmptyGroups : ($embeddedState['kflexGroups'] ?? []),
        ];
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function firstSheetPathFromEntries(array $entries): string
    {
        $workbook = $entries['xl/workbook.xml'] ?? false;
        $relationships = $entries['xl/_rels/workbook.xml.rels'] ?? false;
        if (! is_string($workbook) || ! is_string($relationships)) {
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
     * @param  array<string, string>  $entries
     * @return array<string, string>
     */
    private function extractRowImagesFromEntries(array $entries, string $drawingXml, string $drawingRelsXml): array
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

            $mediaBytes = $entries[$relMap[$embedId]] ?? false;
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
            $d = (int) $m[1];
            $mo = (int) $m[2];
            if ($mo > 12 && $d <= 12) {
                [$d, $mo] = [$mo, $d];
            }

            return sprintf('%04d-%02d-%02d', (int) $m[3], $mo, $d);
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

    /**
     * @return \Illuminate\Support\Collection<int, KflexPrice>
     */
    private function safeKflexPrices(): \Illuminate\Support\Collection
    {
        try {
            return KflexPrice::query()->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    private function safeAristonProducts(): \Illuminate\Support\Collection
    {
        try {
            return Product::query()->with('images')->get();
        } catch (\Throwable) {
            return collect();
        }
    }
}
