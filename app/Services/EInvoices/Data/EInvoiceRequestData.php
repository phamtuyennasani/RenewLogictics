<?php

namespace App\Services\EInvoices\Data;

/**
 * Dữ liệu đầu vào để tạo hóa đơn điện tử.
 *
 * Đây là DTO trung lập, không phụ thuộc provider cụ thể.
 * Mỗi provider sẽ chuyển sang payload riêng trong createInvoice().
 */
class EInvoiceRequestData
{
    public function __construct(
        /** Mã tham chiếu hệ thống (ví dụ mã đơn hàng/hóa đơn). */
        public readonly string $reference,

        /** Mã mẫu hóa đơn (template_code) cấp bởi provider. */
        public readonly string $templateCode,

        /** Ký hiệu hóa đơn (vd: C26TSE). */
        public readonly string $invoiceSeries,

        /** Ngày phát hành (Y-m-d H:i:s). */
        public readonly string $issuedDate,

        /** Tài khoản provider (provider_account_id). */
        public readonly string $providerAccountId,

        /** Thông tin người mua. */
        public readonly array $buyer,

        /** @var array<int, array<string, mixed>> Danh sách item. */
        public readonly array $items,

        /** Tổng tiền (VNĐ). */
        public readonly int $amount = 0,

        /** Tiền tệ. */
        public readonly string $currency = 'VND',

        /** Hình thức thanh toán (CK, TM...). */
        public readonly ?string $paymentMethod = null,

        /** Có lưu ở dạng nháp không. */
        public readonly bool $isDraft = false,

        /** Ghi chú. */
        public readonly ?string $notes = null,

        /** Metadata bổ sung tùy provider. */
        public readonly array $meta = [],
    ) {
    }
}
