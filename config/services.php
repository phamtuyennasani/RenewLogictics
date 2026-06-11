<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'trackingmore' => [
        'key' => env('TRACKINGMORE_API_KEY'),
        'base_url' => env('TRACKINGMORE_BASE_URL', 'https://api.trackingmore.com/v4'),
        'timeout' => (float) env('TRACKINGMORE_TIMEOUT', 20),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'firebase' => [
        // Bật/tắt toàn bộ push notification. Tắt (false) → bỏ qua hoàn toàn,
        // không enqueue job, không gọi FCM. Dùng khi chưa có app để nhận.
        'push_enabled' => env('PUSH_ENABLED', false),
        // Đường dẫn tới service account JSON (CHỈ ở server, KHÔNG đưa vào app).
        // Để trống → FcmSender bỏ qua việc gửi (no-op an toàn khi chưa cấu hình).
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],
];
