<?php

namespace Modules\KbAdmin\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class ProjectReportExcelImporter
{
    /**
     * Header labels written by ProjectReportExcelExporter, mapped to model fields.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'Tên dự án' => 'project_name',
        'Mã dự án' => 'project_code',
        'Khu vực' => 'project_area',
        'Địa chỉ' => 'project_location',
        'Đơn vị phát triển' => 'developer',
        'Tư vấn' => 'me_consultant',
        'Nhà thầu M&E' => 'me_contractor',
        'Tiến độ' => 'progress',
        'Giá trị ước tính' => 'estimate_value',
        'Contact person' => 'contact_person',
        'Sản phẩm tham gia' => 'participating_products',
        'Cập nhật dự án' => 'project_update',
    ];

    /**
     * Columns that older exports do not contain.
     *
     * @var array<int, string>
     */
    private const OPTIONAL_COLUMNS = ['Sản phẩm tham gia'];

    /**
     * @return array<int, array<string, string|null>> Rows keyed by spreadsheet row number.
     */
    public function read(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to import Excel files.');
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('File không phải là file Excel (.xlsx) hợp lệ.');
        }

        try {
            $sheetXml = $archive->getFromName($this->firstSheetPath($archive));
            $sharedStrings = $this->sharedStrings($archive->getFromName('xl/sharedStrings.xml') ?: null);
        } finally {
            $archive->close();
        }

        if ($sheetXml === false) {
            throw new RuntimeException('Không đọc được trang tính đầu tiên của file Excel.');
        }

        $sheet = $this->parseXml($sheetXml);
        $grid = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $rowNumber = (int) $row['r'];
            foreach ($row->c as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $grid[$rowNumber][$column] = $this->cellValue($cell, $sharedStrings);
            }
        }

        if ($grid === []) {
            throw new RuntimeException('File Excel không có dữ liệu.');
        }

        ksort($grid);
        $headerRow = array_key_first($grid);
        $fieldByColumn = [];
        foreach ($grid[$headerRow] as $column => $label) {
            $label = trim((string) $label);
            if (isset(self::COLUMNS[$label])) {
                $fieldByColumn[$column] = self::COLUMNS[$label];
            }
        }

        $missing = array_diff(array_keys(self::COLUMNS), self::OPTIONAL_COLUMNS, array_map(
            fn (string $field): string => (string) array_search($field, self::COLUMNS, true),
            $fieldByColumn,
        ));
        if ($missing !== []) {
            throw new RuntimeException('File Excel thiếu các cột: '.implode(', ', $missing).'.');
        }

        $records = [];
        foreach ($grid as $rowNumber => $cells) {
            if ($rowNumber === $headerRow) {
                continue;
            }

            if (trim($cells[1] ?? '') === 'Chú thích') {
                break;
            }

            $record = array_fill_keys($fieldByColumn, null);
            foreach ($fieldByColumn as $column => $field) {
                $value = isset($cells[$column]) ? trim($cells[$column]) : '';
                $record[$field] = $value === '' ? null : $value;
            }

            if (array_filter($record, fn (?string $value): bool => $value !== null) !== []) {
                $records[$rowNumber] = $record;
            }
        }

        return $records;
    }

    private function firstSheetPath(ZipArchive $archive): string
    {
        $workbook = $archive->getFromName('xl/workbook.xml');
        $relationships = $archive->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $relationships === false) {
            throw new RuntimeException('File Excel không đúng định dạng.');
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

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?? '';
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index;
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($element === false) {
            throw new RuntimeException('File Excel bị lỗi hoặc không đúng định dạng.');
        }

        return $element;
    }
}
