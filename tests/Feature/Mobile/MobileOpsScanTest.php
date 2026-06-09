<?php

namespace Tests\Feature\Mobile;

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderPackage;
use Tests\TestCase;

class MobileOpsScanTest extends TestCase
{
    use BuildsMobileSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMobileSchema();
    }

    protected function tearDown(): void
    {
        $this->dropMobileSchema();
        parent::tearDown();
    }

    private function makeOrder(array $attributes = []): Order
    {
        return Order::query()->create(array_merge([
            'id_bill' => 'HT'.uniqid(),
            'bill_status' => OrderStatusEnum::DA_XAC_NHAN->value,
            'tracking_code' => 'TRK'.uniqid(),
            'mathamchieu' => 'REF'.uniqid(),
            'lock_order' => false,
            'sender' => ['company' => 'ABC', 'fullname' => 'Trần Thị B', 'phone' => '0907654321'],
            'receiver' => ['fullname' => 'John Doe', 'country' => 'USA'],
        ], $attributes));
    }

    public function test_scan_matches_by_id_bill(): void
    {
        $ops = $this->makeUser(['ops']);
        $order = $this->makeOrder(['id_bill' => 'HT-IDBILL-1']);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'HT-IDBILL-1'])
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.matched_by', 'id_bill')
            ->assertJsonPath('data.can_receive', true);
    }

    public function test_scan_matches_by_tracking_code(): void
    {
        $ops = $this->makeUser(['ops']);
        $this->makeOrder(['tracking_code' => 'TRK-XYZ']);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'TRK-XYZ'])
            ->assertOk()
            ->assertJsonPath('data.matched_by', 'tracking_code');
    }

    public function test_scan_matches_by_mathamchieu(): void
    {
        $ops = $this->makeUser(['ops']);
        $this->makeOrder(['mathamchieu' => 'REF-999']);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'REF-999'])
            ->assertOk()
            ->assertJsonPath('data.matched_by', 'mathamchieu');
    }

    public function test_scan_matches_by_package_code(): void
    {
        $ops = $this->makeUser(['ops']);
        $order = $this->makeOrder();
        OrderPackage::query()->create([
            'id_order' => $order->id,
            'code' => 'PKG-001',
            'c_weight' => 2.5,
        ]);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'PKG-001'])
            ->assertOk()
            ->assertJsonPath('data.matched_by', 'package_code')
            ->assertJsonPath('data.matched_package_code', 'PKG-001')
            ->assertJsonPath('data.order.id', $order->id);
    }

    public function test_scan_unknown_code_returns_200_found_false(): void
    {
        $ops = $this->makeUser(['ops']);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'KHONG-TON-TAI'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.found', false)
            ->assertJsonPath('data.can_receive', false);
    }

    public function test_scan_locked_order_cannot_receive(): void
    {
        $ops = $this->makeUser(['ops']);
        $this->makeOrder(['id_bill' => 'HT-LOCKED', 'lock_order' => true]);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'HT-LOCKED'])
            ->assertOk()
            ->assertJsonPath('data.found', true)
            ->assertJsonPath('data.can_receive', false);
    }

    public function test_receive_confirms_order_and_records_history(): void
    {
        $ops = $this->makeUser(['ops']);
        $order = $this->makeOrder(['bill_status' => OrderStatusEnum::DA_XAC_NHAN->value]);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/orders/'.$order->id.'/receive')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order.status.value', OrderStatusEnum::DA_NHAN_HANG->value);

        $fresh = $order->fresh();
        $this->assertSame(OrderStatusEnum::DA_NHAN_HANG, $fresh->bill_status);
        $this->assertNotNull($fresh->ngaynhanhang);

        // Tracking history được ghi.
        $this->assertDatabaseHas('order_history', [
            'id_order' => $order->id,
            'action' => 'tracking_status_auto',
        ]);
    }

    public function test_receive_wrong_status_returns_409(): void
    {
        $ops = $this->makeUser(['ops']);
        $order = $this->makeOrder(['bill_status' => OrderStatusEnum::DA_NHAN_HANG->value]);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/orders/'.$order->id.'/receive')
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_receive_locked_order_returns_409(): void
    {
        $ops = $this->makeUser(['ops']);
        $order = $this->makeOrder(['lock_order' => true]);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/orders/'.$order->id.'/receive')
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_shipper_role_cannot_access_ops_routes(): void
    {
        $shipper = $this->makeUser(['shipper']);

        $this->actingAs($shipper, 'sanctum')
            ->postJson('/api/mobile/ops/scan', ['code' => 'ANY'])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_bulk_receive_mixed_results(): void
    {
        $ops = $this->makeUser(['ops']);
        $ok = $this->makeOrder(['id_bill' => 'HT-OK', 'bill_status' => OrderStatusEnum::DA_XAC_NHAN->value]);
        $wrong = $this->makeOrder(['id_bill' => 'HT-WRONG', 'bill_status' => OrderStatusEnum::DA_NHAN_HANG->value]);

        $response = $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/orders/bulk-receive', [
                'codes' => ['HT-OK', 'HT-WRONG', 'HT-KHONG-TON-TAI'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $succeeded = collect($response->json('data.succeeded'))->pluck('order_id');
        $failed = collect($response->json('data.failed'));

        $this->assertTrue($succeeded->contains($ok->id));
        $this->assertSame(2, $failed->count());
        $this->assertSame(OrderStatusEnum::DA_NHAN_HANG, $ok->fresh()->bill_status);
    }

    public function test_bulk_receive_empty_payload_returns_422(): void
    {
        $ops = $this->makeUser(['ops']);

        $this->actingAs($ops, 'sanctum')
            ->postJson('/api/mobile/ops/orders/bulk-receive', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
