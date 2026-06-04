<?php

namespace App\Actions\Pickup;

use App\Enums\OrderStatusEnum;
use App\Enums\PickupStatusEnum;
use App\Models\Order;
use App\Models\Pickup;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreatePickupAction
{
    public static function execute(Order $order, array $data, ?int $userId = null): Pickup
    {
        return DB::transaction(function () use ($order, $data, $userId) {
            $lockedOrder = Order::query()
                ->withSum('packages as pickup_total_weight', 'c_weight')
                ->withSum('packages as pickup_packages_count', 'number_of_package')
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedOrder->bill_status, [OrderStatusEnum::MOI_TAO, OrderStatusEnum::DA_XAC_NHAN], true)) {
                throw new RuntimeException('Chỉ được tạo Pickup cho đơn Mới tạo hoặc Đã xác nhận.');
            }

            if ($lockedOrder->pickups()->exists()) {
                throw new RuntimeException('Đơn hàng đã có phiếu Pickup.');
            }

            $pickup = Pickup::create([
                'ma_pickup' => Pickup::generateCode(),
                'id_user' => $userId ?? auth()->id(),
                'ngay_tao' => now(),
                'total_weight' => (float) ($data['total_weight'] ?? $lockedOrder->pickup_total_weight ?? 0),
                'total_c_weight' => (float) ($data['total_weight'] ?? $lockedOrder->pickup_total_weight ?? 0),
                'status' => PickupStatusEnum::MOI_TAO_PICKUP,
                'numb' => (int) ($data['packages_count'] ?? $lockedOrder->pickup_packages_count ?? 0),
                'note' => $data['note'] ?? null,
                'id_shipper' => $data['shipper_id'] ?? null,
                'info_pickup' => [
                    'id_phuongtien' => $data['vehicle_id'] ?? null,
                    'ngayhen' => $data['scheduled_at'] ?? null,
                    'chinhanhnhanhang' => $data['branch_id'] ?? null,
                ],
                'info_khachhang' => array_merge($data['sender_snapshot'], array_filter([
                    'pickup_lat' => $data['pickup_lat'] ?? null,
                    'pickup_lng' => $data['pickup_lng'] ?? null,
                ])),
            ]);

            $pickup->orders()->attach($lockedOrder->id, [
                'added_by' => $userId ?? auth()->id(),
            ]);

            return $pickup->fresh('orders');
        });
    }
}
