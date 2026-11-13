<?php

use Illuminate\Support\Facades\Route;
use Modules\KbAdmin\Http\Controllers\AdminAuthController;
use Modules\KbAdmin\Http\Controllers\ArticleController;
use Modules\KbAdmin\Http\Controllers\CatalogueController;
use Modules\KbAdmin\Http\Controllers\CategoryController;
use Modules\KbAdmin\Http\Controllers\ChatController;
use Modules\KbAdmin\Http\Controllers\ConsultationController;
use Modules\KbAdmin\Http\Controllers\CustomerController;
use Modules\KbAdmin\Http\Controllers\DashboardController;
use Modules\KbAdmin\Http\Controllers\FeaturedProjectController;
use Modules\KbAdmin\Http\Controllers\KflexPriceController;
use Modules\KbAdmin\Http\Controllers\NotificationController;
use Modules\KbAdmin\Http\Controllers\ProductController;
use Modules\KbAdmin\Http\Controllers\ProjectReportController;
use Modules\KbAdmin\Http\Controllers\QuotationController;
use Modules\KbAdmin\Http\Controllers\SeoController;
use Modules\KbAdmin\Http\Controllers\SiteSettingController;
use Modules\KbAdmin\Http\Controllers\SiteVisitController;
use Modules\KbAdmin\Http\Middleware\EnsureAdminAuthenticated;
use Modules\KbAdmin\Http\Middleware\UseKbAdminRootView;

Route::middleware(UseKbAdminRootView::class)
    ->prefix('admin')
    ->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLogin'])
            ->name('admin.login');
        Route::post('/login', [AdminAuthController::class, 'login'])
            ->name('admin.login.store');
        Route::get('/login/otp', [AdminAuthController::class, 'showOtp'])
            ->name('admin.login.otp');
        Route::post('/login/otp', [AdminAuthController::class, 'verifyOtp'])
            ->name('admin.login.otp.verify');
        Route::post('/login/otp/resend', [AdminAuthController::class, 'resendOtp'])
            ->name('admin.login.otp.resend');
        Route::post('/logout', [AdminAuthController::class, 'logout'])
            ->name('admin.logout');
    });

