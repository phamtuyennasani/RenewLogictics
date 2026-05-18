<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderHistory extends Model
{
    use HasFactory;

    protected $table = 'order_history';

    protected $fillable = [
        'id_order',
        'id_user',
        'action',
        'content',
        'thoigian',
        'trangthai',
        'diadiem',
        'ghichu',
        'main',
        'created_at',
    ];

    protected $casts = [
        'thoigian' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'main' => 'boolean',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
