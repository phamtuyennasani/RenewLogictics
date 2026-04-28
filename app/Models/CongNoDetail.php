<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongNoDetail extends Model
{
    use HasFactory;

    protected $table = 'congno_detail';

    protected $fillable = [
        'id_congno',
        'id_order',
    ];

    public function congNo()
    {
        return $this->belongsTo(CongNo::class, 'id_congno');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
