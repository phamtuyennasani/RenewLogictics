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
        'dim_thucte',
        'dim_xuatkho',
        'lock_order',
        're_weight',
        'ketoan_success',
        'sale_success',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_create');
    }

    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
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
    public function photos()
    {
        return $this->hasMany(OrderPhoto::class, 'id_order');
    }
}
