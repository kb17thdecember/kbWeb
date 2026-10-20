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

        return [$filePath, 'bao-cao-du-an.xlsx'];
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
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
            .'</Relationships>';
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
            $rows[] = array_map(
                fn (string $field): string => (string) ($report->{$field} ?? ''),
                array_values($columns),
            );
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $cells = '';
            foreach ($row as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'
                    .$this->escapeXml($value)
                    .'</t></is></c>';
            }
            $sheetRows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRows.'</sheetData>'
            .'</worksheet>';
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
