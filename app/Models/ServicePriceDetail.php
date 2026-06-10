<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePriceDetail extends Model
{
    protected $fillable = [
        'service_price_list_id',
        'quycach',
        'weight_from',
        'weight_to',
        'sale_price',
        'cost_price',
        'base_price',
    ];

    protected $casts = [
        'weight_from' => 'decimal:2',
        'weight_to' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'base_price' => 'decimal:2',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(ServicePriceList::class, 'service_price_list_id');
    }
}
