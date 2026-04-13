<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasFactory;

    protected $table = 'city';

    protected $fillable = [
        'namevi',
        'slugvi',
        'level',
        'code',
        'numb',
        'status',
        'date_created',
        'type',
    ];

    protected $casts = [
        'date_created' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function districts()
    {
        return $this->hasMany(District::class, 'id_city');
    }
}
