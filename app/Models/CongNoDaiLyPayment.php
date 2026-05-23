<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongNoDaiLyPayment extends Model
{
    use HasFactory;

    protected $table = 'congno_daily_payments';

    protected $fillable = [
        'id_congno_daily',
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

    public function congNoDaiLy()
    {
        return $this->belongsTo(CongNoDaiLy::class, 'id_congno_daily');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
