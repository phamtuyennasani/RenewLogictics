<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Pickup\CreatePickupAction;
use App\Enums\OrderStatusEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * API order cho app OPS — xem danh sách order được giao, chi tiết, tạo pickup.
 *
 * Scope bảo mật: MỌI query đều ép where('id_ops', auth()->id()).
 * OPS không bao giờ thấy/đụng order của OPS khác.
 */
class MobileOpsOrderController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/mobile/ops/orders
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:'.implode(',', OrderStatusEnum::values())],
            'has_pickup' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $opsId = $request->user()->id;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = Order::query()
            ->where('id_ops', $opsId)
            ->with(['sale:id,fullname,username'])
            ->withCount('packages');

        if (!empty($validated['status'])) {
            $query->where('bill_status', $validated['status']);
        }

        if (isset($validated['has_pickup'])) {
            if ($validated['has_pickup']) {
                $query->has('pickups');
            } else {
                $query->doesntHave('pickups');
            }
        }

        if (!empty($validated['keyword'])) {
            $keyword = trim($validated['keyword']);
            $query->where(function ($sub) use ($keyword) {
                $sub->where('id_bill', 'like', "%{$keyword}%")
                    ->orWhere('tracking_code', 'like', "%{$keyword}%")
                    ->orWhere('mathamchieu', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->latest('created_at')->paginate($perPage);

        $items = collect($paginator->items())
            ->map(fn (Order $order) => $this->orderListPayload($order))
            ->all();

        return $this->ok([
            'items' => $items,
            'meta' => $this->meta($paginator),
        ], 'OK');
    }

    /**
     * GET /api/mobile/ops/orders/{order}
     */
    public function show(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()
            ->where('id_ops', $request->user()->id)
            ->with(['sale:id,fullname,username', 'packages'])
            ->find($orderId);

        if (!$order) {
            return $this->fail('Không tìm thấy đơn hàng.', 404);
        }

        return $this->ok($this->orderDetailPayload($order), 'OK');
    }

    /**
     * POST /api/mobile/ops/orders/{order}/pickups
     */
    public function createPickup(Request $request, int $orderId): JsonResponse
    {
        $order = Order::query()
            ->where('id_ops', $request->user()->id)
            ->lockForUpdate()
            ->find($orderId);

        if (!$order) {
            return $this->fail('Không tìm thấy đơn hàng.', 404);
        }

        $validated = $request->validate([
            'shipper_id' => ['nullable', 'integer', 'exists:user,id'],
            'scheduled_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
            'company' => ['required', 'string', 'max:191'],
            'fullname' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:191'],
            'country' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:500'],
            'id_city' => ['required', 'integer', 'exists:province,id'],
            'id_ward' => ['required', 'integer', 'exists:wards,id'],
            'pickup_lat' => ['nullable', 'numeric'],
            'pickup_lng' => ['nullable', 'numeric'],
            'vehicle_id' => ['nullable', 'integer', 'exists:news,id'],
            'branch_id' => ['nullable', 'integer', 'exists:news,id'],
        ]);

        // Validate shipper role nếu có.
        if (!empty($validated['shipper_id'])) {
            $shipper = User::query()
                ->whereKey($validated['shipper_id'])
                ->whereHas('roles', fn ($q) => $q->where('name', 'shipper'))
                ->first();

            if (!$shipper) {
                return $this->fail('Shipper không hợp lệ.', 422);
            }
        }

        // Validate ward thuộc province.
        $wardBelongs = DB::table('wards')
            ->where('id', $validated['id_ward'])
            ->where('parent_code', $validated['id_city'])
            ->exists();

        if (!$wardBelongs) {
            return $this->fail('Phường/xã không thuộc tỉnh/thành đã chọn.', 422);
        }

        // Map vào format CreatePickupAction mong đợi.
        $data = [
            'ops_id' => $request->user()->id,
            'shipper_id' => $validated['shipper_id'] ?? null,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
            'note' => $validated['note'] ?? null,
            'sender_snapshot' => [
                'company' => $validated['company'],
                'fullname' => $validated['fullname'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'country' => $validated['country'],
                'address' => $validated['address'],
                'id_city' => $validated['id_city'],
                'id_ward' => $validated['id_ward'],
            ],
            'pickup_lat' => $validated['pickup_lat'] ?? null,
            'pickup_lng' => $validated['pickup_lng'] ?? null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
            'total_weight' => (float) $order->packages()->sum(DB::raw('COALESCE(c_weight, 0)')),
            'packages_count' => (int) $order->packages()->count(),
        ];

        try {
            $pickup = CreatePickupAction::execute($order, $data, $request->user()->id);
        } catch (RuntimeException $e) {
            return $this->fail($e->getMessage(), 409);
        }

        return $this->ok([
            'pickup_id' => $pickup->id,
            'ma_pickup' => $pickup->ma_pickup,
        ], 'Đã tạo phiếu pickup.');
    }

    /**
     * Đóng gói order cho danh sách (tái dùng cấu trúc scan controller).
     */
    protected function orderListPayload(Order $order): array
    {
        $sender = $order->sender ?? [];
        $receiver = $order->receiver ?? [];

        return [
            'id' => $order->id,
            'id_bill' => $order->id_bill,
            'tracking_code' => $order->tracking_code,
            'mathamchieu' => $order->mathamchieu,
            'status' => $this->statusPayload($order->bill_status),
            'sender' => [
                'company' => data_get($sender, 'company'),
                'fullname' => data_get($sender, 'fullname', data_get($sender, 'tenlienhe')),
                'phone' => data_get($sender, 'phone'),
            ],
            'receiver' => [
                'fullname' => data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe')),
                'country' => data_get($receiver, 'country'),
            ],
            'package_count' => (int) ($order->packages_count ?? 0),
            'weight_kg' => (float) $order->packages()->sum(DB::raw('COALESCE(c_weight, 0)')),
            'sale_name' => $order->sale?->fullname ?? $order->sale?->username,
            'has_pickup' => $order->pickups()->exists(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

    /**
     * Đóng gói order detail + packages + can_create_pickup.
     */
    protected function orderDetailPayload(Order $order): array
    {
        $sender = $order->sender ?? [];
        $receiver = $order->receiver ?? [];

        $canCreatePickup = in_array($order->bill_status, [
            OrderStatusEnum::MOI_TAO,
            OrderStatusEnum::DA_XAC_NHAN,
        ], true) && !$order->pickups()->exists();

        return [
            'id' => $order->id,
            'id_bill' => $order->id_bill,
            'tracking_code' => $order->tracking_code,
            'mathamchieu' => $order->mathamchieu,
            'status' => $this->statusPayload($order->bill_status),
            'sender' => [
                'company' => data_get($sender, 'company'),
                'fullname' => data_get($sender, 'fullname', data_get($sender, 'tenlienhe')),
                'phone' => data_get($sender, 'phone'),
                'email' => data_get($sender, 'email'),
                'address' => data_get($sender, 'address'),
                'country' => data_get($sender, 'country'),
            ],
            'receiver' => [
                'fullname' => data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe')),
                'country' => data_get($receiver, 'country'),
            ],
            'packages' => $order->packages->map(fn ($pkg) => [
                'id' => $pkg->id,
                'number_of_package' => (int) $pkg->number_of_package,
                'c_weight' => (float) $pkg->c_weight,
            ])->all(),
            'sale_name' => $order->sale?->fullname ?? $order->sale?->username,
            'note' => $order->ghichu,
            'can_create_pickup' => $canCreatePickup,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }

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
