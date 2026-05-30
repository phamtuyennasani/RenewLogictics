<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice & Debt Code Prefixes
    |--------------------------------------------------------------------------
    |
    | Prefix dùng khi sinh mã hóa đơn (ma_hoa_don) và mã công nợ (sohoadon).
    | Đổi qua .env để chuẩn hóa theo tên doanh nghiệp.
    |
    | - thu  : Hóa đơn thu     (mặc định HDTH)  → vd HDTH202605292061
    | - chi  : Hóa đơn chi     (mặc định HDCH)  → vd HDCH202605294353
    | - debt : Mã công nợ      (mặc định DEB)   → vd DEB29053005AB
    |
    */
    'code_prefix' => [
        'thu' => env('INVOICE_CODE_PREFIX_THU', 'HDTH'),
        'chi' => env('INVOICE_CODE_PREFIX_CHI', 'HDCH'),
        'debt' => env('DEBT_CODE_PREFIX', 'DEB'),
    ],
];
