<?php

namespace App\Enums;

enum InvoiceTypeEnum: string
{
    case THU = 'thu';
    case CHI = 'chi';

    public function label(): string
    {
        return match ($this) {
            self::THU => 'Hóa đơn thu',
            self::CHI => 'Hóa đơn chi',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::THU => 'Thu',
            self::CHI => 'Chi',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::THU => 'bg-emerald-100 text-emerald-700',
            self::CHI => 'bg-rose-100 text-rose-700',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::THU => 'M12 6v6m0 0v6m0-6h6m-6 0H6',
            self::CHI => 'M20 12H4',
        };
    }

    public function codePrefix(): string
    {
        return match ($this) {
            self::THU => 'HD-TH',
            self::CHI => 'HD-CH',
        };
    }

    public function isIncome(): bool
    {
        return $this === self::THU;
    }

    public function isExpense(): bool
    {
        return $this === self::CHI;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
