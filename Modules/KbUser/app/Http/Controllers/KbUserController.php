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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $this->deduplicateLegacyKflexProducts();

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

        $featuredProjects = $this->featuredProjectsWithProducts($products);

        return Inertia::render('Home', [
            'categories' => $categories,
            'products' => $products,
            'featuredProjects' => $featuredProjects,
        ]);
    }

    public function categories(Request $request, ?string $categorySlug = null): InertiaResponse
    {
        $this->deduplicateLegacyKflexProducts();

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

        $rawCategoryKey = trim((string) ($categorySlug ?: $request->query('category', '')));
        $selectedCategory = null;

        if ($rawCategoryKey !== '') {
            $normalizedSlug = Str::slug($rawCategoryKey);
            $selectedCategory = $categories->first(
                fn (Category $category): bool => $category->slug === $rawCategoryKey
                    || ($normalizedSlug !== '' && $category->slug === $normalizedSlug)
                    || (ctype_digit($rawCategoryKey) && $category->id === (int) $rawCategoryKey),
            );
        }

        return Inertia::render('categories/Index', [
            'categories' => $categories,
            'selectedCategoryId' => $selectedCategory?->id,
            'selectedCategory' => $selectedCategory,
        ]);
    }

    public function products(Request $request, ?string $categorySlug = null, ?string $subcategorySlug = null): InertiaResponse
    {
        $this->deduplicateLegacyKflexProducts();

        $categories = Category::query()
            ->with(['images', 'children:id,name,parent_id,description'])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        $rawCategoryKey = trim((string) ($categorySlug ?: $request->query('category', '')));
        $rawSubcategoryKey = trim((string) ($subcategorySlug ?: $request->query('subcategory', '')));

        $selectedCategory = null;
        $selectedSubcategory = null;

        if ($rawCategoryKey !== '') {
            $normalizedCatSlug = Str::slug($rawCategoryKey);
            $selectedCategory = $categories->first(
                fn (Category $category): bool => $category->slug === $rawCategoryKey
                    || ($normalizedCatSlug !== '' && $category->slug === $normalizedCatSlug)
                    || (ctype_digit($rawCategoryKey) && $category->id === (int) $rawCategoryKey),
            );
        }

        if ($selectedCategory !== null && $rawSubcategoryKey !== '') {
            $normalizedSubSlug = Str::slug($rawSubcategoryKey);
            $selectedSubcategory = $selectedCategory->children->first(
                fn (Category $child): bool => $child->slug === $rawSubcategoryKey
                    || ($normalizedSubSlug !== '' && $child->slug === $normalizedSubSlug)
                    || (ctype_digit($rawSubcategoryKey) && $child->id === (int) $rawSubcategoryKey),
            );
        }

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
            'selectedCategoryId' => $selectedCategory?->id,
            'selectedSubcategoryId' => $selectedSubcategory?->id,
            'selectedCategory' => $selectedCategory,
            'selectedSubcategory' => $selectedSubcategory,
        ]);
    }

    public function showProduct(string $productSlug): InertiaResponse
    {
        $normalizedSlug = Str::slug($productSlug);

        $product = Product::query()
            ->with([
                'category:id,name',
                'subcategory:id,name',
                'images',
                'reviews' => fn ($query) => $query->with('user:id,name,avatar')->latest(),
                'comments' => fn ($query) => $query->with('user:id,name,avatar')->latest(),
            ])
            ->get()
            ->first(fn (Product $item): bool => $item->slug === $productSlug
                || ($normalizedSlug !== '' && $item->slug === $normalizedSlug)
                || (ctype_digit($productSlug) && $item->id === (int) $productSlug));

        abort_if(! $product instanceof Product, 404);

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

    public function catalogues(): InertiaResponse
    {
        $catalogues = Catalogue::query()
            ->published()
            ->with('images')
            ->latest()
            ->get([
                'id',
                'title',
                'description',
                'file_path',
                'file_name',
                'file_size',
                'created_at',
            ]);

        return Inertia::render('catalogues/Index', [
            'catalogues' => $catalogues,
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'ids' => ['sometimes', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'min:1'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $fetchAll = (bool) ($validated['all'] ?? false);

        $productQuery = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->when(array_key_exists('ids', $validated), function ($query) use ($validated): void {
                $query->whereIn('id', $validated['ids']);
            }, function ($query) use ($validated, $fetchAll): void {
                if ($fetchAll) {
                    return;
                }

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

        if (! array_key_exists('ids', $validated) && ! $fetchAll) {
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
        $this->deduplicateLegacyKflexProducts();

        $allProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->get([
                'id',
                'name',
                'product_title',
                'category_id',
                'subcategory_id',
                'description',
                'price',
            ]);

        $projects = $this->featuredProjectsWithProducts($allProducts);

        return Inertia::render('featured projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function showFeaturedProject(string $projectSlug): InertiaResponse
    {
        $this->deduplicateLegacyKflexProducts();

        $columns = [
            'id',
            'name',
            'description',
            'location',
            'category',
            'subcategory',
            'type',
        ];

        if (Schema::hasColumn('featured_projects', 'products')) {
            $columns[] = 'products';
        }

        $normalizedSlug = Str::slug($projectSlug);

        $featuredProject = FeaturedProject::query()
            ->with('images')
            ->get($columns)
            ->first(fn (FeaturedProject $item): bool => $item->slug === $projectSlug
                || ($normalizedSlug !== '' && $item->slug === $normalizedSlug)
                || (ctype_digit($projectSlug) && $item->id === (int) $projectSlug));

        abort_if(! $featuredProject instanceof FeaturedProject, 404);

        $allProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->get([
                'id',
                'name',
                'product_title',
                'category_id',
                'subcategory_id',
                'description',
                'price',
            ]);

        $project = $this->mapFeaturedProjectWithProducts($featuredProject, $allProducts);

        $relatedProjects = FeaturedProject::query()
            ->with('images')
            ->whereKeyNot($featuredProject->id)
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [(string) $featuredProject->type])
            ->latest()
            ->limit(3)
            ->get($columns)
            ->map(fn (FeaturedProject $item): array => $this->mapFeaturedProjectWithProducts($item, $allProducts))
            ->values();

        return Inertia::render('featured projects/Show', [
            'project' => $project,
            'relatedProjects' => $relatedProjects,
        ]);
    }

    /**
     * @param  Collection<int, Product>  $allProducts
     * @return Collection<int, array<string, mixed>>
     */
    private function featuredProjectsWithProducts(Collection $allProducts): Collection
    {
        $columns = [
            'id',
            'name',
            'description',
            'location',
            'category',
            'subcategory',
            'type',
        ];

        if (Schema::hasColumn('featured_projects', 'products')) {
            $columns[] = 'products';
        }

        return FeaturedProject::query()
            ->with('images')
            ->latest()
            ->get($columns)
            ->map(fn (FeaturedProject $project): array => $this->mapFeaturedProjectWithProducts($project, $allProducts));
    }

    /**
     * @param  Collection<int, Product>  $allProducts
     * @return array<string, mixed>
     */
    private function mapFeaturedProjectWithProducts(FeaturedProject $project, Collection $allProducts): array
    {
        $seededProducts = is_array($project->products) ? array_values(array_filter($project->products)) : [];

        if ($seededProducts !== []) {
            $relatedProducts = collect($seededProducts)
                ->map(function (mixed $reference) use ($allProducts, $project): array {
                    $matchedProduct = $this->findMatchingProduct($allProducts, $reference);

                    if (! $matchedProduct instanceof Product) {
                        $matchedProduct = $this->ensureSeededProductExists((string) $reference, $project);
                        if ($matchedProduct instanceof Product) {
                            $allProducts->push($matchedProduct);
                        }
                    }

                    if ($matchedProduct instanceof Product) {
                        return $matchedProduct->toArray();
                    }

                    return [
                        'id' => 0,
                        'name' => trim((string) $reference),
                        'product_title' => null,
                        'price' => 0,
                        'image' => [],
                        'category' => ['id' => 0, 'name' => (string) $project->category],
                        'subcategory' => ['id' => 0, 'name' => (string) $project->subcategory],
                    ];
                })
                ->values()
                ->all();
        } else {
            $catLower = mb_strtolower(trim((string) $project->category));
            $subLower = mb_strtolower(trim((string) $project->subcategory));

            $relatedProducts = $allProducts->filter(function (Product $product) use ($catLower, $subLower): bool {
                $pCat = mb_strtolower(trim((string) $product->category?->name));
                $pSub = mb_strtolower(trim((string) $product->subcategory?->name));

                return ($subLower !== '' && $pSub === $subLower)
                    || ($catLower !== '' && $pCat === $catLower);
            })->sortByDesc(function (Product $product) use ($subLower): int {
                $pSub = mb_strtolower(trim((string) $product->subcategory?->name));

                return ($subLower !== '' && $pSub === $subLower) ? 1 : 0;
            })->take(4)->values()->toArray();
        }

        return [
            ...$project->toArray(),
            'related_products' => $relatedProducts,
        ];
    }

    /**
     * @param  Collection<int, Product>  $allProducts
     */
    private function findMatchingProduct(Collection $allProducts, mixed $reference): ?Product
    {
        if (is_numeric($reference)) {
            $byId = $allProducts->firstWhere('id', (int) $reference);
            if ($byId instanceof Product) {
                return $byId;
            }
        }

        $refLower = mb_strtolower(trim((string) $reference));
        $refKey = $this->normalizeProductKey((string) $reference);

        if ($refKey === '') {
            return null;
        }

        $exactMatch = $allProducts->first(function (Product $product) use ($refLower, $refKey): bool {
            return mb_strtolower(trim((string) $product->name)) === $refLower
                || $this->normalizeProductKey((string) $product->name) === $refKey;
        });

        if ($exactMatch instanceof Product) {
            return $exactMatch;
        }

        return $allProducts->first(function (Product $product) use ($refKey): bool {
            $productKey = $this->normalizeProductKey((string) $product->name);

            return $productKey !== '' && (str_contains($productKey, $refKey) || str_contains($refKey, $productKey));
        });
    }

    private function normalizeProductKey(string $value): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($value)));

        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?? '';
    }

    private function deduplicateLegacyKflexProducts(): void
    {
        $canonicalNames = [
            'kflexst' => 'K-Flex ST',
            'kflexmflex' => 'K-Flex M-Flex',
            'kflexkprotect' => 'K-Flex K-Protect',
        ];

        $kflexCandidates = Product::query()
            ->with('images')
            ->where(function ($query): void {
                $query->whereIn('name', [
                    'KFlex ST',
                    'K-Flex ST',
                    'KFlex M-Flex',
                    'K-Flex M-Flex',
                    'KFlex K-Protect',
                    'K-Flex K-Protect',
                ])->orWhere('name', 'like', '%KFlex%')
                    ->orWhere('name', 'like', '%K-Flex%');
            })
            ->orderByDesc('id')
            ->get();

        foreach ($canonicalNames as $normalizedKey => $canonicalName) {
            $matches = $kflexCandidates
                ->filter(fn (Product $product): bool => $this->normalizeProductKey((string) $product->name) === $normalizedKey)
                ->sortByDesc(fn (Product $product): int => ($product->name === $canonicalName ? 10 : 0) + ($product->images->isNotEmpty() ? 5 : 0))
                ->values();

            if ($matches->isEmpty()) {
                continue;
            }

            /** @var Product $keeper */
            $keeper = $matches->first();
            if ($keeper->name !== $canonicalName) {
                $keeper->update(['name' => $canonicalName]);
            }

            foreach ($matches->slice(1) as $duplicate) {
                $duplicate->images()->delete();
                $duplicate->delete();
            }
        }
    }

    private function ensureSeededProductExists(string $reference, FeaturedProject $project): ?Product
    {
        $trimmedRef = trim($reference);
        if ($trimmedRef === '') {
            return null;
        }

        $refKey = $this->normalizeProductKey($trimmedRef);
        $presets = [
            'kflexmflex' => [
                'name' => 'K-Flex M-Flex',
                'category' => 'K-FLEX',
                'subcategory' => 'Cao su lưu hóa NBR',
                'product_title' => 'Dòng sản phẩm bảo ôn cao cấp phù hợp cho chung cư và khu công nghiệp .',
                'image' => 'MFLEX.png',
                'description' => implode("\n", [
                    '- vật liệu: Cao su lưu hóa NBR',
                    '-Tỷ trọng: 46-60kg/m3',
                    '- Phạm vi nhiệt độ: -45°C~105°C',
                    '- Hệ số kháng ẩm: >10.000 (ASTM C209, EN 12086, EN 13469)',
                    '-Hệ số dẫn nhiệt: <0.031 W/mK tại 20°C (EN 12667, ISO 8497)',
                    '-Độ thẩm thấu hơi nước: 0.92 x10^-11 g/Pa.s.m (ASTM E96)',
                    '-Độ hấp thụ nước theo thể tích: ≤ 0.2 % (ASTM C209)',
                    '- Chứng chỉ: UL94 V-0 ,HB; BS 476 part 6&7, Class 0; ASTM E84',
                ]),
            ],
            'kflexst' => [
                'name' => 'K-Flex ST',
                'category' => 'K-FLEX',
                'subcategory' => 'Cao su lưu hóa NBR',
                'product_title' => 'Dòng sản phẩm bảo ôn cao cấp phù hợp cho văn phòng khách sạn .',
                'image' => 'ST.png',
                'description' => implode("\n", [
                    '- vật liệu: Cao su lưu hóa NBR',
                    '-Tỷ trọng: 48-70kg/m3',
                    '- Phạm vi nhiệt độ: -200°C~116°C',
                    '- Hệ số kháng ẩm: >10.000 (ASTM C209, EN 12086, EN 13469)',
                    '-Hệ số dẫn nhiệt: <0.034 W/mK tại 20°C (EN 12667, ISO 8497)',
                    '-Độ thẩm thấu hơi nước: 0.92 x10^-11 g/Pa.s.m (ASTM E96)',
                    '-Độ hấp thụ nước theo thể tích: ≤ 0.1 % (ASTM C209)',
                    '- Chứng chỉ: FM Approved; UL94 V-0, 5VA; BS 476 part 6&7, Class 0; ASTM E84',
                    '-Chỉ số lan truyền ngọn lửa FSI ≤25 (ASTM E84)',
                    '-Chỉ số sinh khói SDI ≤30 (ASTM E84)',
                ]),
            ],
            'kflexkprotect' => [
                'name' => 'K-Flex K-Protect',
                'category' => 'K-FLEX',
                'subcategory' => 'Polyolefin XLPE',
                'product_title' => 'Dòng sản phẩm bảo ôn cao cấp phù hợp cho văn phòng khách sạn .',
                'image' => 'KPROTECT.png',
                'description' => implode("\n", [
                    '- vật liệu: Polyolefin XLPE',
                    '-Tỷ trọng: 30kg/m3',
                    '- Phạm vi nhiệt độ: -40°C~115°C',
                    '- Hệ số kháng ẩm: >20.000 (EN 12086)',
                    '-Hệ số dẫn nhiệt: <0.0322 W/mK tại 23°C (ASTM C518)',
                    '-Độ hấp thụ nước theo thể tích: ≤ 0.1 % (ASTM C209)',
                    '- Chứng chỉ: FM Approved; UL94 V-0, 5VA; BS 476 part 6&7, Class 0; ASTM E84',
                    '-Chỉ số lan truyền ngọn lửa FSI ≤25 (ASTM E84)',
                    '-Chỉ số sinh khói SDI ≤30 (ASTM E84)',
                ]),
            ],
        ];

        $preset = $presets[$refKey] ?? null;
        $categoryName = $preset['category'] ?? ((string) $project->category ?: 'HVAC');
        $subcategoryName = $preset['subcategory'] ?? ((string) $project->subcategory ?: 'Vật tư dự án');

        $parentCategory = Category::query()->firstOrCreate([
            'name' => $categoryName,
            'parent_id' => null,
        ]);
        $childCategory = Category::query()->firstOrCreate([
            'name' => $subcategoryName,
            'parent_id' => $parentCategory->id,
        ]);

        $product = Product::query()->updateOrCreate(
            [
                'name' => $preset['name'] ?? $trimmedRef,
                'category_id' => $parentCategory->id,
            ],
            [
                'product_title' => $preset['product_title'] ?? "Vật tư thực tế sử dụng tại dự án {$project->name}",
                'category_id' => $parentCategory->id,
                'subcategory_id' => $childCategory->id,
                'description' => $preset['description'] ?? "Sản phẩm {$trimmedRef} thuộc hạng mục {$categoryName} · {$subcategoryName}.",
                'price' => 0,
                'quantity' => 0,
            ],
        );

        if (isset($preset['image'])) {
            $sourceImage = base_path("Modules/KbAdmin/resources/assets/images/products/{$preset['image']}");
            if (is_file($sourceImage)) {
                $contents = file_get_contents($sourceImage);
                if ($contents !== false) {
                    $storedPath = 'products/'.Str::slug($product->name).'.png';
                    Storage::disk('public')->put($storedPath, $contents);
                    $product->syncImagePaths([$storedPath]);
                }
            }
        }

        return $product->load(['category:id,name', 'subcategory:id,name', 'images']);
    }

    public function about(): InertiaResponse
    {
        return Inertia::render('about us/Index', [
            'stats' => [
                'products' => Product::query()->count(),
                'categories' => Category::query()->whereNull('parent_id')->count(),
                'projects' => FeaturedProject::query()->count(),
                'catalogues' => Catalogue::query()->published()->count(),
            ],
        ]);
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
