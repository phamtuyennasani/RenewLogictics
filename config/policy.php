<?php

return [
    'quy-dinh-tao-don' => [
        'title' => 'Quy định tạo đơn',
        'contentvi' => true,
        'content_editor' => true,
        'canEdit' => ['admin'],
        'canView' => ['admin', 'manager', 'cs', 'sale', 'ctv'],
    ],
    'quy-dinh-khai-hang' => [
        'title' => 'Quy định khai hàng',
        'contentvi' => true,
        'content_editor' => true,
        'canEdit' => ['admin'],
        'canView' => ['admin', 'manager', 'cs', 'sale', 'ctv'],
    ],
    'quy-dinh-them-tai' => [
        'title' => 'Quy định thêm tải',
        'contentvi' => true,
        'content_editor' => true,
        'canEdit' => ['admin'],
        'canView' => ['admin', 'manager', 'cs', 'sale', 'ctv'],
    ],
    'dieu-khoan' => [
        'title' => 'Điều khoản sử dụng',
        'contentvi' => true,
        'content_editor' => true,
        'canEdit' => ['admin'],
        'canView' => ['admin', 'manager', 'cs', 'sale', 'ctv'],
    ],
    'bao-mat' => [
        'title' => 'Chính sách bảo mật',
        'contentvi' => true,
        'content_editor' => true,
        'canEdit' => ['admin'],
        'canView' => ['admin', 'manager', 'cs', 'sale', 'ctv'],
    ],
];
