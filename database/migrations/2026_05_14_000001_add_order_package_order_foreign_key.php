<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_package')) {
            return;
        }

        if (! Schema::hasColumn('order_package', 'id_order')) {
            return;
        }

        Schema::table('order_package', function (Blueprint $table) {
            $table->foreign('id_order')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_package') || ! Schema::hasColumn('order_package', 'id_order')) {
            return;
        }

        Schema::table('order_package', function (Blueprint $table) {
            $table->dropForeign(['id_order']);
        });
    }
};
