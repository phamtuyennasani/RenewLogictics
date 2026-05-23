<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongNoDaiLyDetail extends Model
{
    use HasFactory;

    protected $table = 'congno_daily_detail';

    protected $fillable = [
        'id_congno_daily',
        'id_order',
        'weight',
        'cuocvon',
        'cuocgoc',
        'cuocban',
        'vat',
        'ppxd',
        'phuphi',
        'hoahong',
        'snapshot',
    ];

    protected $casts = [
        'weight' => 'decimal:3',
        'cuocvon' => 'decimal:2',
        'cuocgoc' => 'decimal:2',
        'cuocban' => 'decimal:2',
        'vat' => 'decimal:2',
        'ppxd' => 'decimal:2',
        'phuphi' => 'decimal:2',
        'hoahong' => 'decimal:2',
        'snapshot' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function congNoDaiLy()
    {
        return $this->belongsTo(CongNoDaiLy::class, 'id_congno_daily');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
