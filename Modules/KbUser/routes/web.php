<?php

use App\Models\Article;
use App\Models\Catalogue;
use App\Models\Category;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\SeoAutoIndexService;
use Illuminate\Support\Facades\Route;
use Modules\KbUser\Http\Controllers\ChatController;
use Modules\KbUser\Http\Controllers\KbUserController;
use Modules\KbUser\Http\Controllers\QuotationController;
use Modules\KbUser\Http\Controllers\UserAuthController;
use Modules\KbUser\Http\Middleware\UseKbUserRootView;

Route::get('/robots.txt', function () {
    $settings = SiteSetting::allSettings();
    $robotsDirective = strtolower($settings['seo_robots'] ?? 'index, follow');
    $disallowAll = str_contains($robotsDirective, 'noindex');

    $lines = [
        'User-agent: *',
        $disallowAll ? 'Disallow: /' : 'Allow: /',
        'Disallow: /admin',
        'Disallow: /chat/',
        'Disallow: /my-account',
        'Disallow: /quotations/login',
        '',
        'Sitemap: '.SiteSetting::canonicalUrl('/sitemap.xml'),
    ];

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('kbuser.robots');

Route::get('/sitemap.xml', function () {
    $staticPages = [
        ['path' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['path' => '/products', 'priority' => '0.9', 'changefreq' => 'daily'],
        ['path' => '/categories', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['path' => '/featured-projects', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['path' => '/catalogues', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['path' => '/news', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['path' => '/about-us', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['path' => '/contacts', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['path' => '/quotations', 'priority' => '0.7', 'changefreq' => 'weekly'],
    ];

    $today = now()->toAtomString();

    $urls = collect($staticPages)->map(
        fn (array $page): string => '<url>'
            .'<loc>'.e(SiteSetting::canonicalUrl($page['path'])).'</loc>'
            .'<lastmod>'.e($today).'</lastmod>'
            .'<changefreq>'.$page['changefreq'].'</changefreq>'
            .'<priority>'.$page['priority'].'</priority>'
            .'</url>',
    )
        ->concat(Category::query()->with('parent:id,name')->get(['id', 'parent_id', 'name', 'updated_at'])->map(
            function (Category $category): string {
                $lastmod = e(($category->updated_at ?? now())->toAtomString());

                if ($category->parent_id === null) {
                    return '<url><loc>'.e(SiteSetting::canonicalUrl('/categories/'.$category->slug)).'</loc><lastmod>'.$lastmod.'</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>';
                }

                $parentSlug = $category->parent?->slug ?? (string) $category->parent_id;

                return '<url><loc>'.e(SiteSetting::canonicalUrl('/categories/'.$parentSlug.'/'.$category->slug)).'</loc><lastmod>'.$lastmod.'</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>';
            },
        ))
        ->concat(Product::query()->get(['id', 'name', 'updated_at'])->map(
            fn (Product $product): string => '<url>'
                .'<loc>'.e(SiteSetting::canonicalUrl('/products/'.$product->slug)).'</loc>'
                .'<lastmod>'.e(($product->updated_at ?? now())->toAtomString()).'</lastmod>'
                .'<changefreq>weekly</changefreq>'
                .'<priority>0.8</priority>'
                .'</url>',
        ))
        ->concat(FeaturedProject::query()->get(['id', 'name', 'updated_at'])->map(
            fn (FeaturedProject $project): string => '<url>'
                .'<loc>'.e(SiteSetting::canonicalUrl('/featured-projects/'.$project->slug)).'</loc>'
                .'<lastmod>'.e(($project->updated_at ?? now())->toAtomString()).'</lastmod>'
                .'<changefreq>weekly</changefreq>'
                .'<priority>0.8</priority>'
                .'</url>',
        ))
        ->concat(Article::query()->published()->get(['slug', 'updated_at', 'published_at'])->map(
            fn (Article $article): string => '<url>'
                .'<loc>'.e(SiteSetting::canonicalUrl('/news/'.$article->slug)).'</loc>'
                .'<lastmod>'.e(($article->updated_at ?? $article->published_at ?? now())->toAtomString()).'</lastmod>'
                .'<changefreq>weekly</changefreq>'
                .'<priority>0.7</priority>'
                .'</url>',
        ))
        ->implode('');

    return response(
        '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>',
        200,
        ['Content-Type' => 'application/xml; charset=UTF-8'],
    );
})->name('kbuser.sitemap');

Route::get('/feed.xml', function () {
    $settings = SiteSetting::allSettings();
    $companyName = $settings['company_name'] ?? 'HVAC Mart';
    $siteDesc = $settings['seo_meta_description'] ?? 'HVAC Mart - Vật tư HVAC, bình nước nóng Ariston và bảo ôn K-Flex.';
    $baseUrl = SiteSetting::canonicalBaseUrl();

    $items = collect()
        ->concat(Product::query()->latest('updated_at')->limit(15)->get(['id', 'name', 'description', 'updated_at'])->map(
            fn (Product $product): array => [
                'title' => '[Sản phẩm] '.$product->name,
                'link' => SiteSetting::canonicalUrl('/products/'.$product->slug),
                'description' => strip_tags((string) ($product->description ?: $product->name)),
                'pubDate' => ($product->updated_at ?? now())->toRssString(),
                'timestamp' => ($product->updated_at ?? now())->getTimestamp(),
            ],
        ))
        ->concat(FeaturedProject::query()->latest('updated_at')->limit(15)->get(['id', 'name', 'description', 'updated_at'])->map(
            fn (FeaturedProject $project): array => [
                'title' => '[Dự án tiêu biểu] '.$project->name,
                'link' => SiteSetting::canonicalUrl('/featured-projects/'.$project->slug),
                'description' => strip_tags((string) ($project->description ?: $project->name)),
                'pubDate' => ($project->updated_at ?? now())->toRssString(),
                'timestamp' => ($project->updated_at ?? now())->getTimestamp(),
            ],
        ))
        ->concat(Article::query()->published()->latest('updated_at')->limit(15)->get(['title', 'slug', 'excerpt', 'updated_at'])->map(
            fn (Article $article): array => [
                'title' => $article->title,
                'link' => SiteSetting::canonicalUrl('/news/'.$article->slug),
                'description' => strip_tags((string) ($article->excerpt ?: $article->title)),
                'pubDate' => ($article->updated_at ?? now())->toRssString(),
                'timestamp' => ($article->updated_at ?? now())->getTimestamp(),
            ],
        ))
        ->concat(Category::query()->with('parent:id,name')->latest('updated_at')->limit(15)->get(['id', 'parent_id', 'name', 'description', 'updated_at'])->map(
            fn (Category $category): array => [
                'title' => '[Danh mục] '.$category->name,
                'link' => $category->parent_id !== null
                    ? SiteSetting::canonicalUrl('/categories/'.($category->parent?->slug ?? $category->parent_id).'/'.$category->slug)
                    : SiteSetting::canonicalUrl('/categories/'.$category->slug),
                'description' => strip_tags((string) ($category->description ?: $category->name)),
                'pubDate' => ($category->updated_at ?? now())->toRssString(),
                'timestamp' => ($category->updated_at ?? now())->getTimestamp(),
            ],
        ))
        ->concat(Catalogue::query()->published()->latest('updated_at')->limit(10)->get(['id', 'title', 'description', 'updated_at'])->map(
            fn (Catalogue $catalogue): array => [
                'title' => '[Catalogue] '.$catalogue->title,
                'link' => SiteSetting::canonicalUrl('/catalogues'),
                'description' => strip_tags((string) ($catalogue->description ?: $catalogue->title)),
                'pubDate' => ($catalogue->updated_at ?? now())->toRssString(),
                'timestamp' => ($catalogue->updated_at ?? now())->getTimestamp(),
            ],
        ))
        ->sortByDesc('timestamp')
        ->take(30)
        ->map(fn (array $item): string => '<item>'
            .'<title>'.e($item['title']).'</title>'
            .'<link>'.e($item['link']).'</link>'
            .'<guid isPermaLink="true">'.e($item['link']).'</guid>'
            .'<description>'.e($item['description']).'</description>'
            .'<pubDate>'.e($item['pubDate']).'</pubDate>'
            .'</item>')
        ->implode('');

    $feedXml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'
        .'<channel>'
        .'<title>'.e($companyName).'</title>'
        .'<link>'.e($baseUrl).'/</link>'
        .'<description>'.e($siteDesc).'</description>'
        .'<language>vi-VN</language>'
        .'<atom:link href="'.e(SiteSetting::canonicalUrl('/feed.xml')).'" rel="self" type="application/rss+xml" />'
        .'<atom:link href="https://pubsubhubbub.appspot.com/" rel="hub" />'
        .$items
        .'</channel>'
        .'</rss>';

    return response($feedXml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
})->name('kbuser.feed');

Route::get('/indexnow-{key}.txt', function (string $key) {
    $expectedKey = SeoAutoIndexService::indexNowKey();
    abort_if($key !== $expectedKey, 404);

    return response($expectedKey, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('kbuser.indexnow.key');

Route::middleware(UseKbUserRootView::class)->group(function () {
    Route::get('/', [KbUserController::class, 'index'])->name('kbuser.home');
    Route::get('/categories', [KbUserController::class, 'categories'])->name('kbuser.categories');
    Route::get('/categories/{categorySlug}/{subcategorySlug}', [KbUserController::class, 'products'])->name('kbuser.categories.subcategory');
    Route::get('/categories/{categorySlug}', [KbUserController::class, 'categories'])->name('kbuser.categories.show');
    Route::get('/products/search', [KbUserController::class, 'searchProducts'])->name('kbuser.products.search');
    Route::get('/products', [KbUserController::class, 'products'])->name('kbuser.products');
    Route::get('/catalogues', [KbUserController::class, 'catalogues'])->name('kbuser.catalogues');
    Route::get('/products/{productSlug}', [KbUserController::class, 'showProduct'])->name('kbuser.products.show');
    Route::post('/products/{product}/reviews', [KbUserController::class, 'storeProductReview'])->whereNumber('product')->middleware('auth')->name('kbuser.products.reviews.store');
    Route::post('/products/{product}/comments', [KbUserController::class, 'storeProductComment'])->whereNumber('product')->middleware('auth')->name('kbuser.products.comments.store');
    Route::get('/featured-projects', [KbUserController::class, 'featuredProjects'])->name('kbuser.featured-projects');
    Route::get('/featured-projects/{projectSlug}', [KbUserController::class, 'showFeaturedProject'])->name('kbuser.featured-projects.show');
    Route::get('/about-us', [KbUserController::class, 'about'])->name('kbuser.about');
    Route::get('/news', [KbUserController::class, 'news'])->name('kbuser.news');
    Route::get('/news/{article:slug}', [KbUserController::class, 'showArticle'])->name('kbuser.news.show');
    Route::post('/news/{article:slug}/comments', [KbUserController::class, 'storeArticleComment'])->middleware('auth')->name('kbuser.news.comments.store');
    Route::get('/contacts', [KbUserController::class, 'contacts'])->name('kbuser.contacts');
    Route::post('/contacts', [KbUserController::class, 'storeContact'])->middleware('throttle:6,1')->name('kbuser.contacts.store');
    Route::post('/wishlist/{product}', [KbUserController::class, 'toggleWishlist'])->whereNumber('product')->middleware('auth')->name('kbuser.wishlist.toggle');
    Route::get('/my-account/overview', [KbUserController::class, 'accountOverview'])->middleware('auth')->name('kbuser.account.overview');
    Route::get('/chat/messages', [ChatController::class, 'messages'])->middleware('throttle:120,1')->name('kbuser.chat.messages');
    Route::post('/chat/messages', [ChatController::class, 'store'])->middleware('throttle:20,1')->name('kbuser.chat.store');

    Route::post('/login', [UserAuthController::class, 'login'])->name('kbuser.login.store');
    Route::post('/register', [UserAuthController::class, 'register'])->name('kbuser.register.store');
    Route::post('/forgot-password', [UserAuthController::class, 'sendResetCode'])->middleware('throttle:5,1')->name('kbuser.password.email');
    Route::post('/reset-password', [UserAuthController::class, 'resetPassword'])->middleware('throttle:10,1')->name('kbuser.password.update');
    Route::post('/logout', [UserAuthController::class, 'logout'])->middleware('auth')->name('kbuser.logout');
    Route::put('/my-account/profile', [UserAuthController::class, 'updateProfile'])->middleware('auth')->name('kbuser.account.profile.update');
    Route::put('/my-account/password', [UserAuthController::class, 'updatePassword'])->middleware('auth')->name('kbuser.account.password.update');
    Route::get('/auth/google/redirect', [UserAuthController::class, 'redirectToGoogle'])->name('kbuser.auth.google.redirect');
    Route::get('/auth/google/callback', [UserAuthController::class, 'handleGoogleCallback'])->name('kbuser.auth.google.callback');

    Route::get('/quotations', [QuotationController::class, 'index'])->name('kbuser.quotations.index');
    Route::get('/quotations/login', [QuotationController::class, 'showLogin'])->name('kbuser.quotations.login');
    Route::post('/quotations/login', [QuotationController::class, 'login'])->name('kbuser.quotations.login.store');
    Route::post('/quotations/logout', [QuotationController::class, 'logout'])->name('kbuser.quotations.logout');

    Route::get('/kbusers', [KbUserController::class, 'index'])->name('kbuser.index');
});

Route::middleware([UseKbUserRootView::class, 'auth', 'verified'])
    ->group(function () {
        Route::resource('kbusers', KbUserController::class)
            ->except('index')
            ->names('kbuser');
    });
