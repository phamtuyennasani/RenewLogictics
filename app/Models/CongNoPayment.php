<?php

namespace App\Models;

use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Services\Payments\InvoiceCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CongNoPayment extends Model
{
    use HasFactory;

    public const QR_THROTTLE_MINUTES = 15;

    protected $table = 'congno_payments';

    protected $fillable = [
        'id_congno',
        'id_user',
        'amount',
        'paid_at',
        'method',
        'reference',
        'photo',
        'note',
        'loai_hoa_don',
        'ma_hoa_don',
        'status',
        'id_ketoan',
        'ngay_duyet',
        'qr_url',
        'qr_generated_at',
        'qr_expires_at',
        'qr_payment_code',
        'sepay_transaction_id',
        'cancelled_at',
        'id_cancelled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'ngay_duyet' => 'datetime',
        'qr_generated_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'status' => InvoicePaymentStatusEnum::class,
        'loai_hoa_don' => InvoiceTypeEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CongNoPayment $invoice): void {
            $invoice->loai_hoa_don ??= InvoiceTypeEnum::THU->value;
            $invoice->status ??= InvoicePaymentStatusEnum::MOI_TAO->value;

            if (empty($invoice->ma_hoa_don)) {
                $generator = app(InvoiceCodeGenerator::class);
                $invoice->ma_hoa_don = $generator->generate(InvoiceTypeEnum::THU);
            }
            // qr_payment_code is generated only when online payment is requested (submitOnlinePayment), not at creation time.
            // This prevents premature webhook matches before the invoice enters DA_GUI_YEU_CAU_TT status.
        });
    }

    public function congNo()
    {
        return $this->belongsTo(CongNo::class, 'id_congno');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'id_cancelled_by');
    }

    public function isCreator(?User $user): bool
    {
        return $user && (int) $this->id_user === (int) $user->id;
    }

    public function hasStaffPower(?User $user): bool
    {
        return $user instanceof User && $user->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    public function canApprove(?User $user): bool
    {
        return $this->hasStaffPower($user)
            && $this->status === InvoicePaymentStatusEnum::MOI_TAO;
    }

    public function canCancel(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $status = $this->status;
        if ($status === InvoicePaymentStatusEnum::DA_THANH_TOAN
            || $status === InvoicePaymentStatusEnum::HUY) {
            return false;
        }

        return $this->isCreator($user) || $this->hasStaffPower($user);
    }

    public function canPay(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->status === InvoicePaymentStatusEnum::DA_DUYET
            && ($this->isCreator($user) || $this->hasStaffPower($user));
    }

    public function canConfirmCashPayment(?User $user): bool
    {
        return $this->hasStaffPower($user)
            && $this->status === InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT;
    }

    public function canManageQr(?User $user): bool
    {
        return $this->status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT
            && ($this->isCreator($user) || $this->hasStaffPower($user));
    }

    public function canRegenerateQr(): bool
    {
        if ($this->status !== InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT) {
            return false;
        }

        $next = $this->nextQrAvailableAt();

        return $next === null || $next->isPast();
    }

    public function nextQrAvailableAt(): ?Carbon
    {
        if (! $this->qr_generated_at) {
            return null;
        }

        return $this->qr_generated_at->copy()->addMinutes(self::QR_THROTTLE_MINUTES);
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }
}
