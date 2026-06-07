<?php

namespace Tests\Feature;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\Setting;
use App\Services\TrackingMore\TrackingMore;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ThirdPartyOrderTrackingApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists("shipment_load_histories");
        Schema::dropIfExists("shipment_load_orders");
        Schema::dropIfExists("shipment_loads");
        Schema::dropIfExists("order_history");
        Schema::dropIfExists("order_package");
        Schema::dropIfExists("orders");
        Schema::dropIfExists("news");
        Schema::dropIfExists("setting");

        Schema::create("setting", function (Blueprint $table): void {
            $table->id();
            $table->string("namevi")->nullable();
            $table->json("options")->nullable();
            $table->string("photo")->nullable();
            $table->timestamps();
        });

        Schema::create("orders", function (Blueprint $table): void {
            $table->id();
            $table->string("id_bill")->unique();
            $table->string("tracking_code")->nullable();
            $table->string("id_thamchieu")->nullable();
            $table->string("mathamchieu")->nullable();
            $table->string("bill_status");
            $table->json("receiver")->nullable();
            $table->json("service")->nullable();
            $table->timestamps();
        });

        Schema::create("order_package", function (Blueprint $table): void {
            $table->id();
            $table->foreignId("id_order")->constrained("orders")->cascadeOnDelete();
            $table->string("code")->nullable();
            $table->string("id_thamchieu")->nullable();
            $table->string("mathamchieu")->nullable();
            $table->decimal("c_weight", 12, 3)->default(0);
            $table->decimal("row_c_weight", 12, 3)->nullable();
            $table->string("package_delivery_status")->nullable();
            $table->timestamp("package_delivered_at")->nullable();
            $table->timestamp("package_delivery_synced_at")->nullable();
            $table->timestamps();
        });

        Schema::create("order_history", function (Blueprint $table): void {
            $table->id();
            $table->foreignId("id_order")->constrained("orders")->cascadeOnDelete();
            $table->unsignedInteger("id_user")->nullable();
            $table->string("action")->nullable();
            $table->text("content")->nullable();
            $table->timestamp("thoigian")->nullable();
            $table->string("trangthai")->nullable();
            $table->string("diadiem")->nullable();
            $table->text("ghichu")->nullable();
            $table->boolean("main")->default(false);
            $table->timestamps();
        });

        Schema::create("shipment_loads", function (Blueprint $table): void {
            $table->id();
            $table->string("code");
            $table->string("status")->default("moi_tao");
            $table->timestamps();
        });

        Schema::create("shipment_load_orders", function (Blueprint $table): void {
            $table->id();
            $table->foreignId("shipment_load_id")->constrained("shipment_loads")->cascadeOnDelete();
            $table->foreignId("id_order")->constrained("orders")->cascadeOnDelete();
            $table->unsignedInteger("added_by")->nullable();
            $table->timestamps();
        });

        Schema::create("shipment_load_histories", function (Blueprint $table): void {
            $table->id();
            $table->foreignId("shipment_load_id")->constrained("shipment_loads")->cascadeOnDelete();
            $table->unsignedInteger("id_user")->nullable();
            $table->timestamp("thoigian")->nullable();
            $table->string("diadiem")->nullable();
            $table->string("trangthai")->nullable();
            $table->text("ghichu")->nullable();
            $table->timestamps();
        });

        Schema::create("news", function (Blueprint $table): void {
            $table->id();
            $table->string("namevi");
            $table->string("type")->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists("shipment_load_histories");
        Schema::dropIfExists("shipment_load_orders");
        Schema::dropIfExists("shipment_loads");
        Schema::dropIfExists("order_history");
        Schema::dropIfExists("order_package");
        Schema::dropIfExists("orders");
        Schema::dropIfExists("news");
        Schema::dropIfExists("setting");

        parent::tearDown();
    }

    public function test_disabled_tracking_api_is_hidden(): void
    {
        $this->postJson("/api/third-party/orders/tracking", ["id_bill" => "HD001"])
            ->assertNotFound();
    }

    public function test_it_rejects_missing_or_invalid_api_key(): void
    {
        $this->enableApi();

        $this->postJson("/api/third-party/orders/tracking", ["id_bill" => "HD001"])
            ->assertUnauthorized()
            ->assertJsonPath("success", false);

        $this->withHeader("X-API-Key", "wrong-key")
            ->postJson("/api/third-party/orders/tracking", ["id_bill" => "HD001"])
            ->assertUnauthorized()
            ->assertJsonPath("success", false);
    }

    public function test_it_rejects_blocked_ip(): void
    {
        $this->enableApi(["third_party_tracking_api_blocked_ips" => "203.0.113.10"]);

        $this->withHeader("X-API-Key", "secret-key")
            ->withServerVariables(["REMOTE_ADDR" => "203.0.113.10"])
            ->postJson("/api/third-party/orders/tracking", ["id_bill" => "HD001"])
            ->assertForbidden()
            ->assertJsonPath("success", false);
    }

    public function test_it_returns_order_tracking_payload(): void
    {
        $this->enableApi();
        $this->fakeTrackingMore();
        $order = $this->createOrder();

        DB::table("order_package")->insert([
            ["id_order" => $order->id, "code" => "PKG001", "id_thamchieu" => "dhl", "mathamchieu" => "TRACK001", "c_weight" => 2.25, "row_c_weight" => null, "created_at" => now(), "updated_at" => now()],
            ["id_order" => $order->id, "code" => "PKG002", "id_thamchieu" => null, "mathamchieu" => null, "c_weight" => 1.1, "row_c_weight" => 3.345, "created_at" => now(), "updated_at" => now()],
        ]);
        $this->createShippingHistories($order);

        $this->withHeader("X-API-Key", "secret-key")
            ->postJson("/api/third-party/orders/tracking", ["id_bill" => "HD001"])
            ->assertOk()
            ->assertJsonPath("success", true)
            ->assertJsonPath("data.id_bill", "HD001")
            ->assertJsonPath("data.tracking_code", "TRK001")
            ->assertJsonPath("data.status.code", OrderStatusEnum::DANG_PHAT_HANG->value)
            ->assertJsonPath("data.chargeable_weight.value", 5.595)
            ->assertJsonPath("data.receiver.fullname", "Nguyen Van A")
            ->assertJsonPath("data.service.main.name", "Bay nhanh")
            ->assertJsonPath("data.shipping_history.0.source", "tracking_more")
            ->assertJsonPath("data.shipping_history.0.tracking_number", "TRACK001")
            ->assertJsonPath("data.shipping_history.1.source", "shipment_load")
            ->assertJsonPath("data.shipping_history.1.shipment_load_code", "LOAD001")
            ->assertJsonPath("data.shipping_history.2.source", "manual");
    }

    private function enableApi(array $options = []): void
    {
        Setting::create([
            "namevi" => "Cau hinh he thong",
            "options" => array_merge([
                "third_party_tracking_api_enabled" => true,
                "third_party_tracking_api_key" => "secret-key",
                "third_party_tracking_api_blocked_ips" => "",
                "third_party_tracking_api_rate_limit_per_minute" => 60,
            ], $options),
        ]);
    }

    private function fakeTrackingMore(): void
    {
        $this->app->instance(TrackingMore::class, new class {
            public function tracking(): object
            {
                return new class {
                    public function getTrackingResults(array $params): array
                    {
                        return ["meta" => ["code" => 200], "data" => [["origin_info" => ["trackinfo" => [["checkpoint_date" => "2026-06-07 10:00:00", "checkpoint_delivery_status" => "in_transit", "location" => "Tokyo", "tracking_detail" => "Arrived at facility"]]]]]];
                    }
                };
            }
        });
    }

    private function createShippingHistories(Order $order): void
    {
        $loadId = DB::table("shipment_loads")->insertGetId(["code" => "LOAD001", "status" => "da_duyet_xuat", "created_at" => now(), "updated_at" => now()]);
        DB::table("shipment_load_orders")->insert(["shipment_load_id" => $loadId, "id_order" => $order->id, "created_at" => now(), "updated_at" => now()]);
        DB::table("shipment_load_histories")->insert(["shipment_load_id" => $loadId, "thoigian" => "2026-06-07 09:00:00", "diadiem" => "HCM", "trangthai" => "Xuat tai", "ghichu" => "Da xuat tai", "created_at" => now(), "updated_at" => now()]);
        DB::table("order_history")->insert(["id_order" => $order->id, "action" => "tracking_history", "content" => json_encode(["summary" => "Nhap tay"]), "thoigian" => "2026-06-07 08:00:00", "diadiem" => "Kho VN", "trangthai" => "Da nhan hang", "ghichu" => "Nhan tai kho", "created_at" => now(), "updated_at" => now()]);
    }

    private function createOrder(): Order
    {
        DB::table("news")->insert([
            ["id" => 11, "namevi" => "Bay nhanh", "type" => "service", "created_at" => now(), "updated_at" => now()],
            ["id" => 12, "namevi" => "Giao tan nha", "type" => "service-detail", "created_at" => now(), "updated_at" => now()],
            ["id" => 13, "namevi" => "Hang hoa", "type" => "shipment-type", "created_at" => now(), "updated_at" => now()],
        ]);

        $id = DB::table("orders")->insertGetId([
            "id_bill" => "HD001",
            "tracking_code" => "TRK001",
            "bill_status" => OrderStatusEnum::DANG_PHAT_HANG->value,
            "receiver" => json_encode(["company" => "Cong ty A", "fullname" => "Nguyen Van A", "phone" => "0909000000", "email" => "a@example.com", "address" => "123 Test", "city" => "Ho Chi Minh", "state" => "TP.HCM", "postcode" => "700000", "country" => "Viet Nam", "country_id" => 84], JSON_UNESCAPED_UNICODE),
            "service" => json_encode(["id_dichvu" => 11, "id_chitiet_dichvu" => 12, "loaibuugui" => 13], JSON_UNESCAPED_UNICODE),
            "created_at" => now(),
            "updated_at" => now(),
        ]);

        return Order::query()->findOrFail($id);
    }
}
