<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZaloShippingRequest extends Model
{
    protected $fillable = [
        'requester_name',
        'phone',
        'email',
        'company',
        'pickup_address',
        'pickup_city',
        'receiver_country_id',
        'receiver_country',
        'service_id',
        'service_name',
        'package_count',
        'weight_kg',
        'length_cm',
        'width_cm',
        'height_cm',
        'note',
        'quote_snapshot',
        'zalo_user_id',
        'status',
    ];

    protected $casts = [
        'receiver_country_id' => 'integer',
        'service_id' => 'integer',
        'package_count' => 'integer',
        'weight_kg' => 'decimal:3',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'quote_snapshot' => 'array',
    ];
}
