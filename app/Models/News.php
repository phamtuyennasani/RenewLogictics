<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news';

    protected $fillable = [
        'namevi',
        'slug',
        'descvi',
        'contentvi',
        'photo',
        'type',
        'status',
        'numb',
        'title',
        'keyword',
        'description',
        'options2',
        'id_country',
        'id_dichvu',
        'id_user',
        'id_list',
        'id_cat',
        'id_item',
        'id_sub',
    ];

    protected $casts = [
        'options2' => 'array',
        'id_country' => 'array',
        'id_dichvu' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Các loại news (type)
    const TYPE_STATUS = 'status';
    const TYPE_SERVICE = 'service';
    const TYPE_NEWS = 'news';
    const TYPE_BANNER = 'banner';
    const TYPE_PARTNER = 'partner';
    const TYPE_DISTRICT = 'district';
    const TYPE_PLACE = 'place';
    const TYPE_VSVX = 'vsvx';

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }

    public function categoryList()
    {
        return $this->belongsTo(News::class, 'id_list', 'id');
    }

    public function categoryCat()
    {
        return $this->belongsTo(News::class, 'id_cat', 'id');
    }

    public function categoryItem()
    {
        return $this->belongsTo(News::class, 'id_item', 'id');
    }

    public function categorySub()
    {
        return $this->belongsTo(News::class, 'id_sub', 'id');
    }

    public function vsvx()
    {
        return $this->hasMany(VSVX::class, 'id_dichvu', 'id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'dichvu->id_dichvu', 'id');
    }

    public function ordersByCountry()
    {
        return $this->hasMany(Order::class, 'info_receiver->country_id', 'id');
    }
}
