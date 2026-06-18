<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use App\Enums\PickupStatusEnum;
use App\Models\Pickup;
use App\Models\PickupImage;
use Carbon\Carbon;

/**
 * Đóng gói payload pickup dùng chung cho cả app shipper và OPS.
 *
 * Tách ra từ MobileShipperPickupController để MobileOpsPickupController tái dùng,
 * tránh copy logic FSM/payload (theo MOBILE_API_CONTRACT §3).
 *
 * KHÔNG trả field tài chính (total_cuoc, total_cuocvon...) — theo contract §5.
 */
trait PicksPickupPayload
{
    /**
     * Map tab app → các status tương ứng (đồng bộ với component Livewire).
     */
    protected function statusesForTab(string $tab): array
    {
        return match ($tab) {
            'new'      => [PickupStatusEnum::MOI_TAO_PICKUP->value],
            'accepted' => [PickupStatusEnum::DA_XAC_NHAN->value],
            'picking'  => [PickupStatusEnum::PICKUP_DANG_LAY->value],
            'done'     => [PickupStatusEnum::PICKUP_DA_LAY->value],
            default    => [PickupStatusEnum::MOI_TAO_PICKUP->value],
        };
    }

    /**
     * Đóng gói một pickup thành payload cho app.
     *
     * @param  bool  $detailed  Trả thêm orders[] + khối lượng gross + created_at.
     * @param  bool  $withShipper  Trả thêm thông tin shipper đã gán (cho OPS).
     */
    protected function pickupPayload(Pickup $pickup, bool $detailed = false, bool $withShipper = false): array
    {
        $customer = $pickup->info_khachhang ?? [];
        $info = $pickup->info_pickup ?? [];

        $lat = data_get($customer, 'pickup_lat');
        $lng = data_get($customer, 'pickup_lng');
        $hasLocation = is_numeric($lat) && is_numeric($lng);

        $scheduledAt = data_get($info, 'ngayhen');

        $payload = [
            'id' => $pickup->id,
            'ma_pickup' => $pickup->ma_pickup,
            'status' => $this->statusPayload($pickup->status),
            'customer' => [
                'company' => data_get($customer, 'company'),
                'fullname' => data_get($customer, 'fullname'),
                'phone' => data_get($customer, 'phone'),
                'address' => data_get($customer, 'address'),
                'country' => data_get($customer, 'country'),
            ],
            'location' => [
                'lat' => $hasLocation ? (float) $lat : null,
                'lng' => $hasLocation ? (float) $lng : null,
                'has_location' => $hasLocation,
            ],
            'scheduled_at' => $scheduledAt ? Carbon::parse($scheduledAt)->toIso8601String() : null,
            'package_count' => (int) $pickup->numb,
            'weight_kg' => (float) $pickup->total_c_weight,
            'note' => $pickup->note,
            'created_by' => $pickup->user?->fullname ?: $pickup->user?->username,
            'allowed_transitions' => $this->transitionsPayload($pickup->status),
        ];

        if ($withShipper) {
            $payload['shipper'] = $pickup->id_shipper ? [
                'id' => (int) $pickup->id_shipper,
                'name' => $pickup->shipper?->fullname ?: $pickup->shipper?->username,
            ] : null;
        }

        if ($detailed) {
            $payload['weight_gross_kg'] = (float) $pickup->total_weight;
            $payload['created_at'] = $pickup->ngay_tao?->toIso8601String();
            $payload['orders'] = $pickup->orders->map(fn ($order) => [
                'id' => $order->id,
                'id_bill' => $order->id_bill,
                'tracking_code' => $order->tracking_code,
                'uuid' => $order->uuid,
            ])->all();
            $payload['images'] = $pickup->relationLoaded('images')
                ? $pickup->images->map(fn (PickupImage $img) => $this->imagePayload($img))->all()
                : [];
            $payload['checkin'] = $this->checkinPayload($pickup);
        } else {
            $payload['orders_count'] = (int) ($pickup->orders_count ?? 0);
        }

        return $payload;
    }

    /**
     * Toạ độ GPS check-in của shipper lúc xác nhận đã lấy hàng (null nếu chưa có).
     */
    protected function checkinPayload(Pickup $pickup): ?array
    {
        if ($pickup->pickup_lat === null || $pickup->pickup_lng === null) {
            return null;
        }

        return [
            'lat' => (float) $pickup->pickup_lat,
            'lng' => (float) $pickup->pickup_lng,
            'checkin_at' => $pickup->pickup_checkin_at?->toIso8601String(),
        ];
    }

    /**
     * Đóng gói một ảnh bằng chứng pickup cho app.
     *
     * Trả `path` tương đối (vd `/uploads/pickup/...`) để app tự ghép host đang
     * gọi API (qua Env.apiBaseUrl). Tránh hardcode host theo APP_URL backend —
     * trên emulator/thiết bị thật host khác với APP_URL nên full URL sẽ hỏng.
     */
    protected function imagePayload(PickupImage $image): array
    {
        return [
            'id' => $image->id,
            'path' => $image->path,
            'url' => $image->url,
            'uploaded_at' => $image->created_at?->toIso8601String(),
        ];
    }

    /**
     * Danh sách trạng thái được phép chuyển tiếp (app chỉ render nút theo đây).
     */
    protected function transitionsPayload(?PickupStatusEnum $status): array
    {
        if ($status === null) {
            return [];
        }

        return collect($status->allowedTransitions())
            ->map(fn (PickupStatusEnum $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ])
            ->all();
    }

    /**
     * Summary cho header app: số phiếu chưa lấy + giờ hẹn gần nhất.
     *
     * @param  string  $scopeColumn  Cột scope: 'id_shipper' (shipper) | 'id_user' (OPS).
     */
    protected function pickupSummary(int $userId, string $scopeColumn): array
    {
        $pendingStatuses = [
            PickupStatusEnum::MOI_TAO_PICKUP->value,
            PickupStatusEnum::DA_XAC_NHAN->value,
            PickupStatusEnum::PICKUP_DANG_LAY->value,
        ];

        $pendingCount = Pickup::query()
            ->where($scopeColumn, $userId)
            ->whereIn('status', $pendingStatuses)
            ->count();

        // Dùng JSON ordering của Laravel (portable MySQL/SQLite) thay cho raw SQL.
        $nearestInfo = Pickup::query()
            ->where($scopeColumn, $userId)
            ->whereIn('status', $pendingStatuses)
            ->whereNotNull('info_pickup->ngayhen')
            ->orderBy('info_pickup->ngayhen')
            ->value('info_pickup');

        $nearest = data_get($nearestInfo, 'ngayhen');

        return [
            'pending_count' => $pendingCount,
            'nearest_schedule_at' => $nearest ? Carbon::parse($nearest)->toIso8601String() : null,
        ];
    }

    /**
     * Chuẩn hóa meta pagination (theo contract §1.5).
     */
    protected function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
