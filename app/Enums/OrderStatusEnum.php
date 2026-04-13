<?php

namespace App\Enums;

/**
 * OrderStatusEnum — Trạng thái đơn hàng (FSM)
 *
 * Theo hệ thống cũ:
 * MOI_TAO → DA_XAC_NHAN → DA_NHAN_HANG → DUYET_XUAT_HANG → DANG_PHAT_HANG → DA_GIAO
 * Các trạng thái đặc biệt: HUY, RETURN_ORDER, CAUTION, CUSTOM_RELEASING, CAP_BEN
 */
enum OrderStatusEnum: string
{
    // ---- Trạng thái chính (FSM) ----
    case MOI_TAO          = 'moi_tao';
    case DA_XAC_NHAN      = 'da_xac_nhan';
    case DA_NHAN_HANG     = 'da_nhan_hang';
    case DUYET_XUAT_HANG  = 'duyet_xuat_hang';
    case DANG_PHAT_HANG   = 'dang_phaT_hang';
    case DA_GIAO          = 'da_giao';

    // ---- Trạng thái đặc biệt ----
    case HUY              = 'huy';
    case RETURN_ORDER     = 'return_order';
    case CAUTION         = 'caution';
    case CUSTOM_RELEASING = 'custom_releasing';
    case CAP_BEN          = 'cap_ben';

    /**
     * Label tiếng Việt
     */
    public function label(): string
    {
        return match ($this) {
            self::MOI_TAO          => 'Mới tạo',
            self::DA_XAC_NHAN      => 'Đã xác nhận',
            self::DA_NHAN_HANG     => 'Đã nhận hàng',
            self::DUYET_XUAT_HANG  => 'Duyệt xuất hàng',
            self::DANG_PHAT_HANG   => 'Đang phát hàng',
            self::DA_GIAO          => 'Đã giao',
            self::HUY              => 'Hủy',
            self::RETURN_ORDER     => 'Hoàn hàng',
            self::CAUTION         => 'Cảnh báo',
            self::CUSTOM_RELEASING => 'Hải quan thông quan',
            self::CAP_BEN          => 'Cấp bến',
        };
    }

    /**
     * Màu hiển thị (CSS class)
     */
    public function color(): string
    {
        return match ($this) {
            self::MOI_TAO          => 'bg-neutral-100 text-neutral-700',
            self::DA_XAC_NHAN      => 'bg-blue-100 text-blue-700',
            self::DA_NHAN_HANG     => 'bg-cyan-100 text-cyan-700',
            self::DUYET_XUAT_HANG  => 'bg-purple-100 text-purple-700',
            self::DANG_PHAT_HANG   => 'bg-amber-100 text-amber-700',
            self::DA_GIAO          => 'bg-emerald-100 text-emerald-700',
            self::HUY              => 'bg-red-100 text-red-700',
            self::RETURN_ORDER     => 'bg-orange-100 text-orange-700',
            self::CAUTION          => 'bg-yellow-100 text-yellow-700',
            self::CUSTOM_RELEASING => 'bg-teal-100 text-teal-700',
            self::CAP_BEN          => 'bg-indigo-100 text-indigo-700',
        };
    }

    /**
     * Icon SVG path
     */
    public function icon(): string
    {
        return match ($this) {
            self::MOI_TAO          => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            self::DA_XAC_NHAN      => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            self::DA_NHAN_HANG     => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4zM5 8v10a2 2 0 002 2h10a2 2 0 002-2V8',
            self::DUYET_XUAT_HANG  => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
            self::DANG_PHAT_HANG   => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1',
            self::DA_GIAO          => 'M5 13l4 4L19 7',
            self::HUY              => 'M6 18L18 6M6 6l12 12',
            self::RETURN_ORDER     => 'M3 10h18M9 3H5a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2v-4',
            self::CAUTION          => 'M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            self::CUSTOM_RELEASING => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            self::CAP_BEN          => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16',
        };
    }

    /**
     * Thứ tự hiển thị
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::MOI_TAO          => 1,
            self::DA_XAC_NHAN      => 2,
            self::DA_NHAN_HANG     => 3,
            self::DUYET_XUAT_HANG  => 4,
            self::DANG_PHAT_HANG   => 5,
            self::DA_GIAO          => 6,
            self::CAUTION         => 7,
            self::CUSTOM_RELEASING => 8,
            self::CAP_BEN          => 9,
            self::RETURN_ORDER     => 10,
            self::HUY              => 99,
        };
    }

    /**
     * Có phải trạng thái kết thúc không
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::DA_GIAO,
            self::HUY,
            self::RETURN_ORDER,
        ]);
    }

    /**
     * Có phải trạng thái đặc biệt không
     */
    public function isSpecial(): bool
    {
        return in_array($this, [
            self::HUY,
            self::RETURN_ORDER,
            self::CAUTION,
            self::CUSTOM_RELEASING,
            self::CAP_BEN,
        ]);
    }

    /**
     * Trạng thái tiếp theo trong FSM chính
     */
    public function nextStatus(): ?self
    {
        return match ($this) {
            self::MOI_TAO         => self::DA_XAC_NHAN,
            self::DA_XAC_NHAN     => self::DA_NHAN_HANG,
            self::DA_NHAN_HANG    => self::DUYET_XUAT_HANG,
            self::DUYET_XUAT_HANG => self::DANG_PHAT_HANG,
            self::DANG_PHAT_HANG  => self::DA_GIAO,
            default => null,
        };
    }

    /**
     * Tất cả values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
