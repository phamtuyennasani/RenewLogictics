<?php

namespace App\Actions\ShipmentLoad;

use App\Enums\OrderStatusEnum;
use App\Enums\ShipmentLoadStatusEnum;
use App\Models\Order;
use App\Models\ShipmentLoad;
use App\Models\ShipmentLoadOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateShipmentLoadAction
{
    /**
     * @param  array<int>|null  $orderIds
     */
    public static function execute(?int $userId = null, ?array $orderIds = null): ShipmentLoad
    {
        return DB::transaction(function () use ($userId, $orderIds) {
            $load = ShipmentLoad::create([
                'code' => self::nextCode(),
                'status' => ShipmentLoadStatusEnum::MOI_TAO,
                'created_by' => $userId ?? auth()->id(),
            ]);

            if (empty($orderIds)) {
                return $load;
            }

            $orderIds = collect($orderIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($orderIds->isEmpty()) {
                return $load;
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
                ->pluck('id_order');

            if ($attachedOrderIds->isNotEmpty()) {
                $codes = Order::query()
                    ->whereIn('id', $attachedOrderIds)
                    ->pluck('id_bill')
                    ->filter()
                    ->implode(', ');

                throw new RuntimeException('Đơn đã nằm trong tải khác: '.$codes);
            }

            foreach ($orders as $order) {
                ShipmentLoadOrder::create([
                    'shipment_load_id' => $load->id,
                    'id_order' => $order->id,
                    'added_by' => $userId ?? auth()->id(),
                ]);
            }

            SyncShipmentLoadTotalsAction::execute($load);

            return $load;
        });
    }

    protected static function nextCode(): string
    {
        $prefix = 'TAI-'.now()->format('ymd');
        $latest = ShipmentLoad::query()
            ->where('code', 'like', $prefix.'-%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('code');

        $number = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return $prefix.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}

