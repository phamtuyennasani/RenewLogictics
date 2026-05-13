<?php

return [
    'bank' => env('SEPAY_BANK'),
    'account_number' => env('SEPAY_ACCOUNT_NUMBER'),
    'account_name' => env('SEPAY_ACCOUNT_NAME'),
    'qr_base_url' => env('SEPAY_QR_BASE_URL', 'https://qr.sepay.vn/img'),
    'auth_mode' => env('SEPAY_AUTH_MODE', 'hmac'),
    'api_key' => env('SEPAY_API_KEY'),
    'webhook_secret' => env('SEPAY_WEBHOOK_SECRET'),
    'timestamp_tolerance' => (int) env('SEPAY_TIMESTAMP_TOLERANCE', 300),
    'gateway' => [
        'environment' => env('SEPAY_GATEWAY_ENV', 'sandbox'),
        'merchant_id' => env('SEPAY_GATEWAY_MERCHANT_ID'),
        'secret_key' => env('SEPAY_GATEWAY_SECRET_KEY'),
        'ipn_secret_key' => env('SEPAY_GATEWAY_IPN_SECRET_KEY'),
        'api_base_url' => env('SEPAY_GATEWAY_API_BASE_URL'),
        'checkout_base_url' => env('SEPAY_GATEWAY_CHECKOUT_BASE_URL'),
        'default_success_url' => env('SEPAY_GATEWAY_SUCCESS_URL'),
        'default_error_url' => env('SEPAY_GATEWAY_ERROR_URL'),
        'default_cancel_url' => env('SEPAY_GATEWAY_CANCEL_URL'),
    ],
    'einvoice' => [
        'environment' => env('SEPAY_EINVOICE_ENV', 'sandbox'),
        'client_id' => env('SEPAY_EINVOICE_CLIENT_ID'),
        'client_secret' => env('SEPAY_EINVOICE_CLIENT_SECRET'),
        'base_url' => env('SEPAY_EINVOICE_BASE_URL'),
    ],
];
