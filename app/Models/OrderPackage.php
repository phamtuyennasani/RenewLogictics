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
        'length',
        'width',
        'height',
        'g_weight',
        'v_weight',
        'c_weight',
        're_weight',
        'package_type',
        'row_g_weight',
        'row_v_weight',
        'row_c_weight',
        'number_of_package',
        'id_thamchieu',
        'mathamchieu',
        'tracking_id',
    ];
    // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
