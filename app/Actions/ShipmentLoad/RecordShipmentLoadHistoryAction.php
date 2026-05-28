<?php

namespace App\Actions\ShipmentLoad;

use App\Models\Order;
use App\Models\ShipmentLoad;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordShipmentLoadHistoryAction
{
    public static function execute(
        ShipmentLoad $load,
        CarbonInterface $time,
        string $location,
        string $status,
        ?string $note = null,
        ?int $userId = null,
    ): void {
        DB::transaction(function () use ($load, $time, $location, $status, $note, $userId) {
            $lockedLoad = ShipmentLoad::query()
                ->with('orders')
                ->whereKey($load->id)
                ->lockForUpdate()
                ->firstOrFail();

            $history = $lockedLoad->histories()->create([
                'id_user' => $userId ?? auth()->id(),
                'thoigian' => $time,
                'diadiem' => $location,
                'trangthai' => $status,
                'ghichu' => $note,
            ]);

            $content = json_encode([
                'label' => 'hành trình tải hàng',
                'summary' => 'đồng bộ hành trình từ tải '.$lockedLoad->code,
                'shipment_load_id' => $lockedLoad->id,
                'shipment_load_code' => $lockedLoad->code,
                'shipment_load_history_id' => $history->id,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $lockedLoad->orders->each(function (Order $order) use ($time, $location, $status, $note, $content, $userId) {
                $order->histories()->create([
                    'id_user' => $userId ?? auth()->id(),
                    'action' => 'shipment_load_history',
                    'content' => $content,
                    'thoigian' => $time,
                    'diadiem' => $location,
                    'trangthai' => $status,
                    'ghichu' => $note,
                    'main' => false,
                ]);
            });
        });
    }
}

