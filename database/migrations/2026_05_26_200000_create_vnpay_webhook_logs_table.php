<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vnpay_webhook_logs')) {
            Schema::create('vnpay_webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('txn_ref', 64)->nullable()->index();
                $table->bigInteger('amount')->nullable();
                $table->string('bank_code', 32)->nullable();
                $table->string('bank_tran_no', 64)->nullable();
                $table->string('card_type', 32)->nullable();
                $table->string('response_code', 8)->nullable()->index();
                $table->string('transaction_no', 32)->nullable()->index();
                $table->string('transaction_status', 8)->nullable();
                $table->dateTime('pay_date')->nullable();
                $table->string('order_info', 255)->nullable();
                $table->foreignId('matched_congno_payment_id')->nullable()->constrained('congno_payments')->nullOnDelete();
                $table->string('processed_status', 32)->nullable()->index();
                $table->string('processed_message', 255)->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->json('payload')->nullable();
                $table->json('headers')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->timestamps();

                $table->index(['txn_ref', 'response_code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vnpay_webhook_logs');
    }
};
