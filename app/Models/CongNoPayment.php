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
        'id_order',
        'id_user',
        'amount',
        'paid_at',
        'due_at',
        'submitted_at',
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
        'qr_url',
        'qr_generated_at',
        'qr_expires_at',
        'qr_payment_code',
        'sepay_transaction_id',
        'cancelled_at',
        'id_cancelled_by',
        'cancel_reason',
        'payment_rejection_reason',
        'payment_rejected_at',
        'payment_rejected_by',
        'order_snapshot',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'due_at' => 'datetime',
        'submitted_at' => 'datetime',
        'ngay_duyet' => 'datetime',
        'qr_generated_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'payment_rejected_at' => 'datetime',
        'provider_payload' => 'array',
        'order_snapshot' => 'array',
        'status' => InvoicePaymentStatusEnum::class,
        'loai_hoa_don' => InvoiceTypeEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CongNoPayment $invoice): void {
            $invoice->loai_hoa_don ??= InvoiceTypeEnum::THU->value;
            $invoice->status ??= InvoicePaymentStatusEnum::CHO_DUYET->value;

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
        return $this->belongsTo(CongNo::class, 'id_congno')->withTrashed();
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
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

    public function paymentRejector()
    {
        return $this->belongsTo(User::class, 'payment_rejected_by');
    }

    public function logs()
    {
        return $this->hasMany(InvoicePaymentLog::class, 'congno_payment_id');
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

    public function hasPaymentApprovalPower(?User $user): bool
    {
        return $user instanceof User && $user->hasAnyRole(['admin', 'ketoan']);
    }

    public function canApprove(?User $user): bool
    {
        return $this->hasStaffPower($user)
            && $this->status === InvoicePaymentStatusEnum::CHO_DUYET;
    }

    public function canCancel(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->status === InvoicePaymentStatusEnum::DA_THANH_TOAN
            || $this->status === InvoicePaymentStatusEnum::HUY) {
            return false;
        }

        return $this->isCreator($user) || $this->hasStaffPower($user);
    }

    public function canPay(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $allowedFrom = [
            InvoicePaymentStatusEnum::DA_DUYET,
            InvoicePaymentStatusEnum::KHONG_CHAP_NHAN,
        ];

        $expiredAndPending = $this->status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT
            && $this->isPaymentExpired();

        return (in_array($this->status, $allowedFrom, true) || $expiredAndPending)
            && ($this->isCreator($user) || $this->hasStaffPower($user) || $user->hasRole('sale'));
    }

    public function canApprovePayment(?User $user): bool
    {
        return $this->hasPaymentApprovalPower($user)
            && $this->status === InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT;
    }

    public function canRejectPayment(?User $user): bool
    {
        return $this->hasPaymentApprovalPower($user)
            && $this->status === InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT;
    }

    public function canConfirmCashPayment(?User $user): bool
    {
        return $this->hasPaymentApprovalPower($user)
            && $this->status === InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT;
    }

    public function canManageQr(?User $user): bool
    {
        return $this->status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT
            && ($this->isCreator($user) || $this->hasStaffPower($user));
    }

    public function canResetPaymentChannel(?User $user): bool
    {
        return $user instanceof User
            && $user->hasRole('admin')
            && in_array($this->status, [
                InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT,
                InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT,
            ], true);
    }

    public function canAdminMarkPaid(?User $user): bool
    {
        return $user instanceof User
            && $user->hasRole('admin')
            && $this->status === InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT;
    }

    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('id_order', $orderId);
    }

    public function hasDirectOrder(): bool
    {
        return $this->id_order !== null;
    }

    public function hasRejectionMetadata(): bool
    {
        return $this->payment_rejection_reason !== null
            || $this->payment_rejected_at !== null
            || $this->payment_rejected_by !== null;
    }

    public function clearRejectionMetadata(): void
    {
        $this->forceFill([
            'payment_rejection_reason' => null,
            'payment_rejected_at' => null,
            'payment_rejected_by' => null,
        ])->save();
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

    public function isPaymentExpired(): bool
    {
        return $this->qr_expires_at !== null && $this->qr_expires_at->isPast();
    }

    public function statusLabel(): string
    {
        return $this->status?->label() ?? '—';
    }
}
