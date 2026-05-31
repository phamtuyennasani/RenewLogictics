<?php

namespace App\Enums;

/**
 * PickupStatusEnum — Trạng thái pickup (FSM)
 *
 * Luồng vận hành:
 * MOI_TAO_PICKUP → DA_XAC_NHAN → PICKUP_DANG_LAY → PICKUP_DA_LAY
 * Ba trạng thái đầu có thể chuyển sang DA_HUY.
 */
enum PickupStatusEnum: string
{
    case MOI_TAO_PICKUP    = 'moi_tao_pickup';
    case DA_XAC_NHAN       = 'da_xac_nhan';
    case PICKUP_DANG_LAY   = 'pickup_dang_lay';
    case PICKUP_DA_LAY     = 'pickup_da_lay';
    case DA_HUY            = 'da_huy';

    public function label(): string
    {
        return match ($this) {
            self::MOI_TAO_PICKUP  => 'Mới tạo',
            self::DA_XAC_NHAN     => 'Đã xác nhận',
            self::PICKUP_DANG_LAY => 'Đang lấy hàng',
            self::PICKUP_DA_LAY   => 'Đã lấy hàng',
            self::DA_HUY          => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MOI_TAO_PICKUP  => 'bg-neutral-100 text-neutral-700',
            self::DA_XAC_NHAN     => 'bg-blue-100 text-blue-700',
            self::PICKUP_DANG_LAY => 'bg-amber-100 text-amber-700',
            self::PICKUP_DA_LAY   => 'bg-emerald-100 text-emerald-700',
            self::DA_HUY          => 'bg-red-100 text-red-700',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::PICKUP_DA_LAY, self::DA_HUY], true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::MOI_TAO_PICKUP => [self::DA_XAC_NHAN, self::DA_HUY],
            self::DA_XAC_NHAN => [self::PICKUP_DANG_LAY, self::DA_HUY],
            self::PICKUP_DANG_LAY => [self::PICKUP_DA_LAY, self::DA_HUY],
            self::PICKUP_DA_LAY, self::DA_HUY => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