Route::middleware([UseKbAdminRootView::class, EnsureAdminAuthenticated::class])
    ->prefix('admin')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('admin.dashboard.index');

        Route::get('/visits', [SiteVisitController::class, 'index'])
            ->name('admin.visits.index');
        Route::delete('/visits', [SiteVisitController::class, 'clear'])
            ->name('admin.visits.clear');
        Route::delete('/visits/{siteVisit}', [SiteVisitController::class, 'destroy'])
            ->name('admin.visits.destroy');

        Route::get('/notifications', [NotificationController::class, 'index'])
            ->name('admin.notifications.index');
        Route::get('/search', [NotificationController::class, 'search'])
            ->name('admin.search');
        Route::post('/notifications/read-chats', [NotificationController::class, 'markAllChatsRead'])
            ->name('admin.notifications.read-chats');

        Route::get('/settings', [SiteSettingController::class, 'index'])
            ->name('admin.settings.index');
        Route::match(['put', 'post'], '/settings', [SiteSettingController::class, 'update'])
            ->name('admin.settings.update');

        Route::get('/seo', [SeoController::class, 'index'])
            ->name('admin.seo.index');
        Route::put('/seo', [SeoController::class, 'update'])
            ->name('admin.seo.update');
        Route::post('/seo/generate-all', [SeoController::class, 'generateAll'])
            ->name('admin.seo.generate-all');
        Route::put('/seo/entities/{type}/{id}', [SeoController::class, 'updateEntity'])
            ->whereNumber('id')
            ->name('admin.seo.entities.update');
        Route::post('/seo/ping', [SeoController::class, 'ping'])
            ->name('admin.seo.ping');

        Route::get('/products', [ProductController::class, 'index'])
            ->name('admin.products.index');

        Route::get('/kflex-prices', [KflexPriceController::class, 'index'])
            ->name('admin.kflex-prices.index');
        Route::post('/kflex-prices/bulk', [KflexPriceController::class, 'bulkStoreOrUpdate'])
            ->name('admin.kflex-prices.bulk');
        Route::delete('/kflex-prices/bulk', [KflexPriceController::class, 'bulkDestroy'])
            ->name('admin.kflex-prices.bulk-destroy');
        Route::put('/kflex-prices/{kflexPrice}', [KflexPriceController::class, 'update'])
            ->name('admin.kflex-prices.update');
        Route::delete('/kflex-prices/{kflexPrice}', [KflexPriceController::class, 'destroy'])
            ->name('admin.kflex-prices.destroy');

        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('admin.categories.index');

        Route::get('/case-studies', [FeaturedProjectController::class, 'index'])
            ->name('admin.case-studies.index');

        Route::get('/articles', [ArticleController::class, 'index'])->name('admin.articles.index');
        Route::post('/articles', [ArticleController::class, 'store'])->name('admin.articles.store');
        Route::post('/articles/images', [ArticleController::class, 'storeContentImage'])->name('admin.articles.images.store');
        Route::put('/articles/{article}', [ArticleController::class, 'update'])->name('admin.articles.update');
        Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->name('admin.articles.destroy');

        Route::get('/catalogues', [CatalogueController::class, 'index'])->name('admin.catalogues.index');
        Route::post('/catalogues', [CatalogueController::class, 'store'])->name('admin.catalogues.store');
        Route::put('/catalogues/{catalogue}', [CatalogueController::class, 'update'])->name('admin.catalogues.update');
        Route::delete('/catalogues/{catalogue}', [CatalogueController::class, 'destroy'])->name('admin.catalogues.destroy');

        Route::get('/consultations', [ConsultationController::class, 'index'])
            ->name('admin.consultations.index');

        Route::get('/customers', [CustomerController::class, 'index'])
            ->name('admin.customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])
            ->name('admin.customers.store');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])
            ->name('admin.customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
            ->name('admin.customers.destroy');

        Route::get('/chats', [ChatController::class, 'index'])->name('admin.chats.index');
        Route::get('/chats/list', [ChatController::class, 'conversations'])->name('admin.chats.list');
        Route::get('/chats/{conversation}/messages', [ChatController::class, 'messages'])->name('admin.chats.messages');
        Route::post('/chats/{conversation}/messages', [ChatController::class, 'store'])->name('admin.chats.store');
        Route::patch('/chats/{conversation}/status', [ChatController::class, 'updateStatus'])->name('admin.chats.update-status');
        Route::delete('/chats/{conversation}', [ChatController::class, 'destroy'])->name('admin.chats.destroy');

        Route::get('/project-reports', [ProjectReportController::class, 'index'])
            ->name('admin.project-reports.index');

        Route::get('/quotations', [QuotationController::class, 'index'])
            ->name('admin.quotations.index');
        Route::post('/quotations/drafts', [QuotationController::class, 'storeDraft'])
            ->name('admin.quotations.drafts.store');
        Route::delete('/quotations/drafts/{quotationDraft}', [QuotationController::class, 'destroyDraft'])
            ->whereNumber('quotationDraft')
            ->name('admin.quotations.drafts.destroy');
        Route::post('/quotations/import', [QuotationController::class, 'importFile'])
            ->name('admin.quotations.import');

        Route::get('/project-reports/export', [ProjectReportController::class, 'export'])
            ->name('admin.project-reports.export');

        Route::post('/project-reports/import', [ProjectReportController::class, 'import'])
            ->name('admin.project-reports.import');

        Route::post('/project-reports', [ProjectReportController::class, 'store'])
            ->name('admin.project-reports.store');

        Route::put('/project-reports/{projectReport}', [ProjectReportController::class, 'update'])
            ->name('admin.project-reports.update');

        Route::delete('/project-reports/{projectReport}', [ProjectReportController::class, 'destroy'])
            ->name('admin.project-reports.destroy');

        Route::patch('/consultations/{contact}/status', [ConsultationController::class, 'updateStatus'])
            ->name('admin.consultations.update-status');

        Route::post('/consultations/{contact}/quotation', [ConsultationController::class, 'respondWithQuotation'])
            ->name('admin.consultations.quotation');

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
