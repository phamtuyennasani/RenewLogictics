<?php

namespace App\Models;

use App\Enums\ShipmentLoadStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentLoad extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'orders_count',
        'total_chargeable_weight',
    ];

    protected $casts = [
        'status' => ShipmentLoadStatusEnum::class,
        'approved_at' => 'datetime',
        'orders_count' => 'integer',
        'total_chargeable_weight' => 'decimal:2',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items()
    {
        return $this->hasMany(ShipmentLoadOrder::class);
    }

    public function orders()
    {
        return $this->belongsToMany(Order::class, 'shipment_load_orders', 'shipment_load_id', 'id_order')
            ->withPivot(['added_by', 'created_at'])
            ->withTimestamps();
    }

    public function histories()
    {
        return $this->hasMany(ShipmentLoadHistory::class);
    }

    public function canEditOrders(): bool
    {
        return $this->status?->canEditOrders() ?? false;
    }
}

