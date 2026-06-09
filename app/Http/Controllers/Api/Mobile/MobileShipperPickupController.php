<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Pickup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * API pickup cho app shipper.
 *
 * Nguyên tắc bảo mật: MỌI query đều ép where('id_shipper', auth()->id()).
 * Shipper không bao giờ thấy/đụng pickup của người khác.
 *
 * FSM trạng thái: tái dùng TransitionPickupStatusAction + PickupStatusEnum,
 * KHÔNG copy logic FSM trong controller (theo MOBILE_API_CONTRACT §3).
 */
class MobileShipperPickupController extends Controller
{
    use ApiResponse;

    /**
     * Map tab app → các status tương ứng (đồng bộ với component Livewire shipper).
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
     * GET /api/mobile/shipper/pickups
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'string', 'in:new,accepted,picking,done'],
            'status' => ['nullable', 'string', 'in:'.implode(',', PickupStatusEnum::values())],
            'keyword' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $shipperId = $request->user()->id;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Pickup::query()
            ->where('id_shipper', $shipperId)
            ->with(['user:id,fullname,username'])
            ->withCount('orders');

        // Lọc theo status trực tiếp hoặc tab; nếu không truyền gì → ẩn DA_HUY.
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } elseif (! empty($validated['tab'])) {
            $query->whereIn('status', $this->statusesForTab($validated['tab']));
        } else {
            $query->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', PickupStatusEnum::DA_HUY->value);
            });
        }

        if (! empty($validated['keyword'])) {
            $keyword = trim($validated['keyword']);
            $query->where(function ($sub) use ($keyword) {
                $sub->where('ma_pickup', 'like', "%{$keyword}%")
                    ->orWhere('info_khachhang', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->latest('ngay_tao')->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Pickup $pickup) => $this->pickupPayload($pickup))
            ->all();

        return $this->ok([
            'summary' => $this->summary($shipperId),
            'items' => $items,
            'meta' => $this->meta($paginator),
        ], 'OK');
    }

    /**
     * GET /api/mobile/shipper/pickups/{pickup}
     */
    public function show(Request $request, int $pickup): JsonResponse
    {
        $model = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->with(['user:id,fullname,username', 'orders:id,id_bill,tracking_code,uuid'])
            ->find($pickup);

        // Không tiết lộ pickup của người khác tồn tại → 404.
        if (! $model) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        return $this->ok($this->pickupPayload($model, detailed: true), 'OK');
    }

    /**
     * POST /api/mobile/shipper/pickups/{pickup}/status
     */
    public function updateStatus(Request $request, int $pickup): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', PickupStatusEnum::values())],
            'reason' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ]);

        $model = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->find($pickup);

        if (! $model) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        $fromStatus = $model->status;
        $toStatus = PickupStatusEnum::from($validated['status']);

        try {
            $model = TransitionPickupStatusAction::execute($model, $toStatus);
        } catch (RuntimeException $e) {
            // Sai FSM → 409 (action ném RuntimeException khi canTransitionTo = false).
            return $this->fail($e->getMessage(), 409);
        }

        // Ghi vết thao tác đổi trạng thái pickup (theo contract §3.3, §5).
        Log::info('mobile.shipper.pickup.status', [
            'pickup_id' => $model->id,
            'shipper_id' => $request->user()->id,
            'from' => $fromStatus?->value,
            'to' => $toStatus->value,
            'reason' => $validated['reason'] ?? null,
        ]);

        return $this->ok([
            'id' => $model->id,
            'status' => $this->statusPayload($model->status),
            'allowed_transitions' => $this->transitionsPayload($model->status),
        ], 'Đã cập nhật trạng thái.');
    }

    /**
     * Đóng gói một pickup thành payload cho app.
     *
     * KHÔNG trả field tài chính (total_cuoc, total_cuocvon...) — theo contract §5.
     */
    protected function pickupPayload(Pickup $pickup, bool $detailed = false): array
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
            'scheduled_at' => $scheduledAt ? \Carbon\Carbon::parse($scheduledAt)->toIso8601String() : null,
            'package_count' => (int) $pickup->numb,
            'weight_kg' => (float) $pickup->total_c_weight,
            'note' => $pickup->note,
            'created_by' => $pickup->user?->fullname ?: $pickup->user?->username,
            'allowed_transitions' => $this->transitionsPayload($pickup->status),
        ];

        if ($detailed) {
            $payload['weight_gross_kg'] = (float) $pickup->total_weight;
            $payload['created_at'] = $pickup->ngay_tao?->toIso8601String();
            $payload['orders'] = $pickup->orders->map(fn ($order) => [
                'id' => $order->id,
                'id_bill' => $order->id_bill,
                'tracking_code' => $order->tracking_code,
                'uuid' => $order->uuid,
            ])->all();
        } else {
            $payload['orders_count'] = (int) ($pickup->orders_count ?? 0);
        }

        return $payload;
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
     * Summary cho header app: số đơn chưa lấy + giờ hẹn gần nhất.
     */
    protected function summary(int $shipperId): array
    {
        $pendingStatuses = [
            PickupStatusEnum::MOI_TAO_PICKUP->value,
            PickupStatusEnum::DA_XAC_NHAN->value,
            PickupStatusEnum::PICKUP_DANG_LAY->value,
        ];

        $pendingCount = Pickup::query()
            ->where('id_shipper', $shipperId)
            ->whereIn('status', $pendingStatuses)
            ->count();

        // Dùng JSON ordering của Laravel (portable MySQL/SQLite) thay cho
        // JSON_UNQUOTE raw SQL — Laravel tự dịch path theo driver.
        $nearestInfo = Pickup::query()
            ->where('id_shipper', $shipperId)
            ->whereIn('status', $pendingStatuses)
            ->whereNotNull('info_pickup->ngayhen')
            ->orderBy('info_pickup->ngayhen')
            ->value('info_pickup');

        $nearest = data_get($nearestInfo, 'ngayhen');

        return [
            'pending_count' => $pendingCount,
            'nearest_schedule_at' => $nearest ? \Carbon\Carbon::parse($nearest)->toIso8601String() : null,
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
