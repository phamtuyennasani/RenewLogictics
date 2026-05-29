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
}
