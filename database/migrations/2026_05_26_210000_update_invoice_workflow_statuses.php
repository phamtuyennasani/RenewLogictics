<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('congno_payments')) {
            Schema::table('congno_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('congno_payments', 'payment_rejection_reason')) {
                    $table->string('payment_rejection_reason', 500)->nullable()->after('cancel_reason');
                }

                if (! Schema::hasColumn('congno_payments', 'payment_rejected_at')) {
                    $table->dateTime('payment_rejected_at')->nullable()->after('payment_rejection_reason');
                }

                if (! Schema::hasColumn('congno_payments', 'payment_rejected_by')) {
                    $table->unsignedBigInteger('payment_rejected_by')->nullable()->index()->after('payment_rejected_at');
                }
            });

            DB::table('congno_payments')
                ->where('status', 'moi_tao')
                ->update(['status' => 'cho_duyet']);
        }

        if (Schema::hasTable('congno_daily_payments')) {
            DB::table('congno_daily_payments')
                ->where('status', 'moi_tao')
                ->update(['status' => 'cho_duyet']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('congno_payments')) {
            DB::table('congno_payments')
                ->where('status', 'cho_duyet')
                ->update(['status' => 'moi_tao']);

            Schema::table('congno_payments', function (Blueprint $table) {
                if (Schema::hasColumn('congno_payments', 'payment_rejected_by')) {
                    $table->dropColumn('payment_rejected_by');
                }

                if (Schema::hasColumn('congno_payments', 'payment_rejected_at')) {
                    $table->dropColumn('payment_rejected_at');
                }

                if (Schema::hasColumn('congno_payments', 'payment_rejection_reason')) {
                    $table->dropColumn('payment_rejection_reason');
                }
            });
        }

        if (Schema::hasTable('congno_daily_payments')) {
            DB::table('congno_daily_payments')
                ->where('status', 'cho_duyet')
                ->update(['status' => 'moi_tao']);
        }
    }
};
