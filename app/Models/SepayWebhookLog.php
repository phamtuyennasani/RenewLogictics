<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SepayWebhookLog extends Model
{
    protected $table = 'sepay_webhook_logs';

    protected $fillable = [
        'transaction_id',
        'payload',
        'headers',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
    ];
}
