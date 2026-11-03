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
        $productsCount = Product::query()->count();
        $productsWithDescCount = Product::query()
            ->whereNotNull('description')
            ->whereRaw('LENGTH(TRIM(description)) >= 30')
            ->count();
        $productsWithImageCount = Product::query()
            ->whereHas('images')
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

        $cataloguesCount = Catalogue::query()->published()->count();
        $categoryUrlsCount = Category::query()->count();
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

        $sampleProducts = Product::query()
            ->latest('id')
            ->limit(5)
            ->get(['id', 'name', 'product_title'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'subtitle' => $product->product_title,
                'url' => SiteSetting::canonicalUrl('/products/'.$product->slug),
                'path' => '/products/'.$product->slug,
            ])
            ->values();

        $sampleArticles = Article::query()
            ->published()
            ->latest('published_at')
            ->limit(5)
            ->get(['id', 'title', 'slug'])
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'url' => SiteSetting::canonicalUrl('/news/'.$article->slug),
                'path' => '/news/'.$article->slug,
            ])
            ->values();

        return Inertia::render('seo/Index', [
            'settings' => SiteSetting::allSettings(),
            'defaults' => SiteSetting::defaults(),
            'seoAudit' => [
                'staticPagesCount' => $staticPagesCount,
                'productsCount' => $productsCount,
                'productsWithDescCount' => $productsWithDescCount,
                'productsWithImageCount' => $productsWithImageCount,
                'projectsCount' => $projectsCount,
                'projectsWithDescCount' => $projectsWithDescCount,
                'articlesCount' => $articlesCount,
                'articlesWithExcerptCount' => $articlesWithExcerptCount,
                'cataloguesCount' => $cataloguesCount,
                'totalIndexedUrls' => $staticPagesCount + $categoryUrlsCount + $productsCount + $projectsCount + $articlesCount,
                'sitemapUrl' => SiteSetting::canonicalUrl('/sitemap.xml'),
                'robotsUrl' => SiteSetting::canonicalUrl('/robots.txt'),
                'baseUrl' => SiteSetting::canonicalBaseUrl(),
                'sampleProjects' => $sampleProjects,
                'sampleProducts' => $sampleProducts,
                'sampleArticles' => $sampleArticles,
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
