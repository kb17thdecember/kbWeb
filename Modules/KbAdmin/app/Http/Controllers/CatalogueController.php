<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Catalogue;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class CatalogueController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        return Inertia::render('catalogues/Index', [
            'catalogues' => Catalogue::query()->with('images')->latest()->get(),
            ...$dashboardStats->summary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(true));
        $attributes = $this->attributes($validated);
        $attributes += $this->storePdf($validated['file']);

        $catalogue = Catalogue::create($attributes);

        if (isset($validated['cover'])) {
            $catalogue->syncImagePaths([ImageStorage::storeUpload($validated['cover'], 'catalogues/covers')]);
        }

        return to_route('admin.catalogues.index');
    }

    public function update(Request $request, Catalogue $catalogue): RedirectResponse
    {
        $validated = $request->validate($this->rules(false));
        $attributes = $this->attributes($validated);
        $oldPath = $catalogue->file_path;

        if (isset($validated['file'])) {
            $attributes += $this->storePdf($validated['file']);
        }

        $catalogue->update($attributes);

        if (isset($validated['file'])) {
            Storage::disk('public')->delete($oldPath);
        }

        if (isset($validated['cover'])) {
            $catalogue->syncImagePaths([ImageStorage::storeUpload($validated['cover'], 'catalogues/covers')]);
        }

        return to_route('admin.catalogues.index');
    }

    public function destroy(Catalogue $catalogue): RedirectResponse
    {
        $catalogue->delete();

        return to_route('admin.catalogues.index');
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(bool $fileRequired): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_published' => ['required', 'boolean'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:30720'],
            'cover' => ['nullable', 'image', 'max:5120'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'is_published' => $validated['is_published'],
        ];
    }

    /**
     * @return array{file_path: string, file_name: string, file_size: int}
     */
    private function storePdf(UploadedFile $file): array
    {
        return [
            'file_path' => $file->store('catalogues', 'public'),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => (int) $file->getSize(),
        ];
    }
}
