<?php

namespace App\Enums;

enum ShipmentLoadStatusEnum: string
{
    case MOI_TAO = 'moi_tao';
    case DA_DUYET_XUAT = 'da_duyet_xuat';

    public function label(): string
    {
        return match ($this) {
            self::MOI_TAO => 'Mới tạo',
            self::DA_DUYET_XUAT => 'Đã duyệt xuất',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MOI_TAO => 'bg-neutral-100 text-neutral-700',
            self::DA_DUYET_XUAT => 'bg-purple-100 text-purple-700',
        };
    }

    public function canEditOrders(): bool
    {
        return $this === self::MOI_TAO;
    }
}

