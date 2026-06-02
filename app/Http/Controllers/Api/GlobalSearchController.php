<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        // Search orders by id_bill or tracking_code
        $orders = Order::query()
            ->where(function ($query) use ($q) {
                $query->where('id_bill', 'like', "%{$q}%")
                    ->orWhere('tracking_code', 'like', "%{$q}%");
            })
            ->limit(5)
            ->get(['id', 'uuid', 'id_bill', 'tracking_code', 'bill_status']);

        foreach ($orders as $order) {
            $results[] = [
                'type' => 'order',
                'label' => $order->id_bill ?: 'ORDER-' . $order->id,
                'sublabel' => $order->tracking_code ? 'Tracking: ' . $order->tracking_code : ($order->bill_status?->label() ?? ''),
                'url' => route('tracking', ['idbill' => $order->id_bill ?: $order->uuid]),
            ];
        }

        return response()->json(['results' => $results]);
    }
}
