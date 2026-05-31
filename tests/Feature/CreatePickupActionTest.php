<?php

namespace Tests\Feature;

use App\Actions\Pickup\CreatePickupAction;
use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\OrderStatusEnum;
use App\Enums\PickupStatusEnum;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CreatePickupActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('pickup_orders');
        Schema::dropIfExists('pickup');
        Schema::dropIfExists('order_package');
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('bill_status');
            $table->json('sender')->nullable();
            $table->timestamps();
        });

        Schema::create('order_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('number_of_package')->default(1);
            $table->decimal('c_weight', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('pickup', function (Blueprint $table) {
            $table->id();
            $table->string('ma_pickup')->unique();
            $table->unsignedInteger('id_user')->nullable();
            $table->timestamp('ngay_tao')->nullable();
            $table->decimal('total_weight', 12, 2)->default(0);
            $table->decimal('total_c_weight', 12, 2)->default(0);
            $table->string('status');
            $table->unsignedInteger('numb')->default(0);
            $table->text('note')->nullable();
            $table->json('info_pickup')->nullable();
            $table->json('info_khachhang')->nullable();
            $table->timestamps();
        });

        Schema::create('pickup_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_id')->constrained('pickup')->cascadeOnDelete();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('added_by')->nullable();
            $table->timestamps();
            $table->unique('id_order');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pickup_orders');
        Schema::dropIfExists('pickup');
        Schema::dropIfExists('order_package');
        Schema::dropIfExists('orders');

        parent::tearDown();
    }

    public function test_it_creates_a_pickup_from_a_confirmed_order_and_keeps_sender_snapshot_separate(): void
    {
        $order = $this->createOrder(OrderStatusEnum::DA_XAC_NHAN);

        DB::table('order_package')->insert([
            'id_order' => $order->id,
            'number_of_package' => 2,
            'c_weight' => 3.5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pickup = CreatePickupAction::execute($order, [
            'sender_snapshot' => [
                'company' => 'Snapshot Company',
                'fullname' => 'Snapshot Sender',
                'phone' => '0909000000',
                'email' => 'snapshot@example.com',
                'country' => 'VIETNAM',
                'address' => 'Pickup address',
                'id_city' => 1,
                'id_ward' => 2,
            ],
            'vehicle_id' => 10,
            'scheduled_at' => '2026-05-31 15:31:00',
            'labor_cost' => 150000,
        ], 99);

        $this->assertStringStartsWith('PICK', $pickup->ma_pickup);
        $this->assertSame('Snapshot Company', data_get($pickup->info_khachhang, 'company'));
        $this->assertSame('Original Company', data_get($order->fresh()->sender, 'company'));
        $this->assertSame(1, $pickup->orders()->count());
        $this->assertSame('3.50', $pickup->total_weight);
        $this->assertSame(2, $pickup->numb);
    }

    public function test_it_rejects_an_order_that_is_not_confirmed(): void
    {
        $order = $this->createOrder(OrderStatusEnum::MOI_TAO);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Chỉ được tạo Pickup cho đơn đã xác nhận.');

        CreatePickupAction::execute($order, [
            'sender_snapshot' => [],
        ], 99);
    }

    public function test_it_rejects_a_second_pickup_for_the_same_order(): void
    {
        $order = $this->createOrder(OrderStatusEnum::DA_XAC_NHAN);

        CreatePickupAction::execute($order, [
            'sender_snapshot' => [],
        ], 99);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Đơn hàng đã có phiếu Pickup.');

        CreatePickupAction::execute($order, [
            'sender_snapshot' => [],
        ], 99);
    }

    public function test_it_transitions_pickup_through_the_operational_flow(): void
    {
        $pickup = CreatePickupAction::execute($this->createOrder(OrderStatusEnum::DA_XAC_NHAN), [
            'sender_snapshot' => [],
        ], 99);

        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::DA_XAC_NHAN);
        $this->assertSame(PickupStatusEnum::DA_XAC_NHAN, $pickup->status);

        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::PICKUP_DANG_LAY);
        $this->assertSame(PickupStatusEnum::PICKUP_DANG_LAY, $pickup->status);

        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::PICKUP_DA_LAY);
        $this->assertSame(PickupStatusEnum::PICKUP_DA_LAY, $pickup->status);
    }

    public function test_it_cancels_an_open_pickup(): void
    {
        $pickup = CreatePickupAction::execute($this->createOrder(OrderStatusEnum::DA_XAC_NHAN), [
            'sender_snapshot' => [],
        ], 99);

        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::DA_HUY);

        $this->assertSame(PickupStatusEnum::DA_HUY, $pickup->status);
    }

    public function test_it_rejects_changes_after_pickup_has_been_collected(): void
    {
        $pickup = CreatePickupAction::execute($this->createOrder(OrderStatusEnum::DA_XAC_NHAN), [
            'sender_snapshot' => [],
        ], 99);

        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::DA_XAC_NHAN);
        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::PICKUP_DANG_LAY);
        $pickup = TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::PICKUP_DA_LAY);

        $this->expectException(RuntimeException::class);

        TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::DA_HUY);
    }

    protected function createOrder(OrderStatusEnum $status): Order
    {
        $id = DB::table('orders')->insertGetId([
            'bill_status' => $status->value,
            'sender' => json_encode([
                'company' => 'Original Company',
                'fullname' => 'Original Sender',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::query()->findOrFail($id);
    }
}
