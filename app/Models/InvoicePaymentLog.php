<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoicePaymentLog extends Model
{
    protected $table = 'invoice_payment_logs';

    protected $fillable = [
        'congno_payment_id',
        'congno_daily_payment_id',
        'action',
        'from_status',
        'to_status',
        'actor_id',
        'note',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
