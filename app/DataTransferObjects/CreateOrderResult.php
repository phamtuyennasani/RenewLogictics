<?php

namespace App\DataTransferObjects;

use App\Models\Order;

/**
 * Kết quả tạo đơn: đơn luôn được tạo (phần lõi), các bước bổ sung
 * (kiện hàng, khai báo hàng hóa, ảnh, liên hệ) có thể fail mềm —
 * tên bước fail nằm trong $warnings để UI báo user bổ sung sau.
 */
class CreateOrderResult
{
    /**
     * @param  array<int, string>  $warnings  Nhãn các bước chưa lưu được
     */
    public function __construct(
        public Order $order,
        public array $warnings = [],
    ) {}

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }
}
