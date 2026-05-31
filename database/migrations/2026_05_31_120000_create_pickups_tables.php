<?php

use App\Enums\PickupStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pickup')) {
            Schema::create('pickup', function (Blueprint $table) {
                $table->id();
                $table->string('ma_pickup')->unique();
                $table->unsignedInteger('id_user')->nullable();
                $table->unsignedInteger('id_ctv')->nullable();
                $table->unsignedInteger('id_shipper')->nullable();
                $table->unsignedInteger('id_ketoan')->nullable();
                $table->unsignedInteger('id_xuatkho')->nullable();
                $table->unsignedBigInteger('id_status')->nullable();
                $table->timestamp('ngay_tao')->nullable();
                $table->timestamp('ngay_nhanhang')->nullable();
                $table->timestamp('ngay_xuatkho')->nullable();
                $table->decimal('total_weight', 12, 2)->default(0);
                $table->decimal('total_dim', 12, 2)->default(0);
                $table->decimal('total_c_weight', 12, 2)->default(0);
                $table->decimal('total_dim_thucte', 12, 2)->default(0);
                $table->decimal('total_cuoc', 15, 0)->default(0);
                $table->decimal('total_cuocvon', 15, 0)->default(0);
                $table->decimal('total_cuocban', 15, 0)->default(0);
                $table->string('status')->default(PickupStatusEnum::MOI_TAO_PICKUP->value)->index();
                $table->unsignedInteger('numb')->default(0);
                $table->text('note')->nullable();
                $table->json('options')->nullable();
                $table->json('info_pickup')->nullable();
                $table->json('info_khachhang')->nullable();
                $table->timestamps();
            });
        } else {
            $this->upgradeLegacyPickupTable();
        }

        if (! Schema::hasTable('pickup_orders')) {
            Schema::create('pickup_orders', function (Blueprint $table) {
                $table->id();
                $table->integer('pickup_id');
                $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
                $table->unsignedInteger('added_by')->nullable();
                $table->timestamps();

                $table->foreign('pickup_id')->references('id')->on('pickup')->cascadeOnDelete();
                $table->unique('id_order', 'pickup_orders_order_unique');
                $table->unique(['pickup_id', 'id_order'], 'pickup_orders_pickup_order_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_orders');
    }

    protected function upgradeLegacyPickupTable(): void
    {
        $columns = [
            'ma_pickup' => fn (Blueprint $table) => $table->string('ma_pickup')->nullable()->unique(),
            'ngay_tao' => fn (Blueprint $table) => $table->timestamp('ngay_tao')->nullable(),
            'ngay_xuatkho' => fn (Blueprint $table) => $table->timestamp('ngay_xuatkho')->nullable(),
            'total_weight' => fn (Blueprint $table) => $table->decimal('total_weight', 12, 2)->default(0),
            'total_dim' => fn (Blueprint $table) => $table->decimal('total_dim', 12, 2)->default(0),
            'total_c_weight' => fn (Blueprint $table) => $table->decimal('total_c_weight', 12, 2)->default(0),
            'total_dim_thucte' => fn (Blueprint $table) => $table->decimal('total_dim_thucte', 12, 2)->default(0),
            'total_cuoc' => fn (Blueprint $table) => $table->decimal('total_cuoc', 15, 0)->default(0),
            'total_cuocvon' => fn (Blueprint $table) => $table->decimal('total_cuocvon', 15, 0)->default(0),
            'total_cuocban' => fn (Blueprint $table) => $table->decimal('total_cuocban', 15, 0)->default(0),
            'status' => fn (Blueprint $table) => $table->string('status')->default(PickupStatusEnum::MOI_TAO_PICKUP->value)->index(),
            'numb' => fn (Blueprint $table) => $table->unsignedInteger('numb')->default(0),
            'note' => fn (Blueprint $table) => $table->text('note')->nullable(),
            'options' => fn (Blueprint $table) => $table->json('options')->nullable(),
        ];

        foreach ($columns as $column => $addColumn) {
            if (Schema::hasColumn('pickup', $column)) {
                continue;
            }

            Schema::table('pickup', $addColumn);
        }
    }
};
