<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProjectReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Modules\KbAdmin\Support\ProjectReportExcelExporter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectReportController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        return Inertia::render('project reports/Index', [
            'projectReports' => ProjectReport::query()
                ->orderByDesc('id')
                ->get(),
            'provinceOptions' => $this->provinceOptions(),
            ...$dashboardStats->summary(),
        ]);
    }

    public function export(ProjectReportExcelExporter $exporter): BinaryFileResponse
    {
        [$filePath, $filename] = $exporter->create(
            ProjectReport::query()->orderBy('id')->get(),
        );

        return response()
            ->download($filePath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }

    public function store(Request $request): RedirectResponse
    {
        ProjectReport::query()->create($request->validate($this->rules()));

        return to_route('admin.project-reports.index');
    }

    public function update(Request $request, ProjectReport $projectReport): RedirectResponse
    {
        $projectReport->update($request->validate($this->rules()));

        return to_route('admin.project-reports.index');
    }

    public function destroy(ProjectReport $projectReport): RedirectResponse
    {
        $projectReport->delete();

        return to_route('admin.project-reports.index');
    }

    /**
     * @return array<string, array<int, string|In>>
     */
    private function rules(): array
    {
        return [
            'project_name' => ['required', 'string', 'max:255'],
            'project_code' => ['required', 'string', 'max:255'],
            'project_area' => ['required', 'string', 'max:255', Rule::in($this->provinceOptions())],
            'project_location' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'me_consultant' => ['nullable', 'string', 'max:255'],
            'me_contractor' => ['nullable', 'string', 'max:255'],
            'progress' => ['nullable', 'string', 'max:255'],
            'estimate_value' => ['nullable', 'numeric', 'min:0'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'project_update' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function provinceOptions(): array
    {
        return [
            'An Giang',
            'Bắc Ninh',
            'Cà Mau',
            'Cao Bằng',
            'Cần Thơ',
            'Đà Nẵng',
            'Đắk Lắk',
            'Điện Biên',
            'Đồng Nai',
            'Đồng Tháp',
            'Gia Lai',
            'Hà Nội',
            'Hà Tĩnh',
            'Hải Phòng',
            'Hồ Chí Minh',
            'Huế',
            'Hưng Yên',
            'Khánh Hòa',
            'Lai Châu',
            'Lâm Đồng',
            'Lạng Sơn',
            'Lào Cai',
            'Nghệ An',
            'Ninh Bình',
            'Phú Thọ',
            'Quảng Ngãi',
            'Quảng Ninh',
            'Quảng Trị',
            'Sơn La',
            'Tây Ninh',
            'Thái Nguyên',
            'Thanh Hóa',
            'Tuyên Quang',
            'Vĩnh Long',
        ];
    }
}
