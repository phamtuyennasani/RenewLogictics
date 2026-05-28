<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLoadHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_load_id',
        'id_user',
        'thoigian',
        'diadiem',
        'trangthai',
        'ghichu',
    ];

    protected $casts = [
        'thoigian' => 'datetime',
    ];

    public function load()
    {
        return $this->belongsTo(ShipmentLoad::class, 'shipment_load_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}

