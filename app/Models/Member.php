<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'member';

    protected $fillable = [
        'ma_khach',
        'name',
        'slug',
        'email',
        'phone',
        'address',
        'options',
        'numb',
        'status',
        'type',
        'id_create',
        'id_sale',
        'id_ctv',
        'id_khachhang',
        'code',
        'uuid',
    ];

    protected $casts = [
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    // Relationships
    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
    }

    public function ctv()
    {
        return $this->belongsTo(CongTacVien::class, 'id_ctv');
    }

    public function khachHang()
    {
        return $this->belongsTo(Member::class, 'id_khachhang');
    }

    public function receivers()
    {
        return $this->hasMany(Member::class, 'id_khachhang');
    }

    public function sender()
    {
        return $this->belongsTo(Member::class, 'id_khachhang');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'id_customer', 'uuid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_create');
    }

    // Computed attributes
    protected function infoSale(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->sale) return '';
            return $this->sale->fullname . ' - ' . $this->sale->code;
        });
    }

    protected function infoCtv(): Attribute
    {
        return Attribute::get(function () {
            if (!$this->ctv) return '';
            return $this->ctv->name . ' - ' . $this->ctv->code ?? '';
        });
    }
}
