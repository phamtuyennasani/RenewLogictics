<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $table = 'states';
    protected $fillable = [
        'id',
        'name',
        'country_id',
        'country_code',
        'iso2',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function cities(): HasMany
    {
        return $this->hasMany(Cities::class, 'state_id');
    }
}