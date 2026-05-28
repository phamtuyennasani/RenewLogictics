<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLoadOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_load_id',
        'id_order',
        'added_by',
    ];

    public function shipmentLoad()
    {
        return $this->belongsTo(ShipmentLoad::class, 'shipment_load_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}

