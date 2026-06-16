<?php

namespace App\Actions\Order;

use App\Models\ServicePriceDetail;
use App\Models\ServicePriceList;
use RuntimeException;

class ResolveServicePriceAction
{
    public function execute(array $service, array $receiver, array $packages, float $dim): array
    {
        $serviceId = (int) ($service['id_dichvu'] ?? 0);
        $countryId = (int) ($receiver['country_id'] ?? 0);

        if ($serviceId <= 0) {
            throw new RuntimeException('Vui lòng chọn dịch vụ trước khi tính cước.');
        }

        if ($countryId <= 0) {
            throw new RuntimeException('Vui lòng chọn quốc gia nhận để tính cước.');
        }

        $chargeableWeight = $this->calculateChargeableWeight($packages, $dim);

        if ($chargeableWeight <= 0) {
            throw new RuntimeException('Vui lòng nhập thông tin kiện hàng để tính cước.');
        }

        $priceList = ServicePriceList::query()
            ->where('service_id', $serviceId)
            ->whereHas('countries', fn ($query) => $query->where('countries.id', $countryId))
            ->latest('updated_at')
            ->first();

        // Chưa có bảng giá cho dịch vụ + quốc gia này → mặc định cước = 0.
        if (! $priceList) {
            return $this->zeroPriceResult($chargeableWeight);
        }

        $detail = $priceList->details()
            ->where('weight_from', '<=', $chargeableWeight)
            ->where('weight_to', '>=', $chargeableWeight)
            ->first();

        // Không có dòng giá phù hợp cân nặng → mặc định cước = 0.
        if (! $detail) {
            return $this->zeroPriceResult($chargeableWeight, $priceList);
        }

        return [
            'price_list' => $priceList,
            'detail' => $detail,
            'chargeable_weight' => $chargeableWeight,
            'sale_price' => $this->calculateAmount($detail, 'sale_price', $chargeableWeight),
            'cost_price' => $this->calculateAmount($detail, 'cost_price', $chargeableWeight),
            'base_price' => $this->calculateAmount($detail, 'base_price', $chargeableWeight),
        ];
    }

    protected function zeroPriceResult(float $chargeableWeight, ?ServicePriceList $priceList = null): array
    {
        return [
            'price_list' => $priceList,
            'detail' => null,
            'chargeable_weight' => $chargeableWeight,
            'sale_price' => 0.0,
            'cost_price' => 0.0,
            'base_price' => 0.0,
        ];
    }

    protected function calculateChargeableWeight(array $packages, float $dim): float
    {
        $total = 0;

        foreach ($packages as $package) {
            if (! is_array($package)) {
                continue;
            }

            $quantity = max(1, (int) ($package['number_of_package'] ?? 1));
            $weights = CalculateChargeableWeightAction::execute(
                length: (float) ($package['length'] ?? 0),
                width: (float) ($package['width'] ?? 0),
                height: (float) ($package['height'] ?? 0),
                gWeight: (float) ($package['g_weight'] ?? 0),
                dim: $dim
            );

            $total += (float) $weights['c_weight'] * $quantity;
        }

        return round($total, 2);
    }

    protected function calculateAmount(ServicePriceDetail $detail, string $priceColumn, float $chargeableWeight): float
    {
        $price = (float) $detail->{$priceColumn};

        if ($detail->quycach === 'DON_GIA') {
            return round($price * $chargeableWeight);
        }

        return round($price);
    }
}
