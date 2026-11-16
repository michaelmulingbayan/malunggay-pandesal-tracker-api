<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\PandesalPriceController;
use App\Http\Controllers\Api\V1\ProductionController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Controllers\Api\V1\RiderStatusController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SalesSummaryController;
use App\Http\Controllers\Api\V1\StockDistributionController;
use App\Http\Controllers\Api\V1\StockReturnController;
use App\Http\Controllers\Api\V1\StockReturnVerificationController;
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

        Route::apiResource('productions', ProductionController::class)->only(['index', 'store']);
        Route::apiResource('stock-distributions', StockDistributionController::class)->only(['index', 'store']);

        Route::get('/inventory', InventoryController::class)->name('inventory');
        Route::apiResource('stock-returns', StockReturnController::class)->only(['index', 'store']);
        Route::post('/stock-returns/{stockReturn}/verification', [StockReturnVerificationController::class, 'store'])
            ->name('stock-returns.verification.store');

        Route::apiResource('prices', PandesalPriceController::class)->only(['index', 'store']);

        Route::get('/sales/summary', SalesSummaryController::class)->name('sales.summary');
        Route::apiResource('sales', SaleController::class)->only(['store']);
    });
});
