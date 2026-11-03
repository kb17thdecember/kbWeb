<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Support\ImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
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
        $products = Product::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'category_id', 'subcategory_id']);

        return Inertia::render('featured projects/Index', [
            'projects' => $projects,
            'categories' => $categories,
            'products' => $products,
            ...$dashboardStats->summary(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->prepareCategoryInputs($request);
        $validated = $request->validate($this->rules($request));
        $uploadedImages = $validated['images'] ?? [$validated['image']];
        unset($validated['image'], $validated['images']);
        $validated = $this->normalizeProductsPayload($validated);

        $project = FeaturedProject::create($validated);
        $project->syncImagePaths($this->storeImages($uploadedImages));

        return to_route('admin.case-studies.index');
    }

    public function update(Request $request, FeaturedProject $featuredProject): RedirectResponse
    {
        $this->prepareCategoryInputs($request);
        $validated = $request->validate($this->rules($request, false));
        $uploadedImages = $validated['images'] ?? [];
        if ($uploadedImages === [] && ($validated['image'] ?? null) instanceof UploadedFile) {
            $uploadedImages = [$validated['image']];
        }

        unset($validated['image'], $validated['images']);
        $validated = $this->normalizeProductsPayload($validated);
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

    private function prepareCategoryInputs(Request $request): void
    {
        $categories = $this->parseSelectionList($request->input('categories', $request->input('category')));
        $subcategories = $this->parseSelectionList($request->input('subcategories', $request->input('subcategory')));

        if ($categories !== [] || is_array($request->input('category')) || $request->has('categories')) {
            $request->merge([
                'category' => implode(', ', $categories),
            ]);
        }

        if ($subcategories !== [] || is_array($request->input('subcategory')) || $request->has('subcategories')) {
            $request->merge([
                'subcategory' => implode(', ', $subcategories),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function parseSelectionList(mixed $value): array
    {
        $rawItems = is_array($value)
            ? $value
            : (is_string($value) ? explode(',', $value) : []);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $rawItems,
        ))));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request, bool $imageRequired = true): array
    {
        $categoryNames = $this->parseSelectionList($request->input('categories', $request->input('category')));
        $parentCategoryIds = $categoryNames !== []
            ? Category::query()
                ->whereNull('parent_id')
                ->whereIn('name', $categoryNames)
                ->pluck('id')
                ->all()
            : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:350'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
            'location' => ['required', 'string', 'max:255'],
            'category' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $selected = $this->parseSelectionList($value);
                    if ($selected === []) {
                        $fail('Vui lòng chọn ít nhất một hạng mục.');

                        return;
                    }

                    $validCount = Category::query()
                        ->whereNull('parent_id')
                        ->whereIn('name', $selected)
                        ->count();

                    if ($validCount !== count($selected)) {
                        $fail('Hạng mục đã chọn không hợp lệ.');
                    }
                },
            ],
            'subcategory' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($parentCategoryIds): void {
                    $selected = $this->parseSelectionList($value);
                    if ($selected === [] || $parentCategoryIds === []) {
                        $fail('Nhóm sản phẩm đã chọn không hợp lệ.');

                        return;
                    }

                    $validNames = Category::query()
                        ->whereIn('parent_id', $parentCategoryIds)
                        ->whereIn('name', $selected)
                        ->pluck('name')
                        ->unique()
                        ->all();

                    if (count($validNames) !== count($selected)) {
                        $fail('Nhóm sản phẩm đã chọn không thuộc hạng mục đã chọn.');
                    }
                },
            ],
            'type' => ['required', 'string', Rule::in(['1', '2', '3'])],
            'products' => ['nullable', 'array'],
            'products.*' => ['required', 'string', 'max:255'],
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
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeProductsPayload(array $validated): array
    {
        $validated['category'] = implode(', ', $this->parseSelectionList($validated['category'] ?? ''));
        $validated['subcategory'] = implode(', ', $this->parseSelectionList($validated['subcategory'] ?? ''));

        if (! Schema::hasColumn('featured_projects', 'products')) {
            unset($validated['products']);

            return $validated;
        }

        if (array_key_exists('products', $validated)) {
            $validated['products'] = array_values(array_unique(array_filter(array_map(
                static fn (mixed $item): string => trim((string) $item),
                (array) $validated['products'],
            ))));
        }

        return $validated;
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
