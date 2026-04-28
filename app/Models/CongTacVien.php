<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CongTacVien extends Model
{
    use HasFactory;

    protected $table = 'ctv';

    protected $fillable = [
        'id_sale',
        'id_khachhang',
        'name',
        'phone',
        'email',
        'address',
        'options',
        'numb',
        'status',
    ];

    protected $casts = [
        'options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'id_ctv');
    }
}
