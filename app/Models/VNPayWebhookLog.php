<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VNPayWebhookLog extends Model
{
    protected $table = 'vnpay_webhook_logs';

    protected $fillable = [
        'txn_ref',
        'amount',
        'bank_code',
        'bank_tran_no',
        'card_type',
        'response_code',
        'transaction_no',
        'transaction_status',
        'pay_date',
        'order_info',
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
        'pay_date' => 'datetime',
    ];

    public function matchedInvoice()
    {
        return $this->belongsTo(CongNoPayment::class, 'matched_congno_payment_id');
    }
}
