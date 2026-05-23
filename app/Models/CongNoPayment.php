<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongNoPayment extends Model
{
    use HasFactory;

    protected $table = 'congno_payments';

    protected $fillable = [
        'id_congno',
        'id_user',
        'amount',
        'paid_at',
        'method',
        'reference',
        'photo',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function congNo()
    {
        return $this->belongsTo(CongNo::class, 'id_congno');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
