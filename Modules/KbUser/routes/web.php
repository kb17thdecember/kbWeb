<?php

use Illuminate\Support\Facades\Route;
use Modules\KbUser\Http\Controllers\ChatController;
use Modules\KbUser\Http\Controllers\KbUserController;
use Modules\KbUser\Http\Middleware\UseKbUserRootView;

Route::get('/sitemap.xml', function () {
    $paths = ['/', '/categories', '/products', '/featured-projects', '/about-us', '/contacts'];
    $urls = collect($paths)->map(fn (string $path): string => '<url><loc>'.e(url($path)).'</loc></url>')->implode('');

    return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200, ['Content-Type' => 'application/xml']);
})->name('kbuser.sitemap');

Route::middleware(UseKbUserRootView::class)->group(function () {
    Route::get('/', [KbUserController::class, 'index'])->name('kbuser.home');
    Route::get('/categories', [KbUserController::class, 'categories'])->name('kbuser.categories');
    Route::get('/products', [KbUserController::class, 'products'])->name('kbuser.products');
    Route::get('/featured-projects', [KbUserController::class, 'featuredProjects'])->name('kbuser.featured-projects');
    Route::get('/about-us', [KbUserController::class, 'about'])->name('kbuser.about');
    Route::get('/contacts', [KbUserController::class, 'contacts'])->name('kbuser.contacts');
    Route::post('/contacts', [KbUserController::class, 'storeContact'])->middleware('throttle:6,1')->name('kbuser.contacts.store');
    Route::get('/chat/messages', [ChatController::class, 'messages'])->middleware('throttle:120,1')->name('kbuser.chat.messages');
    Route::post('/chat/messages', [ChatController::class, 'store'])->middleware('throttle:20,1')->name('kbuser.chat.store');
    Route::get('/kbusers', [KbUserController::class, 'index'])->name('kbuser.index');
});

Route::middleware([UseKbUserRootView::class, 'auth', 'verified'])
    ->group(function () {
        Route::resource('kbusers', KbUserController::class)
            ->except('index')
            ->names('kbuser');
    });
