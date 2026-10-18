<?php

use Illuminate\Support\Facades\Route;
use Modules\KbUser\Http\Controllers\KbUserController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('kbusers', KbUserController::class)->names('kbuser');
});
