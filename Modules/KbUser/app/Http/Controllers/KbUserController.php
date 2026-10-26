<?php

namespace Modules\KbUser\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryReceived;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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
            ->with(['images', 'children:id,name,parent_id'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $products = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'product_title',
                'category_id',
                'subcategory_id',
                'description',
                'price',
            ]);

        $featuredProjects = FeaturedProject::query()
            ->with('images')
            ->latest()
            ->get([
                'id',
                'name',
                'description',
                'location',
                'category',
                'subcategory',
                'type',
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
                'images',
                'children' => fn ($query) => $query
                    ->with('images')
                    ->withCount('products')
                    ->orderBy('name'),
            ])
            ->withCount('children')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'description']);

        $requestedCategoryId = $request->integer('category');
        $selectedCategory = $categories->firstWhere('id', $requestedCategoryId);

        return Inertia::render('categories/Index', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategory?->id,
        ]);
    }

    public function products(): InertiaResponse
    {
        $categories = Category::query()
            ->with(['images', 'children:id,name,parent_id'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $products = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'product_title',
                'category_id',
                'subcategory_id',
                'description',
                'price',
            ]);

        return Inertia::render('products/Index', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    public function featuredProjects(): InertiaResponse
    {
        $projects = FeaturedProject::query()
            ->with('images')
            ->latest()
            ->get([
                'id',
                'name',
                'description',
                'location',
                'category',
                'subcategory',
                'type',
            ]);

        return Inertia::render('featured projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function about(): InertiaResponse
    {
        return Inertia::render('about us/Index');
    }

    public function contacts(): InertiaResponse
    {
        return Inertia::render('contacts/Index', [
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'category_id' => [
                'nullable',
                'string',
                Rule::in([
                    'Bình nước nóng Ariston',
                    'Bảo ôn K-FLEX',
                    'Vật tư HVAC',
                    'Vật tư MEP tổng hợp',
                    'Cung ứng cho dự án',
                ]),
            ],
            'project_name' => ['nullable', 'string', 'max:255'],
            'message' => ['required_without:items', 'nullable', 'string', 'max:5000'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        if ($items !== []) {
            $products = Product::query()
                ->whereIn('id', array_column($items, 'id'))
                ->pluck('name', 'id');

            $validated['product'] = collect($items)
                ->map(fn (array $item): string => "{$products[$item['id']]} × {$item['quantity']}")
                ->implode("\n");
            $validated['message'] ??= 'Yêu cầu báo giá cho các sản phẩm trong giỏ hàng.';
        }

        $contact = Contact::query()->create($validated);
        Mail::to(config('mail.to.address'))->send(new ContactInquiryReceived($contact));

        return $request->query('from') === 'home'
            ? to_route('kbuser.home')
            : to_route('kbuser.contacts');
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
