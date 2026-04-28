<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Province extends Model
{
    protected $table = 'province';

    protected $fillable = [
        'name',
        'code',
    ];

    public $timestamps = false;

    public function members()
    {
        return $this->hasMany(Member::class, 'id_province');
    }

    public function wards()
    {
        return $this->hasMany(Ward::class, 'parent_code', 'id');
    }
}
