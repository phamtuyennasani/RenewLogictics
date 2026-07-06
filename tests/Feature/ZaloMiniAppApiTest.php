<?php

namespace Tests\Feature;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Mobile\BuildsMobileSchema;
use Tests\TestCase;

class ZaloMiniAppApiTest extends TestCase
{
    use BuildsMobileSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildMobileSchema();
        $this->buildZaloMiniAppSchema();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('service_price_details');
        Schema::dropIfExists('service_price_list_countries');
        Schema::dropIfExists('service_price_lists');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('setting');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_sequences');
        Schema::dropIfExists('zalo_shipping_requests');
        Schema::dropIfExists('shipment_load_histories');
        Schema::dropIfExists('shipment_load_orders');
        Schema::dropIfExists('shipment_loads');

        $this->dropMobileSchema();

        parent::tearDown();
    }

    public function test_ctv_can_login_and_receives_zalo_mini_app_abilities(): void
    {
        $user = $this->makeUser(['ctv'], [
            'username' => 'ctv01',
            'password' => bcrypt('secret123'),
        ]);

        $this->postJson('/api/zalo-mini-app/auth/login', [
            'username' => 'ctv01',
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.roles.0', 'ctv')
            ->assertJsonPath('data.abilities.orders_create', true)
            ->assertJsonPath('data.abilities.orders_scope', 'ctv')
            ->assertJsonStructure(['data' => ['token', 'user', 'roles', 'abilities']]);
    }

    public function test_tracking_endpoint_masks_public_receiver_data(): void
    {
        $order = $this->makeOrder([
            'id_bill' => 'HDZALO001',
            'tracking_code' => 'TRKZALO001',
            'receiver' => [
                'fullname' => 'Nguyễn Văn Đức',
                'phone' => '0909123456',
                'email' => 'duc@example.com',
                'address' => '123 Secret Street',
                'city' => 'Tokyo',
                'country' => 'Japan',
            ],
        ]);

        DB::table('order_package')->insert([
            'id_order' => $order->id,
            'code' => 'PKG001',
            'c_weight' => 2.5,
            'row_c_weight' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order->histories()->create([
            'action' => 'tracking_status_auto',
            'thoigian' => now(),
            'trangthai' => 'Đang phát hàng',
            'diadiem' => 'Tokyo',
            'ghichu' => 'Đang giao tới người nhận',
            'main' => true,
        ]);

        $response = $this->getJson('/api/zalo-mini-app/tracking/TRKZALO001')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_bill', 'HDZALO001')
            ->assertJsonPath('data.receiver.name', 'N*** V*** Đ***')
            ->assertJsonPath('data.receiver.phone', '*******456')
            ->assertJsonPath('data.receiver.destination', 'Tokyo, Japan')
            ->assertJsonPath('data.chargeable_weight.value', 2.5);

        $payload = $response->json('data');
        $this->assertStringNotContainsString('0909123456', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('duc@example.com', json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('123 Secret Street', json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    public function test_shipping_request_does_not_trust_raw_zalo_user_id(): void
    {
        $this->postJson('/api/zalo-mini-app/shipping-requests', [
            'requester_name' => 'Nguyễn A',
            'phone' => '0900000000',
            'pickup_address' => 'HCM',
            'package_count' => 1,
            'weight_kg' => 1.25,
            'zalo_user_id' => 'client-forged-id',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('zalo_shipping_requests', [
            'phone' => '0900000000',
            'zalo_user_id' => null,
        ]);
    }

    public function test_cs_can_create_list_and_show_order_from_mini_app(): void
    {
        $user = $this->makeUser(['cs']);
        [$serviceId, $countryId] = $this->seedCatalog();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/zalo-mini-app/orders', [
                'service_id' => $serviceId,
                'country_id' => $countryId,
                'sender' => [
                    'name' => 'Sender Mini',
                    'phone' => '0900000001',
                    'address' => 'Ho Chi Minh',
                ],
                'receiver' => [
                    'name' => 'Receiver Mini',
                    'phone' => '0800000002',
                    'address' => '1 Market St',
                    'city' => 'San Francisco',
                    'state' => 'CA',
                    'postcode' => '94105',
                ],
                'packages' => [
                    [
                        'number_of_package' => 1,
                        'length' => 20,
                        'width' => 20,
                        'height' => 10,
                        'g_weight' => 2,
                    ],
                ],
                'invoice_items' => [
                    ['tenhang' => 'Sample item', 'soluong' => 2, 'price' => 15],
                ],
                'notes' => 'Created from Zalo Mini App test',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.warnings', [])
            ->assertJsonPath('data.order.receiver.name', 'Receiver Mini')
            ->assertJsonPath('data.order.chargeable_weight.value', 2);

        $orderId = $response->json('data.order.id');
        $bill = $response->json('data.order.id_bill');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'id_cs' => $user->id,
            'id_create' => $user->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/zalo-mini-app/orders?search='.$bill)
            ->assertOk()
            ->assertJsonPath('data.items.0.id_bill', $bill);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/zalo-mini-app/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.id_bill', $bill)
            ->assertJsonPath('data.packages.0.g_weight', 2)
            ->assertJsonPath('data.invoice_items.0.tenhang', 'Sample item');
    }

    public function test_price_list_api_rejects_overlapping_ranges_and_creates_valid_list(): void
    {
        $manager = $this->makeUser(['manager']);
        [$serviceId, $countryId] = $this->seedCatalog(seedPriceList: false);

        $payload = [
            'name' => 'US Express',
            'service_id' => $serviceId,
            'country_ids' => [$countryId],
            'details' => [
                ['quycach' => 'DON_GIA', 'weight_from' => 0, 'weight_to' => 5, 'sale_price' => 100000],
                ['quycach' => 'DON_GIA', 'weight_from' => 4.5, 'weight_to' => 10, 'sale_price' => 95000],
            ],
        ];

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/zalo-mini-app/price-lists', $payload)
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $payload['details'][1]['weight_from'] = 5.01;

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/zalo-mini-app/price-lists', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'US Express')
            ->assertJsonPath('data.details.1.weight_from', 5.01);
    }

    public function test_ctv_order_scope_hides_other_users_orders(): void
    {
        $ctv = $this->makeUser(['ctv']);
        $ownOrder = $this->makeOrder(['id_customer' => $ctv->id, 'id_bill' => 'OWNZMA001']);
        $otherOrder = $this->makeOrder(['id_customer' => $ctv->id + 100, 'id_bill' => 'OTHERZMA001']);

        $this->actingAs($ctv, 'sanctum')
            ->getJson('/api/zalo-mini-app/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $ownOrder->id);

        $this->actingAs($ctv, 'sanctum')
            ->getJson('/api/zalo-mini-app/orders/'.$otherOrder->id)
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_manager_cannot_create_order_from_mini_app(): void
    {
        $manager = $this->makeUser(['manager']);
        [$serviceId, $countryId] = $this->seedCatalog();

        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/zalo-mini-app/orders', [
                'service_id' => $serviceId,
                'country_id' => $countryId,
                'sender' => ['name' => 'Sender', 'phone' => '0900000001', 'address' => 'HCM'],
                'receiver' => ['name' => 'Receiver', 'phone' => '0800000002', 'address' => 'US'],
                'packages' => [['g_weight' => 1]],
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_only_admin_can_delete_price_list(): void
    {
        $manager = $this->makeUser(['manager']);
        $admin = $this->makeUser(['admin']);
        $this->seedCatalog();
        $priceListId = (int) DB::table('service_price_lists')->value('id');

        $this->actingAs($manager, 'sanctum')
            ->deleteJson('/api/zalo-mini-app/price-lists/'.$priceListId)
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/zalo-mini-app/price-lists/'.$priceListId)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('service_price_lists', ['id' => $priceListId]);
    }

    private function buildZaloMiniAppSchema(): void
    {
        Schema::create('setting', function (Blueprint $table): void {
            $table->id();
            $table->json('options')->nullable();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('iso2')->nullable();
            $table->string('iso3')->nullable();
            $table->string('phonecode')->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('id_manager')->nullable();
            $table->unsignedBigInteger('id_ketoan')->nullable();
            $table->float('dim')->nullable();
            $table->text('ghichu')->nullable();
            $table->json('payment_cuocban')->nullable();
            $table->json('payment_cuocvon')->nullable();
            $table->json('payment_cuocgoc')->nullable();
            $table->json('payment_loinhuan')->nullable();
        });

        Schema::table('order_package', function (Blueprint $table): void {
            $table->decimal('length', 12, 2)->default(0);
            $table->decimal('width', 12, 2)->default(0);
            $table->decimal('height', 12, 2)->default(0);
            $table->decimal('g_weight', 12, 3)->default(0);
            $table->decimal('v_weight', 12, 3)->default(0);
            $table->decimal('row_g_weight', 12, 3)->default(0);
            $table->decimal('row_v_weight', 12, 3)->default(0);
            $table->unsignedInteger('number_of_package')->default(1);
            $table->string('package_type')->nullable();
            $table->string('id_thamchieu')->nullable();
            $table->string('mathamchieu')->nullable();
            $table->string('package_delivery_status')->nullable();
            $table->timestamp('package_delivered_at')->nullable();
            $table->timestamp('package_delivery_synced_at')->nullable();
        });

        Schema::create('order_sequences', function (Blueprint $table): void {
            $table->id();
            $table->date('sequence_date')->unique();
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_order');
            $table->string('tenhang')->nullable();
            $table->decimal('soluong', 12, 2)->default(0);
            $table->string('xuatxu')->nullable();
            $table->string('loaihang')->nullable();
            $table->string('hscode')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('service_price_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('service_id');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('service_price_list_countries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_price_list_id');
            $table->unsignedBigInteger('country_id');
            $table->timestamps();
        });

        Schema::create('service_price_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_price_list_id');
            $table->string('quycach', 20)->default('DON_GIA');
            $table->decimal('weight_from', 12, 2);
            $table->decimal('weight_to', 12, 2);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('base_price', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('shipment_loads', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('status')->default('moi_tao');
            $table->timestamps();
        });

        Schema::create('shipment_load_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shipment_load_id');
            $table->unsignedBigInteger('id_order');
            $table->unsignedInteger('added_by')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_load_histories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shipment_load_id');
            $table->unsignedInteger('id_user')->nullable();
            $table->timestamp('thoigian')->nullable();
            $table->string('diadiem')->nullable();
            $table->string('trangthai')->nullable();
            $table->text('ghichu')->nullable();
            $table->timestamps();
        });

        Schema::create('zalo_shipping_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('requester_name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('pickup_address', 500);
            $table->string('pickup_city')->nullable();
            $table->unsignedBigInteger('receiver_country_id')->nullable();
            $table->string('receiver_country')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('service_name')->nullable();
            $table->unsignedInteger('package_count');
            $table->decimal('weight_kg', 12, 3);
            $table->decimal('length_cm', 12, 2)->nullable();
            $table->decimal('width_cm', 12, 2)->nullable();
            $table->decimal('height_cm', 12, 2)->nullable();
            $table->text('note')->nullable();
            $table->json('quote_snapshot')->nullable();
            $table->string('zalo_user_id', 100)->nullable();
            $table->string('status')->default('new');
            $table->timestamps();
        });
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'uuid' => 'zma-'.uniqid(),
            'id_bill' => 'HDZMA'.random_int(100000, 999999),
            'bill_status' => OrderStatusEnum::DANG_PHAT_HANG,
            'receiver' => [
                'fullname' => 'Nguyễn Văn A',
                'phone' => '0900000000',
                'city' => 'Tokyo',
                'country' => 'Japan',
            ],
            'service' => [],
        ], $attributes));
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedCatalog(bool $seedPriceList = true): array
    {
        $serviceId = DB::table('news')->insertGetId([
            'namevi' => 'Air Express',
            'type' => 'dichvuchinh',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countryId = DB::table('countries')->insertGetId([
            'name' => 'United States',
            'iso2' => 'US',
            'iso3' => 'USA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($seedPriceList) {
            $priceListId = DB::table('service_price_lists')->insertGetId([
                'name' => 'US Express',
                'service_id' => $serviceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('service_price_list_countries')->insert([
                'service_price_list_id' => $priceListId,
                'country_id' => $countryId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('service_price_details')->insert([
                'service_price_list_id' => $priceListId,
                'quycach' => 'DON_GIA',
                'weight_from' => 0,
                'weight_to' => 10,
                'sale_price' => 100000,
                'cost_price' => 70000,
                'base_price' => 60000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$serviceId, $countryId];
    }
}
