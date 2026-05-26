<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MoMoWebhookLog extends Model
{
    protected $table = 'momo_webhook_logs';

    protected $fillable = [
        'event_id',
        'order_id',
        'amount',
        'trans_id',
        'result_code',
        'message',
        'payment_option',
        'response_time',
        'matched_congno_payment_id',
        'processed_status',
        'processed_message',
        'processed_at',
        'payload',
        'headers',
        'received_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'response_time' => 'datetime',
    ];

    public function matchedInvoice()
    {
        return $this->belongsTo(CongNoPayment::class, 'matched_congno_payment_id');
    }
}
