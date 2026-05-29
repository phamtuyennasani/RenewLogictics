<?php

use App\Services\Providers\Sepay\SepayEInvoiceService;

return [
    'default' => env('EINVOICE_PROVIDER_DEFAULT', 'sepay'),
    'drivers' => [
        'sepay' => SepayEInvoiceService::class,
        // Thêm provider mới tại đây:
        // 'vnpt' => \App\Services\Providers\VNPT\VNPTEInvoiceService::class,
        // 'viettel' => \App\Services\Providers\Viettel\ViettelEInvoiceService::class,
    ],
];
