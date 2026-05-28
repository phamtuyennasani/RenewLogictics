<?php

namespace App\Models;

use App\Enums\OrderStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;
    use \Staudenmeir\EloquentJsonRelations\HasJsonRelationships;
    protected $table = 'orders';

    protected $fillable = [
        'uuid',
        'id_bill',
        'id_sale',
        'id_manager',
        'id_ketoan',
        'id_ops',
        'id_cs',
        'id_customer',
        'bill_status',
        'service',
        'id_create',
        'ngaynhanhang',
        'ngayxuathang',
        'ngaygiaohang',
        'ngaygiaodukien',
        'dim',
        'tracking_code',
        'id_thamchieu',
        'mathamchieu',
        'trackingmore_id',
        'dim_thucte',
        'dim_xuatkho',
        'lock_order',
        're_weight',
        'ketoan_success',
        'sale_success',
        'sale_price_locked_at',
        'sale_price_locked_by',
        'customer_payment_status',
        'customer_paid_at',
        'customer_payment_photo',
        'customer_invoice_ref',
        'ghichu',
        'created_at',
        'updated_at',
        'sender',
        'receiver',
        'payment_cuocvon',
        'payment_cuocgoc',
        'payment_cuocban',
        'payment_loinhuan'
    ];

    protected $casts = [
        'service' => 'json',
        'ngaynhanhang' => 'datetime',
        'ngayxuathang' => 'datetime',
        'ngaygiaohang' => 'datetime',
        'ngaygiaodukien' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'ketoan_success' => 'boolean',
        'sale_success' => 'boolean',
        'sale_price_locked_at' => 'datetime',
        'customer_paid_at' => 'datetime',
        'lock_order' => 'boolean',
        'sender' => 'json',
        'receiver' => 'json',
        'payment_cuocvon' => 'json',
        'payment_cuocgoc' => 'json',
        'payment_cuocban' => 'json',
        'payment_loinhuan' => 'json',
        'bill_status' => OrderStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Order $order): void {
            $order->photos()->get()->each->delete();
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function customer()
    {
        return $this->belongsTo(Member::class, 'id_customer', 'id');
    }

    public function customerAccount()
    {
        return $this->belongsTo(User::class, 'id_customer');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_create');
    }

    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
    }

    public function salePriceLocker()
    {
        return $this->belongsTo(User::class, 'sale_price_locked_by');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'id_manager');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function ops()
    {
        return $this->belongsTo(User::class, 'id_ops');
    }

    public function cs()
    {
        return $this->belongsTo(User::class, 'id_cs');
    }

    public function packages()
    {
        return $this->hasMany(OrderPackage::class, 'id_order');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'id_order');
    }

    public function dichvu()
    {
        return $this->belongsTo(News::class, 'service->id_dichvu');
    }

    public function chiTietDichVu()
    {
        return $this->belongsTo(News::class, 'service->id_chitiet_dichvu');
    }

    public function chiNhanhNhanHang()
    {
        return $this->belongsTo(News::class, 'service->id_chinhanh_nhanhang');
    }

    public function receiverCountry()
    {
        return $this->belongsTo(Country::class, 'receiver->country_id');
    }

    public function receiverCountryLegacy()
    {
        return $this->belongsTo(Country::class, 'receiver->id_country');
    }

    public function loaiBuuGui()
    {
        return $this->belongsTo(News::class, 'service->loaibuugui');
    }

    public function hinhThucGuiHang()
    {
        return $this->belongsTo(News::class, 'service->hinhthucguihang');
    }

    public function lyDoGuiHang()
    {
        return $this->belongsTo(News::class, 'service->lydoguihang');
    }

    public function deliveryTerm()
    {
        return $this->belongsTo(News::class, 'service->deliveryterm');
    }

    public function daiLy()
    {
        return $this->belongsTo(News::class, 'service->id_daily');
    }

    public function hangBay()
    {
        return $this->belongsTo(News::class, 'service->id_hangbay');
    }

    public function doiTacChungChuyen()
    {
        return $this->belongsTo(News::class, 'service->id_doitacchungchuyen');
    }

    public function doiTacChungChuyenMoi()
    {
        return $this->belongsTo(News::class, 'service->id_doitac_chungchuyen');
    }
    public function photos()
    {
        return $this->hasMany(OrderPhoto::class, 'id_order');
    }

    public function histories()
    {
        return $this->hasMany(OrderHistory::class, 'id_order')->latest();
    }

    public function congNoDetails()
    {
        return $this->hasMany(CongNoDetail::class, 'id_order');
    }

    public function congNoDaiLyDetails()
    {
        return $this->hasMany(CongNoDaiLyDetail::class, 'id_order');
    }

    public function congNoPayments()
    {
        return $this->hasMany(\App\Models\CongNoPayment::class, 'id_order');
    }

    public function shipmentLoads()
    {
        return $this->belongsToMany(ShipmentLoad::class, 'shipment_load_orders', 'id_order', 'shipment_load_id')
            ->withPivot(['added_by', 'created_at'])
            ->withTimestamps();
    }

    public function isWalkIn(): bool
    {
        return ! $this->id_customer || (int) $this->id_customer === 0;
    }

    public function isInvoiceLocked(): bool
    {
        return $this->congNoPayments()
            ->whereNotIn('status', [
                \App\Enums\InvoicePaymentStatusEnum::HUY->value,
            ])
            ->exists();
    }

    public function hasActiveInvoice(): bool
    {
        return $this->congNoPayments()
            ->whereNotIn('status', [
                \App\Enums\InvoicePaymentStatusEnum::HUY->value,
                \App\Enums\InvoicePaymentStatusEnum::DA_THANH_TOAN->value,
            ])
            ->exists();
    }

    public function getActiveInvoice(): ?\App\Models\CongNoPayment
    {
        return $this->congNoPayments()
            ->whereNotIn('status', [\App\Enums\InvoicePaymentStatusEnum::HUY->value])
            ->first();
    }

    public function congNos()
    {
        return $this->belongsToMany(CongNo::class, 'congno_detail', 'id_order', 'id_congno')
            ->withPivot([
                'weight',
                'cuocban',
                'cuocvon',
                'cuocgoc',
                'vat',
                'ppxd',
                'phuphi',
                'hoahong',
                'snapshot',
            ])
            ->withTimestamps();
    }
}
