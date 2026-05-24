<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasLockedAt = Schema::hasColumn('orders', 'sale_price_locked_at');

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'sale_price_locked_at')) {
                $table->timestamp('sale_price_locked_at')->nullable()->after('sale_success');
            }

            if (! Schema::hasColumn('orders', 'sale_price_locked_by')) {
                $table->unsignedBigInteger('sale_price_locked_by')->nullable()->after('sale_price_locked_at');
            }
        });

        if (! $hasLockedAt) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('sale_price_locked_at', 'orders_sale_price_locked_at_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'sale_price_locked_at')) {
                $table->dropIndex('orders_sale_price_locked_at_index');
            }

            if (Schema::hasColumn('orders', 'sale_price_locked_by')) {
                $table->dropColumn('sale_price_locked_by');
            }

            if (Schema::hasColumn('orders', 'sale_price_locked_at')) {
                $table->dropColumn('sale_price_locked_at');
            }
        });
    }
};
