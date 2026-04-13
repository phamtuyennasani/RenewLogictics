<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';

    protected $fillable = [
        'id_bill',
        'numb',
        'id_sale',
        'id_manager',
        'id_ctv',
        'id_ketoan',
        'id_ops',
        'id_cs',
        'id_customer',
        'info_ctv',
        'info_sender',
        'info_receiver',
        'info_pickup',
        'bill_status',
        'dichvu',
        'payment',
        'id_create',
        'payment_status',
        'payment_status_ncc',
        'shipper_status',
        'ngaynhanhang',
        'ngayxuathang',
        'ngaygiaohang',
        'ngaygiaodukien',
        'dim',
        'tracking_code',
        'photo_payment_khachhang',
        'photo_payment_ncc',
        'hoadon_khachhang',
        'hoadon_ncc',
        'dim_thucte',
        'dim_xuatkho',
        'dim_daily',
        'lock_order',
        'last_update',
        'last_update_info',
        'last_update_dichvu',
        'last_update_pickup',
        'khoxuathang',
        'ghichu',
        'del',
    ];

    protected $casts = [
        'info_ctv' => 'array',
        'info_sender' => 'array',
        'info_receiver' => 'array',
        'info_pickup' => 'array',
        'dichvu' => 'array',
        'payment' => 'array',
        'shipper_status' => 'array',
        'last_update' => 'array',
        'last_update_info' => 'array',
        'last_update_dichvu' => 'array',
        'last_update_pickup' => 'array',
        'ngaynhanhang' => 'datetime',
        'ngayxuathang' => 'datetime',
        'ngaygiaohang' => 'datetime',
        'ngaygiaodukien' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function customer()
    {
        return $this->belongsTo(Member::class, 'id_customer', 'uuid');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'id_create');
    }

    public function sale()
    {
        return $this->belongsTo(User::class, 'id_sale');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'id_manager');
    }

    public function ctv()
    {
        return $this->belongsTo(User::class, 'id_ctv');
    }

    public function ketoan()
    {
        return $this->belongsTo(User::class, 'id_ketoan');
    }

    public function ops()
    {
        return $this->belongsTo(User::class, 'id_ops');
    }

    public function cs()
    {
        return $this->belongsTo(User::class, 'id_cs');
    }

    public function status()
    {
        return $this->belongsTo(News::class, 'bill_status', 'id');
    }

    public function pickupStatus()
    {
        return $this->belongsTo(News::class, 'pickup_status', 'id');
    }

    public function paymentStatusKH()
    {
        return $this->belongsTo(News::class, 'payment_status', 'id');
    }

    public function paymentStatusNCC()
    {
        return $this->belongsTo(News::class, 'payment_status_ncc', 'id');
    }

    public function dichvu()
    {
        return $this->belongsTo(News::class, 'dichvu->id_dichvu');
    }

    public function chiTietDichVu()
    {
        return $this->belongsTo(News::class, 'dichvu->id_chitiet_dichvu');
    }

    public function chiNhanhNhanHang()
    {
        return $this->belongsTo(News::class, 'dichvu->id_chinhanh_nhanhang');
    }

    public function loaiBuuGui()
    {
        return $this->belongsTo(News::class, 'dichvu->loaibuugui');
    }

    public function hinhThucGuiHang()
    {
        return $this->belongsTo(News::class, 'dichvu->hinhthucguihang');
    }

    public function lyDoGuiHang()
    {
        return $this->belongsTo(News::class, 'dichvu->lydoguihang');
    }

    public function deliveryTerm()
    {
        return $this->belongsTo(News::class, 'dichvu->deliveryterm');
    }

    public function daiLy()
    {
        return $this->belongsTo(News::class, 'dichvu->id_daily');
    }

    public function hangBay()
    {
        return $this->belongsTo(News::class, 'dichvu->id_hangbay');
    }

    public function doiTacChungChuyen()
    {
        return $this->belongsTo(News::class, 'dichvu->id_doitacchungchuyen');
    }

    public function phuongTien()
    {
        return $this->belongsTo(News::class, 'info_pickup->phuongtien');
    }

    public function packages()
    {
        return $this->hasMany(OrderPackage::class, 'id_order');
    }

    public function packagesThucTe()
    {
        return $this->hasMany(OrderPackageThucTe::class, 'id_order');
    }

    public function packagesDaiLy()
    {
        return $this->hasMany(OrderPackageDaiLy::class, 'id_order');
    }

    public function packagesTaiHang()
    {
        return $this->belongsToMany(
            Package::class,
            'packages_detail',
            'id_order',
            'id_package'
        );
    }

    public function congNos()
    {
        return $this->belongsToMany(
            CongNo::class,
            'congno_detail',
            'id_order',
            'id_congno'
        );
    }

    public function photos()
    {
        return $this->hasMany(OrderPhoto::class, 'id_order');
    }

    public function photosThucTe()
    {
        return $this->hasMany(OrderPhotoThucTe::class, 'id_order');
    }

    public function photosXuatKho()
    {
        return $this->hasMany(OrderPhotoXuatKho::class, 'id_order');
    }

    public function photosPickup()
    {
        return $this->hasMany(OrderPhotoPickup::class, 'id_order');
    }

    public function history()
    {
        return $this->hasMany(OrderHistory::class, 'id_order')->orderBy('thoigian', 'desc');
    }

    public function notes()
    {
        return $this->hasMany(OrderNotes::class, 'id_order');
    }

    public function actions()
    {
        return $this->hasMany(OrderAction::class, 'id_order');
    }

    public function invoices()
    {
        return $this->hasMany(PackageInvoice::class, 'id_order');
    }

    public function invoicesDaily()
    {
        return $this->hasMany(PackageInvoiceDaily::class, 'id_order');
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    protected function packageOrder(): Attribute
    {
        return Attribute::get(function () {
            if ($this->relationLoaded('packages_thucte') && $this->packages_thucte->isNotEmpty()) {
                return $this->packages_thucte;
            }
            return $this->packages;
        });
    }

    protected function packageBanDau(): Attribute
    {
        return Attribute::get(function () {
            return $this->packages->isNotEmpty() ? $this->packages : collect();
        });
    }

    protected function packageThucTe(): Attribute
    {
        return Attribute::get(function () {
            return $this->packages_thucte->isNotEmpty() ? $this->packages_thucte : $this->packages;
        });
    }

    protected function soKienBanDau(): Attribute
    {
        return Attribute::get(fn () => $this->packages->count() . ' kiện');
    }

    protected function soKienThucTe(): Attribute
    {
        return Attribute::get(fn () => $this->packages_thucte->count() . ' kiện');
    }

    protected function soKienDaiLy(): Attribute
    {
        return Attribute::get(fn () => $this->packages_daily->count() . ' kiện');
    }

    protected function canNangBanDau(): Attribute
    {
        return Attribute::get(fn () => round($this->packages->sum('c_weight'), 2) . ' kg');
    }

    protected function canNangThucTe(): Attribute
    {
        return Attribute::get(fn () => $this->packages_thucte->isNotEmpty()
            ? round($this->packages_thucte->sum('c_weight'), 2) . ' kg'
            : 'Chưa nhập'
        );
    }

    protected function canNangDaiLy(): Attribute
    {
        return Attribute::get(fn () => $this->packages_daily->isNotEmpty()
            ? round($this->packages_daily->sum('c_weight'), 2) . ' kg'
            : 'Chưa nhập'
        );
    }

    protected function canNangG(): Attribute
    {
        return Attribute::get(fn () => round($this->package_order->sum('g_weight'), 2));
    }

    protected function canNangV(): Attribute
    {
        return Attribute::get(fn () => round($this->package_order->sum('v_weight'), 2));
    }

    protected function canNangC(): Attribute
    {
        return Attribute::get(fn () => round($this->package_order->sum('c_weight'), 2));
    }

    protected function canNangGDaiLy(): Attribute
    {
        return Attribute::get(fn () => round($this->packages_daily->sum('g_weight'), 2));
    }

    protected function canNangVDaiLy(): Attribute
    {
        return Attribute::get(fn () => round($this->packages_daily->sum('v_weight'), 2));
    }

    protected function canNangCDaiLy(): Attribute
    {
        return Attribute::get(fn () => round($this->packages_daily->sum('c_weight'), 2));
    }

    protected function canNangTTG(): Attribute
    {
        return Attribute::get(fn () => round($this->packages_thucte->sum('g_weight'), 2));
    }

    protected function canNangTTV(): Attribute
    {
        return Attribute::get(fn () => round($this->packages_thucte->sum('v_weight'), 2));
    }

    protected function canNangTTC(): Attribute
    {
        return Attribute::get(fn () => round($this->packages_thucte->sum('c_weight'), 2));
    }

    protected function lastUpdateUser(): Attribute
    {
        return Attribute::get(function () {
            $idUser = $this->last_update_info['id_user'] ?? null;
            return [
                'user' => $idUser ? User::find($idUser) : null,
                'time' => $this->last_update_info['time'] ?? null,
            ];
        });
    }

    protected function lastUpdateOps(): Attribute
    {
        return Attribute::get(function () {
            $idUser = $this->last_update_pickup['id_user'] ?? null;
            return [
                'user' => $idUser ? User::find($idUser) : null,
                'time' => $this->last_update_pickup['time'] ?? null,
            ];
        });
    }

    protected function lastUpdateDichVu(): Attribute
    {
        return Attribute::get(function () {
            $idUser = $this->last_update_dichvu['id_user'] ?? null;
            return [
                'user' => $idUser ? User::find($idUser) : null,
                'time' => $this->last_update_dichvu['time'] ?? null,
            ];
        });
    }

    protected function taiHang(): Attribute
    {
        return Attribute::get(fn () => $this->packages_tai_hang->first() ?? collect());
    }

    protected function congNo(): Attribute
    {
        return Attribute::get(fn () => $this->cong_nos->first() ?? collect());
    }

    protected function isCongNo(): Attribute
    {
        return Attribute::get(fn () => $this->cong_nos->isNotEmpty());
    }

    protected function isTaiHang(): Attribute
    {
        return Attribute::get(fn () => $this->packages_tai_hang->isNotEmpty());
    }

    protected function dichVuDiKem(): Attribute
    {
        return Attribute::get(function () {
            $ids = $this->dichvu['dichvudikem'] ?? [];
            if (empty($ids)) return '';
            return News::whereIn('id', $ids)->pluck('namevi')->implode(' / ');
        });
    }
    protected function tinhTrangDon(): Attribute
    {
        return Attribute::get(function () {
            $ids = $this->dichvu['tinhtrangdon'] ?? [];
            if (empty($ids)) return '';
            return News::whereIn('id', $ids)->pluck('namevi')->implode(' / ');
        });
    }

    // ============================================
    // STATIC METHODS - ATOMIC ORDER CODE GENERATION
    // ============================================

    /**
     * Generate order code với DB transaction lock
     * Thread-safe khi nhiều user cùng thao tác
     * Format: AVN{YYMMDD}{NNN}
     */
    public static function generateOrderCode(): string
    {
        $prefix = 'AVN' . now()->format('ymd');
        return DB::transaction(function () use ($prefix) {
            // Lock row cuối cùng có cùng prefix để prevent race condition
            $lastOrder = self::where('id_bill', 'like', $prefix . '%')
                ->orderByDesc('id_bill')
                ->lockForUpdate()
                ->first();
            if ($lastOrder) {
                $numberPart = (int) substr($lastOrder->id_bill, strlen($prefix));
                $nextNumber = $numberPart + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        });
    }
}
