<?php

use App\Enums\ShipmentLoadStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_loads', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('status')->default(ShipmentLoadStatusEnum::MOI_TAO->value)->index();
            $table->foreignId('created_by')->nullable()->constrained('user')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('user')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_chargeable_weight', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['created_at', 'status'], 'shipment_loads_created_status_index');
        });

        Schema::create('shipment_load_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_load_id')->constrained('shipment_loads')->cascadeOnDelete();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('user')->nullOnDelete();
            $table->timestamps();

            $table->unique('id_order', 'shipment_load_orders_order_unique');
            $table->unique(['shipment_load_id', 'id_order'], 'shipment_load_orders_load_order_unique');
        });

        Schema::create('shipment_load_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_load_id')->constrained('shipment_loads')->cascadeOnDelete();
            $table->foreignId('id_user')->nullable()->constrained('user')->nullOnDelete();
            $table->timestamp('thoigian');
            $table->string('diadiem');
            $table->string('trangthai');
            $table->text('ghichu')->nullable();
            $table->timestamps();

            $table->index(['shipment_load_id', 'thoigian'], 'shipment_load_histories_load_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_load_histories');
        Schema::dropIfExists('shipment_load_orders');
        Schema::dropIfExists('shipment_loads');
    }
};

