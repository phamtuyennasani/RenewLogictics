<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SepayWebhookLog extends Model
{
    protected $table = 'sepay_webhook_logs';

    protected $fillable = [
        'transaction_id',
        'matched_congno_payment_id',
        'processed_status',
        'processed_message',
        'processed_at',
        'payload',
        'headers',
        'received_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function matchedInvoice()
    {
        return $this->belongsTo(CongNoPayment::class, 'matched_congno_payment_id');
    }
}
