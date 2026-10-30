<?php

namespace Modules\KbUser\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\ContactInquiryReceived;
use App\Models\Article;
use App\Models\Catalogue;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
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
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
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
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
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

    public function showProduct(Product $product): InertiaResponse
    {
        $product->load([
            'category:id,name',
            'subcategory:id,name',
            'images',
            'reviews' => fn ($query) => $query->with('user:id,name,avatar')->latest(),
            'comments' => fn ($query) => $query->with('user:id,name,avatar')->latest(),
        ]);

        $relatedProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->orderByDesc('id')
            ->limit(4)
            ->get([
                'id',
                'name',
                'product_title',
                'category_id',
                'subcategory_id',
                'description',
                'price',
            ]);

        $catalogues = Catalogue::query()
            ->published()
            ->with('images')
            ->latest()
            ->limit(4)
            ->get(['id', 'title', 'description', 'file_path', 'file_name', 'file_size']);

        return Inertia::render('products/Show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'catalogues' => $catalogues,
            'averageRating' => round((float) $product->reviews->avg('rating'), 1),
            'reviewsCount' => $product->reviews->count(),
        ]);
    }

    public function storeProductReview(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        ProductReview::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'user_id' => $request->user()->id,
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
            ],
        );

        return back();
    }

    public function storeProductComment(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $product->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back();
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'ids' => ['sometimes', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $productQuery = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->when(array_key_exists('ids', $validated), function ($query) use ($validated): void {
                $query->whereIn('id', $validated['ids']);
            }, function ($query) use ($validated): void {
                $search = trim($validated['q'] ?? '');

                if (mb_strlen($search) < 2) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $term = "%{$search}%";

                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('product_title', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', $term))
                        ->orWhereHas('subcategory', fn ($subcategoryQuery) => $subcategoryQuery->where('name', 'like', $term));
                });
            })
            ->orderByDesc('id');

        if (! array_key_exists('ids', $validated)) {
            $productQuery->limit(8);
        }

        $products = $productQuery->get([
            'id',
            'name',
            'product_title',
            'category_id',
            'subcategory_id',
            'description',
            'price',
        ]);

        return response()->json(['products' => $products]);
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

    public function news(): InertiaResponse
    {
        return Inertia::render('news/Index', [
            'articles' => Article::query()
                ->published()
                ->with('images')
                ->orderByDesc('published_at')
                ->get(['id', 'title', 'slug', 'excerpt', 'published_at']),
            'catalogues' => Catalogue::query()
                ->published()
                ->with('images')
                ->latest()
                ->get(['id', 'title', 'description', 'file_path', 'file_name', 'file_size']),
        ]);
    }

    public function showArticle(Article $article): InertiaResponse
    {
        abort_unless($article->is_published, 404);

        $article->load([
            'images',
            'comments' => fn ($query) => $query->with('user:id,name,avatar')->latest(),
        ]);

        return Inertia::render('news/Show', [
            'article' => $article,
            'related' => Article::query()
                ->published()
                ->with('images')
                ->whereKeyNot($article->id)
                ->orderByDesc('published_at')
                ->limit(3)
                ->get(['id', 'title', 'slug', 'excerpt', 'published_at']),
        ]);
    }

    public function storeArticleComment(Request $request, Article $article): RedirectResponse
    {
        abort_unless($article->is_published, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $article->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back();
    }

    public function toggleWishlist(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $existing = Wishlist::query()
            ->where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $wishlisted = false;
        } else {
            Wishlist::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
            ]);
            $wishlisted = true;
        }

        $wishlistIds = $user->wishlists()->pluck('product_id')->all();

        if ($request->expectsJson()) {
            return response()->json([
                'wishlisted' => $wishlisted,
                'wishlistIds' => $wishlistIds,
            ]);
        }

        return back();
    }

    public function accountOverview(Request $request): JsonResponse
    {
        $user = $request->user();

        $quotes = Contact::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->latest()
            ->limit(25)
            ->get([
                'id',
                'project_name',
                'category_id',
                'product',
                'message',
                'status',
                'attachment_path',
                'attachment_name',
                'created_at',
            ]);

        $wishlistProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->whereIn('id', $user->wishlists()->pluck('product_id'))
            ->get([
                'id',
                'name',
                'product_title',
                'category_id',
                'subcategory_id',
                'description',
                'price',
            ]);

        return response()->json([
            'quotes' => $quotes,
            'wishlistProducts' => $wishlistProducts,
        ]);
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
            'attachment' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,csv,jpg,jpeg,png,webp', 'max:10240'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        $items = $validated['items'] ?? [];
        unset($validated['items'], $validated['attachment']);

        if ($request->user()) {
            $validated['user_id'] = $request->user()->id;
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $validated['attachment_path'] = $file->store('contact-attachments', 'public');
            $validated['attachment_name'] = $file->getClientOriginalName();
        }

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
