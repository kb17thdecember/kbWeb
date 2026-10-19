<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->withCount('products')
                    ->orderBy('name'),
            ])
            ->withCount('children')
            ->orderBy('name')
            ->get();

        $categories->each(function (Category $category): void {
            $category->setAttribute('image_url', $this->imageUrl($category->image));
            $category->children->each(function (Category $child): void {
                $child->setAttribute('image_url', $this->imageUrl($child->image));
            });
        });

        return Inertia::render('categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rawParentId = $request->input('parent_id');
        $parentId = is_string($rawParentId) || is_int($rawParentId)
            ? filter_var($rawParentId, FILTER_VALIDATE_INT)
            : null;
        $parentId = $parentId === false ? null : $parentId;
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where(
                    fn ($query) => $parentId === null
                        ? $query->whereNull('parent_id')
                        : $query->where('parent_id', $parentId),
                ),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->whereNull('parent_id'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'description_2' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        if (($validated['image'] ?? null) instanceof UploadedFile) {
            $validated['image'] = $this->storeImage($validated['image']);
        }

        Category::create([
            ...$validated,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return to_route('admin.categories.index');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')
                    ->ignore($category->id)
                    ->where('parent_id', $category->parent_id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'description_2' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        $oldImage = $category->image;
        $imageUpload = $validated['image'] ?? null;
        $uploadedImage = $imageUpload instanceof UploadedFile;
        $removeImage = (bool) ($validated['remove_image'] ?? false);
        unset($validated['remove_image']);

        if ($uploadedImage) {
            $validated['image'] = $this->storeImage($imageUpload);
        } elseif ($removeImage) {
            $validated['image'] = null;
        } else {
            unset($validated['image']);
        }

        $category->update($validated);

        if (($uploadedImage || $removeImage) && $oldImage !== $category->image) {
            $this->deleteStoredImage($oldImage);
        }

        return to_route('admin.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if (
            ($category->parent_id === null && $category->children()->exists())
            || Product::query()
                ->where($category->parent_id === null ? 'category_id' : 'subcategory_id', $category->id)
                ->exists()
        ) {
            return back()->withErrors([
                'category' => $category->parent_id === null
                    ? 'Không thể xóa danh mục chính khi vẫn còn danh mục con hoặc sản phẩm liên quan.'
                    : 'Không thể xóa dòng danh mục khi vẫn còn sản phẩm liên quan.',
            ]);
        }

        $image = $category->image;
        $category->delete();
        $this->deleteStoredImage($image);

        return to_route('admin.categories.index');
    }

    private function storeImage(UploadedFile $image): string
    {
        $imagePath = $image->store('categories', 'public');
        if ($imagePath === false) {
            throw new RuntimeException('Unable to store the category image.');
        }

        return $imagePath;
    }

    private function deleteStoredImage(?string $image): void
    {
        if ($image === null || ! str_starts_with($image, 'categories/')) {
            return;
        }

        $disk = Storage::disk('public');
        if ($disk->exists($image) && ! $disk->delete($image)) {
            throw new RuntimeException('Unable to delete the category image.');
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
