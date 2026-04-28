<?php

/**
 * Theme Configuration — Cấu hình màu chủ đạo hệ thống
 *
 * Cách dùng:
 *   config('theme.primary')     → '3b82f6'  (blue-500)
 *   config('theme.accent')      → '0ea5e9'  (sky-500)
 *   config('theme.primary_rgb') → '59, 130, 246'  (dùng cho rgba)
 *
 * Để đổi màu toàn hệ thống, chỉ cần thay đổi các giá trị HEX bên dưới.
 */

return [

    /*
    |------------------------------------------------------------
    | MÀU CHỦ ĐẠO (PRIMARY)
    | Màu chính của hệ thống — xanh dương
    |------------------------------------------------------------
    */
    'primary' => [
        'hex'   => env('THEME_PRIMARY_HEX', '#3b82f6'),        // ← Đổi màu tại đây
        'rgb'   => env('THEME_PRIMARY_RGB', '59, 130, 246'),   // RGB cho rgba()
        'dark'  => env('THEME_PRIMARY_DARK', '#2563eb'),       // Màu hover/active
        'light' => env('THEME_PRIMARY_LIGHT', '#eff6ff'),      // Màu nền nhạt
    ],

    /*
    |------------------------------------------------------------
    | MÀU NHẤN MẠNH (ACCENT)
    | Màu phụ để tạo điểm nhấn — xanh nhạt (sky)
    |------------------------------------------------------------
    */
    'accent' => [
        'hex'   => env('THEME_ACCENT_HEX', '#0ea5e9'),
        'rgb'   => env('THEME_ACCENT_RGB', '14, 165, 233'),
        'dark'  => env('THEME_ACCENT_DARK', '#0284c7'),
        'light' => env('THEME_ACCENT_LIGHT', '#f0f9ff'),
    ],

    /*
    |------------------------------------------------------------
    | MÀU TRUNG TÍNH (NEUTRAL)
    |------------------------------------------------------------
    */
    'neutral' => [
        'text'      => '#171717',
        'muted'     => '#737373',
        'border'    => '#e5e5e5',
        'bg'        => '#fafafa',
        'bg-surface'=> '#ffffff',
    ],

    /*
    |------------------------------------------------------------
    | MÀU SEMANTIC — Trạng thái
    |------------------------------------------------------------
    */
    'semantic' => [
        'success'   => ['hex' => '#10b981', 'rgb' => '16, 185, 129'],
        'warning'   => ['hex' => '#f59e0b', 'rgb' => '245, 158, 11'],
        'danger'    => ['hex' => '#ef4444', 'rgb' => '239, 68, 68'],
        'info'      => ['hex' => '#06b6d4', 'rgb' => '6, 182, 212'],
    ],

    /*
    |------------------------------------------------------------
    | BRAND — Logo & Icon
    |------------------------------------------------------------
    */
    'brand' => [
        'name'   => env('BRAND_NAME', 'VAU TRANS'),
        'slogan' => env('BRAND_SLOGAN', 'Hệ thống quản lý vận chuyển'),
        'logo'   => env('BRAND_LOGO', null),
    ],

    /*
    |------------------------------------------------------------
    | CẤU HÌNH KHÁC
    |------------------------------------------------------------
    */
    'font' => [
        'family' => env('THEME_FONT_FAMILY', 'Inter'),
    ],

];
