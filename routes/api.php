<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Controllers\Api\V1\RiderStatusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::apiResource('riders', RiderController::class)->only(['index', 'store']);
        Route::patch('/riders/{rider}/status', [RiderStatusController::class, 'update'])->name('riders.status.update');
    });
});
