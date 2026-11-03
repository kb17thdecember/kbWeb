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
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Throwable;

class SeoController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        SeoAutoIndexService::syncAllEntitiesSeo(false);

        $hasProductSeo = Schema::hasColumn('products', 'seo_title');
        $hasCategorySeo = Schema::hasColumn('categories', 'seo_title');
        $hasArticleSeo = Schema::hasColumn('articles', 'seo_title');
        $hasProjectSeo = Schema::hasColumn('featured_projects', 'seo_title');

        $productsCount = Product::query()->count();
        $productsWithDescCount = Product::query()
            ->whereNotNull('description')
            ->whereRaw('LENGTH(TRIM(description)) >= 30')
            ->count();
        $productsWithImageCount = Product::query()
            ->whereHas('images')
            ->count();
        $productsWithSeoCount = $hasProductSeo
            ? Product::query()
                ->whereNotNull('seo_title')
                ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
                ->whereNotNull('seo_description')
                ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
                ->count()
            : $productsCount;

        $projectsCount = FeaturedProject::query()->count();
        $projectsWithDescCount = FeaturedProject::query()
            ->whereNotNull('description')
            ->whereRaw('LENGTH(TRIM(description)) >= 30')
            ->count();
        $projectsWithSeoCount = $hasProjectSeo
            ? FeaturedProject::query()
                ->whereNotNull('seo_title')
                ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
                ->whereNotNull('seo_description')
                ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
                ->count()
            : $projectsCount;

        $articlesCount = Article::query()->published()->count();
        $articlesWithExcerptCount = Article::query()
            ->published()
            ->whereNotNull('excerpt')
            ->whereRaw('LENGTH(TRIM(excerpt)) >= 30')
            ->count();
        $articlesWithSeoCount = $hasArticleSeo
            ? Article::query()
                ->whereNotNull('seo_title')
                ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
                ->whereNotNull('seo_description')
                ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
                ->count()
            : Article::query()->count();

        $cataloguesCount = Catalogue::query()->published()->count();
        $categoryUrlsCount = Category::query()->count();
        $categoriesWithSeoCount = $hasCategorySeo
            ? Category::query()
                ->whereNotNull('seo_title')
                ->whereRaw('LENGTH(TRIM(seo_title)) >= 10')
                ->whereNotNull('seo_description')
                ->whereRaw('LENGTH(TRIM(seo_description)) >= 30')
                ->count()
            : $categoryUrlsCount;
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

        $productColumns = ['id', 'name', 'product_title', 'category_id', 'subcategory_id', 'description'];
        if ($hasProductSeo) {
            $productColumns = [...$productColumns, 'seo_title', 'seo_description', 'seo_keywords'];
        }

        $allProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name'])
            ->orderByDesc('id')
            ->get($productColumns)
            ->map(function (Product $product): array {
                $generated = ($product->seo_title && $product->seo_description)
                    ? null
                    : SeoAutoIndexService::generateProductSeo($product);

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'subtitle' => $product->product_title,
                    'category_name' => $product->category?->name,
                    'subcategory_name' => $product->subcategory?->name,
                    'seo_title' => (string) ($product->seo_title ?: ($generated['seo_title'] ?? '')),
                    'seo_description' => (string) ($product->seo_description ?: ($generated['seo_description'] ?? '')),
                    'seo_keywords' => (string) ($product->seo_keywords ?: ($generated['seo_keywords'] ?? '')),
                    'url' => SiteSetting::canonicalUrl('/products/'.$product->slug),
                    'path' => '/products/'.$product->slug,
                ];
            })
            ->values();

        $categoryColumns = ['id', 'name', 'parent_id', 'description'];
        if ($hasCategorySeo) {
            $categoryColumns = [...$categoryColumns, 'seo_title', 'seo_description', 'seo_keywords'];
        }

        $allCategories = Category::query()
            ->with('parent:id,name')
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get($categoryColumns)
            ->map(function (Category $category): array {
                $parentSlug = $category->parent?->slug ?? (string) $category->parent_id;
                $path = $category->parent_id !== null
                    ? '/categories/'.$parentSlug.'/'.$category->slug
                    : '/categories/'.$category->slug;
                $generated = ($category->seo_title && $category->seo_description)
                    ? null
                    : SeoAutoIndexService::generateCategorySeo($category);

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'parent_name' => $category->parent?->name,
                    'description' => $category->description,
                    'seo_title' => (string) ($category->seo_title ?: ($generated['seo_title'] ?? '')),
                    'seo_description' => (string) ($category->seo_description ?: ($generated['seo_description'] ?? '')),
                    'seo_keywords' => (string) ($category->seo_keywords ?: ($generated['seo_keywords'] ?? '')),
                    'url' => SiteSetting::canonicalUrl($path),
                    'path' => $path,
                ];
            })
            ->values();

        $articleColumns = ['id', 'title', 'slug', 'excerpt', 'is_published', 'published_at'];
        if ($hasArticleSeo) {
            $articleColumns = [...$articleColumns, 'seo_title', 'seo_description', 'seo_keywords'];
        }

        $allArticles = Article::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get($articleColumns)
            ->map(function (Article $article): array {
                $generated = ($article->seo_title && $article->seo_description)
                    ? null
                    : SeoAutoIndexService::generateArticleSeo($article);

                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'excerpt' => $article->excerpt,
                    'is_published' => (bool) $article->is_published,
                    'seo_title' => (string) ($article->seo_title ?: ($generated['seo_title'] ?? '')),
                    'seo_description' => (string) ($article->seo_description ?: ($generated['seo_description'] ?? '')),
                    'seo_keywords' => (string) ($article->seo_keywords ?: ($generated['seo_keywords'] ?? '')),
                    'url' => SiteSetting::canonicalUrl('/news/'.$article->slug),
                    'path' => '/news/'.$article->slug,
                ];
            })
            ->values();

        $projectColumns = ['id', 'name', 'location', 'category', 'subcategory', 'description'];
        if ($hasProjectSeo) {
            $projectColumns = [...$projectColumns, 'seo_title', 'seo_description', 'seo_keywords'];
        }

        $allProjects = FeaturedProject::query()
            ->orderByDesc('id')
            ->get($projectColumns)
            ->map(function (FeaturedProject $project): array {
                $generated = ($project->seo_title && $project->seo_description)
                    ? null
                    : SeoAutoIndexService::generateProjectSeo($project);

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'location' => $project->location,
                    'category' => $project->category,
                    'subcategory' => $project->subcategory,
                    'seo_title' => (string) ($project->seo_title ?: ($generated['seo_title'] ?? '')),
                    'seo_description' => (string) ($project->seo_description ?: ($generated['seo_description'] ?? '')),
                    'seo_keywords' => (string) ($project->seo_keywords ?: ($generated['seo_keywords'] ?? '')),
                    'url' => SiteSetting::canonicalUrl('/featured-projects/'.$project->slug),
                    'path' => '/featured-projects/'.$project->slug,
                ];
            })
            ->values();

        return Inertia::render('seo/Index', [
            'settings' => SiteSetting::allSettings(),
            'defaults' => SiteSetting::defaults(),
            'seoEntities' => [
                'products' => $allProducts,
                'categories' => $allCategories,
                'projects' => $allProjects,
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
                'projectsWithSeoCount' => $projectsWithSeoCount,
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
                    'Đã tự động tạo & chuẩn hóa SEO cho %d sản phẩm, %d danh mục, %d dự án tiêu biểu và %d bài viết.',
                    $counts['products'],
                    $counts['categories'],
                    $counts['projects'] ?? 0,
                    $counts['articles'],
                ),
            );
    }

    public function updateEntity(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, ['product', 'category', 'project', 'article'], true), 404);

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

        if ($type === 'project') {
            $project = FeaturedProject::query()->findOrFail($id);
            if ($autoGenerate) {
                $generated = SeoAutoIndexService::generateProjectSeo($project);
                $project->forceFill($generated)->save();
            } else {
                $project->forceFill([
                    'seo_title' => trim((string) $validated['seo_title']),
                    'seo_description' => trim((string) $validated['seo_description']),
                    'seo_keywords' => trim((string) ($validated['seo_keywords'] ?? '')),
                ])->save();
            }

            return to_route('admin.seo.index')
                ->with('toast', 'Đã cập nhật SEO cho dự án tiêu biểu: '.$project->name);
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
