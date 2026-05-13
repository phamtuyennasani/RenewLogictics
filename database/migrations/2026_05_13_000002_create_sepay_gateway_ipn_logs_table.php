<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sepay_gateway_ipn_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();
            $table->string('notification_type')->nullable();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('invoice_number')->nullable()->index();
            $table->string('transaction_id')->nullable()->index();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sepay_gateway_ipn_logs');
    }
};
