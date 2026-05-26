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
        'due_at',
        'method',
        'payment_provider',
        'payment_channel',
        'payment_reference',
        'payment_url',
        'provider_intent_id',
        'provider_transaction_id',
        'provider_payload',
        'reference',
        'photo',
        'note',
        'loai_hoa_don',
        'ma_hoa_don',
        'status',
        'id_ketoan',
        'approved_by',
        'payment_confirmed_by',
        'ngay_duyet',
        'cancelled_at',
        'id_cancelled_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'due_at' => 'datetime',
        'ngay_duyet' => 'datetime',
        'cancelled_at' => 'datetime',
        'provider_payload' => 'array',
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
        return $this->belongsTo(CongNoDaiLy::class, 'id_congno_daily')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function paymentConfirmer()
    {
        return $this->belongsTo(User::class, 'payment_confirmed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'id_cancelled_by');
    }

    public function logs()
    {
        return $this->hasMany(InvoicePaymentLog::class, 'congno_daily_payment_id');
    }

    public function writeStatusLog(
        string $action,
        InvoicePaymentStatusEnum|string|null $fromStatus,
        InvoicePaymentStatusEnum|string|null $toStatus,
        ?int $actorId = null,
        ?string $note = null,
        array $metadata = []
    ): void {
        $this->logs()->create([
            'action' => $action,
            'from_status' => $fromStatus instanceof InvoicePaymentStatusEnum ? $fromStatus->value : $fromStatus,
            'to_status' => $toStatus instanceof InvoicePaymentStatusEnum ? $toStatus->value : $toStatus,
            'actor_id' => $actorId,
            'note' => $note,
            'metadata' => $metadata ?: null,
        ]);
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
