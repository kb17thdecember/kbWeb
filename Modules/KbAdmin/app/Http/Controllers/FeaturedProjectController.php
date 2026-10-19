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
use RuntimeException;

class FeaturedProjectController extends Controller
{
    public function index(): Response
    {
        $projects = FeaturedProject::query()
            ->with([
                'parentCategory:id,name',
                'childCategory:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->each(function (FeaturedProject $project): void {
                $project->setAttribute('image_url', $this->imageUrl($project->image));
            });
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with('children:id,name,parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('featured projects/Index', [
            'projects' => $projects,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules($request));
        $parentCategory = Category::query()->findOrFail($validated['category_id']);
        $childCategory = Category::query()->findOrFail($validated['subcategory_id']);
        $validated['image'] = $this->storeImage($validated['image']);
        $validated['category'] = $parentCategory->name;
        $validated['subcategory'] = $childCategory->name;

        FeaturedProject::create($validated);

        return to_route('admin.case-studies.index');
    }

    public function update(Request $request, FeaturedProject $featuredProject): RedirectResponse
    {
        $validated = $request->validate($this->rules($request, false));
        $parentCategory = Category::query()->findOrFail($validated['category_id']);
        $childCategory = Category::query()->findOrFail($validated['subcategory_id']);
        $oldImage = $featuredProject->image;
        $imageUpload = $validated['image'] ?? null;
        unset($validated['image']);

        if ($imageUpload instanceof UploadedFile) {
            $validated['image'] = $this->storeImage($imageUpload);
        }
        $validated['category'] = $parentCategory->name;
        $validated['subcategory'] = $childCategory->name;

        $featuredProject->update($validated);

        if (isset($validated['image']) && $oldImage !== $featuredProject->image) {
            $this->deleteStoredImage($oldImage);
        }

        return to_route('admin.case-studies.index');
    }

    public function destroy(FeaturedProject $featuredProject): RedirectResponse
    {
        $image = $featuredProject->image;
        $featuredProject->delete();
        $this->deleteStoredImage($image);

        return to_route('admin.case-studies.index');
    }

    /**
     * @return array<string, array<int, string|Illuminate\Validation\Rules\Exists>>
     */
    private function rules(Request $request, bool $imageRequired = true): array
    {
        $rawCategoryId = $request->input('category_id');
        $categoryId = is_string($rawCategoryId) || is_int($rawCategoryId)
            ? filter_var($rawCategoryId, FILTER_VALIDATE_INT)
            : false;
        $categoryId = $categoryId === false ? 0 : $categoryId;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
            ],
            'subcategory_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(
                    fn ($query) => $query->where('parent_id', $categoryId),
                ),
            ],
            'type' => ['required', 'string', 'max:255'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'max:5120'],
        ];
    }

    private function storeImage(UploadedFile $image): string
    {
        $imagePath = $image->store('featured-projects', 'public');
        if ($imagePath === false) {
            throw new RuntimeException('Unable to store the featured project image.');
        }

        return $imagePath;
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
