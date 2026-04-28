<?php

namespace App\Enums;

/**
 * PermissionEnum — Định nghĩa các permission (quyền) trong hệ thống
 *
 * Format: {module}.{action}.{type}
 * Ví dụ: orders.view, orders.create, orders.update
 */
enum PermissionEnum: string
{
    // ======================================================
    // MODULE: ORDERS — Đơn hàng
    // ======================================================
    case ORDERS_VIEW    = 'orders.view';
    case ORDERS_CREATE  = 'orders.create';
    case ORDERS_UPDATE  = 'orders.update';
    case ORDERS_DELETE  = 'orders.delete';
    case ORDERS_EXPORT  = 'orders.export';
    case ORDERS_LOCK    = 'orders.lock';

    // ======================================================
    // MODULE: PICKUPS — Lấy hàng
    // ======================================================
    case PICKUPS_VIEW   = 'pickups.view';
    case PICKUPS_CREATE = 'pickups.create';
    case PICKUPS_UPDATE = 'pickups.update';

    // ======================================================
    // MODULE: PACKAGES — Lô hàng / Tải hàng
    // ======================================================
    case PACKAGES_VIEW   = 'packages.view';
    case PACKAGES_CREATE = 'packages.create';
    case PACKAGES_UPDATE = 'packages.update';
    case PACKAGES_SCAN   = 'packages.scan';

    // ======================================================
    // MODULE: CONGNO — Công nợ CTV
    // ======================================================
    case CONGNO_VIEW    = 'congno.view';
    case CONGNO_CREATE  = 'congno.create';
    case CONGNO_CONFIRM = 'congno.confirm';
    case CONGNO_PAID     = 'congno.paid';

    // ======================================================
    // MODULE: CONGNO_DAILY — Công nợ Đại lý / NCC
    // ======================================================
    case CONGNO_DAILY_VIEW   = 'congno_daily.view';
    case CONGNO_DAILY_CREATE = 'congno_daily.create';
    case CONGNO_DAILY_CONFIRM = 'congno_daily.confirm';
    case CONGNO_DAILY_PAID   = 'congno_daily.paid';

    // ======================================================
    // MODULE: THONGKE — Thống kê / Báo cáo
    // ======================================================
    case THONGKE_VIEW    = 'thongke.view';
    case THONGKE_EXPORT  = 'thongke.export';

    // ======================================================
    // MODULE: CUSTOMERS — Khách hàng
    // ======================================================
    case CUSTOMERS_VIEW    = 'customers.view';
    case CUSTOMERS_CREATE  = 'customers.create';
    case CUSTOMERS_UPDATE  = 'customers.update';
    case CUSTOMERS_DELETE  = 'customers.delete';

    // ======================================================
    // MODULE: ADDRESSES — Địa chỉ nhận
    // ======================================================
    case ADDRESSES_VIEW    = 'addresses.view';
    case ADDRESSES_CREATE  = 'addresses.create';
    case ADDRESSES_UPDATE  = 'addresses.update';
    case ADDRESSES_DELETE  = 'addresses.delete';

    // ======================================================
    // MODULE: CTV — Cộng tác viên
    // ======================================================
    case CTV_VIEW    = 'ctv.view';
    case CTV_CREATE  = 'ctv.create';
    case CTV_UPDATE  = 'ctv.update';
    case CTV_DELETE  = 'ctv.delete';

    // ======================================================
    // MODULE: NHANSU — Nhân sự
    // ======================================================
    case NHANSU_VIEW    = 'nhansu.view';
    case NHANSU_CREATE  = 'nhansu.create';
    case NHANSU_UPDATE  = 'nhansu.update';
    case NHANSU_DELETE  = 'nhansu.delete';

    // ======================================================
    // MODULE: DULIEU — Dữ liệu tham chiếu
    // ======================================================
    case DULIEU_VIEW    = 'dulieu.view';
    case DULIEU_CREATE  = 'dulieu.create';
    case DULIEU_UPDATE  = 'dulieu.update';
    case DULIEU_DELETE  = 'dulieu.delete';

    // ======================================================
    // MODULE: CHINHSACH — Chính sách
    // ======================================================
    case CHINHSACH_VIEW   = 'chinhsach.view';
    case CHINHSACH_CREATE = 'chinhsach.create';
    case CHINHSACH_UPDATE = 'chinhsach.update';

    // ======================================================
    // MODULE: SETTINGS — Cấu hình hệ thống
    // ======================================================
    case SETTINGS_VIEW    = 'settings.view';
    case SETTINGS_UPDATE  = 'settings.update';

    // ======================================================
    // MODULE: PROFILE — Hồ sơ cá nhân
    // ======================================================
    case PROFILE_VIEW    = 'profile.view';
    case PROFILE_UPDATE  = 'profile.update';

