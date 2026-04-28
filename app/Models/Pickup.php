<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Pickup extends Model
{
    use HasFactory;

    protected $table = 'pickup';

    protected $fillable = [
        'ma_pickup',
        'id_user',
        'id_ctv',
        'id_shipper',
        'id_ketoan',
        'id_xuatkho',
        'id_status',
        'ngay_tao',
        'ngay_nhanhang',
        'ngay_xuatkho',
        'total_weight',
        'total_dim',
        'total_c_weight',
        'total_dim_thucte',
        'total_cuoc',
        'total_cuocvon',
        'total_cuocban',
        'status',
        'numb',
        'note',
        'options',
        'info_pickup',
        'info_khachhang',
    ];

    protected $casts = [
        'total_weight' => 'decimal:2',
        'total_dim' => 'decimal:2',
        'total_c_weight' => 'decimal:2',
        'total_dim_thucte' => 'decimal:2',
        'total_cuoc' => 'decimal:0',
        'total_cuocvon' => 'decimal:0',
        'total_cuocban' => 'decimal:0',
        'options' => 'array',
        'info_pickup' => 'array',
        'info_khachhang' => 'array',
        'ngay_tao' => 'datetime',
        'ngay_nhanhang' => 'datetime',
        'ngay_xuatkho' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function ctv()
    {
        return $this->belongsTo(User::class, 'id_ctv');
    }

    public function shipper()
    {
        return $this->belongsTo(User::class, 'id_shipper');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function xuatkho()
    {
        return $this->belongsTo(User::class, 'id_xuatkho');
    }

    public function status()
    {
        return $this->belongsTo(News::class, 'id_status');
    }

    public function phuongTien()
    {
        return $this->belongsTo(News::class, 'info_pickup->id_phuongtien');
    }

    public function chiNhanhNhanHang()
    {
        return $this->belongsTo(News::class, 'info_khachhang->chinhanhnhanhang');
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'info_khachhang->province_id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'info_khachhang->ward_id');
    }

    public function packages()
    {
        return $this->hasMany(Package::class, 'id_pickup');
    }

    // Static methods
    protected static function generateRandomCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    public static function generateCode(): string
    {
        do {
            $code = 'PICK' . self::generateRandomCode(8);
        } while (self::where('ma_pickup', $code)->exists());

        return $code;
    }
}
