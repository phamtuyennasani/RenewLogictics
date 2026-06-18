<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickupImage extends Model
{
    protected $table = 'pickup_images';

    protected $fillable = [
        'pickup_id',
        'path',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * URL đầy đủ tới ảnh (path lưu tương đối kiểu `/uploads/pickup/...`).
     */
    public function getUrlAttribute(): string
    {
        return url((string) $this->path);
    }

    public function pickup()
    {
        return $this->belongsTo(Pickup::class, 'pickup_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
