<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class CategoryController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with([
                'images',
                'children' => fn ($query) => $query
                    ->with('images')
                    ->withCount('products')
                    ->orderBy('name'),
            ])
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return Inertia::render('categories/Index', [
            'categories' => $categories,
            ...$dashboardStats->summary(),
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
            'description_2' => ['nullable', 'string', 'max:65535'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $uploadedImage = $validated['image'] ?? null;
        unset($validated['image']);

        $category = Category::create([
            ...$validated,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        if ($uploadedImage instanceof UploadedFile) {
            $category->syncImagePaths([ImageStorage::storeUpload($uploadedImage, 'categories')]);
        }

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
            'description_2' => ['nullable', 'string', 'max:65535'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['sometimes', 'boolean'],
        ]);

        $imageUpload = $validated['image'] ?? null;
        $removeImage = (bool) ($validated['remove_image'] ?? false);
        unset($validated['remove_image'], $validated['image']);

        $category->update($validated);

        if ($imageUpload instanceof UploadedFile) {
            $category->syncImagePaths([ImageStorage::storeUpload($imageUpload, 'categories')]);
        } elseif ($removeImage) {
            $category->syncImagePaths([]);
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

        $category->delete();

        return to_route('admin.categories.index');
    }
}
