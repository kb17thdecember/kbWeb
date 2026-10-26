<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Image;
use App\Models\Product;
use App\Support\ImageStorage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        $categories = Category::query()
            ->with('children:id,name,parent_id')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
        $products = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->get();

        return Inertia::render('products/Index', [
            'products' => $products,
            'categories' => $categories,
            ...$dashboardStats->summary(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('kbadmin::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules($request));

        $images = $validated['images'] ?? [];
        unset($validated['images']);

        $product = Product::create([
            ...$validated,
            'quantity' => 0,
        ]);
        $product->syncImageInputs($images, 'products');

        return to_route('admin.products.index');
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('kbadmin::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('kbadmin::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate($this->rules($request, $product));

        $images = $validated['images'] ?? [];
        unset($validated['images']);

        $product->update($validated);
        $product->syncImageInputs($images, 'products');

        return to_route('admin.products.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return to_route('admin.products.index');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(Request $request, ?Product $product = null): array
    {
        $existingUrls = $product?->images()->get()->map(fn (Image $image): string => $image->url)->all() ?? [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'product_title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('parent_id', $request->input('category_id'))],
            'tag' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'price' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'description' => ['required', 'string', 'max:65535'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => [
                'required',
                'string',
                'max:15360000',
                function (string $attribute, mixed $value, Closure $fail) use ($existingUrls): void {
                    $isValid = in_array($value, $existingUrls, true)
                        || ImageStorage::isExternalUrl($value)
                        || ImageStorage::isDataUri($value);

                    if (! $isValid) {
                        $fail('Ảnh sản phẩm không hợp lệ (chỉ hỗ trợ PNG, JPG, GIF, WEBP).');
                    }
                },
            ],
        ];
    }
}
