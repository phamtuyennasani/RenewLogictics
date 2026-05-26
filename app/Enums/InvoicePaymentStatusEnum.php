<?php

namespace App\Enums;

enum InvoicePaymentStatusEnum: string
{
    case CHO_DUYET = 'cho_duyet';
    case DA_DUYET = 'da_duyet';
    case DA_GUI_HOA_DON_TT = 'da_gui_hoa_don_tt';
    case KHONG_CHAP_NHAN = 'khong_chap_nhan';
    case DA_GUI_YEU_CAU_TT = 'da_gui_yeu_cau_tt';
    case DA_THANH_TOAN = 'da_thanh_toan';
    case HUY = 'huy';

    public function label(): string
    {
        return match ($this) {
            self::CHO_DUYET => 'Chờ duyệt',
            self::DA_DUYET => 'Đã duyệt',
            self::DA_GUI_HOA_DON_TT => 'Chờ duyệt thanh toán',
            self::KHONG_CHAP_NHAN => 'Không chấp nhận',
            self::DA_GUI_YEU_CAU_TT => 'Đã gửi yêu cầu thanh toán',
            self::DA_THANH_TOAN => 'Đã thanh toán',
            self::HUY => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CHO_DUYET => 'bg-neutral-100 text-neutral-700',
            self::DA_DUYET => 'bg-blue-100 text-blue-700',
            self::DA_GUI_HOA_DON_TT => 'bg-amber-100 text-amber-700',
            self::KHONG_CHAP_NHAN => 'bg-orange-100 text-orange-700',
            self::DA_GUI_YEU_CAU_TT => 'bg-indigo-100 text-indigo-700',
            self::DA_THANH_TOAN => 'bg-emerald-100 text-emerald-700',
            self::HUY => 'bg-red-100 text-red-700',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CHO_DUYET => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            self::DA_DUYET => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
            self::DA_GUI_HOA_DON_TT => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            self::KHONG_CHAP_NHAN => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
            self::DA_GUI_YEU_CAU_TT => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10v2m0 12v2m8-8h-2M6 12H4',
            self::DA_THANH_TOAN => 'M5 13l4 4L19 7',
            self::HUY => 'M6 18L18 6M6 6l12 12',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::CHO_DUYET => 1,
            self::DA_DUYET => 2,
            self::DA_GUI_HOA_DON_TT => 3,
            self::KHONG_CHAP_NHAN => 3,
            self::DA_GUI_YEU_CAU_TT => 4,
            self::DA_THANH_TOAN => 5,
            self::HUY => 6,
        };
    }

    public function isFinal(): bool
    {
        return $this === self::DA_THANH_TOAN || $this === self::HUY;
    }

    public function isPaid(): bool
    {
        return $this === self::DA_THANH_TOAN;
    }

    public function isCancelled(): bool
    {
        return $this === self::HUY;
    }

    public function isOpen(): bool
    {
        return ! $this->isFinal();
    }

    public function isPendingPayment(): bool
    {
        return in_array($this, self::pendingCases(), true);
    }

    /**
     * @return self[]
     */
    public static function pendingCases(): array
    {
        return [
            self::CHO_DUYET,
            self::DA_DUYET,
            self::DA_GUI_HOA_DON_TT,
            self::KHONG_CHAP_NHAN,
            self::DA_GUI_YEU_CAU_TT,
        ];
    }

    public static function pendingValues(): array
    {
        return array_map(fn (self $status) => $status->value, self::pendingCases());
    }

    /**
     * @return self[]
     */
    public static function allowedForIncome(): array
    {
        return self::cases();
    }

    /**
     * @return self[]
     */
    public static function allowedForExpense(): array
    {
        return [
            self::CHO_DUYET,
            self::DA_THANH_TOAN,
            self::HUY,
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Legacy value map for migration.
     * @return array<string, string>
     */
    public static function legacyValueMap(): array
    {
        return [
            'moi_tao' => self::CHO_DUYET->value,
        ];
    }
}
