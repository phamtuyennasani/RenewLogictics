<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('momo_webhook_logs')) {
            Schema::create('momo_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('event_id', 64)->nullable()->index();
                $table->string('order_id', 64)->nullable()->index();
                $table->bigInteger('amount')->nullable();
                $table->string('trans_id', 64)->nullable()->index();
                $table->string('result_code', 16)->nullable();
                $table->string('message', 255)->nullable();
                $table->string('payment_option', 64)->nullable();
                $table->dateTime('response_time')->nullable();
                $table->foreignId('matched_congno_payment_id')->nullable()->constrained('congno_payments')->nullOnDelete();
                $table->string('processed_status', 32)->nullable()->index();
                $table->string('processed_message', 255)->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->json('payload')->nullable();
                $table->json('headers')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->timestamps();

                $table->index(['order_id', 'result_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('momo_webhook_logs');
    }
};
