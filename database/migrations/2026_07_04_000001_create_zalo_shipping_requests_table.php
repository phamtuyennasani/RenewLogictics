<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zalo_shipping_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('requester_name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('pickup_address', 500);
            $table->string('pickup_city')->nullable();
            $table->foreignId('receiver_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('receiver_country')->nullable();
            // news.id là int unsigned (bảng legacy) — không dùng foreignId (bigint) để FK không bị lỗi kiểu.
            $table->unsignedInteger('service_id')->nullable();
            $table->foreign('service_id')->references('id')->on('news')->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->unsignedInteger('package_count')->default(1);
            $table->decimal('weight_kg', 10, 3);
            $table->decimal('length_cm', 10, 2)->nullable();
            $table->decimal('width_cm', 10, 2)->nullable();
            $table->decimal('height_cm', 10, 2)->nullable();
            $table->text('note')->nullable();
            $table->json('quote_snapshot')->nullable();
            $table->string('zalo_user_id')->nullable()->index();
            $table->string('status', 30)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zalo_shipping_requests');
    }
};
