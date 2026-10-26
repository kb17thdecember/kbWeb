<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProjectReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Modules\KbAdmin\Support\ProjectReportExcelExporter;
use Modules\KbAdmin\Support\ProjectReportExcelImporter;
use RuntimeException;
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

    public function import(Request $request, ProjectReportExcelImporter $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        try {
            $records = $importer->read($request->file('file')->getRealPath());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($records === []) {
            throw ValidationException::withMessages(['file' => 'File Excel không có dòng dữ liệu nào.']);
        }

        $errors = [];
        $validated = [];
        foreach ($records as $rowNumber => $record) {
            $record['project_area'] = $this->matchProvince($record['project_area']) ?? $record['project_area'];
            $record['project_update'] ??= '';

            $validator = Validator::make($record, [
                ...$this->rules(),
                'project_update' => ['present', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $errors[] = "Dòng {$rowNumber}: ".implode(' ', $validator->errors()->all());

                continue;
            }

            $validated[$rowNumber] = [...$validator->validated(), 'project_code' => $record['project_code']];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => array_slice($errors, 0, 10)]);
        }

        DB::transaction(function () use ($validated): void {
            foreach ($validated as $data) {
                $projectCode = $data['project_code'];
                unset($data['project_code']);

                $projectReport = $projectCode !== null
                    ? ProjectReport::query()->where('project_code', $projectCode)->first()
                    : null;

                if ($projectReport !== null) {
                    $projectReport->update($data);

                    continue;
                }

                $projectReport = ProjectReport::query()->create([...$data, 'project_code' => '']);
                $projectReport->update(['project_code' => 'NTK'.$projectReport->id]);
            }
        });

        return to_route('admin.project-reports.index');
    }

    public function store(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $projectReport = ProjectReport::query()->create([
                ...$request->validate($this->rules()),
                'project_code' => '',
            ]);

            $projectReport->update([
                'project_code' => 'NTK'.$projectReport->id,
            ]);
        });

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
            'project_area' => ['required', 'string', 'max:255', Rule::in($this->provinceOptions())],
            'project_location' => ['nullable', 'string', 'max:255'],
            'developer' => ['nullable', 'string', 'max:255'],
            'me_consultant' => ['nullable', 'string', 'max:255'],
            'me_contractor' => ['nullable', 'string', 'max:255'],
            'progress' => ['nullable', 'string', 'max:255'],
            'estimate_value' => ['nullable', 'numeric', 'min:0'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'participating_products' => ['nullable', 'string', 'max:255'],
            'project_status' => ['sometimes', Rule::in(['1', '2', '3'])],
            'project_update' => ['required', 'string', 'max:255'],
        ];
    }

    private function matchProvince(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalize = fn (string $text): string => preg_replace(
            '/^(tinh|thanh pho|tp\.?|t\.p\.?)\s+/',
            '',
            preg_replace('/\s+/', ' ', trim(Str::lower(Str::ascii($text)))) ?? '',
        ) ?? '';

        $needle = $normalize($value);

        foreach ($this->provinceOptions() as $province) {
            if ($normalize($province) === $needle) {
                return $province;
            }
        }

        return null;
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
