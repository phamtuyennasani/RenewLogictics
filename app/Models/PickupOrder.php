<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickupOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_id',
        'id_order',
        'added_by',
    ];

    public function pickup()
    {
        return $this->belongsTo(Pickup::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
