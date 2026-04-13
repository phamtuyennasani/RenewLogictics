<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class OrderPackage extends Model
{
    use HasFactory;

    protected $table = 'order_package';

    protected $fillable = [
        'id_order',
        'code',
        'number',
        'g_weight',
        'v_weight',
        'c_weight',
        're_weight',
        'stage',
        'info_bandau',
        'info_thucte',
        'info_daily',
        'info',
    ];

    protected $casts = [
        'info_bandau' => 'array',
        'info_thucte' => 'array',
        'info_daily' => 'array',
        'info' => 'array',
    ];

    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }

    public function invoices()
    {
        return $this->hasMany(PackageInvoice::class, 'package_code', 'code');
    }

    // Computed attributes
    protected function canNangG(): Attribute
    {
        return Attribute::get(fn () => round($this->g_weight ?? 0, 2));
    }

    protected function canNangV(): Attribute
    {
        return Attribute::get(fn () => round($this->v_weight ?? 0, 2));
    }

    protected function canNangC(): Attribute
    {
        return Attribute::get(fn () => round($this->c_weight ?? 0, 2));
    }

    // Static methods
    public static function generateOrderCode($prefix = null)
    {
        if (!$prefix) {
            $prefix = now()->format('ymd');
        }

        $lastCode = self::where('code', 'like', '%' . $prefix . '%')
            ->orderByDesc('code')
            ->first();

        if (!$lastCode) {
            $nextNumber = 1;
        } else {
            $numberPart = (int) substr($lastCode->code, -3);
            $nextNumber = $numberPart + 1;
        }

        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}