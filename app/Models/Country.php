<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    // id nằm trong fillable vì dùng insertOrIgnore, không phải auto-increment
    protected $fillable = [
        'id',
        'name',
        'iso2',
        'iso3',
        'phonecode',
    ];

    public function states(): HasMany
    {
        return $this->hasMany(State::class, 'country_id');
    }
}
