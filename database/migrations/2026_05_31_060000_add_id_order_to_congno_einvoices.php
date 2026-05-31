<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congno_einvoices', function (Blueprint $table) {
            // Cho phép tạo hóa đơn điện tử cho đơn lẻ (id_order) — không bắt buộc id_congno.
            $table->unsignedBigInteger('id_order')->nullable()->after('id_congno')->index();
        });

        // Drop FK + cho phép id_congno nullable (đơn lẻ không có congno)
        try {
            Schema::table('congno_einvoices', function (Blueprint $table) {
                $table->dropForeign(['id_congno']);
            });
        } catch (\Throwable) {
            // FK có thể đã bị drop trước, bỏ qua
        }

        Schema::table('congno_einvoices', function (Blueprint $table) {
            $table->unsignedBigInteger('id_congno')->nullable()->change();
        });

        Schema::table('congno_einvoices', function (Blueprint $table) {
            // Re-create FK với cascade
            $table->foreign('id_congno')
                ->references('id')->on('congno')
                ->cascadeOnDelete();

            // FK orders (nullOnDelete để giữ history)
            $table->foreign('id_order')
                ->references('id')->on('orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('congno_einvoices', function (Blueprint $table) {
            try {
                $table->dropForeign(['id_order']);
            } catch (\Throwable) {}
            $table->dropColumn('id_order');
        });

        try {
            Schema::table('congno_einvoices', function (Blueprint $table) {
                $table->dropForeign(['id_congno']);
            });
        } catch (\Throwable) {}

        Schema::table('congno_einvoices', function (Blueprint $table) {
            $table->unsignedBigInteger('id_congno')->nullable(false)->change();
            $table->foreign('id_congno')
                ->references('id')->on('congno')
                ->cascadeOnDelete();
        });
    }
};
