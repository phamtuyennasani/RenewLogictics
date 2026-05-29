<?php

namespace App\Services\EInvoices\Contracts;

use App\Services\EInvoices\Data\EInvoiceRequestData;
use App\Services\EInvoices\Data\EInvoiceResultData;
use App\Services\EInvoices\Data\EInvoiceStatusData;

/**
 * Contract chuẩn cho mọi cổng hóa đơn điện tử.
 *
 * Mỗi provider (SePay, VNPT, Viettel, MISA...) phải implement interface này
 * để EInvoiceProviderManager có thể resolve và gọi thống nhất.
 *
 * Lưu ý: các method dùng tên động từ ngắn (create/issue/status/download)
 * để không xung đột với raw API method nội bộ của từng provider
 * (vd: SePay có createInvoice(array): array là lời gọi HTTP cấp thấp).
 */
interface EInvoiceProvider
{
    /**
     * Key định danh provider (vd: "sepay", "vnpt", "viettel").
     */
    public function key(): string;

    /**
     * Tạo hóa đơn điện tử (có thể ở trạng thái draft hoặc issued).
     *
     * Provider chuyển EInvoiceRequestData sang payload nội bộ
     * và gọi API tạo hóa đơn.
     */
    public function create(EInvoiceRequestData $data): EInvoiceResultData;

    /**
     * Phát hành hóa đơn (issue) sau khi đã tạo draft.
     */
    public function issue(string $referenceCode): EInvoiceResultData;

    /**
     * Kiểm tra trạng thái hóa đơn theo tracking code hoặc reference code.
     */
    public function status(string $trackingOrReferenceCode): EInvoiceStatusData;

    /**
     * Tải file hóa đơn (PDF/XML) đã issue, trả về nội dung đã decode (binary).
     *
     * @param  string  $type  pdf|xml
     */
    public function download(string $trackingCode, string $type = 'pdf'): string;

    /**
     * Khai báo các trường cấu hình của cổng để trang Cấu hình hệ thống
     * render form động (thêm cổng mới không phải sửa giao diện).
     *
     * Mỗi phần tử là 1 trường với cấu trúc:
     *   - key         (string)  Khóa lưu trong Setting.options (giữ nguyên khóa đang dùng).
     *   - label       (string)  Nhãn hiển thị.
     *   - type        (string)  text | password | select.
     *   - required    (bool)    Bắt buộc nhập (chỉ validate khi mở khóa nếu sensitive=true).
     *   - sensitive   (bool)    true => ẩn sau lớp xác thực Admin (re-auth) như API key.
     *   - placeholder (string)  Gợi ý nhập (tùy chọn).
     *   - options     (array)   [value => label] cho type=select (tùy chọn).
     *   - mirrorKeys  (array)   Các khóa phụ cần ghi cùng giá trị (tương thích ngược, tùy chọn).
     *
     * @return array<int, array{key:string,label:string,type:string,required?:bool,sensitive?:bool,placeholder?:string,options?:array<string,string>,mirrorKeys?:array<int,string>}>
     */
    public static function configSchema(): array;
}
