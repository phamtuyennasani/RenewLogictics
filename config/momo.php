<?php

return [
    'partner_code' => env('MOMO_PARTNER_CODE'),
    'access_key' => env('MOMO_ACCESS_KEY'),
    'secret_key' => env('MOMO_SECRET_KEY'),

    'environment' => env('MOMO_ENV', 'sandbox'),

    'redirect_url' => env('MOMO_REDIRECT_URL'),
    'ipn_url' => env('MOMO_IPN_URL'),

    'request_type' => env('MOMO_REQUEST_TYPE', 'captureWallet'),
    'partner_name' => env('MOMO_PARTNER_NAME', 'Test'),
    'store_id' => env('MOMO_STORE_ID', 'MoMoTestStore'),

    'endpoint' => env('MOMO_ENDPOINT'),
];
