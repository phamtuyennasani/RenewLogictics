<?php

/**
 * License Guard — cấu hình riêng cho TỪNG dự án.
 *
 * QUAN TRỌNG:
 * - Public key đặt HARDCODE ở đây (KHÔNG dùng env()), vì .env do khách kiểm soát.
 *   Nếu để trong .env, khách có thể thay key của họ và tự ký license để bypass.
 * - Mỗi dự án / mỗi khách dùng MỘT cặp khóa riêng → dán public key tương ứng vào đây.
 * - Khi bàn giao, encode file này bằng ionCube/SourceGuardian để khách không sửa được.
 *
 * Tạo khóa:  php artisan license:keygen
 * Sau đó dán PUBLIC KEY vào hằng dưới đây, giữ PRIVATE KEY offline.
 */

return [
    // Public key Ed25519 (base64) — chỉ dùng để verify, an toàn để lộ.
    'public_key' => 'uPSeY95ml2Hmnc6DUzR9FZhhmrC/y0VaNmgOcrfiA0U=',

    // Mã sản phẩm (tùy chọn) — chỉ để hiển thị/đối chiếu.
    'product' => 'hethong-laravel',
];


