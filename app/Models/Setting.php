<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Setting extends Model
{
    protected $table = 'setting';
    protected $fillable = [
        'namevi',
        'options',
        'photo'
    ];
    protected $casts = [
        'options' => 'json',
    ];
}