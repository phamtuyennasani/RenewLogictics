<?php

use App\Services\Providers\Sepay\SepayPaymentService;

return [
    'default' => env('PAYMENT_PROVIDER_DEFAULT', 'sepay'),
    'drivers' => [
        'sepay' => SepayPaymentService::class,
    ],
];
