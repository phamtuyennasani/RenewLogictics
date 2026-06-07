<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThirdPartyOrderTrackingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_bill' => ['required', 'string', 'max:100'],
        ], [
            'id_bill.required' => 'Vui lòng nhập mã đơn hàng.',
        ]);

        $order = Order::query()
            ->with(['packages:id,id_order,c_weight,row_c_weight'])
            ->where('id_bill', $validated['id_bill'])
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng.',
            ], 404);
        }

        $service = $order->service ?? [];
        $serviceLabels = $this->serviceLabels($service);
        $chargeableWeight = $this->chargeableWeight($order);
        $receiver = $order->receiver ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'id_bill' => $order->id_bill,
                'tracking_code' => $order->tracking_code,
                'status' => [
                    'code' => $order->bill_status?->value ?? (string) $order->bill_status,
                    'label' => $order->bill_status?->label() ?? (string) $order->bill_status,
                ],
                'chargeable_weight' => [
                    'value' => $chargeableWeight,
                    'unit' => 'kg',
                ],
                'receiver' => [
                    'company' => data_get($receiver, 'company'),
                    'fullname' => data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe')),
                    'phone' => data_get($receiver, 'phone'),
                    'email' => data_get($receiver, 'email'),
                    'address' => data_get($receiver, 'address'),
                    'city' => data_get($receiver, 'city'),
                    'state' => data_get($receiver, 'state'),
                    'postcode' => data_get($receiver, 'postcode'),
                    'country' => data_get($receiver, 'country'),
                    'country_id' => data_get($receiver, 'country_id', data_get($receiver, 'id_country')),
                ],
                'service' => [
                    'main' => [
                        'id' => data_get($service, 'id_dichvu'),
                        'name' => $serviceLabels['main'],
                    ],
                    'detail' => [
                        'id' => data_get($service, 'id_chitiet_dichvu'),
                        'name' => $serviceLabels['detail'],
                    ],
                    'shipment_type' => [
                        'id' => data_get($service, 'loaibuugui'),
                        'name' => $serviceLabels['shipment_type'],
                    ],
                ],
            ],
        ]);
    }

    private function chargeableWeight(Order $order): float
    {
        $weight = $order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight));

        return round($weight, 3);
    }

    /**
     * @param  array<string, mixed>  $service
     * @return array{main:?string,detail:?string,shipment_type:?string}
     */
    private function serviceLabels(array $service): array
    {
        $ids = collect([
            data_get($service, 'id_dichvu'),
            data_get($service, 'id_chitiet_dichvu'),
            data_get($service, 'loaibuugui'),
        ])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $labels = $ids->isEmpty()
            ? collect()
            : News::query()->whereIn('id', $ids)->pluck('namevi', 'id');

        return [
            'main' => $this->labelFor($labels, data_get($service, 'id_dichvu')),
            'detail' => $this->labelFor($labels, data_get($service, 'id_chitiet_dichvu')),
            'shipment_type' => $this->labelFor($labels, data_get($service, 'loaibuugui')),
        ];
    }

    private function labelFor($labels, mixed $id): ?string
    {
        return $id ? ($labels[(int) $id] ?? null) : null;
    }
}