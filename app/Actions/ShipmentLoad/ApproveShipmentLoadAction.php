<?php

namespace App\Actions\ShipmentLoad;

use App\Enums\OrderStatusEnum;
use App\Enums\ShipmentLoadStatusEnum;
use App\Models\Order;
use App\Models\ShipmentLoad;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApproveShipmentLoadAction
{
    public static function execute(ShipmentLoad $load, ?int $userId = null): void
    {
        DB::transaction(function () use ($load, $userId) {
            $lockedLoad = ShipmentLoad::query()
                ->with('orders')
                ->whereKey($load->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedLoad->status === ShipmentLoadStatusEnum::DA_DUYET_XUAT) {
                throw new RuntimeException('Tải đã được duyệt xuất.');
            }

            if ($lockedLoad->orders->isEmpty()) {
                throw new RuntimeException('Tải chưa có đơn hàng.');
            }

            $orders = Order::query()
                ->whereIn('id', $lockedLoad->orders->pluck('id'))
                ->lockForUpdate()
                ->get();

            $invalidOrders = $orders
                ->filter(fn (Order $order) => $order->bill_status !== OrderStatusEnum::DA_NHAN_HANG)
                ->pluck('id_bill')
                ->filter()
                ->values();

            if ($invalidOrders->isNotEmpty()) {
                throw new RuntimeException('Có đơn không còn ở trạng thái Đã nhận hàng: '.$invalidOrders->implode(', '));
            }

            $lockedLoad->forceFill([
                'status' => ShipmentLoadStatusEnum::DA_DUYET_XUAT,
                'approved_by' => $userId ?? auth()->id(),
                'approved_at' => now(),
            ])->save();

            $content = json_encode([
                'label' => 'duyệt xuất tải hàng',
                'summary' => 'đơn được duyệt xuất từ tải '.$lockedLoad->code,
                'shipment_load_id' => $lockedLoad->id,
                'shipment_load_code' => $lockedLoad->code,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            foreach ($orders as $order) {
                $order->forceFill([
                    'bill_status' => OrderStatusEnum::DUYET_XUAT_HANG,
                    'ngayxuathang' => $order->ngayxuathang ?: now(),
                ])->save();

                $order->histories()->create([
                    'id_user' => $userId ?? auth()->id(),
                    'action' => 'shipment_load_approved',
                    'content' => $content,
                    'thoigian' => now(),
                    'diadiem' => 'VN',
                    'trangthai' => OrderStatusEnum::DUYET_XUAT_HANG->label(),
                    'ghichu' => 'Duyệt xuất từ tải '.$lockedLoad->code,
                    'main' => true,
                ]);
            }

            SyncShipmentLoadTotalsAction::execute($lockedLoad);
        });
    }
}

