<?php

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Http\Controllers\Api\Mobile\MobileConfigController;
use App\Http\Controllers\Api\Mobile\MobileDeviceTokenController;
use App\Http\Controllers\Api\Mobile\MobileLocationController;
use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use App\Http\Controllers\Api\Mobile\MobileOpsOrderController;
use App\Http\Controllers\Api\Mobile\MobileOpsPickupController;
use App\Http\Controllers\Api\Mobile\MobileOpsScanController;
use App\Http\Controllers\Api\Mobile\MobileShipperPickupController;
use App\Http\Controllers\Api\ThirdPartyOrderTrackingController;
use App\Http\Controllers\Api\VietmapProxyController;
use App\Http\Controllers\Api\ZaloMiniAppController;
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

Route::prefix('zalo-mini-app')
    ->middleware('throttle:60,1')
    ->name('api.zalo-mini-app.')
    ->group(function (): void {
        Route::get('/bootstrap', [ZaloMiniAppController::class, 'bootstrap'])->name('bootstrap');
        Route::get('/countries', [ZaloMiniAppController::class, 'countries'])->name('countries');
        Route::post('/quote', [ZaloMiniAppController::class, 'quote'])->name('quote');
        Route::post('/shipping-requests', [ZaloMiniAppController::class, 'storeShippingRequest'])->name('shipping-requests.store');
    });

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

        // Profile self-service (mọi role mobile sửa hồ sơ của chính mình).
        // Avatar tách POST vì PHP không parse multipart cho method PUT.
        Route::put('/profile', [MobileAuthController::class, 'updateProfile'])->name('profile.update');
        Route::put('/profile/password', [MobileAuthController::class, 'updatePassword'])->name('profile.password');
        Route::post('/profile/avatar', [MobileAuthController::class, 'updateAvatar'])->name('profile.avatar');

        // Device token cho push notification (mọi role mobile).
        Route::post('/device-tokens', [MobileDeviceTokenController::class, 'store'])->name('device-tokens.store');
        Route::post('/device-tokens/revoke', [MobileDeviceTokenController::class, 'revoke'])->name('device-tokens.revoke');

        // Cấu hình public cho app (vd VietMap tile key để render bản đồ).
        Route::get('/config', [MobileConfigController::class, 'index'])->name('config');

        // Thông báo nội bộ — trả tất cả thông báo active theo role, không chỉ unread.
        Route::get('/notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [MobileNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::get('/notifications/{notification}', [MobileNotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/{notification}/read', [MobileNotificationController::class, 'markRead'])->name('notifications.read');

        // Proxy VietMap (search/place/reverse/route) — giấu geocode/route key ở
        // server. Tile/style tải thẳng từ VietMap bằng tile key, KHÔNG qua đây.
        Route::prefix('vietmap')->name('vietmap.')->middleware('throttle:120,1')->group(function (): void {
            Route::get('/search', [VietmapProxyController::class, 'search'])->name('search');
            Route::get('/place', [VietmapProxyController::class, 'place'])->name('place');
            Route::get('/reverse', [VietmapProxyController::class, 'reverse'])->name('reverse');
            Route::get('/route', [VietmapProxyController::class, 'routeDirections'])->name('route');
        });

        // Shipper pickup — bắt buộc role shipper; controller ép id_shipper = auth()->id().
        Route::middleware('role:shipper')->prefix('shipper')->name('shipper.')->group(function (): void {
            Route::get('/pickups', [MobileShipperPickupController::class, 'index'])->name('pickups.index');
            Route::get('/pickups/{pickup}', [MobileShipperPickupController::class, 'show'])->name('pickups.show');
            Route::post('/pickups/{pickup}/status', [MobileShipperPickupController::class, 'updateStatus'])->name('pickups.status');

            // Ảnh bằng chứng pickup (shipper chụp khi lấy hàng).
            Route::get('/pickups/{pickup}/images', [MobileShipperPickupController::class, 'images'])->name('pickups.images.index');
            Route::post('/pickups/{pickup}/images', [MobileShipperPickupController::class, 'storeImage'])->name('pickups.images.store');
            Route::delete('/pickups/{pickup}/images/{image}', [MobileShipperPickupController::class, 'destroyImage'])->name('pickups.images.destroy');

            // Quét mã kiện → tìm pickup → nhận hàng.
            Route::post('/scan', [MobileShipperPickupController::class, 'scan'])
                ->middleware('throttle:mobile-scan')->name('scan');
            Route::post('/pickups/receive-by-scan', [MobileShipperPickupController::class, 'receiveByScan'])
                ->middleware('throttle:mobile-receive')->name('pickups.receive-by-scan');
        });

        // OPS scan/nhập kho — role ops|admin|manager. CS không được scan.
        Route::middleware('role:ops|admin|manager')->prefix('ops')->name('ops.')->group(function (): void {
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

        // OPS module mobile (orders, pickups, danh mục) — role ops|admin|manager|cs.
        Route::middleware('role:ops|admin|manager|cs')->prefix('ops')->name('ops.')->group(function (): void {
            // OPS orders — đơn được giao cho OPS này (id_ops = auth id).
            Route::get('/orders', [MobileOpsOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [MobileOpsOrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/pickups', [MobileOpsOrderController::class, 'createPickup'])->name('orders.pickups.create');

            // OPS pickups — phiếu pickup OPS này sở hữu (id_user = auth id).
            Route::get('/pickups', [MobileOpsPickupController::class, 'index'])->name('pickups.index');
            Route::get('/pickups/{pickup}', [MobileOpsPickupController::class, 'show'])->name('pickups.show');
            Route::post('/pickups/{pickup}/assign-shipper', [MobileOpsPickupController::class, 'assignShipper'])->name('pickups.assign-shipper');

            // Danh mục dùng chung cho form tạo pickup.
            Route::get('/shippers', [MobileOpsPickupController::class, 'shippers'])->name('shippers.index');
            Route::get('/locations/provinces', [MobileLocationController::class, 'provinces'])->name('locations.provinces');
            Route::get('/locations/wards', [MobileLocationController::class, 'wards'])->name('locations.wards');
        });
    });
});
