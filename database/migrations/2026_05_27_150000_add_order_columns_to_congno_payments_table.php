<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congno_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('id_order')->nullable()->after('id_congno');
            $table->json('order_snapshot')->nullable()->after('provider_payload');

            $table->foreign('id_order')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();

            $table->index('id_order');
        });
    }

    public function down(): void
    {
        Schema::table('congno_payments', function (Blueprint $table) {
            $table->dropForeign(['id_order']);
            $table->dropIndex(['id_order']);
            $table->dropColumn(['id_order', 'order_snapshot']);
        });
    }
};
