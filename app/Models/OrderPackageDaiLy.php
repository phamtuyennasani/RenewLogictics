<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPackageDaiLy extends Model
{
    use HasFactory;

    protected $table = 'order_package_daily';

    protected $fillable = [
        'id_order',
        'code',
        'number',
        'g_weight',
        'v_weight',
        'c_weight',
        're_weight',
        'stage',
        'info',
    ];

    protected $casts = [
        'info' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
