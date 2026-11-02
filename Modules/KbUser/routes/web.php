<?php

use App\Models\Article;
use Illuminate\Support\Facades\Route;
use Modules\KbUser\Http\Controllers\ChatController;
use Modules\KbUser\Http\Controllers\KbUserController;
use Modules\KbUser\Http\Controllers\QuotationController;
use Modules\KbUser\Http\Controllers\UserAuthController;
use Modules\KbUser\Http\Middleware\UseKbUserRootView;

Route::get('/sitemap.xml', function () {
    $paths = ['/', '/categories', '/products', '/catalogues', '/featured-projects', '/news', '/about-us', '/contacts'];
    $urls = collect($paths)->map(fn (string $path): string => '<url><loc>'.e(url($path)).'</loc></url>')
        ->concat(Article::query()->published()->get(['slug'])->map(
            fn (Article $article): string => '<url><loc>'.e(url('/news/'.$article->slug)).'</loc></url>',
        ))
        ->implode('');

    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200, ['Content-Type' => 'application/xml']);
})->name('kbuser.sitemap');

Route::middleware(UseKbUserRootView::class)->group(function () {
    Route::get('/', [KbUserController::class, 'index'])->name('kbuser.home');
    Route::get('/categories', [KbUserController::class, 'categories'])->name('kbuser.categories');
    Route::get('/products/search', [KbUserController::class, 'searchProducts'])->name('kbuser.products.search');
    Route::get('/products', [KbUserController::class, 'products'])->name('kbuser.products');
    Route::get('/catalogues', [KbUserController::class, 'catalogues'])->name('kbuser.catalogues');
    Route::get('/products/{product}', [KbUserController::class, 'showProduct'])->whereNumber('product')->name('kbuser.products.show');
    Route::post('/products/{product}/reviews', [KbUserController::class, 'storeProductReview'])->whereNumber('product')->middleware('auth')->name('kbuser.products.reviews.store');
    Route::post('/products/{product}/comments', [KbUserController::class, 'storeProductComment'])->whereNumber('product')->middleware('auth')->name('kbuser.products.comments.store');
    Route::get('/featured-projects', [KbUserController::class, 'featuredProjects'])->name('kbuser.featured-projects');
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
