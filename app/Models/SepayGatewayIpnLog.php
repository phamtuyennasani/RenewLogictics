<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SepayGatewayIpnLog extends Model
{
    protected $table = 'sepay_gateway_ipn_logs';

    protected $fillable = [
        'event_key',
        'notification_type',
        'gateway_order_id',
        'invoice_number',
        'transaction_id',
        'payload',
        'headers',
        'received_at',
        'matched_congno_payment_id',
        'processed_status',
        'processed_message',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
