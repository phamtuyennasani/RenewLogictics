<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'id_thamchieu')) {
                    $table->string('id_thamchieu')->nullable()->after('tracking_code');
                }

                if (! Schema::hasColumn('orders', 'mathamchieu')) {
                    $table->string('mathamchieu')->nullable()->after('id_thamchieu');
                }

                if (! Schema::hasColumn('orders', 'trackingmore_id')) {
                    $table->string('trackingmore_id')->nullable()->after('mathamchieu');
                }
            });
        }

        if (Schema::hasTable('order_package')) {
            Schema::table('order_package', function (Blueprint $table) {
                if (! Schema::hasColumn('order_package', 'id_thamchieu')) {
                    $table->string('id_thamchieu')->nullable()->after('number_of_package');
                }

                if (! Schema::hasColumn('order_package', 'mathamchieu')) {
                    $table->string('mathamchieu')->nullable()->after('id_thamchieu');
                }

                if (! Schema::hasColumn('order_package', 'tracking_id')) {
                    $table->string('tracking_id')->nullable()->after('mathamchieu');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_package')) {
            Schema::table('order_package', function (Blueprint $table) {
                foreach (['tracking_id', 'mathamchieu', 'id_thamchieu'] as $column) {
                    if (Schema::hasColumn('order_package', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach (['trackingmore_id', 'mathamchieu', 'id_thamchieu'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
