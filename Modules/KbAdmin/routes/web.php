<?php

use Illuminate\Support\Facades\Route;
use Modules\KbAdmin\Http\Controllers\CategoryController;
use Modules\KbAdmin\Http\Controllers\DashboardController;
use Modules\KbAdmin\Http\Controllers\ProductController;
use Modules\KbAdmin\Http\Middleware\UseKbAdminRootView;

Route::middleware(UseKbAdminRootView::class)
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('admin.dashboard.index');

        Route::get('/products', [ProductController::class, 'index'])
            ->name('admin.products.index');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('admin.categories.index');

        Route::post('/categories', [CategoryController::class, 'store'])
            ->name('admin.categories.store');

        Route::put('/categories/{category}', [CategoryController::class, 'update'])
            ->name('admin.categories.update');

        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->name('admin.categories.destroy');

        Route::post('/products', [ProductController::class, 'store'])
            ->name('admin.products.store');

        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->name('admin.products.update');

        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->name('admin.products.destroy');
    });
