<?php

namespace App\Models;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CongNoDaiLy extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'congno_daily';

    protected $fillable = [
        'uuid',
        'sohoadon',
        'sohoadon_thamchieu',
        'id_daily',
        'id_sale',
        'id_user',
        'id_ketoan',
        'id_success',
        'tungay',
        'denngay',
        'ngaytaohoadon',
        'ngaychothoadon',
        'hanthanhtoan',
        'ngaythanhtoan',
        'songaythanhtoan',
        'status',
        'photo',
        'ghichu',
        'total_orders',
        'total_weight',
        'total_cuocvon',
        'total_cuocgoc',
        'total_cuocban',
        'total_vat',
        'total_ppxd',
        'total_phuphi',
        'total_hoahong',
        'paid_amount',
    ];

    protected $casts = [
        'tungay' => 'datetime',
        'denngay' => 'datetime',
        'ngaytaohoadon' => 'datetime',
        'ngaychothoadon' => 'datetime',
        'hanthanhtoan' => 'datetime',
        'ngaythanhtoan' => 'datetime',
        'status' => DebtStatusEnum::class,
        'total_weight' => 'decimal:3',
        'total_cuocvon' => 'decimal:2',
        'total_cuocgoc' => 'decimal:2',
        'total_cuocban' => 'decimal:2',
        'total_vat' => 'decimal:2',
        'total_ppxd' => 'decimal:2',
        'total_phuphi' => 'decimal:2',
        'total_hoahong' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'remaining_amount',
    ];

    protected static function booted(): void
    {
        static::creating(function (CongNoDaiLy $debt): void {
            $debt->uuid ??= (string) Str::uuid();
            $debt->status ??= DebtStatusEnum::MOI_TAO->value;
            $debt->ngaytaohoadon ??= now();
        });
    }

    public function daily()
    {
        return $this->belongsTo(News::class, 'id_daily');
    }

    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function user()
    {
        return $this->creator();
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function successUser()
    {
        return $this->belongsTo(User::class, 'id_success');
    }

    public function success()
    {
        return $this->successUser();
    }

    public function details()
    {
        return $this->hasMany(CongNoDaiLyDetail::class, 'id_congno_daily');
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'congno_daily_detail', 'id_congno_daily', 'id_order')
            ->withPivot([
                'weight',
                'cuocvon',
                'cuocgoc',
                'cuocban',
                'vat',
                'ppxd',
                'phuphi',
                'hoahong',
                'snapshot',
            ])
            ->withTimestamps();
    }

    public function payments()
    {
        return $this->hasMany(CongNoDaiLyPayment::class, 'id_congno_daily');
    }

    protected function remainingAmount(): Attribute
    {
        return Attribute::get(fn () => max(0, (float) $this->total_cuocvon - (float) $this->paid_amount));
    }

    public function syncTotalsFromDetails(): void
    {
        $details = $this->details()->get();

        $this->forceFill([
            'total_orders' => $details->count(),
            'total_weight' => $details->sum('weight'),
            'total_cuocvon' => $details->sum('cuocvon'),
            'total_cuocgoc' => $details->sum('cuocgoc'),
            'total_cuocban' => $details->sum('cuocban'),
            'total_vat' => $details->sum('vat'),
            'total_ppxd' => $details->sum('ppxd'),
            'total_phuphi' => $details->sum('phuphi'),
            'total_hoahong' => $details->sum('hoahong'),
        ])->save();
    }

    public function syncPaidAmountFromPayments(): void
    {
        $paidAmount = (float) $this->payments()
            ->where('status', InvoicePaymentStatusEnum::DA_THANH_TOAN->value)
            ->sum('amount');
        $total = (float) $this->total_cuocvon;

        $status = match (true) {
            $total > 0 && $paidAmount >= $total => DebtStatusEnum::DA_THANH_TOAN,
            $paidAmount > 0 => DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN,
            default => DebtStatusEnum::DA_CHOT_CUOC,
        };

        $this->forceFill([
            'paid_amount' => $paidAmount,
            'status' => $status->value,
            'ngaythanhtoan' => $status === DebtStatusEnum::DA_THANH_TOAN ? now() : null,
        ])->save();
    }

    public function canCreatePaymentInvoice(): bool
    {
        return in_array($this->status, [
            DebtStatusEnum::DA_CHOT_CUOC,
            DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN,
        ], true);
    }

    public function pendingInvoicesTotal(): float
    {
        return (float) $this->payments()
            ->whereIn('status', InvoicePaymentStatusEnum::pendingValues())
            ->sum('amount');
    }

    public function availableForNewInvoice(): float
    {
        $total = (float) $this->total_cuocvon;
        $paid = (float) $this->paid_amount;
        $pending = $this->pendingInvoicesTotal();

        return max(0.0, $total - $paid - $pending);
    }

    public static function generateSoHoaDon(string $tungay, string $denngay): string
    {
        $prefix = (string) config('invoice.code_prefix.debt', 'DEB');
        do {
            $code = $prefix.$tungay.$denngay.self::generateRandomCode();
        } while (self::where('sohoadon', $code)->exists());

        return $code;
    }

    protected static function generateRandomCode(int $length = 2): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
