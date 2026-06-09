<?php

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileOpsScanController;
use App\Http\Controllers\Api\Mobile\MobileShipperPickupController;
use App\Http\Controllers\Api\ThirdPartyOrderTrackingController;
use App\Http\Controllers\Webhook\MoMoWebhookController;
use App\Http\Controllers\Webhook\SepayGatewayIpnController;
use App\Http\Controllers\Webhook\SepayWebhookController;
use App\Http\Controllers\Webhook\VNPayWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/sepay', SepayWebhookController::class)
    ->name('api.webhooks.sepay');

Route::post('/payment-gateways/sepay/ipn', SepayGatewayIpnController::class)
    ->name('api.payment-gateways.sepay.ipn');

Route::post('/webhooks/momo', MoMoWebhookController::class)
    ->name('api.webhooks.momo');

Route::match(['post', 'get'], '/webhooks/vnpay', VNPayWebhookController::class)
    ->name('api.webhooks.vnpay');

Route::post('/third-party/orders/tracking', ThirdPartyOrderTrackingController::class)
    ->middleware(['throttle:third-party-tracking', 'third-party.tracking-api'])
    ->name('api.third-party.orders.tracking');

/*
|--------------------------------------------------------------------------
| Mobile API (Flutter app — Shipper & OPS)
|--------------------------------------------------------------------------
| Sanctum token auth. Tái dùng business action/enum hiện có.
| Xem docs/MOBILE_API_CONTRACT.md.
*/
Route::prefix('mobile')->name('api.mobile.')->group(function (): void {
    // Auth — login public (rate limit chống brute-force), còn lại cần token.
    Route::post('/login', [MobileAuthController::class, 'login'])
        ->middleware('throttle:mobile-login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [MobileAuthController::class, 'me'])->name('me');
        Route::post('/logout', [MobileAuthController::class, 'logout'])->name('logout');

        // Shipper pickup — bắt buộc role shipper; controller ép id_shipper = auth()->id().
        Route::middleware('role:shipper')->prefix('shipper')->name('shipper.')->group(function (): void {
            Route::get('/pickups', [MobileShipperPickupController::class, 'index'])->name('pickups.index');
            Route::get('/pickups/{pickup}', [MobileShipperPickupController::class, 'show'])->name('pickups.show');
            Route::post('/pickups/{pickup}/status', [MobileShipperPickupController::class, 'updateStatus'])->name('pickups.status');
        });

        // OPS scan/nhập kho — role ops|admin|manager|cs.
        Route::middleware('role:ops|admin|manager|cs')->prefix('ops')->name('ops.')->group(function (): void {
            Route::post('/scan', [MobileOpsScanController::class, 'scan'])
                ->middleware('throttle:mobile-scan')
                ->name('scan');
            Route::post('/orders/{order}/receive', [MobileOpsScanController::class, 'receive'])
                ->middleware('throttle:mobile-receive')
                ->name('orders.receive');
            Route::post('/orders/bulk-receive', [MobileOpsScanController::class, 'bulkReceive'])
                ->middleware('throttle:mobile-receive')
                ->name('orders.bulk-receive');
        });
    });
});