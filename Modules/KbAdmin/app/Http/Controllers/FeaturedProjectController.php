<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FeaturedProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use RuntimeException;

class FeaturedProjectController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        $projects = FeaturedProject::query()
            ->orderByDesc('created_at')
            ->get()
            ->each(function (FeaturedProject $project): void {
                $images = $this->projectImages($project);
                $project->setAttribute('images', $images);
                $project->setAttribute(
                    'images_url',
                    array_values(array_filter(array_map(
                        fn (string $image): ?string => $this->imageUrl($image),
                        $images,
                    ))),
                );
                $project->setAttribute('image_url', $this->imageUrl($images[0] ?? null));
            });
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
        $imagePaths = $this->storeImages($uploadedImages);
        $validated['image'] = $imagePaths[0];
        $validated['images'] = $imagePaths;

        FeaturedProject::create($validated);

        return to_route('admin.case-studies.index');
    }

    public function update(Request $request, FeaturedProject $featuredProject): RedirectResponse
    {
        $validated = $request->validate($this->rules($request, false));
        $oldImages = $this->projectImages($featuredProject);
        $uploadedImages = $validated['images'] ?? [];
        if ($uploadedImages === [] && ($validated['image'] ?? null) instanceof UploadedFile) {
            $uploadedImages = [$validated['image']];
        }

        unset($validated['image'], $validated['images']);
        if ($uploadedImages !== []) {
            $imagePaths = $this->storeImages($uploadedImages);
            $validated['image'] = $imagePaths[0];
            $validated['images'] = $imagePaths;
        }
        $featuredProject->update($validated);

        if (isset($validated['images'])) {
            foreach ($oldImages as $oldImage) {
                if (! in_array($oldImage, $validated['images'], true)) {
                    $this->deleteStoredImage($oldImage);
                }
            }
        }

        return to_route('admin.case-studies.index');
    }

    public function destroy(FeaturedProject $featuredProject): RedirectResponse
    {
        $images = $this->projectImages($featuredProject);
        $featuredProject->delete();
        foreach ($images as $image) {
            $this->deleteStoredImage($image);
        }

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
     * @return array<int, string>
     */
    private function storeImages(array $images): array
    {
        $imagePaths = [];
        foreach ($images as $image) {
            $imagePath = $image->store('featured-projects', 'public');
            if ($imagePath === false) {
                throw new RuntimeException('Unable to store a featured project image.');
            }
            $imagePaths[] = $imagePath;
        }

        return $imagePaths;
    }

    /**
     * @return array<int, string>
     */
    private function projectImages(FeaturedProject $project): array
    {
        $images = $project->images ?? [];

        return $images !== [] ? $images : [$project->image];
    }

    private function deleteStoredImage(?string $image): void
    {
        if ($image === null || ! str_starts_with($image, 'featured-projects/')) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($image) && ! $disk->delete($image)) {
            throw new RuntimeException('Unable to delete the featured project image.');
        }
    }

    private function imageUrl(?string $image): ?string
    {
        if ($image === null || $image === '') {
            return null;
        }

        if (
            str_starts_with($image, 'http://')
            || str_starts_with($image, 'https://')
            || str_starts_with($image, 'data:image/')
        ) {
            return $image;
        }

        return Storage::disk('public')->url($image);
    }
}
