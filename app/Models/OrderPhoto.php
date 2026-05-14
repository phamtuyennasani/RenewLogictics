<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class OrderPhoto extends Model
{
    use HasFactory;
    protected $table = 'order_photo';
    protected $fillable = [
        'id_order',
        'photo',
    ];

    protected static function booted(): void
    {
        static::deleted(function (OrderPhoto $photo): void {
            if (! $photo->photo) {
                return;
            }

            $path = public_path('uploads'.DIRECTORY_SEPARATOR.'order'.DIRECTORY_SEPARATOR.$photo->photo);

            if (File::isFile($path)) {
                File::delete($path);
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'id_order');
    }
}
