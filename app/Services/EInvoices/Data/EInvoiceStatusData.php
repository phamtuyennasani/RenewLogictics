<?php

namespace App\Services\EInvoices\Data;

/**
 * Trạng thái hóa đơn trả về từ provider.
 */
class EInvoiceStatusData
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        /** Provider key. */
        public readonly string $provider,

        /** Tracking code đã tra. */
        public readonly string $trackingCode,

        /** Trạng thái chuẩn hóa: pending|success|failed. */
        public readonly string $status,

        /** Số hóa đơn (nếu đã issue). */
        public readonly ?string $invoiceNumber = null,

        /** Reference code do provider gán. */
        public readonly ?string $providerReferenceCode = null,

        /** Thông điệp lỗi / thành công. */
        public readonly ?string $message = null,

        /** Dữ liệu thô. */
        public readonly array $raw = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
