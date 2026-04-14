<?php

namespace App\Enums;

/**
 * RoleEnum — Định nghĩa các role trong hệ thống
 *
 * Mapping theo hệ thống cũ:
 * - ADMIN   → Toàn quyền
 * - MANAGER → Quản lý
 * - KETOAN  → Kế toán
 * - CS      → Chăm sóc khách hàng
 * - SALE    → Kinh doanh
 * - OPS     → Vận hành
 * - CTV     → Cộng tác viên
 * - SHIPPER → Shipper / Tài xế
 */

// Luồng Phân Quyền:
// Admin được phép tất cả, tạo đơn, xóa đơn, quản lý công nợ, tạo công nợ, xóa công nợ, xem nhân sự, xóa nhân sự, xóa cộng tác viên...
// Manager được phép tạo đơn, xóa đơn, quản lý công nợ, tạo công nợ, xóa công nợ, xem nhân sự,
// Sale được phép tạo đơn, tạo công nợ, xem công nợ. Chỉ được xóa đơn khi chưa xác nhận (đơn mới tạo), xóa công nợ ở trạng thái "Mới tạo"
// Kế toán được phép quản lý công nợ, tạo công nợ, xóa công nợ (chỉ khi ở trạng thái "Mới tạo"), được phép cập nhật trạng thái công nợ (chuyển từ "Mới tạo" sang "Đang xử lý", hoặc từ "Đang xử lý" sang "Đã thanh toán")
// CTV được phép tạo đơn, xem công nợ của chính mình.
// CS được phép tạo đơn, Xóa đơn hàng mới tạo.
// Ops chỉnh được xem đơn và cập nhật trạng thái từ Đã xác nhận sang đã nhận hàng.
enum RoleEnum: string
{
    case ADMIN   = 'admin';
    case MANAGER = 'manager';
    case KETOAN  = 'ketoan';
    case CS      = 'cs';
    case SALE    = 'sale';
    case OPS     = 'ops';
    case CTV     = 'ctv';
    case SHIPPER = 'shipper';

    /**
     * Label hiển thị cho từng role
     */
    public static function label(string $value): string
    {
        return match ($value) {
            self::ADMIN->value   => 'Quản trị viên',
            self::MANAGER->value => 'Quản lý',
            self::KETOAN->value  => 'Kế toán',
            self::CS->value       => 'Chăm sóc khách hàng',
            self::SALE->value    => 'Kinh doanh',
            self::OPS->value     => 'Vận hành',
            self::CTV->value     => 'Cộng tác viên',
            self::SHIPPER->value => 'Shipper',
        };
    }

    /**
     * Màu hiển thị (CSS class / hex)
     */
    public function color(): string
    {
        return match ($this) {
            self::ADMIN   => '#ef4444', // Đỏ
            self::MANAGER => '#f97316', // Cam
            self::KETOAN  => '#a855f7', // Tím
            self::CS      => '#06b6d4', // Cyan
            self::SALE    => '#3b82f6', // Xanh dương
            self::OPS     => '#10b981', // Xanh lá
            self::CTV     => '#eab308', // Vàng
            self::SHIPPER => '#64748b', // Xám
        };
    }

    /**
     * Icon SVG path (optional)
     */
    public function icon(): string
    {
        return match ($this) {
            self::ADMIN   => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
            self::MANAGER => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            self::KETOAN  => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
            self::CS      => 'M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z',
            self::SALE    => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            self::OPS     => 'M13 10V3L4 14h7v7l9-11h-7z',
            self::CTV     => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
            self::SHIPPER => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
        };
    }

    /**
     * Role nào được phép thấy nhóm "Nhân sự"
     */
    public static function canSeeNhanSu(): array
    {
        return [
            self::ADMIN->value,
            self::MANAGER->value,
        ];
    }

    /**
     * Role nào được phép tạo đơn
     */
    public static function canCreateOrder(): array
    {
        return [
            self::ADMIN->value,
            self::CS->value,
            self::SALE->value,
            self::CTV->value,
        ];
    }
    /**
     * Role nào được phép xóa đơn
     */
    public static function canDeleteOrder(): array
    {
        return [
            self::ADMIN->value,
            self::MANAGER->value,
        ];
    }

    /**
     * Role nào được phép quản lý công nợ
     */
    public static function canViewCongNo(): array
    {
        return [
            self::ADMIN->value,
            self::MANAGER->value,
            self::SALE->value,
            self::KETOAN->value,
            self::CTV->value,
        ];
    }

    /**
     * Role nào được phép tạo công nợ
     */
    public static function canCreateCongNo(): array
    {
        return [
            self::ADMIN->value,
            self::MANAGER->value,
            self::SALE->value,
            self::KETOAN->value,
        ];
    }
    /**
     * Role nào được phép xóa công nợ
     */
    public static function canDeleteCongNo(): array
    {
        return [
            self::ADMIN->value,
            self::MANAGER->value,
            self::KETOAN->value,
        ];
    }

    /**
     * Tất cả các role values (dùng cho config)
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}