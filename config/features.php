<?php

return [
    'items' => [
        'packages' => [
            'label' => 'Quản lý tải hàng',
            'description' => 'Bật/tắt menu và màn hình quản lý tải hàng.',
            'icon' => 'truck',
            'available' =>true,
            'default' => true,
        ],

        'invoice' => [
            'label' => 'Hóa đơn thu',
            'description' => 'Bật/tắt màn hình quản lý hóa đơn thu.',
            'icon' => 'receipt-percent',
            'available' =>true,
            'default' => true,
        ],

        // Khi cần mở rộng, thêm key mới tại đây rồi gắn `feature:<key>`
        // vào route/menu hoặc gọi Feature::enabled('<key>') ở đúng điểm nghiệp vụ.
        // Ví dụ: online_payment, einvoice, email_order, customer_portal...
    ],
];