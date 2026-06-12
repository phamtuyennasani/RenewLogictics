<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Enums\PickupStatusEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Api\Mobile\Concerns\PicksPickupPayload;
use App\Http\Controllers\Controller;
use App\Models\Pickup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pickup cho app OPS — xem pickup của OPS, gán shipper.
 *
 * Scope bảo mật: MỌI query đều ép where('id_user', auth()->id()).
 * OPS không bao giờ thấy/đụng pickup của OPS khác.
 *
 * Tái dùng trait PicksPickupPayload để tránh copy logic từ shipper controller.
 */
class MobileOpsPickupController extends Controller
{
    use ApiResponse, PicksPickupPayload;

    /**
     * GET /api/mobile/ops/pickups
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

        $opsId = $request->user()->id;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Pickup::query()
            ->where('id_user', $opsId)
            ->with(['shipper:id,fullname,username'])
            ->withCount('orders');

        // Lọc theo status trực tiếp hoặc tab; nếu không truyền gì → ẩn DA_HUY.
        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        } elseif (!empty($validated['tab'])) {
            $query->whereIn('status', $this->statusesForTab($validated['tab']));
        } else {
            $query->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', '!=', PickupStatusEnum::DA_HUY->value);
            });
        }

        if (!empty($validated['keyword'])) {
            $keyword = trim($validated['keyword']);
            $query->where(function ($sub) use ($keyword) {
                $sub->where('ma_pickup', 'like', "%{$keyword}%")
                    ->orWhere('info_khachhang', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->latest('ngay_tao')->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Pickup $pickup) => $this->pickupPayload($pickup, detailed: false, withShipper: true))
            ->all();

        return $this->ok([
            'summary' => $this->pickupSummary($opsId, 'id_user'),
            'items' => $items,
            'meta' => $this->meta($paginator),
        ], 'OK');
    }

    /**
     * GET /api/mobile/ops/pickups/{pickup}
     */
    public function show(Request $request, int $pickupId): JsonResponse
    {
        $pickup = Pickup::query()
            ->where('id_user', $request->user()->id)
            ->with(['shipper:id,fullname,username', 'orders:id,id_bill,tracking_code,uuid'])
            ->find($pickupId);

        if (!$pickup) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        return $this->ok($this->pickupPayload($pickup, detailed: true, withShipper: true), 'OK');
    }

    /**
     * POST /api/mobile/ops/pickups/{pickup}/assign-shipper
     */
    public function assignShipper(Request $request, int $pickupId): JsonResponse
    {
        $validated = $request->validate([
            'shipper_id' => ['required', 'integer', 'exists:user,id'],
        ]);

        $pickup = Pickup::query()
            ->where('id_user', $request->user()->id)
            ->lockForUpdate()
            ->find($pickupId);

        if (!$pickup) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        // Chỉ cho gán khi pickup chưa final.
        if ($pickup->status?->isFinal()) {
            return $this->fail('Không thể gán shipper cho pickup đã hoàn tất hoặc đã hủy.', 409);
        }

        // Validate shipper role.
        $shipper = User::query()
            ->whereKey($validated['shipper_id'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'shipper'))
            ->first();

        if (!$shipper) {
            return $this->fail('Shipper không hợp lệ.', 422);
        }

        $pickup->id_shipper = $shipper->id;
        $pickup->save();

        // PickupObserver tự bắn push cho shipper khi id_shipper thay đổi.

        return $this->ok([
            'pickup_id' => $pickup->id,
            'shipper' => [
                'id' => $shipper->id,
                'name' => $shipper->fullname ?: $shipper->username,
            ],
        ], 'Đã gán shipper cho phiếu pickup.');
    }

    /**
     * GET /api/mobile/ops/shippers — danh sách shipper để chọn.
     */
    public function shippers(Request $request): JsonResponse
    {
        $shippers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'shipper'))
            ->orderBy('fullname')
            ->orderBy('username')
            ->get(['id', 'fullname', 'username'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->fullname ?: $user->username,
            ])
            ->all();

        return $this->ok(['shippers' => $shippers], 'OK');
    }
}
