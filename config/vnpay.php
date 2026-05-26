<?php

return [
    'tmn_code' => env('VNPAY_TMN_CODE'),
    'hash_secret' => env('VNPAY_HASH_SECRET'),

    'environment' => env('VNPAY_ENV', 'sandbox'),

    'return_url' => env('VNPAY_RETURN_URL'),
    'ipn_url' => env('VNPAY_IPN_URL'),

    'command' => env('VNPAY_COMMAND', 'pay'),
    'order_type' => env('VNPAY_ORDER_TYPE', 'billpayment'),
    'locale' => env('VNPAY_LOCALE', 'vn'),
    'currency' => env('VNPAY_CURRENCY', 'VND'),
];
