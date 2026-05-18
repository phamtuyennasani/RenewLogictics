<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_package')) {
            return;
        }

        Schema::table('order_package', function (Blueprint $table) {
            if (! Schema::hasColumn('order_package', 'package_delivery_status')) {
                $table->string('package_delivery_status', 50)->nullable()->after('tracking_id');
            }

            if (! Schema::hasColumn('order_package', 'package_delivered_at')) {
                $table->timestamp('package_delivered_at')->nullable()->after('package_delivery_status');
            }

            if (! Schema::hasColumn('order_package', 'package_delivery_synced_at')) {
                $table->timestamp('package_delivery_synced_at')->nullable()->after('package_delivered_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_package')) {
            return;
        }

        Schema::table('order_package', function (Blueprint $table) {
            foreach (['package_delivery_synced_at', 'package_delivered_at', 'package_delivery_status'] as $column) {
                if (Schema::hasColumn('order_package', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
