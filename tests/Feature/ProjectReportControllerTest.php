<?php

use App\Models\ProjectReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->actingAs(User::factory()->admin()->create()));

function projectReportAttributes(array $overrides = []): array
{
    return array_merge([
        'project_name' => 'Trung tâm thương mại ABC',
        'project_code' => 'PRJ-001',
        'project_area' => 'Hà Nội',
        'project_location' => 'Quận Cầu Giấy',
        'developer' => 'Công ty ABC',
        'me_consultant' => 'Tư vấn M&E',
        'me_contractor' => 'Nhà thầu XYZ',
        'progress' => 'Đang thi công',
        'estimate_value' => '500000000000',
        'contact_person' => 'Nguyễn Văn A',
        'project_update' => 'Hoàn thành phần móng',
    ], $overrides);
}

test('the project reports page lists saved reports', function () {
    $report = ProjectReport::query()->create(projectReportAttributes());

    $this->get(route('admin.project-reports.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('project reports/Index')
            ->has('projectReports', 1)
            ->where('projectReports.0.id', $report->id)
            ->where('projectReports.0.project_name', 'Trung tâm thương mại ABC'),
        );
});

test('project reports can be created and updated', function () {
    $this->post(route('admin.project-reports.store'), projectReportAttributes())
        ->assertRedirect(route('admin.project-reports.index'));

    $report = ProjectReport::query()->sole();
    expect($report->project_status)->toBe('1');
    expect($report->project_code)->toBe('NTK'.$report->id);

    $this->put(
        route('admin.project-reports.update', $report),
        projectReportAttributes([
            'project_name' => 'Trung tâm thương mại mới',
            'progress' => 'Đã hoàn thành',
            'project_code' => 'OVERRIDE',
        ]),
    )->assertRedirect(route('admin.project-reports.index'));

    expect($report->fresh()->project_name)->toBe('Trung tâm thương mại mới')
        ->and($report->fresh()->progress)->toBe('Đã hoàn thành')
        ->and($report->fresh()->project_code)->toBe('NTK'.$report->id);
});

test('project reports require the core project details but not progress', function () {
    $this->from(route('admin.project-reports.index'))
        ->post(route('admin.project-reports.store'), [])
        ->assertRedirect(route('admin.project-reports.index'))
        ->assertSessionHasErrors([
            'project_name',
            'project_area',
            'project_update',
        ]);

    expect(ProjectReport::query()->count())->toBe(0);
});

test('project reports can be deleted', function () {
    $report = ProjectReport::query()->create(projectReportAttributes());

    $this->delete(route('admin.project-reports.destroy', $report))
        ->assertRedirect(route('admin.project-reports.index'));

    $this->assertDatabaseMissing('project_reports', ['id' => $report->id]);
});

test('project reports allow an empty progress and require a current province or city', function () {
    $this->post(route('admin.project-reports.store'), projectReportAttributes([
        'progress' => '',
        'project_area' => 'Hà Nội',
    ]))->assertRedirect(route('admin.project-reports.index'));

    $report = ProjectReport::query()->sole();
    expect($report->progress)->toBeNull();

    $this->from(route('admin.project-reports.index'))
        ->post(route('admin.project-reports.store'), projectReportAttributes([
            'project_area' => 'Tỉnh không tồn tại',
        ]))
        ->assertRedirect(route('admin.project-reports.index'))
        ->assertSessionHasErrors(['project_area']);

    $this->from(route('admin.project-reports.index'))
        ->post(route('admin.project-reports.store'), projectReportAttributes([
            'estimate_value' => '500 tỷ đồng',
        ]))
        ->assertRedirect(route('admin.project-reports.index'))
        ->assertSessionHasErrors(['estimate_value']);

    expect(ProjectReport::query()->count())->toBe(1);
});

test('project reports can be created without a project location', function () {
    $this->post(route('admin.project-reports.store'), projectReportAttributes([
        'project_location' => null,
    ]))->assertRedirect(route('admin.project-reports.index'));

    $report = ProjectReport::query()->sole();
    expect($report->project_location)->toBeNull();
});

test('project reports export to an Excel workbook with the requested columns', function () {
    ProjectReport::query()->create(projectReportAttributes([
        'project_name' => 'Dự án điện lực',
        'project_code' => 'PRJ-002',
        'estimate_value' => '1250000000',
        'project_update' => str_repeat('Cập nhật dự án theo đơn vị hành chính mới. ', 6),
    ]));

    $response = $this->get(route('admin.project-reports.export'));

    $response->assertOk()
        ->assertDownload('Nguyen Tuan Kiet-Bao Cao Du An.xlsx')
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
    expect($response->headers->get('content-disposition'))
        ->toContain("filename*=utf-8''Nguy%E1%BB%85n%20Tu%E1%BA%A5n%20Ki%E1%BB%87t-B%C3%A1o%20C%C3%A1o%20D%E1%BB%B1%20%C3%81n.xlsx");

    $filePath = $response->baseResponse->getFile()->getPathname();
    $workbook = new ZipArchive;

    expect($workbook->open($filePath))->toBeTrue();

    $worksheet = $workbook->getFromName('xl/worksheets/sheet1.xml');
    $styles = $workbook->getFromName('xl/styles.xml');
    $workbook->close();
    unlink($filePath);

    expect($worksheet)->toBeString()
        ->toContain('STT')
        ->toContain('Tên dự án')
        ->toContain('Mã dự án')
        ->toContain('Khu vực')
        ->toContain('Địa chỉ')
        ->toContain('Đơn vị phát triển')
        ->toContain('Tư vấn')
        ->toContain('Nhà thầu M&amp;E')
        ->toContain('Tiến độ')
        ->toContain('Giá trị ước tính')
        ->toContain('Contact person')
        ->toContain('Cập nhật dự án')
        ->toContain('Dự án điện lực')
        ->toContain('PRJ-002')
        ->toContain('customWidth="1"')
        ->toContain('customHeight="1"')
        ->toContain('width="60"')
        ->toContain('ht="91"')
        ->toContain('r="A2" s="4" t="n"><v>1</v>')
        ->toContain('r="J2" s="3" t="n"><v>1250000000</v>');
    expect($styles)->toBeString()
        ->toContain('name val="Times New Roman"')
        ->toContain('<b/>')
        ->toContain('wrapText="1"')
        ->toContain('style="thin"');
    expect(simplexml_load_string($worksheet))->toBeInstanceOf(SimpleXMLElement::class);
    expect(simplexml_load_string($styles))->toBeInstanceOf(SimpleXMLElement::class);
});

test('project reports can be imported from an exported Excel workbook', function () {
    $existing = ProjectReport::query()->create(projectReportAttributes(['project_code' => 'NTK-EXIST']));
    $response = $this->get(route('admin.project-reports.export'));
    $filePath = $response->baseResponse->getFile()->getPathname();
    $contents = file_get_contents($filePath);
    unlink($filePath);

    $existing->update(['project_name' => 'Tên đã thay đổi']);
    ProjectReport::query()->create(projectReportAttributes(['project_code' => 'NTK-OTHER']))->delete();

    $workbook = tempnam(sys_get_temp_dir(), 'import-').'.xlsx';
    file_put_contents($workbook, $contents);
    $upload = new UploadedFile($workbook, 'reports.xlsx', null, null, true);

    $this->post(route('admin.project-reports.import'), ['file' => $upload])
        ->assertRedirect(route('admin.project-reports.index'));

    expect(ProjectReport::query()->count())->toBe(1);
    expect($existing->fresh()->project_name)->toBe('Trung tâm thương mại ABC');
    expect($existing->fresh()->project_area)->toBe('Hà Nội');

    ProjectReport::query()->whereKey($existing->id)->delete();
    $this->post(route('admin.project-reports.import'), ['file' => $upload])->assertRedirect();
    expect(ProjectReport::query()->sole()->project_code)->toBe('NTK'.ProjectReport::query()->sole()->id);
});

test('project report import rejects invalid files and rows', function () {
    $this->post(route('admin.project-reports.import'), [
        'file' => UploadedFile::fake()->createWithContent('bad.xlsx', 'not a zip'),
    ])->assertSessionHasErrors('file');

    expect(ProjectReport::query()->count())->toBe(0);
});

test('project reports store a work status and participating products', function () {
    $this->post(route('admin.project-reports.store'), projectReportAttributes([
        'project_status' => '2',
        'participating_products' => 'Ariston, K-FLEX',
    ]))->assertRedirect(route('admin.project-reports.index'));

    $report = ProjectReport::query()->sole();
    expect($report->project_status)->toBe('2');
    expect($report->participating_products)->toBe('Ariston, K-FLEX');

    $this->post(route('admin.project-reports.store'), projectReportAttributes(['project_status' => '9']))
        ->assertSessionHasErrors('project_status');
});

test('the Excel export colours rows by work status and adds a legend without a status column', function () {
    ProjectReport::query()->create(projectReportAttributes(['project_name' => 'Dự án bình thường']));
    ProjectReport::query()->create(projectReportAttributes(['project_name' => 'Dự án nóng', 'project_status' => '2']));
    ProjectReport::query()->create(projectReportAttributes([
        'project_name' => 'Dự án cần hỗ trợ',
        'project_status' => '3',
        'participating_products' => 'Ariston',
    ]));

    $response = $this->get(route('admin.project-reports.export'));
    $filePath = $response->baseResponse->getFile()->getPathname();
    $workbook = new ZipArchive;
    $workbook->open($filePath);
    $worksheet = $workbook->getFromName('xl/worksheets/sheet1.xml');
    $styles = $workbook->getFromName('xl/styles.xml');
    $workbook->close();
    unlink($filePath);

    expect($worksheet)
        ->toContain('Sản phẩm tham gia')
        ->toContain('Ariston')
        ->toContain('s="8"')
        ->toContain('s="5"')
        ->toContain('Chú thích')
        ->toContain('Dự án đang nóng: nền đỏ, chữ đen.')
        ->toContain('Dự án cần hỗ trợ thông tin: chữ màu đỏ.')
        ->not->toContain('Tiến độ làm việc');
    expect($styles)->toContain('FFFF0000');
});

test('project reports can still be imported from a file without the participating products column', function () {
    $report = ProjectReport::query()->create(projectReportAttributes([
        'project_code' => 'NTK-OLD',
        'participating_products' => 'Giữ nguyên',
    ]));

    $response = $this->get(route('admin.project-reports.export'));
    $filePath = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    $zip->open($filePath);
    $sheet = str_replace('Sản phẩm tham gia', 'Cột khác', $zip->getFromName('xl/worksheets/sheet1.xml'));
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    $upload = new UploadedFile($filePath, 'old.xlsx', null, null, true);
    $this->post(route('admin.project-reports.import'), ['file' => $upload])->assertSessionHasNoErrors();

    expect($report->fresh()->participating_products)->toBe('Giữ nguyên');
});
