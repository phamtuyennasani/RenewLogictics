<?php

use App\Services\Providers\MoMo\MoMoPaymentService;
use App\Services\Providers\Sepay\SepayPaymentService;
use App\Services\Providers\VNPay\VNPayPaymentService;

return [
    'default' => env('PAYMENT_PROVIDER_DEFAULT', 'sepay'),
    'drivers' => [
        'sepay' => SepayPaymentService::class,
        'momo' => MoMoPaymentService::class,
        'vnpay' => VNPayPaymentService::class,
    ],
];
