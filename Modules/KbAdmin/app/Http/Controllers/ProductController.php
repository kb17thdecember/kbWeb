<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
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
            ->with(['category:id,name', 'subcategory:id,name'])
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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('parent_id', $request->input('category_id'))],
            'tag' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['required', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['required', 'string', 'max:15360000'],
        ]);

        $images = $validated['images'] ?? [];
        unset($validated['images']);

        Product::create([
            ...$validated,
            'quantity' => 0,
            'image' => $images,
        ]);

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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_title' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('parent_id', $request->input('category_id'))],
            'tag' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['required', 'string'],
            'images' => ['nullable', 'array'],
            'images.*' => ['required', 'string', 'max:15360000'],
        ]);

        $images = $validated['images'] ?? [];
        unset($validated['images']);

        $product->update([
            ...$validated,
            'image' => $images,
        ]);

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
}
