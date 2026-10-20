<?php

namespace Modules\KbAdmin\Support;

use App\Models\ProjectReport;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use ZipArchive;

class ProjectReportExcelExporter
{
    /**
     * @return array{0: string, 1: string}
     */
    public function create(Collection $reports): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is required to export Excel files.');
        }

        foreach ($reports as $report) {
            if ($report->estimate_value !== null && $report->estimate_value !== '' && ! is_numeric($report->estimate_value)) {
                throw new RuntimeException("Project report [{$report->id}] has a non-numeric estimated value and cannot be exported.");
            }
        }

        $filePath = tempnam(sys_get_temp_dir(), 'project-reports-');
        if ($filePath === false) {
            throw new RuntimeException('Unable to create a temporary Excel export file.');
        }

        $archive = new ZipArchive;
        if ($archive->open($filePath, ZipArchive::OVERWRITE) !== true) {
            unlink($filePath);

            throw new RuntimeException('Unable to open the Excel export file.');
        }

        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->packageRelationships(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/worksheets/sheet1.xml' => $this->worksheet($reports),
            'xl/styles.xml' => $this->styles(),
        ];

        foreach ($files as $name => $contents) {
            if (! $archive->addFromString($name, $contents)) {
                $archive->close();
                unlink($filePath);

                throw new RuntimeException("Unable to add [{$name}] to the Excel export.");
            }
        }

        if (! $archive->close()) {
            unlink($filePath);

            throw new RuntimeException('Unable to finish the Excel export file.');
        }

        return [$filePath, 'Nguyễn Tuấn Kiệt-Báo Cáo Dự Án.xlsx'];
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function packageRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Báo cáo dự án" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.##"/></numFmts>'
            .'<fonts count="2">'
            .'<font><sz val="14"/><name val="Times New Roman"/><family val="1"/></font>'
            .'<font><b/><sz val="14"/><name val="Times New Roman"/><family val="1"/></font>'
            .'</fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="2">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF000000"/></left><right style="thin"><color rgb="FF000000"/></right><top style="thin"><color rgb="FF000000"/></top><bottom style="thin"><color rgb="FF000000"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * @param  Collection<int, ProjectReport>  $reports
     */
    private function worksheet(Collection $reports): string
    {
        $columns = [
            'STT' => 'id',
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
            'Cập nhật dự án' => 'project_update',
        ];
        $rows = [array_keys($columns)];

        foreach ($reports as $report) {
            $rows[] = array_values(array_map(
                fn (string $field): string|int|float|null => $report->{$field},
                $columns,
            ));
        }

        $columnWidths = $this->columnWidths($rows);
        $columnDefinitions = '';
        foreach ($columnWidths as $index => $width) {
            $column = $index + 1;
            $columnDefinitions .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            $rowHeight = 22;
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $styleId = $rowIndex === 0 ? 2 : 1;

                if ($rowIndex > 0 && $columnIndex === 0) {
                    $styleId = 4;
                } elseif ($rowIndex > 0 && $columnIndex === 9) {
                    $styleId = 3;
                }

                $rowHeight = max(
                    $rowHeight,
                    $this->rowHeight((string) ($value ?? ''), $columnWidths[$columnIndex]),
                );

                if ($value === null || $value === '') {
                    $cells .= '<c r="'.$reference.'" s="'.$styleId.'"/>';
                } elseif ($rowIndex > 0 && in_array($columnIndex, [0, 9], true)) {
                    $cells .= '<c r="'.$reference.'" s="'.$styleId.'" t="n"><v>'
                        .$this->escapeXml((string) $value)
                        .'</v></c>';
                } else {
                    $cells .= '<c r="'.$reference.'" s="'.$styleId.'" t="inlineStr"><is><t xml:space="preserve">'
                        .$this->escapeXml((string) $value)
                        .'</t></is></c>';
                }
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'" ht="'.$rowHeight.'" customHeight="1">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols>'.$columnDefinitions.'</cols>'
            .'<sheetData>'.$sheetRows.'</sheetData>'
            .'</worksheet>';
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @return array<int, int>
     */
    private function columnWidths(array $rows): array
    {
        $widths = array_fill(0, count($rows[0]), 12);

        foreach ($rows as $row) {
            foreach ($row as $index => $value) {
                $textWidth = max(array_map(
                    fn (string $line): int => mb_strwidth($line, 'UTF-8'),
                    explode("\n", (string) ($value ?? '')),
                ));
                $widths[$index] = min(60, max($widths[$index], $textWidth + 2));
            }
        }

        return $widths;
    }

    private function rowHeight(string $value, int $columnWidth): int
    {
        $lineCount = 0;
        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [''] as $line) {
            $lineWidth = mb_strwidth($line, 'UTF-8');
            $lineCount += max(1, (int) ceil($lineWidth / max(1, $columnWidth - 2)));
        }

        return min(409, max(22, (int) ($lineCount * 17 + 6)));
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function escapeXml(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        if ($value === null) {
            throw new RuntimeException('Unable to encode a project report value for Excel.');
        }

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
