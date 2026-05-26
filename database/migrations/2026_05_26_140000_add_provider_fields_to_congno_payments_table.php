<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['congno_payments', 'congno_daily_payments'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (! Schema::hasColumn($tableName, 'payment_provider')) {
                    $table->string('payment_provider')->nullable()->after('method')->index();
                }

                if (! Schema::hasColumn($tableName, 'payment_channel')) {
                    $table->string('payment_channel')->nullable()->after('payment_provider')->index();
                }

                if (! Schema::hasColumn($tableName, 'payment_reference')) {
                    $table->string('payment_reference')->nullable()->after('payment_channel')->index();
                }

                if (! Schema::hasColumn($tableName, 'payment_url')) {
                    $table->text('payment_url')->nullable()->after('payment_reference');
                }

                if (! Schema::hasColumn($tableName, 'provider_intent_id')) {
                    $table->string('provider_intent_id')->nullable()->after('payment_url')->index();
                }

                if (! Schema::hasColumn($tableName, 'provider_transaction_id')) {
                    $table->string('provider_transaction_id')->nullable()->after('provider_intent_id')->index();
                }

                if (! Schema::hasColumn($tableName, 'provider_payload')) {
                    $table->json('provider_payload')->nullable()->after('provider_transaction_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['congno_payments', 'congno_daily_payments'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                foreach ([
                    'payment_provider',
                    'payment_channel',
                    'payment_reference',
                    'payment_url',
                    'provider_intent_id',
                    'provider_transaction_id',
                    'provider_payload',
                ] as $column) {
                    if (Schema::hasColumn($tableName, $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
