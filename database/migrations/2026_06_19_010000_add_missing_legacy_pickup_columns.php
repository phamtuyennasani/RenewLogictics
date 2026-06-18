<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bù các cột mà nhánh nâng cấp bảng legacy (upgradeLegacyPickupTable) bỏ sót:
 * ngay_nhanhang, info_pickup, info_khachhang. Bản create bảng mới có sẵn 3 cột
 * này nhưng DB legacy (table_pickup) thì chưa, gây lỗi "Unknown column
 * 'ngay_nhanhang'" khi shipper xác nhận đã lấy hàng. Guard hasColumn để an toàn
 * cho cả môi trường đã có sẵn cột.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup', function (Blueprint $table) {
            if (! Schema::hasColumn('pickup', 'ngay_nhanhang')) {
                $table->timestamp('ngay_nhanhang')->nullable();
            }
            if (! Schema::hasColumn('pickup', 'info_pickup')) {
                $table->json('info_pickup')->nullable();
            }
            if (! Schema::hasColumn('pickup', 'info_khachhang')) {
                $table->json('info_khachhang')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pickup', function (Blueprint $table) {
            foreach (['ngay_nhanhang', 'info_pickup', 'info_khachhang'] as $column) {
                if (Schema::hasColumn('pickup', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
