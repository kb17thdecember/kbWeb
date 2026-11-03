<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Catalogue;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\SeoAutoIndexService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Throwable;

class SeoController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        SeoAutoIndexService::syncAllEntitiesSeo(false);

        $productsCount = Product::query()->count();
        $productsWithDescCount = Product::query()
            ->whereNotNull('description')
            ->whereRaw('LENGTH(TRIM(description)) >= 30')
            ->count();
        $productsWithImageCount = Product::query()
            ->whereHas('images')
            ->count();
        $productsWithSeoCount = Product::query()
            ->whereNotNull('seo_title')
            ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
            ->whereNotNull('seo_description')
            ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
            ->count();

        $projectsCount = FeaturedProject::query()->count();
        $projectsWithDescCount = FeaturedProject::query()
            ->whereNotNull('description')
            ->whereRaw('LENGTH(TRIM(description)) >= 30')
            ->count();

        $articlesCount = Article::query()->published()->count();
        $articlesWithExcerptCount = Article::query()
            ->published()
            ->whereNotNull('excerpt')
            ->whereRaw('LENGTH(TRIM(excerpt)) >= 30')
            ->count();
        $articlesWithSeoCount = Article::query()
            ->whereNotNull('seo_title')
            ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
            ->whereNotNull('seo_description')
            ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
            ->count();

        $cataloguesCount = Catalogue::query()->published()->count();
        $categoryUrlsCount = Category::query()->count();
        $categoriesWithSeoCount = Category::query()
            ->whereNotNull('seo_title')
            ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
            ->whereNotNull('seo_description')
            ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
            ->count();
        $staticPagesCount = 9;

        $sampleProjects = FeaturedProject::query()
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'location', 'category'])
            ->map(fn (FeaturedProject $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'url' => SiteSetting::canonicalUrl('/featured-projects/'.$project->slug),
                'path' => '/featured-projects/'.$project->slug,
                'location' => $project->location,
            ])
            ->values();

        $allProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->orderByDesc('id')
            ->get(['id', 'name', 'product_title', 'category_id', 'subcategory_id', 'description', 'seo_title', 'seo_description', 'seo_keywords'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'subtitle' => $product->product_title,
                'category_name' => $product->category?->name,
                'subcategory_name' => $product->subcategory?->name,
                'seo_title' => (string) ($product->seo_title ?? ''),
                'seo_description' => (string) ($product->seo_description ?? ''),
                'seo_keywords' => (string) ($product->seo_keywords ?? ''),
                'url' => SiteSetting::canonicalUrl('/products/'.$product->slug),
                'path' => '/products/'.$product->slug,
            ])
            ->values();

        $allCategories = Category::query()
            ->with('parent:id,name')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'description', 'seo_title', 'seo_description', 'seo_keywords'])
            ->map(function (Category $category): array {
                $parentSlug = $category->parent?->slug ?? (string) $category->parent_id;
                $path = $category->parent_id !== null
                    ? '/categories/'.$parentSlug.'/'.$category->slug
                    : '/categories/'.$category->slug;

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'parent_name' => $category->parent?->name,
                    'description' => $category->description,
                    'seo_title' => (string) ($category->seo_title ?? ''),
                    'seo_description' => (string) ($category->seo_description ?? ''),
                    'seo_keywords' => (string) ($category->seo_keywords ?? ''),
                    'url' => SiteSetting::canonicalUrl($path),
                    'path' => $path,
                ];
            })
            ->values();

        $allArticles = Article::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'excerpt', 'is_published', 'published_at', 'seo_title', 'seo_description', 'seo_keywords'])
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'excerpt' => $article->excerpt,
                'is_published' => (bool) $article->is_published,
                'seo_title' => (string) ($article->seo_title ?? ''),
                'seo_description' => (string) ($article->seo_description ?? ''),
                'seo_keywords' => (string) ($article->seo_keywords ?? ''),
                'url' => SiteSetting::canonicalUrl('/news/'.$article->slug),
                'path' => '/news/'.$article->slug,
            ])
            ->values();

        return Inertia::render('seo/Index', [
            'settings' => SiteSetting::allSettings(),
            'defaults' => SiteSetting::defaults(),
            'seoEntities' => [
                'products' => $allProducts,
                'categories' => $allCategories,
                'articles' => $allArticles,
            ],
            'seoAudit' => [
                'staticPagesCount' => $staticPagesCount,
                'categoriesCount' => $categoryUrlsCount,
                'categoriesWithSeoCount' => $categoriesWithSeoCount,
                'productsCount' => $productsCount,
                'productsWithDescCount' => $productsWithDescCount,
                'productsWithImageCount' => $productsWithImageCount,
                'productsWithSeoCount' => $productsWithSeoCount,
                'projectsCount' => $projectsCount,
                'projectsWithDescCount' => $projectsWithDescCount,
                'articlesCount' => $articlesCount,
                'articlesTotalCount' => $allArticles->count(),
                'articlesWithExcerptCount' => $articlesWithExcerptCount,
                'articlesWithSeoCount' => $articlesWithSeoCount,
                'cataloguesCount' => $cataloguesCount,
                'totalIndexedUrls' => $staticPagesCount + $categoryUrlsCount + $productsCount + $projectsCount + $articlesCount,
                'sitemapUrl' => SiteSetting::canonicalUrl('/sitemap.xml'),
                'robotsUrl' => SiteSetting::canonicalUrl('/robots.txt'),
                'baseUrl' => SiteSetting::canonicalBaseUrl(),
                'sampleProjects' => $sampleProjects,
                'sampleProducts' => $allProducts->take(5)->values(),
                'sampleArticles' => $allArticles->where('is_published', true)->take(5)->values(),
            ],
            ...$dashboardStats->summary(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seo_meta_title' => ['required', 'string', 'max:160'],
            'seo_meta_description' => ['required', 'string', 'max:320'],
            'seo_meta_keywords' => ['nullable', 'string', 'max:500'],
            'seo_og_image' => ['nullable', 'string', 'max:255'],
            'seo_robots' => ['required', 'string', 'max:160'],
            'seo_canonical_domain' => ['nullable', 'string', 'max:255'],
            'seo_google_verification' => ['nullable', 'string', 'max:255'],
            'seo_google_analytics_id' => ['nullable', 'string', 'max:80'],
            'seo_schema_type' => ['required', 'string', 'max:80'],
            'seo_geo_region' => ['required', 'string', 'max:40'],
            'seo_geo_placename' => ['required', 'string', 'max:120'],
            'seo_home_title' => ['required', 'string', 'max:160'],
            'seo_home_description' => ['required', 'string', 'max:320'],
            'seo_categories_title' => ['nullable', 'string', 'max:160'],
            'seo_categories_description' => ['nullable', 'string', 'max:320'],
            'seo_products_title' => ['required', 'string', 'max:160'],
            'seo_products_description' => ['required', 'string', 'max:320'],
            'seo_projects_title' => ['required', 'string', 'max:160'],
            'seo_projects_description' => ['required', 'string', 'max:320'],
            'seo_news_title' => ['required', 'string', 'max:160'],
            'seo_news_description' => ['required', 'string', 'max:320'],
            'seo_catalogues_title' => ['required', 'string', 'max:160'],
            'seo_catalogues_description' => ['required', 'string', 'max:320'],
            'seo_contacts_title' => ['required', 'string', 'max:160'],
            'seo_contacts_description' => ['required', 'string', 'max:320'],
        ]);

        if (! empty($validated['seo_google_verification'])) {
            $rawVerification = trim((string) $validated['seo_google_verification']);
            if (preg_match('/content=["\']([^"\']+)["\']/i', $rawVerification, $matches)) {
                $validated['seo_google_verification'] = trim($matches[1]);
            }
        }

        SiteSetting::updateSettings($validated);

        try {
            $robotsDirective = strtolower((string) ($validated['seo_robots'] ?? 'index, follow'));
            $disallowAll = str_contains($robotsDirective, 'noindex');
            $robotsContent = implode("\n", [
                'User-agent: *',
                $disallowAll ? 'Disallow: /' : 'Allow: /',
                'Disallow: /admin',
                'Disallow: /chat/',
                'Disallow: /my-account',
                'Disallow: /quotations/login',
                '',
                'Sitemap: '.SiteSetting::canonicalUrl('/sitemap.xml'),
            ]);
            @file_put_contents(public_path('robots.txt'), $robotsContent);
        } catch (Throwable) {
            // Ignore static file write errors if read-only
        }

        return to_route('admin.seo.index')
            ->with('toast', 'Đã lưu cấu hình SEO & đồng bộ chuẩn hiển thị Google thành công.');
    }

    public function generateAll(): RedirectResponse
    {
        $counts = SeoAutoIndexService::syncAllEntitiesSeo(true);

        return to_route('admin.seo.index')
            ->with(
                'toast',
                sprintf(
                    'Đã tự động tạo & chuẩn hóa SEO cho %d sản phẩm, %d danh mục và %d bài viết.',
                    $counts['products'],
                    $counts['categories'],
                    $counts['articles'],
                ),
            );
    }

    public function updateEntity(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['product', 'category', 'article'], true), 404);

        $autoGenerate = $request->boolean('auto_generate');

        $validated = $request->validate([
            'seo_title' => [$autoGenerate ? 'nullable' : 'required', 'string', 'max:180'],
            'seo_description' => [$autoGenerate ? 'nullable' : 'required', 'string', 'max:350'],
            'seo_keywords' => ['nullable', 'string', 'max:500'],
        ]);

        if ($type === 'product') {
            $product = Product::query()->with(['category:id,name', 'subcategory:id,name'])->findOrFail($id);
            if ($autoGenerate) {
                $generated = SeoAutoIndexService::generateProductSeo($product);
                $product->forceFill($generated)->save();
            } else {
                $product->forceFill([
                    'seo_title' => trim((string) $validated['seo_title']),
                    'seo_description' => trim((string) $validated['seo_description']),
                    'seo_keywords' => trim((string) ($validated['seo_keywords'] ?? '')),
                ])->save();
            }

            return to_route('admin.seo.index')
                ->with('toast', 'Đã cập nhật SEO cho sản phẩm: '.$product->name);
        }

        if ($type === 'category') {
            $category = Category::query()->with('parent:id,name')->findOrFail($id);
            if ($autoGenerate) {
                $generated = SeoAutoIndexService::generateCategorySeo($category);
                $category->forceFill($generated)->save();
            } else {
                $category->forceFill([
                    'seo_title' => trim((string) $validated['seo_title']),
                    'seo_description' => trim((string) $validated['seo_description']),
                    'seo_keywords' => trim((string) ($validated['seo_keywords'] ?? '')),
                ])->save();
            }

            return to_route('admin.seo.index')
                ->with('toast', 'Đã cập nhật SEO cho danh mục: '.$category->name);
        }

        $article = Article::query()->findOrFail($id);
        if ($autoGenerate) {
            $generated = SeoAutoIndexService::generateArticleSeo($article);
            $article->forceFill($generated)->save();
        } else {
            $article->forceFill([
                'seo_title' => trim((string) $validated['seo_title']),
                'seo_description' => trim((string) $validated['seo_description']),
                'seo_keywords' => trim((string) ($validated['seo_keywords'] ?? '')),
            ])->save();
        }

        return to_route('admin.seo.index')
            ->with('toast', 'Đã cập nhật SEO cho bài viết: '.$article->title);
    }

    public function ping(): RedirectResponse
    {
        $urls = collect([
            SiteSetting::canonicalUrl('/'),
            SiteSetting::canonicalUrl('/categories'),
            SiteSetting::canonicalUrl('/products'),
            SiteSetting::canonicalUrl('/featured-projects'),
            SiteSetting::canonicalUrl('/news'),
            SiteSetting::canonicalUrl('/catalogues'),
            SiteSetting::canonicalUrl('/about-us'),
            SiteSetting::canonicalUrl('/contacts'),
            SiteSetting::canonicalUrl('/quotations'),
        ])
            ->concat(Category::query()->with('parent:id,name')->latest('id')->limit(30)->get(['id', 'parent_id', 'name'])->map(
                fn (Category $category): string => $category->parent_id !== null
                    ? SiteSetting::canonicalUrl('/categories/'.($category->parent?->slug ?? $category->parent_id).'/'.$category->slug)
                    : SiteSetting::canonicalUrl('/categories/'.$category->slug),
            ))
            ->concat(Product::query()->latest('id')->limit(30)->get(['id', 'name'])->map(
                fn (Product $product): string => SiteSetting::canonicalUrl('/products/'.$product->slug),
            ))
            ->concat(FeaturedProject::query()->latest('id')->limit(30)->get(['id', 'name'])->map(
                fn (FeaturedProject $project): string => SiteSetting::canonicalUrl('/featured-projects/'.$project->slug),
            ))
            ->concat(Article::query()->published()->latest('id')->limit(30)->get(['slug'])->map(
                fn (Article $article): string => SiteSetting::canonicalUrl('/news/'.$article->slug),
            ))
            ->values()
            ->all();

        $result = SeoAutoIndexService::pingUrls($urls);

        return to_route('admin.seo.index')
            ->with('toast', 'Đã gửi tín hiệu đề xuất lập chỉ mục '.$result['urls_count'].' URL lên Google Hub & IndexNow.');
    }
}
