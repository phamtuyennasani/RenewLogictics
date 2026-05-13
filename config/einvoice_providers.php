<?php

use App\Services\Providers\Sepay\SepayEInvoiceService;

return [
    'default' => env('EINVOICE_PROVIDER_DEFAULT', 'sepay'),
    'drivers' => [
        'sepay' => SepayEInvoiceService::class,
    ],
];
