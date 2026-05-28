<?php

namespace App\Actions\ShipmentLoad;

use App\Models\OrderPackage;
use App\Models\ShipmentLoad;

class SyncShipmentLoadTotalsAction
{
    public static function execute(ShipmentLoad $load): void
    {
        $orderIds = $load->orders()->pluck('orders.id');

        $load->forceFill([
            'orders_count' => $orderIds->count(),
            'total_chargeable_weight' => $orderIds->isEmpty()
                ? 0
                : (float) OrderPackage::query()
                    ->whereIn('id_order', $orderIds)
                    ->sum('c_weight'),
        ])->save();
    }
}

