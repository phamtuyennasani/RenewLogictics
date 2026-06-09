<?php

namespace Tests\Feature\Mobile;

use App\Enums\PickupStatusEnum;
use App\Models\Pickup;
use Tests\TestCase;

class MobileShipperPickupTest extends TestCase
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

    private function makePickup(int $shipperId, array $attributes = []): Pickup
    {
        return Pickup::query()->create(array_merge([
            'ma_pickup' => 'PICK'.uniqid(),
            'id_shipper' => $shipperId,
            'status' => PickupStatusEnum::MOI_TAO_PICKUP->value,
            'ngay_tao' => now(),
            'total_weight' => 14.0,
            'total_c_weight' => 12.5,
            'numb' => 3,
            'note' => 'Gọi trước khi đến',
            'info_khachhang' => [
                'company' => 'Công ty TNHH ABC',
                'fullname' => 'Trần Thị B',
                'phone' => '0907654321',
                'address' => '123 Lê Lợi, Q1',
                'country' => 'VN',
                'pickup_lat' => 10.7769,
                'pickup_lng' => 106.7009,
            ],
            'info_pickup' => ['ngayhen' => now()->addHours(2)->toDateTimeString()],
        ], $attributes));
    }

    public function test_shipper_sees_only_own_pickups(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $other = $this->makeUser(['shipper']);

        $this->makePickup($shipper->id, ['ma_pickup' => 'PICKMINE']);
        $this->makePickup($other->id, ['ma_pickup' => 'PICKOTHER']);

        $response = $this->actingAs($shipper, 'sanctum')
            ->getJson('/api/mobile/shipper/pickups?tab=new')
            ->assertOk()
            ->assertJsonPath('success', true);

        $codes = collect($response->json('data.items'))->pluck('ma_pickup');

        $this->assertTrue($codes->contains('PICKMINE'));
        $this->assertFalse($codes->contains('PICKOTHER'));
    }

    public function test_show_returns_404_for_other_shipper_pickup(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $other = $this->makeUser(['shipper']);
        $pickup = $this->makePickup($other->id);

        $this->actingAs($shipper, 'sanctum')
            ->getJson('/api/mobile/shipper/pickups/'.$pickup->id)
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_status_update_follows_fsm(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $pickup = $this->makePickup($shipper->id, ['status' => PickupStatusEnum::MOI_TAO_PICKUP->value]);

        $this->actingAs($shipper, 'sanctum')
            ->postJson('/api/mobile/shipper/pickups/'.$pickup->id.'/status', [
                'status' => PickupStatusEnum::DA_XAC_NHAN->value,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', PickupStatusEnum::DA_XAC_NHAN->value);

        $this->assertSame(PickupStatusEnum::DA_XAC_NHAN, $pickup->fresh()->status);
    }

    public function test_invalid_fsm_transition_returns_409(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $pickup = $this->makePickup($shipper->id, ['status' => PickupStatusEnum::PICKUP_DA_LAY->value]);

        $this->actingAs($shipper, 'sanctum')
            ->postJson('/api/mobile/shipper/pickups/'.$pickup->id.'/status', [
                'status' => PickupStatusEnum::PICKUP_DANG_LAY->value,
            ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_cancel_removes_shipper_assignment(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $pickup = $this->makePickup($shipper->id, ['status' => PickupStatusEnum::DA_XAC_NHAN->value]);

        $this->actingAs($shipper, 'sanctum')
            ->postJson('/api/mobile/shipper/pickups/'.$pickup->id.'/status', [
                'status' => PickupStatusEnum::DA_HUY->value,
                'reason' => 'Khách hủy',
            ])
            ->assertOk();

        // Action gỡ id_shipper khi hủy để điều phối gán lại.
        $this->assertNull($pickup->fresh()->id_shipper);
    }

    public function test_invalid_status_value_returns_422(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $pickup = $this->makePickup($shipper->id);

        $this->actingAs($shipper, 'sanctum')
            ->postJson('/api/mobile/shipper/pickups/'.$pickup->id.'/status', [
                'status' => 'khong_ton_tai',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_ops_role_cannot_access_shipper_routes(): void
    {
        $ops = $this->makeUser(['ops']);

        $this->actingAs($ops, 'sanctum')
            ->getJson('/api/mobile/shipper/pickups')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_payload_excludes_financial_fields(): void
    {
        $shipper = $this->makeUser(['shipper']);
        $this->makePickup($shipper->id);

        $response = $this->actingAs($shipper, 'sanctum')
            ->getJson('/api/mobile/shipper/pickups?tab=new')
            ->assertOk();

        $item = $response->json('data.items.0');

        $this->assertArrayNotHasKey('total_cuoc', $item);
        $this->assertArrayNotHasKey('total_cuocvon', $item);
        $this->assertArrayNotHasKey('total_cuocban', $item);
    }
}