    // ======================================================
    // MODULE: TRACKING — Theo dõi (công khai)
    // ======================================================
    case TRACKING_VIEW = 'tracking.view';

    // ======================================================
    // Methods
    // ======================================================

    /**
     * Module của permission (ví dụ: orders, pickups, congno...)
     */
    public function module(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * Action của permission (view, create, update, delete...)
     */
    public function action(): string
    {
        return explode('.', $this->value)[1];
    }

    /**
     * Label tiếng Việt
     */
    public function label(): string
    {
        return match ($this) {
            // Orders
            self::ORDERS_VIEW    => 'Xem đơn hàng',
            self::ORDERS_CREATE  => 'Tạo đơn hàng',
            self::ORDERS_UPDATE  => 'Cập nhật đơn hàng',
            self::ORDERS_DELETE  => 'Xóa đơn hàng',
            self::ORDERS_EXPORT  => 'Xuất đơn hàng',
            self::ORDERS_LOCK    => 'Khóa đơn hàng',

            // Pickups
            self::PICKUPS_VIEW   => 'Xem pickup',
            self::PICKUPS_CREATE => 'Tạo pickup',
            self::PICKUPS_UPDATE => 'Cập nhật pickup',

            // Packages
            self::PACKAGES_VIEW   => 'Xem tải hàng',
            self::PACKAGES_CREATE => 'Tạo lô hàng',
            self::PACKAGES_UPDATE => 'Cập nhật lô hàng',
            self::PACKAGES_SCAN   => 'Quét barcode',

            // CongNo
            self::CONGNO_VIEW    => 'Xem công nợ CTV',
            self::CONGNO_CREATE  => 'Tạo công nợ CTV',
            self::CONGNO_CONFIRM => 'Chốt công nợ CTV',
            self::CONGNO_PAID    => 'Thanh toán công nợ CTV',

            // CongNo Daily
            self::CONGNO_DAILY_VIEW    => 'Xem công nợ đại lý',
            self::CONGNO_DAILY_CREATE  => 'Tạo công nợ đại lý',
            self::CONGNO_DAILY_CONFIRM => 'Chốt công nợ đại lý',
            self::CONGNO_DAILY_PAID    => 'Thanh toán công nợ đại lý',

            // Thống kê
            self::THONGKE_VIEW   => 'Xem thống kê',
            self::THONGKE_EXPORT => 'Xuất thống kê',

            // Khách hàng
            self::CUSTOMERS_VIEW   => 'Xem khách hàng',
            self::CUSTOMERS_CREATE => 'Tạo khách hàng',
            self::CUSTOMERS_UPDATE => 'Cập nhật khách hàng',
            self::CUSTOMERS_DELETE => 'Xóa khách hàng',

            // Địa chỉ nhận
            self::ADDRESSES_VIEW   => 'Xem địa chỉ nhận',
            self::ADDRESSES_CREATE => 'Tạo địa chỉ nhận',
            self::ADDRESSES_UPDATE => 'Cập nhật địa chỉ nhận',
            self::ADDRESSES_DELETE => 'Xóa địa chỉ nhận',

            // CTV
            self::CTV_VIEW   => 'Xem cộng tác viên',
            self::CTV_CREATE => 'Tạo cộng tác viên',
            self::CTV_UPDATE => 'Cập nhật cộng tác viên',
            self::CTV_DELETE => 'Xóa cộng tác viên',

            // Nhân sự
            self::NHANSU_VIEW   => 'Xem nhân sự',
            self::NHANSU_CREATE => 'Tạo nhân sự',
            self::NHANSU_UPDATE => 'Cập nhật nhân sự',
            self::NHANSU_DELETE => 'Xóa nhân sự',

            // Dữ liệu
            self::DULIEU_VIEW   => 'Xem dữ liệu',
            self::DULIEU_CREATE => 'Tạo dữ liệu',
            self::DULIEU_UPDATE => 'Cập nhật dữ liệu',
            self::DULIEU_DELETE => 'Xóa dữ liệu',

            // Chính sách
            self::CHINHSACH_VIEW    => 'Xem chính sách',
            self::CHINHSACH_CREATE  => 'Tạo chính sách',
            self::CHINHSACH_UPDATE  => 'Cập nhật chính sách',

            // Cấu hình
            self::SETTINGS_VIEW   => 'Xem cấu hình',
            self::SETTINGS_UPDATE => 'Cập nhật cấu hình',

            // Profile
            self::PROFILE_VIEW   => 'Xem hồ sơ',
            self::PROFILE_UPDATE => 'Cập nhật hồ sơ',

            // Tracking
            self::TRACKING_VIEW => 'Theo dõi đơn',
        };
    }

    /**
     * Tất cả permission values (dùng cho seeder)
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Theo module
     */
    public static function byModule(string $module): array
    {
        return array_filter(
            self::values(),
            fn ($perm) => str_starts_with($perm, $module . '.')
        );
    }
}
