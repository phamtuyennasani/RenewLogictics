<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtActivityLog extends Model
{
    protected $table = 'debt_activity_logs';

    protected $fillable = [
        'congno_id',
        'congno_daily_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'title',
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

    public function customerDebt()
    {
        return $this->belongsTo(CongNo::class, 'congno_id');
    }

    public function agencyDebt()
    {
        return $this->belongsTo(CongNoDaiLy::class, 'congno_daily_id');
    }
}
