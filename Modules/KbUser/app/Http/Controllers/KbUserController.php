<?php

namespace Modules\KbUser\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class KbUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): InertiaResponse
    {
        $categories = Category::query()
            ->with('children:id,name,parent_id')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'image']);

        $products = Product::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'category_id',
                'subcategory_id',
                'description',
                'price',
                'image',
            ]);

        $featuredProjects = FeaturedProject::query()
            ->latest()
            ->limit(3)
            ->get([
                'id',
                'name',
                'description',
                'location',
                'category',
                'subcategory',
                'type',
                'image',
            ]);

        return Inertia::render('Home', [
            'categories' => $categories,
            'products' => $products,
            'featuredProjects' => $featuredProjects,
        ]);
    }

    public function categories(Request $request): InertiaResponse
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
            ->get(['id', 'name', 'parent_id', 'description', 'image']);

        $requestedCategoryId = $request->integer('category');
        $selectedCategory = $categories->firstWhere('id', $requestedCategoryId);

        $categories->each(function (Category $category): void {
            $category->setAttribute(
                'image_url',
                $category->image ? Storage::disk('public')->url($category->image) : null,
            );
            $category->children->each(function (Category $child): void {
                $child->setAttribute(
                    'image_url',
                    $child->image ? Storage::disk('public')->url($child->image) : null,
                );
            });
        });

        return Inertia::render('categories/Index', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategory?->id,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('kbuser::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('kbuser::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('kbuser::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
