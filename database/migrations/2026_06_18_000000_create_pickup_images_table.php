<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pickup_images')) {
            return;
        }

        Schema::create('pickup_images', function (Blueprint $table) {
            $table->id();
            // table_pickup.id là kiểu signed integer (DB legacy) — phải khớp đúng
            // kiểu để tạo được khóa ngoại. Xem pickup_orders.pickup_id cùng pattern.
            $table->integer('pickup_id')->index();
            $table->string('path');
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('pickup_id')
                ->references('id')->on('pickup')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_images');
    }
};
