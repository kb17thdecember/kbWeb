<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class FeaturedProjectController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        $projects = FeaturedProject::query()
            ->with('images')
            ->orderByDesc('created_at')
            ->get();
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children:id,name,parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('featured projects/Index', [
            'projects' => $projects,
            'categories' => $categories,
            ...$dashboardStats->summary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules($request));
        $uploadedImages = $validated['images'] ?? [$validated['image']];
        unset($validated['image'], $validated['images']);

        $project = FeaturedProject::create($validated);
        $project->syncImagePaths($this->storeImages($uploadedImages));

        return to_route('admin.case-studies.index');
    }

    public function update(Request $request, FeaturedProject $featuredProject): RedirectResponse
    {
        $validated = $request->validate($this->rules($request, false));
        $uploadedImages = $validated['images'] ?? [];
        if ($uploadedImages === [] && ($validated['image'] ?? null) instanceof UploadedFile) {
            $uploadedImages = [$validated['image']];
        }

        unset($validated['image'], $validated['images']);
        $featuredProject->update($validated);

        if ($uploadedImages !== []) {
            $featuredProject->syncImagePaths($this->storeImages($uploadedImages));
        }

        return to_route('admin.case-studies.index');
    }

    public function destroy(FeaturedProject $featuredProject): RedirectResponse
    {
        $featuredProject->delete();

        return to_route('admin.case-studies.index');
    }

    /**
     * @return array<string, array<int, string|Illuminate\Validation\Rules\Exists>>
     */
    private function rules(Request $request, bool $imageRequired = true): array
    {
        $rawCategory = $request->input('category');
        $parentCategory = is_string($rawCategory)
            ? Category::query()->whereNull('parent_id')->where('name', $rawCategory)->first(['id'])
            : null;
        $parentCategoryId = $parentCategory?->id ?? 0;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'category' => [
                'required',
                'string',
                'max:255',
                Rule::exists('categories', 'name')->whereNull('parent_id'),
            ],
            'subcategory' => [
                'required',
                'string',
                'max:255',
                Rule::exists('categories', 'name')->where(
                    fn ($query) => $query->where('parent_id', $parentCategoryId),
                ),
            ],
            'type' => ['required', 'string', Rule::in(['1', '2', '3'])],
            'image' => [
                $imageRequired ? 'required_without:images' : 'nullable',
                'image',
                'max:5120',
            ],
            'images' => [
                $imageRequired ? 'required_without:image' : 'nullable',
                'array',
                'min:1',
                'max:3',
            ],
            'images.*' => ['required', 'image', 'max:5120'],
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return list<string>
     */
    private function storeImages(array $images): array
    {
        return array_map(
            fn (UploadedFile $image): string => ImageStorage::storeUpload($image, 'featured-projects'),
            array_values($images),
        );
    }
}
