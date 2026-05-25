<?php

namespace App\Models;

use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Services\Payments\InvoiceCodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongNoDaiLyPayment extends Model
{
    use HasFactory;

    protected $table = 'congno_daily_payments';

    protected $fillable = [
        'id_congno_daily',
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
        'cancelled_at',
        'id_cancelled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'ngay_duyet' => 'datetime',
        'cancelled_at' => 'datetime',
        'status' => InvoicePaymentStatusEnum::class,
        'loai_hoa_don' => InvoiceTypeEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CongNoDaiLyPayment $invoice): void {
            $invoice->loai_hoa_don ??= InvoiceTypeEnum::CHI->value;
            $invoice->status ??= InvoicePaymentStatusEnum::MOI_TAO->value;

            if (empty($invoice->ma_hoa_don)) {
                $generator = app(InvoiceCodeGenerator::class);
                $invoice->ma_hoa_don = $generator->generate(InvoiceTypeEnum::CHI);
            }
        });
    }

    public function congNoDaiLy()
    {
        return $this->belongsTo(CongNoDaiLy::class, 'id_congno_daily');
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

    public function canMarkPaid(?User $user): bool
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

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }
}
