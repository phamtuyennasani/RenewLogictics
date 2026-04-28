<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class District extends Model
{
    use HasFactory;

    protected $table = 'district';

    protected $fillable = [
        'namevi',
        'slugvi',
        'id_city',
        'code',
        'numb',
        'status',
    ];

    public function city()
    {
        return $this->belongsTo(City::class, 'id_city');
    }

    public function wards()
    {
        return $this->hasMany(Ward::class, 'id_district');
    }
}
