<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VSVX extends Model
{
    use HasFactory;

    protected $table = 'vsvx';

    protected $fillable = [
        'id_dichvu',
        'name',
        'code',
    ];

    protected $casts = [
        'id_dichvu' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function dichvu()
    {
        return $this->belongsTo(News::class, 'id_dichvu', 'id');
    }
}
