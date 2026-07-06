<?php

namespace App\Http\Controllers\Api\ZaloMiniApp;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\News;
use App\Models\Order;
use App\Support\ThirdPartyOrderShippingHistory;
use Illuminate\Http\JsonResponse;

class ZaloMiniAppTrackingController extends Controller
{
    use ApiResponse;

    public function __construct(protected ThirdPartyOrderShippingHistory $shippingHistory)
    {
    }

    public function show(string $code): JsonResponse
    {
        $code = trim($code);

        if ($code === '' || mb_strlen($code) > 64 || ! preg_match('/^[A-Za-z0-9._-]+$/', $code)) {
            return $this->fail('Mã vận đơn không hợp lệ.', 422);
        }

        $order = Order::query()
            ->with([
                'packages:id,id_order,code,c_weight,row_c_weight,id_thamchieu,mathamchieu,package_delivery_status,package_delivered_at,package_delivery_synced_at',
                'histories:id,id_order,action,content,thoigian,diadiem,trangthai,ghichu,created_at',
                'shipmentLoadHistories' => fn ($query) => $query->select(['shipment_load_histories.id', 'shipment_load_histories.shipment_load_id', 'shipment_load_histories.id_user', 'shipment_load_histories.thoigian', 'shipment_load_histories.diadiem', 'shipment_load_histories.trangthai', 'shipment_load_histories.ghichu', 'shipment_load_histories.created_at']),
                'shipmentLoadHistories.shipmentLoad:id,code',
            ])
            ->where('id_bill', $code)
            ->orWhere('tracking_code', $code)
            ->first();

        if (! $order) {
            return $this->fail('Không tìm thấy đơn hàng.', 404);
        }

        $receiver = $order->receiver ?? [];
        $service = $order->service ?? [];
        $serviceLabels = $this->serviceLabels($service);

        return $this->ok([
            'id_bill' => $order->id_bill,
            'tracking_code' => $order->tracking_code,
            'status' => $this->statusPayload($order->bill_status),
            'chargeable_weight' => [
                'value' => $this->chargeableWeight($order),
                'unit' => 'kg',
            ],
            'receiver' => [
                'name' => $this->maskName((string) data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', ''))),
                'phone' => $this->maskPhone((string) data_get($receiver, 'phone', '')),
                'destination' => $this->destination($receiver),
                'country' => data_get($receiver, 'country'),
                'country_id' => data_get($receiver, 'country_id', data_get($receiver, 'id_country')),
            ],
            'shipping_history' => $this->shippingHistory->forOrder($order),
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
        ], 'OK');
    }

    private function chargeableWeight(Order $order): float
    {
        $weight = $order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight));

        return round($weight, 3);
    }

    private function destination(array $receiver): string
    {
        $destination = implode(', ', array_filter([
            data_get($receiver, 'city'),
            data_get($receiver, 'state'),
            data_get($receiver, 'country', data_get($receiver, 'postcode')),
        ]));

        return $destination !== '' ? $destination : '-';
    }

    private function maskName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return '-';
        }

        return collect(preg_split('/\s+/u', $name))
            ->filter()
            ->map(fn (string $word) => mb_substr($word, 0, 1).'***')
            ->implode(' ');
    }

    private function maskPhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '-';
        }

        if (mb_strlen($phone) <= 3) {
            return str_repeat('*', mb_strlen($phone));
        }

        return str_repeat('*', mb_strlen($phone) - 3).mb_substr($phone, -3);
    }

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
