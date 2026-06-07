<?php

namespace Tests\Feature;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ThirdPartyOrderTrackingApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('order_package');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('news');
        Schema::dropIfExists('setting');

        Schema::create('setting', function (Blueprint $table): void {
            $table->id();
            $table->string('namevi')->nullable();
            $table->json('options')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('id_bill')->unique();
            $table->string('tracking_code')->nullable();
            $table->string('bill_status');
            $table->json('receiver')->nullable();
            $table->json('service')->nullable();
            $table->timestamps();
        });

        Schema::create('order_package', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->decimal('c_weight', 12, 3)->default(0);
            $table->decimal('row_c_weight', 12, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('news', function (Blueprint $table): void {
            $table->id();
            $table->string('namevi');
            $table->string('type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('order_package');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('news');
        Schema::dropIfExists('setting');

        parent::tearDown();
    }

    public function test_disabled_tracking_api_is_hidden(): void
    {
        $this->postJson('/api/third-party/orders/tracking', ['id_bill' => 'HD001'])
            ->assertNotFound();
    }

    public function test_it_rejects_missing_or_invalid_api_key(): void
    {
        $this->enableApi();

        $this->postJson('/api/third-party/orders/tracking', ['id_bill' => 'HD001'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);

        $this->withHeader('X-API-Key', 'wrong-key')
            ->postJson('/api/third-party/orders/tracking', ['id_bill' => 'HD001'])
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_it_rejects_blocked_ip(): void
    {
        $this->enableApi([
            'third_party_tracking_api_blocked_ips' => '203.0.113.10',
        ]);

        $this->withHeader('X-API-Key', 'secret-key')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/third-party/orders/tracking', ['id_bill' => 'HD001'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_it_returns_order_tracking_payload(): void
    {
        $this->enableApi();
        $order = $this->createOrder();

        DB::table('order_package')->insert([
            [
                'id_order' => $order->id,
                'c_weight' => 2.25,
                'row_c_weight' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_order' => $order->id,
                'c_weight' => 1.1,
                'row_c_weight' => 3.345,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withHeader('X-API-Key', 'secret-key')
            ->postJson('/api/third-party/orders/tracking', ['id_bill' => 'HD001'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_bill', 'HD001')
            ->assertJsonPath('data.tracking_code', 'TRK001')
            ->assertJsonPath('data.status.code', OrderStatusEnum::DANG_PHAT_HANG->value)
            ->assertJsonPath('data.status.label', OrderStatusEnum::DANG_PHAT_HANG->label())
            ->assertJsonPath('data.chargeable_weight.value', 5.595)
            ->assertJsonPath('data.chargeable_weight.unit', 'kg')
            ->assertJsonPath('data.receiver.fullname', 'Nguyen Van A')
            ->assertJsonPath('data.receiver.phone', '0909000000')
            ->assertJsonPath('data.receiver.country_id', 84)
            ->assertJsonPath('data.service.main.name', 'Bay nhanh')
            ->assertJsonPath('data.service.detail.name', 'Giao tận nhà')
            ->assertJsonPath('data.service.shipment_type.name', 'Hàng hóa');
    }

    private function enableApi(array $options = []): void
    {
        Setting::create([
            'namevi' => 'Cau hinh he thong',
            'options' => array_merge([
                'third_party_tracking_api_enabled' => true,
                'third_party_tracking_api_key' => 'secret-key',
                'third_party_tracking_api_blocked_ips' => '',
                'third_party_tracking_api_rate_limit_per_minute' => 60,
            ], $options),
        ]);
    }

    private function createOrder(): Order
    {
        DB::table('news')->insert([
            ['id' => 11, 'namevi' => 'Bay nhanh', 'type' => 'service', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'namevi' => 'Giao tận nhà', 'type' => 'service-detail', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 13, 'namevi' => 'Hàng hóa', 'type' => 'shipment-type', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $id = DB::table('orders')->insertGetId([
            'id_bill' => 'HD001',
            'tracking_code' => 'TRK001',
            'bill_status' => OrderStatusEnum::DANG_PHAT_HANG->value,
            'receiver' => json_encode([
                'company' => 'Cong ty A',
                'fullname' => 'Nguyen Van A',
                'phone' => '0909000000',
                'email' => 'a@example.com',
                'address' => '123 Test',
                'city' => 'Ho Chi Minh',
                'state' => 'TP.HCM',
                'postcode' => '700000',
                'country' => 'Viet Nam',
                'country_id' => 84,
            ], JSON_UNESCAPED_UNICODE),
            'service' => json_encode([
                'id_dichvu' => 11,
                'id_chitiet_dichvu' => 12,
                'loaibuugui' => 13,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Order::query()->findOrFail($id);
    }
}
