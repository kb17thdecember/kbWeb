<?php

use App\Models\ProjectReport;
use Inertia\Testing\AssertableInertia as Assert;

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
        'estimate_value' => '500 tỷ đồng',
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

    $this->put(
        route('admin.project-reports.update', $report),
        projectReportAttributes([
            'project_name' => 'Trung tâm thương mại mới',
            'progress' => 'Đã hoàn thành',
        ]),
    )->assertRedirect(route('admin.project-reports.index'));

    expect($report->fresh()->project_name)->toBe('Trung tâm thương mại mới')
        ->and($report->fresh()->progress)->toBe('Đã hoàn thành');
});

test('project reports require the core project details', function () {
    $this->from(route('admin.project-reports.index'))
        ->post(route('admin.project-reports.store'), [])
        ->assertRedirect(route('admin.project-reports.index'))
        ->assertSessionHasErrors([
            'project_name',
            'project_code',
            'project_area',
            'project_location',
            'progress',
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

test('project reports export to an Excel workbook with the requested columns', function () {
    ProjectReport::query()->create(projectReportAttributes([
        'project_name' => 'Dự án điện lực',
        'project_code' => 'PRJ-002',
    ]));

    $response = $this->get(route('admin.project-reports.export'));

    $response->assertOk()
        ->assertDownload('bao-cao-du-an.xlsx')
        ->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

    $filePath = $response->baseResponse->getFile()->getPathname();
    $workbook = new ZipArchive;

    expect($workbook->open($filePath))->toBeTrue();

    $worksheet = $workbook->getFromName('xl/worksheets/sheet1.xml');
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
        ->toContain('PRJ-002');
    expect(simplexml_load_string($worksheet))->toBeInstanceOf(SimpleXMLElement::class);
});
