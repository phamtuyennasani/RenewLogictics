<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Filter by status + sort by id (statusCounts GROUP BY, main query WHERE)
            $table->index(['bill_status', 'id'], 'orders_bill_status_id_index');

            // Role-based filter: sale
            $table->index('id_sale', 'orders_id_sale_index');

            // Role-based filter: customer
            $table->index('id_customer', 'orders_id_customer_index');

            // Role-based filter: CS
            $table->index('id_cs', 'orders_id_cs_index');

            // Date range filter
            $table->index('created_at', 'orders_created_at_index');

            // Search by tracking_code (prefix LIKE)
            $table->index('tracking_code', 'orders_tracking_code_index');

            // Search by mathamchieu (prefix LIKE)
            $table->index('mathamchieu', 'orders_mathamchieu_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_bill_status_id_index');
            $table->dropIndex('orders_id_sale_index');
            $table->dropIndex('orders_id_customer_index');
            $table->dropIndex('orders_id_cs_index');
            $table->dropIndex('orders_created_at_index');
            $table->dropIndex('orders_tracking_code_index');
            $table->dropIndex('orders_mathamchieu_index');
        });
    }
};