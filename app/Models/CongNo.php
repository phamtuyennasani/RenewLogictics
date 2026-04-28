<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CongNo extends Model
{
    use HasFactory;

    protected $table = 'congno';

    protected $fillable = [
        'id_order',
        'id_customer',
        'id_user',
        'id_ctv',
        'id_ketoan',
        'id_success',
        'id_sale',
        'sohoadon',
        'tungay',
        'denngay',
        'total_cuoc',
        'total_cuocvon',
        'total_cuocban',
        'hoahong',
        'status',
        'type',
        'ngay_tao',
    ];

    protected $casts = [
        'total_cuoc' => 'decimal:0',
        'total_cuocvon' => 'decimal:0',
        'total_cuocban' => 'decimal:0',
        'hoahong' => 'decimal:0',
        'ngay_tao' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function ctv()
    {
        return $this->belongsTo(User::class, 'id_ctv');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'id_user');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function success()
    {
        return $this->belongsTo(User::class, 'id_success');
    }

    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
    }

    public function status()
    {
        return $this->belongsTo(News::class, 'status', 'id');
    }

    public function orders()
    {
        return $this->belongsToMany(
            Order::class,
            'congno_detail',
            'id_congno',
            'id_order'
        );
    }

    public function details()
    {
        return $this->hasMany(CongNoDetail::class, 'id_congno', 'id');
    }

    // Computed attributes
    protected function orderDTT(): Attribute
    {
        return Attribute::get(function () {
            return $this->orders()
                ->where('payment_status', config('constants.DA_THANH_TOAN', 417))
                ->count();
        });
    }

    protected function tongCuocChuaThanhToan(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders()
                ->where('payment_status', '!=', config('constants.DA_THANH_TOAN', 417))
                ->sum('totalpricegiaban') ?? 0, 0);
        });
    }

    protected function tongCuocDaThanhToan(): Attribute
    {
        return Attribute::get(function () {
            return round(($this->tong_cuoc ?? 0) - ($this->tongcuocchuathanhtoan ?? 0), 0);
        });
    }

    protected function sumCanNangThucTe(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('cannangg'), 1);
        });
    }

    protected function sumCanNangCBanDau(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('cannangc'), 1);
        });
    }

    protected function tongCuoc(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('totalpricegiaban'), 0);
        });
    }

    protected function donGia(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('dongiaban'), 0);
        });
    }

    protected function hoaHongSale(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('allhoahong'), 0);
        });
    }

    protected function ppxd(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('ppxdgiaban'), 0);
        });
    }

    protected function phuPhi(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('phuphigiaban'), 0);
        });
    }

    protected function tongVat(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('vatgiaban'), 0);
        });
    }

    protected function tongCuocBan(): Attribute
    {
        return Attribute::get(function () {
            return round($this->orders->sum('tongcuocban'), 0);
        });
    }

    // Static methods
    protected static function generateRandomCode(int $length = 2): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    public static function generateSoHoaDon(string $tungay, string $denngay): string
    {
        do {
            $prefix = 'DEB' . $tungay . $denngay . self::generateRandomCode();
        } while (self::where('sohoadon', $prefix)->exists());

        return $prefix;
    }
}