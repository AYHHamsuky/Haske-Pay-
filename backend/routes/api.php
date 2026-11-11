<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PaystackWebhookController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:otp')->group(function () {
        Route::post('auth/otp', [AuthController::class, 'requestCode']);
        Route::post('auth/verify', [AuthController::class, 'verify']);
    });

    Route::get('services', [CatalogController::class, 'services']);
    Route::get('services/{code}/plans', [CatalogController::class, 'plans']);
    Route::get('network', [CatalogController::class, 'detectNetwork']);

    Route::middleware(['auth:sanctum', 'device', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);
        Route::post('me/pin', [MeController::class, 'setPin']);

        Route::get('wallet/entries', [WalletController::class, 'entries']);
        Route::post('meters/verify', [CatalogController::class, 'verifyMeter'])->middleware('throttle:verify');
        Route::get('saved-meters', [PurchaseController::class, 'savedMeters']);
        Route::delete('saved-meters/{id}', [PurchaseController::class, 'deleteMeter']);

        Route::post('purchases', [PurchaseController::class, 'store'])->middleware('throttle:purchases');
        Route::get('transactions', [PurchaseController::class, 'index']);
        Route::get('transactions/{reference}', [PurchaseController::class, 'show']);
        Route::post('transactions/{reference}/requery', [PurchaseController::class, 'requery']);

        Route::get('agent/summary', [AgentController::class, 'summary']);
    });
});
