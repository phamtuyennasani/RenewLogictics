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
        'id_province',
        'id_ward',
        'country_id',
        'state',
        'cities',
        'postcode',
        'id_sender',
        'company_name',
        'fullname',
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
        return $this->belongsTo(User::class, 'id_ctv');
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
        return $this->belongsTo(Member::class, 'id_sender');
    }

    public function receiverMembers()
    {
        return $this->hasMany(Member::class, 'id_sender');
    }

    public function province()
    {
        return $this->belongsTo(Province::class, 'id_province');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'id_ward');
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'id_customer', 'uuid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_create');
    }

    // Scopes
    public function scopeSender($query)
    {
        return $query->where('type', 'sender');
    }

    public function scopeReceiver($query)
    {
        return $query->where('type', 'receiver');
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
            return ($this->ctv->fullname ?: $this->ctv->username) . ' - ' . ($this->ctv->code ?? '');
        });
    }

    protected function fullAddress(): Attribute
    {
        return Attribute::get(function () {
            if ($this->type === 'sender') {
                $parts = array_filter([
                    $this->address,
                    $this->ward?->name,
                    $this->province?->name,
                ]);
                return implode(', ', $parts);
            } else {
                $parts = array_filter([
                    $this->address,
                    $this->cities,
                    $this->state,
                    $this->country?->name,
                    $this->postcode,
                ]);
                return implode(', ', $parts);
            }
        });
    }
}
