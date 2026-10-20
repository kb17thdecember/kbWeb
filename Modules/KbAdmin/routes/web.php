<?php

use Illuminate\Support\Facades\Route;
use Modules\KbAdmin\Http\Controllers\CategoryController;
use Modules\KbAdmin\Http\Controllers\ConsultationController;
use Modules\KbAdmin\Http\Controllers\DashboardController;
use Modules\KbAdmin\Http\Controllers\FeaturedProjectController;
use Modules\KbAdmin\Http\Controllers\ProductController;
use Modules\KbAdmin\Http\Controllers\ProjectReportController;
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

        Route::get('/case-studies', [FeaturedProjectController::class, 'index'])
            ->name('admin.case-studies.index');

        Route::get('/consultations', [ConsultationController::class, 'index'])
            ->name('admin.consultations.index');

        Route::get('/project-reports', [ProjectReportController::class, 'index'])
            ->name('admin.project-reports.index');

        Route::get('/project-reports/export', [ProjectReportController::class, 'export'])
            ->name('admin.project-reports.export');

        Route::post('/project-reports', [ProjectReportController::class, 'store'])
            ->name('admin.project-reports.store');

        Route::put('/project-reports/{projectReport}', [ProjectReportController::class, 'update'])
            ->name('admin.project-reports.update');

        Route::delete('/project-reports/{projectReport}', [ProjectReportController::class, 'destroy'])
            ->name('admin.project-reports.destroy');

        Route::patch('/consultations/{contact}/status', [ConsultationController::class, 'updateStatus'])
            ->name('admin.consultations.update-status');

        Route::delete('/consultations/{contact}', [ConsultationController::class, 'destroy'])
            ->name('admin.consultations.destroy');

        Route::post('/case-studies', [FeaturedProjectController::class, 'store'])
            ->name('admin.case-studies.store');

        Route::put('/case-studies/{featuredProject}', [FeaturedProjectController::class, 'update'])
            ->name('admin.case-studies.update');

        Route::delete('/case-studies/{featuredProject}', [FeaturedProjectController::class, 'destroy'])
            ->name('admin.case-studies.destroy');

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
