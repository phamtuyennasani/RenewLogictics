<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Api\Mobile\Concerns\PicksPickupPayload;
use App\Http\Controllers\Controller;
use App\Models\OrderPackage;
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
    use ApiResponse, PicksPickupPayload;

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
            ->map(fn (Pickup $pickup) => $this->pickupPayload($pickup, detailed: false, withShipper: false))
            ->all();

        return $this->ok([
            'summary' => $this->pickupSummary($shipperId, 'id_shipper'),
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

        return $this->ok($this->pickupPayload($model, detailed: true, withShipper: false), 'OK');
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
     * POST /api/mobile/shipper/scan
     *
     * Quét mã kiện → tìm pickup được gán cho shipper chứa đơn của kiện đó.
     * Chỉ đọc, không đổi dữ liệu (đồng bộ web shipper-scan §processScan).
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:191'],
        ], [
            'code.required' => 'Vui lòng quét hoặc nhập mã kiện.',
        ]);

        $code = trim(strtoupper($validated['code']));

        $package = OrderPackage::query()
            ->with(['order:id,id_bill,tracking_code'])
            ->where('code', $code)
            ->first();

        if (! $package || ! $package->order) {
            return $this->ok([
                'found' => false,
                'package_code' => $code,
                'pickup' => null,
                'order_code' => null,
                'can_receive' => false,
                'reason' => 'Không tìm thấy đơn hàng từ mã kiện này.',
            ], 'Không tìm thấy đơn hàng từ mã kiện này.');
        }

        $pickup = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->whereHas('orders', fn ($query) => $query->whereKey($package->order->id))
            ->where('status', '!=', PickupStatusEnum::DA_HUY->value)
            ->with([
                'user:id,fullname,username',
                'orders:id,id_bill,tracking_code,uuid',
            ])
            ->first();

        if (! $pickup) {
            return $this->ok([
                'found' => false,
                'package_code' => $code,
                'pickup' => null,
                'order_code' => null,
                'can_receive' => false,
                'reason' => 'Không tìm thấy pickup được gán cho bạn từ mã kiện này.',
            ], 'Không tìm thấy pickup được gán cho bạn.');
        }

        $canReceive = $pickup->status !== PickupStatusEnum::PICKUP_DA_LAY;
        $orderCode = $package->order->id_bill ?: $package->order->tracking_code ?: null;

        return $this->ok([
            'found' => true,
            'package_code' => $package->code,
            'order_id' => $package->order->id,
            'order_code' => $orderCode,
            'pickup' => $this->pickupPayload($pickup, detailed: true, withShipper: false),
            'can_receive' => $canReceive,
            'reason' => $canReceive ? null : 'Pickup này đã được nhận hàng.',
        ], $canReceive
            ? 'Đã tìm thấy pickup. Kiểm tra thông tin rồi bấm nhận hàng.'
            : 'Pickup này đã được nhận hàng.');
    }

    /**
     * POST /api/mobile/shipper/pickups/receive-by-scan
     *
     * Nhận hàng pickup sau khi quét mã (chuyển sang PICKUP_DA_LAY).
     * Đồng bộ web §receiveScannedPickup + §markPickupAsReceived.
     */
    public function receiveByScan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pickup_id' => ['required', 'integer'],
            'order_id' => ['required', 'integer'],
        ], [
            'pickup_id.required' => 'Thiếu thông tin pickup.',
            'order_id.required' => 'Thiếu thông tin đơn hàng.',
        ]);

        $pickup = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->whereHas('orders', fn ($query) => $query->whereKey($validated['order_id']))
            ->find($validated['pickup_id']);

        if (! $pickup) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        try {
            $pickup = $this->markPickupAsReceived($pickup);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 409);
        }

        Log::info('mobile.shipper.pickup.receive-by-scan', [
            'pickup_id' => $pickup->id,
            'shipper_id' => $request->user()->id,
            'order_id' => $validated['order_id'],
        ]);

        return $this->ok([
            'id' => $pickup->id,
            'status' => $this->statusPayload($pickup->status),
            'allowed_transitions' => $this->transitionsPayload($pickup->status),
        ], 'Shipper đã nhận hàng thành công.');
    }

    /**
     * Chuyển pickup qua chuỗi trạng thái tới PICKUP_DA_LAY (idempotent).
     *
     * Lặp DA_XAC_NHAN → PICKUP_DANG_LAY → PICKUP_DA_LAY, chỉ chuyển khi FSM cho
     * phép. Tái dùng TransitionPickupStatusAction, KHÔNG copy logic FSM.
     */
    protected function markPickupAsReceived(Pickup $pickup): Pickup
    {
        $pickup = $pickup->fresh();

        if ($pickup->status === PickupStatusEnum::PICKUP_DA_LAY) {
            return $pickup;
        }

        $chain = [
            PickupStatusEnum::DA_XAC_NHAN,
            PickupStatusEnum::PICKUP_DANG_LAY,
            PickupStatusEnum::PICKUP_DA_LAY,
        ];

        foreach ($chain as $nextStatus) {
            if ($pickup->status?->canTransitionTo($nextStatus)) {
                $pickup = TransitionPickupStatusAction::execute($pickup, $nextStatus);
            }
        }

        if ($pickup->status !== PickupStatusEnum::PICKUP_DA_LAY) {
            throw new RuntimeException('Không thể chuyển pickup sang trạng thái đã lấy hàng.');
        }

        if (blank($pickup->ngay_nhanhang)) {
            $pickup->forceFill(['ngay_nhanhang' => now()])->save();
            $pickup = $pickup->fresh();
        }

        return $pickup;
    }
}
