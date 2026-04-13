<?php

namespace App\Enums;

/**
 * PickupStatusEnum — Trạng thái pickup (FSM)
 *
 * Theo hệ thống cũ:
 * MOI_TAO_PICKUP → PICKUP_CHO_NHAN → PICKUP_DANG_LAY_HANG → PICKUP_DA_LAY_HANG → DA_CHOT_PICKUP
 */
enum PickupStatusEnum: string
{
    case MOI_TAO_PICKUP    = 'moi_tao_pickup';
    case PICKUP_CHO_NHAN   = 'pickup_cho_nhan';
    case PICKUP_DANG_LAY   = 'pickup_dang_lay';
    case PICKUP_DA_LAY     = 'pickup_da_lay';
    case DA_CHOT_PICKUP    = 'da_chot_pickup';

    public function label(): string
    {
        return match ($this) {
            self::MOI_TAO_PICKUP  => 'Mới tạo',
            self::PICKUP_CHO_NHAN => 'Chờ nhận',
            self::PICKUP_DANG_LAY => 'Đang lấy hàng',
            self::PICKUP_DA_LAY   => 'Đã lấy hàng',
            self::DA_CHOT_PICKUP  => 'Đã chốt pickup',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MOI_TAO_PICKUP  => 'bg-neutral-100 text-neutral-700',
            self::PICKUP_CHO_NHAN => 'bg-blue-100 text-blue-700',
            self::PICKUP_DANG_LAY => 'bg-amber-100 text-amber-700',
            self::PICKUP_DA_LAY   => 'bg-emerald-100 text-emerald-700',
            self::DA_CHOT_PICKUP  => 'bg-purple-100 text-purple-700',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::DA_CHOT_PICKUP;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
