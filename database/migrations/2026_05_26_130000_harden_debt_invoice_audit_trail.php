<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['congno', 'congno_daily'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        foreach (['congno_payments', 'congno_daily_payments'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('id_ketoan')->index();
                }

                if (! Schema::hasColumn($tableName, 'payment_confirmed_by')) {
                    $table->unsignedBigInteger('payment_confirmed_by')->nullable()->after('approved_by')->index();
                }

                if (! Schema::hasColumn($tableName, 'due_at')) {
                    $table->dateTime('due_at')->nullable()->after('paid_at')->index();
                }
            });
        }

        if (! Schema::hasTable('invoice_payment_logs')) {
            Schema::create('invoice_payment_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('congno_payment_id')->nullable()->index();
                $table->unsignedBigInteger('congno_daily_payment_id')->nullable()->index();
                $table->string('action')->index();
                $table->string('from_status')->nullable()->index();
                $table->string('to_status')->nullable()->index();
                $table->unsignedBigInteger('actor_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('sepay_webhook_logs')) {
            Schema::table('sepay_webhook_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('sepay_webhook_logs', 'matched_congno_payment_id')) {
                    $table->unsignedBigInteger('matched_congno_payment_id')->nullable()->after('transaction_id')->index();
                }

                if (! Schema::hasColumn('sepay_webhook_logs', 'processed_status')) {
                    $table->string('processed_status')->nullable()->after('matched_congno_payment_id')->index();
                }

                if (! Schema::hasColumn('sepay_webhook_logs', 'processed_message')) {
                    $table->text('processed_message')->nullable()->after('processed_status');
                }

                if (! Schema::hasColumn('sepay_webhook_logs', 'processed_at')) {
                    $table->dateTime('processed_at')->nullable()->after('processed_message')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sepay_webhook_logs')) {
            Schema::table('sepay_webhook_logs', function (Blueprint $table) {
                foreach (['matched_congno_payment_id', 'processed_status', 'processed_message', 'processed_at'] as $column) {
                    if (Schema::hasColumn('sepay_webhook_logs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('invoice_payment_logs');

        foreach (['congno_payments', 'congno_daily_payments'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach (['approved_by', 'payment_confirmed_by', 'due_at'] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        foreach (['congno', 'congno_daily'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'deleted_at')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
