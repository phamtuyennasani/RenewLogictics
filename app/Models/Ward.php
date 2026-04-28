<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    use HasFactory;

    protected $table = 'ward';

    protected $fillable = [
        'namevi',
        'slugvi',
        'id_district',
        'id_city',
        'code',
        'numb',
        'status',
    ];

    public function district()
    {
        return $this->belongsTo(District::class, 'id_district');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'id_city');
    }
}
