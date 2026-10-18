<?php

use Illuminate\Support\Facades\Route;
use Modules\KbAdmin\Http\Controllers\KbAdminController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('kbadmins', KbAdminController::class)->names('kbadmin');
});
