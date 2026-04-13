<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Branding Configuration
    |--------------------------------------------------------------------------
    |
    | Các thông tin thương hiệu của hệ thống. Có thể thay đổi tùy công ty
    | sử dụng hệ thống này.
    |
    */

    // Tên đầy đủ của công ty/hệ thống
    'name' => env('SYSTEM_NAME', 'LOGISTICS'),

    // Tên viết tắt (dùng trong logo, favicon)
    'short_name' => env('SYSTEM_SHORT_NAME', 'LOG'),

    // Slogan của công ty
    'slogan' => env('SYSTEM_SLOGAN', 'Hệ thống quản lý vận chuyển'),

    // Logo (để trống = dùng text)
    //VD: 'logo' => '/images/logo.png'
    'logo' => env('SYSTEM_LOGO', ''),

    // Favicon (để trống = dùng mặc định)
    'favicon' => env('SYSTEM_FAVICON', ''),

    // Màu chủ đạo (primary color)
    'primary_color' => env('SYSTEM_PRIMARY_COLOR', '#0EA5E9'),

    // Màu phụ (secondary color)
    'secondary_color' => env('SYSTEM_SECONDARY_COLOR', '#06B6D4'),
];