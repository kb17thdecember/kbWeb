<?php

use Illuminate\Support\Facades\Route;
use Modules\KbUser\Http\Controllers\KbUserController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('kbusers', KbUserController::class)->names('kbuser');
});
