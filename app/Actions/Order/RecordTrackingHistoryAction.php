<?php

namespace App\Actions\Order;

use App\Enums\OrderStatusEnum;
use App\Models\Country;
use App\Models\Order;
use Carbon\CarbonInterface;

class RecordTrackingHistoryAction
{
    public static function execute(Order $order, OrderStatusEnum $status, ?CarbonInterface $time = null): void
    {
        $order->histories()->create([
            'id_user' => auth()->id(),
            'action' => 'tracking_status_auto',
            'content' => json_encode([
                'label' => 'hành trình',
                'summary' => 'tự động thêm hành trình khi đổi trạng thái đơn',
            ], JSON_UNESCAPED_UNICODE),
            'thoigian' => $time ?? now(),
            'diadiem' => self::locationFor($order, $status),
            'trangthai' => $status->label(),
            'ghichu' => '',
            'main' => true,
        ]);
    }

    protected static function locationFor(Order $order, OrderStatusEnum $status): string
    {
        if ($status->sortOrder() <= OrderStatusEnum::DANG_PHAT_HANG->sortOrder()) {
            $branch = $order->relationLoaded('chiNhanhNhanHang')
                ? $order->chiNhanhNhanHang
                : $order->chiNhanhNhanHang()->first();

            return trim(collect([$branch?->namevi, 'VN'])->filter()->implode(', '));
        }

        $receiver = $order->receiver ?? [];
        $countryId = data_get($receiver, 'country_id', data_get($receiver, 'id_country'));
        $country = $countryId
            ? Country::query()->whereKey($countryId)->value('name')
            : data_get($receiver, 'country');

        return trim(collect([
            data_get($receiver, 'state'),
            $country,
        ])->filter()->implode(', '));
    }
}
