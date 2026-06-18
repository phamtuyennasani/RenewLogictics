<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột GPS check-in cho bảng pickup: toạ độ shipper lúc xác nhận đã lấy
 * hàng (chống khai khống). Lưu lần check-in cuối + thời điểm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pickup', function (Blueprint $table) {
            if (! Schema::hasColumn('pickup', 'pickup_lat')) {
                $table->decimal('pickup_lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('pickup', 'pickup_lng')) {
                $table->decimal('pickup_lng', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('pickup', 'pickup_checkin_at')) {
                $table->timestamp('pickup_checkin_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pickup', function (Blueprint $table) {
            foreach (['pickup_lat', 'pickup_lng', 'pickup_checkin_at'] as $column) {
                if (Schema::hasColumn('pickup', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
