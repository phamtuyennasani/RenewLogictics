<?php

/**
 * NhansuConfig — Cấu hình danh sách nhân sự nội bộ
 *
 * Key = type (dùng trong route)
 * Mỗi type tương ứng 1 Role trong Spatie Permission
 */
return [

    'sale' => [
        'role'  => 'sale',
        'title' => 'Kinh doanh',
        'prefix' => 'SALE',
        'columns'    => ['username' => 'Username', 'fullname' => 'Họ và tên', 'email' => 'Email', 'phone' => 'SĐT'],
        'formFields' => [
            'username'  => ['label' => 'Username',   'type' => 'text', 'required' => true],
            'code'      => ['label' => 'Mã nhân viên','type' => 'text', 'required' => false, 'hint' => 'Để trống để tự động tạo'],
            'fullname'  => ['label' => 'Họ và tên',   'type' => 'text', 'required' => true],
            'email'     => ['label' => 'Email',        'type' => 'email','required' => false],
            'phone'     => ['label' => 'SĐT',          'type' => 'text', 'required' => false],
            'password'  => ['label' => 'Mật khẩu',    'type' => 'password','required' => true],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'ketoan' => [
        'role'  => 'ketoan',
        'title' => 'Kế toán',
        'prefix' => 'KETOAN',
        'columns'    => ['username' => 'Username', 'fullname' => 'Họ và tên', 'email' => 'Email', 'phone' => 'SĐT'],
        'formFields' => [
            'username'  => ['label' => 'Username',   'type' => 'text', 'required' => true],
            'code'      => ['label' => 'Mã nhân viên','type' => 'text', 'required' => false, 'hint' => 'Để trống để tự động tạo'],
            'fullname'  => ['label' => 'Họ và tên',   'type' => 'text', 'required' => true],
            'email'     => ['label' => 'Email',        'type' => 'email','required' => false],
            'phone'     => ['label' => 'SĐT',          'type' => 'text', 'required' => false],
            'password'  => ['label' => 'Mật khẩu',    'type' => 'password','required' => true],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'cs' => [
        'role'  => 'cs',
        'title' => 'Chăm sóc khách hàng',
        'prefix' => 'CS',
        'columns'    => ['username' => 'Username', 'fullname' => 'Họ và tên', 'email' => 'Email', 'phone' => 'SĐT'],
        'formFields' => [
            'username'  => ['label' => 'Username',   'type' => 'text', 'required' => true],
            'code'      => ['label' => 'Mã nhân viên','type' => 'text', 'required' => false, 'hint' => 'Để trống để tự động tạo'],
            'fullname'  => ['label' => 'Họ và tên',   'type' => 'text', 'required' => true],
            'email'     => ['label' => 'Email',        'type' => 'email','required' => false],
            'phone'     => ['label' => 'SĐT',          'type' => 'text', 'required' => false],
            'password'  => ['label' => 'Mật khẩu',    'type' => 'password','required' => true],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'ops' => [
        'role'  => 'ops',
        'title' => 'Vận hành',
        'prefix' => 'OPS',
        'columns'    => ['username' => 'Username', 'fullname' => 'Họ và tên', 'email' => 'Email', 'phone' => 'SĐT'],
        'formFields' => [
            'username'  => ['label' => 'Username',   'type' => 'text', 'required' => true],
            'code'      => ['label' => 'Mã nhân viên','type' => 'text', 'required' => false, 'hint' => 'Để trống để tự động tạo'],
            'fullname'  => ['label' => 'Họ và tên',   'type' => 'text', 'required' => true],
            'email'     => ['label' => 'Email',        'type' => 'email','required' => false],
            'phone'     => ['label' => 'SĐT',          'type' => 'text', 'required' => false],
            'password'  => ['label' => 'Mật khẩu',    'type' => 'password','required' => true],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'shipper' => [
        'role'  => 'shipper',
        'title' => 'Shipper',
        'prefix' => 'SHIPPER',
        'columns'    => ['username' => 'Username', 'fullname' => 'Họ và tên', 'email' => 'Email', 'phone' => 'SĐT'],
        'formFields' => [
            'username'  => ['label' => 'Username',   'type' => 'text', 'required' => true],
            'code'      => ['label' => 'Mã nhân viên','type' => 'text', 'required' => false, 'hint' => 'Để trống để tự động tạo'],
            'fullname'  => ['label' => 'Họ và tên',   'type' => 'text', 'required' => true],
            'email'     => ['label' => 'Email',        'type' => 'email','required' => false],
            'phone'     => ['label' => 'SĐT',          'type' => 'text', 'required' => false],
            'password'  => ['label' => 'Mật khẩu',    'type' => 'password','required' => true],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],

    'manager' => [
        'role'  => 'manager',
        'title' => 'Quản lý',
        'prefix' => 'MGR',
        'columns'    => ['username' => 'Username', 'fullname' => 'Họ và tên', 'email' => 'Email', 'phone' => 'SĐT'],
        'formFields' => [
            'username'  => ['label' => 'Username',   'type' => 'text', 'required' => true],
            'code'      => ['label' => 'Mã nhân viên','type' => 'text', 'required' => false, 'hint' => 'Để trống để tự động tạo'],
            'fullname'  => ['label' => 'Họ và tên',   'type' => 'text', 'required' => true],
            'email'     => ['label' => 'Email',        'type' => 'email','required' => false],
            'phone'     => ['label' => 'SĐT',          'type' => 'text', 'required' => false],
            'password'  => ['label' => 'Mật khẩu',    'type' => 'password','required' => true],
        ],
        'canCreate' => true,
        'canEdit'   => true,
        'canDelete' => true,
    ],
];
