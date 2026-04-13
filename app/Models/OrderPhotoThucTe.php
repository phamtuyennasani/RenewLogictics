<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPhotoThucTe extends Model
{
    use HasFactory;

    protected $table = 'order_photo_thucte';

    protected $fillable = [
        'id_order',
        'photo',
        'type',
        'note',
        'id_user',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
