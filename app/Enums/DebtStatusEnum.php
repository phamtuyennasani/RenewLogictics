<?php

namespace App\Enums;

enum DebtStatusEnum: string
{
    case MOI_TAO = 'moi_tao';
    case DA_CHOT_CUOC = 'da_chot_cuoc';
    case DA_THANH_TOAN_MOT_PHAN = 'da_thanh_toan_mot_phan';
    case DA_THANH_TOAN = 'da_thanh_toan';
    case QUA_HAN = 'qua_han';

    public function label(): string
    {
        return match ($this) {
            self::MOI_TAO => 'Mới tạo',
            self::DA_CHOT_CUOC => 'Đã chốt cước',
            self::DA_THANH_TOAN_MOT_PHAN => 'Đã thanh toán một phần',
            self::DA_THANH_TOAN => 'Đã thanh toán',
            self::QUA_HAN => 'Quá hạn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::MOI_TAO => 'bg-neutral-100 text-neutral-700',
            self::DA_CHOT_CUOC => 'bg-blue-100 text-blue-700',
            self::DA_THANH_TOAN_MOT_PHAN => 'bg-amber-100 text-amber-700',
            self::DA_THANH_TOAN => 'bg-emerald-100 text-emerald-700',
            self::QUA_HAN => 'bg-red-100 text-red-700',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::MOI_TAO => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            self::DA_CHOT_CUOC => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            self::DA_THANH_TOAN_MOT_PHAN => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10v2m0 12v2m8-8h-2M6 12H4',
            self::DA_THANH_TOAN => 'M5 13l4 4L19 7',
            self::QUA_HAN => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::MOI_TAO => 1,
            self::DA_CHOT_CUOC => 2,
            self::DA_THANH_TOAN_MOT_PHAN => 3,
            self::DA_THANH_TOAN => 4,
            self::QUA_HAN => 5,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::DA_THANH_TOAN;
    }

    public function nextStatus(): ?self
    {
        return match ($this) {
            self::MOI_TAO => self::DA_CHOT_CUOC,
            self::DA_CHOT_CUOC => self::DA_THANH_TOAN_MOT_PHAN,
            self::DA_THANH_TOAN_MOT_PHAN => self::DA_THANH_TOAN,
            default => null,
        };
    }

    public function allowedTransitions(bool $admin = false): array
    {
        if ($admin) {
            return array_values(array_filter(
                self::cases(),
                fn (self $status) => $status !== $this
            ));
        }

        return match ($this) {
            self::MOI_TAO => [
                self::DA_CHOT_CUOC,
                self::QUA_HAN,
            ],
            self::DA_CHOT_CUOC => [
                self::DA_THANH_TOAN_MOT_PHAN,
                self::DA_THANH_TOAN,
                self::QUA_HAN,
            ],
            self::DA_THANH_TOAN_MOT_PHAN => [
                self::DA_THANH_TOAN,
                self::QUA_HAN,
            ],
            self::QUA_HAN => [
                self::DA_THANH_TOAN_MOT_PHAN,
                self::DA_THANH_TOAN,
            ],
            self::DA_THANH_TOAN => [],
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
