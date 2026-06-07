<?php

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