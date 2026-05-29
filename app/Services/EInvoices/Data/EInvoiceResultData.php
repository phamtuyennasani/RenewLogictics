<?php

namespace App\Services\EInvoices\Data;

/**
 * Kết quả trả về sau khi tạo / phát hành hóa đơn.
 */
class EInvoiceResultData
{
    public function __construct(
        /** Provider key (sepay, vnpt...). */
        public readonly string $provider,

        /** Mã tham chiếu hệ thống. */
        public readonly string $reference,

        /** Mã tracking để check trạng thái async. */
        public readonly ?string $trackingCode = null,

        /** Reference code do provider trả về (có thể khác $reference). */
        public readonly ?string $providerReferenceCode = null,

        /** URL kiểm tra trạng thái (nếu provider trả về). */
        public readonly ?string $trackingUrl = null,

        /** URL hóa đơn đã issue (PDF link). */
        public readonly ?string $invoiceUrl = null,

        /** Số hóa đơn (sau khi issue thành công). */
        public readonly ?string $invoiceNumber = null,

        /** Thông điệp. */
        public readonly ?string $message = null,

        /** Dữ liệu thô từ API. */
        public readonly array $raw = [],
    ) {
    }
}
