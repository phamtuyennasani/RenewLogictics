<?php

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\PaymentIntentData;
use App\Services\Payments\Data\PaymentRequestData;
use App\Services\Payments\Data\PaymentWebhookData;
use Illuminate\Http\Request;

interface PaymentProvider
{
    public function key(): string;

    public function createPayment(PaymentRequestData $data): PaymentIntentData;

    public function parseWebhook(Request $request): PaymentWebhookData;

    /**
     * Khai báo các trường cấu hình của cổng để trang Cấu hình hệ thống
     * render form động (thêm cổng mới không phải sửa giao diện).
     *
     * Mỗi phần tử là 1 trường với cấu trúc:
     *   - key         (string)  Khóa lưu trong Setting.options (giữ nguyên khóa đang dùng).
     *   - label       (string)  Nhãn hiển thị.
     *   - type        (string)  text | password | select.
     *   - required    (bool)    Bắt buộc nhập (chỉ validate khi sensitive=false).
     *   - sensitive   (bool)    true => ẩn sau lớp xác thực Admin (re-auth) như API key.
     *   - placeholder (string)  Gợi ý nhập (tùy chọn).
     *   - options     (array)   [value => label] cho type=select (tùy chọn).
     *   - mirrorKeys  (array)   Các khóa phụ cần ghi cùng giá trị (tương thích ngược, tùy chọn).
     *
     * @return array<int, array{key:string,label:string,type:string,required?:bool,sensitive?:bool,placeholder?:string,options?:array<string,string>,mirrorKeys?:array<int,string>}>
     */
    public static function configSchema(): array;
}
