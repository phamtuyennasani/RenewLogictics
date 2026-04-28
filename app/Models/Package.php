<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Package extends Model
{
    use HasFactory;

    protected $table = 'packages';

    protected $fillable = [
        'ma_lo',
        'id_pickup',
        'id_ketoan',
        'id_xuatkho',
        'ngay_tao',
        'ngay_xuatkho',
        'ngay_nhanhang',
        'total_weight',
        'total_dim',
        'total_c_weight',
        'total_dim_thucte',
        'total_cuoc',
        'status',
        'numb',
        'note',
        'total_cuocvon',
        'total_cuocban',
    ];

    protected $casts = [
        'total_weight' => 'decimal:2',
        'total_dim' => 'decimal:2',
        'total_c_weight' => 'decimal:2',
        'total_dim_thucte' => 'decimal:2',
        'total_cuoc' => 'decimal:0',
        'total_cuocvon' => 'decimal:0',
        'total_cuocban' => 'decimal:0',
        'ngay_tao' => 'datetime',
        'ngay_xuatkho' => 'datetime',
        'ngay_nhanhang' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function pickup()
    {
        return $this->belongsTo(Pickup::class, 'id_pickup');
    }

    public function ops()
    {
        return $this->belongsTo(User::class, 'id_ops', 'id');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function xuatkho()
    {
        return $this->belongsTo(User::class, 'id_xuatkho');
    }

    public function status()
    {
        return $this->belongsTo(News::class, 'status', 'id');
    }

    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'packages_detail',
            'id_package',
            'id_order'
        );
    }

    public function details()
    {
        return $this->hasMany(PackagesDetail::class, 'id_package', 'id');
    }

    // Computed attributes
    protected function tongCanNang(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->sum('cannangc');
        });
    }

    protected function tongCanNangBanDau(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->sum('cannangg');
        });
    }

    protected function tongCanNangQuyDoi(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->sum('cannangv');
        });
    }

    protected function tongCanNangXuatKho(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->sum('cannangcdaily');
        });
    }

    protected function tongCanNangRW(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->sum('re_weight');
        });
    }

    protected function tongKienHang(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->map(fn($order) => $order->package_order->count())->sum();
        });
    }

    protected function tongDonHang(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders->count();
        });
    }
}
