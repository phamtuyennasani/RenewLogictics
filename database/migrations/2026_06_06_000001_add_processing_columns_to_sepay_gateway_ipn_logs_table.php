<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sepay_gateway_ipn_logs')) {
            Schema::table('sepay_gateway_ipn_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('sepay_gateway_ipn_logs', 'matched_congno_payment_id')) {
                    $table->unsignedBigInteger('matched_congno_payment_id')->nullable()->after('transaction_id')->index();
                }

                if (! Schema::hasColumn('sepay_gateway_ipn_logs', 'processed_status')) {
                    $table->string('processed_status')->nullable()->after('matched_congno_payment_id')->index();
                }

                if (! Schema::hasColumn('sepay_gateway_ipn_logs', 'processed_message')) {
                    $table->text('processed_message')->nullable()->after('processed_status');
                }

                if (! Schema::hasColumn('sepay_gateway_ipn_logs', 'processed_at')) {
                    $table->dateTime('processed_at')->nullable()->after('processed_message')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sepay_gateway_ipn_logs')) {
            Schema::table('sepay_gateway_ipn_logs', function (Blueprint $table) {
                foreach (['matched_congno_payment_id', 'processed_status', 'processed_message', 'processed_at'] as $column) {
                    if (Schema::hasColumn('sepay_gateway_ipn_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
