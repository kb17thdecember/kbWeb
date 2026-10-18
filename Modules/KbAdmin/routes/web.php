<?php

use Illuminate\Support\Facades\Route;
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
    });