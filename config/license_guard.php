<?php
return [
    'public_key' => 'gCMOn1JvA9pv82lDih7CYYj1dusLfHwF3qfXtIjaQtw=',
    'product' => 'hethong-laravel',
    'license_key' => env('LICENSE_KEY', ''),
    'no_check' => env('LICENSE_KEY_NOCHECK', ''),
    'brand_name' => 'Beehive Technology',
    // Đường dẫn tương đối — KHÔNG gọi asset() ở đây vì config được nạp ở
    // giai đoạn bootstrap sớm, trước khi 'url'/'request' sẵn sàng (gây lỗi
    // UrlGenerator khi config không được cache). Gọi asset() lúc render view.
    'brand_logo' => 'images/logo.svg',
];

