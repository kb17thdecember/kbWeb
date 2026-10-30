<?php

use App\Models\Article;
use Illuminate\Support\Facades\Route;
use Modules\KbUser\Http\Controllers\ChatController;
use Modules\KbUser\Http\Controllers\KbUserController;
use Modules\KbUser\Http\Controllers\UserAuthController;
use Modules\KbUser\Http\Middleware\UseKbUserRootView;

Route::get('/sitemap.xml', function () {
    $paths = ['/', '/categories', '/products', '/featured-projects', '/news', '/about-us', '/contacts'];
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
    Route::get('/featured-projects', [KbUserController::class, 'featuredProjects'])->name('kbuser.featured-projects');
    Route::get('/about-us', [KbUserController::class, 'about'])->name('kbuser.about');
    Route::get('/news', [KbUserController::class, 'news'])->name('kbuser.news');
    Route::get('/news/{article:slug}', [KbUserController::class, 'showArticle'])->name('kbuser.news.show');
    Route::get('/contacts', [KbUserController::class, 'contacts'])->name('kbuser.contacts');
    Route::post('/contacts', [KbUserController::class, 'storeContact'])->middleware('throttle:6,1')->name('kbuser.contacts.store');
    Route::get('/chat/messages', [ChatController::class, 'messages'])->middleware('throttle:120,1')->name('kbuser.chat.messages');
    Route::post('/chat/messages', [ChatController::class, 'store'])->middleware('throttle:20,1')->name('kbuser.chat.store');

    Route::post('/login', [UserAuthController::class, 'login'])->name('kbuser.login.store');
    Route::post('/register', [UserAuthController::class, 'register'])->name('kbuser.register.store');
    Route::post('/logout', [UserAuthController::class, 'logout'])->middleware('auth')->name('kbuser.logout');
    Route::get('/auth/google/redirect', [UserAuthController::class, 'redirectToGoogle'])->name('kbuser.auth.google.redirect');
    Route::get('/auth/google/callback', [UserAuthController::class, 'handleGoogleCallback'])->name('kbuser.auth.google.callback');

    Route::get('/kbusers', [KbUserController::class, 'index'])->name('kbuser.index');
});

Route::middleware([UseKbUserRootView::class, 'auth', 'verified'])
    ->group(function () {
        Route::resource('kbusers', KbUserController::class)
            ->except('index')
            ->names('kbuser');
    });
