<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lưới an toàn cuối cho generateMemberCode(): unique index trên member.code
 * chặn trùng mã CUSxxxxxx ở tầng DB (code nullable — nhiều NULL vẫn hợp lệ).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pre-check: nếu dữ liệu hiện có đã trùng code thì fail với thông báo
        // rõ ràng để làm sạch trước, thay vì lỗi SQL khó hiểu giữa chừng.
        $duplicates = DB::table('member')
            ->select('code', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->groupBy('code')
            ->having('cnt', '>', 1)
            ->pluck('cnt', 'code');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Không thể thêm unique index: bảng member đang có mã trùng ['
                .$duplicates->keys()->take(20)->implode(', ')
                .']. Làm sạch dữ liệu trước rồi chạy lại migration.'
            );
        }

        Schema::table('member', function (Blueprint $table) {
            $table->unique('code', 'member_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->dropUnique('member_code_unique');
        });
    }
};
