<?php

namespace App\Actions\ShipmentLoad;

use App\Enums\OrderStatusEnum;
use App\Enums\ShipmentLoadStatusEnum;
use App\Models\Order;
use App\Models\ShipmentLoad;
use App\Models\ShipmentLoadOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AddOrdersToShipmentLoadAction
{
    public static function execute(ShipmentLoad $load, array $orderIds, ?int $userId = null): void
    {
        $orderIds = collect($orderIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) {
            throw new RuntimeException('Vui lòng chọn ít nhất một đơn hàng.');
        }

        DB::transaction(function () use ($load, $orderIds, $userId) {
            $lockedLoad = ShipmentLoad::query()->whereKey($load->id)->lockForUpdate()->firstOrFail();

            if (! $lockedLoad->canEditOrders()) {
                throw new RuntimeException('Tải đã duyệt xuất, không thể thêm đơn.');
            }

            $orders = Order::query()
                ->whereIn('id', $orderIds)
                ->lockForUpdate()
                ->get();

            if ($orders->count() !== $orderIds->count()) {
                throw new RuntimeException('Một số đơn hàng không tồn tại.');
            }

            $invalidStatus = $orders
                ->filter(fn (Order $order) => $order->bill_status !== OrderStatusEnum::DA_NHAN_HANG)
                ->pluck('id_bill')
                ->filter()
                ->values();

            if ($invalidStatus->isNotEmpty()) {
                throw new RuntimeException('Chỉ được thêm đơn ở trạng thái Đã nhận hàng: '.$invalidStatus->implode(', '));
            }

            $attachedOrderIds = ShipmentLoadOrder::query()
                ->whereIn('id_order', $orderIds)
                ->whereHas('shipmentLoad', fn ($query) => $query->where('status', ShipmentLoadStatusEnum::MOI_TAO->value))
                ->pluck('id_order');

            if ($attachedOrderIds->isNotEmpty()) {
                $codes = Order::query()
                    ->whereIn('id', $attachedOrderIds)
                    ->pluck('id_bill')
                    ->filter()
                    ->implode(', ');

                throw new RuntimeException('Đơn đã nằm trong tải đang mở khác: '.$codes);
            }

            foreach ($orders as $order) {
                ShipmentLoadOrder::create([
                    'shipment_load_id' => $lockedLoad->id,
                    'id_order' => $order->id,
                    'added_by' => $userId ?? auth()->id(),
                ]);
            }

            SyncShipmentLoadTotalsAction::execute($lockedLoad);
        });
    }
}

