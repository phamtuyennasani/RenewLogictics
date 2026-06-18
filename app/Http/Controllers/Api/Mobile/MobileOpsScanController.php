<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Order\RecordTrackingHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Enums\RoleEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OPS Scan API — tra cứu đơn theo mã quét và nhập kho.
 *
 * Theo MOBILE_API_CONTRACT §4. Role được phép: ops, admin, manager, cs
 * (đồng bộ web — OPS chỉ chuyển DA_XAC_NHAN → DA_NHAN_HANG).
 *
 * Tái dùng RecordTrackingHistoryAction khi nhập kho — KHÔNG copy logic FSM.
 */
class MobileOpsScanController extends Controller
{
    use ApiResponse;

    /**
     * Các role được phép thao tác OPS scan/receive.
     * CS không thuộc danh sách này (xem RoleEnum comment).
     *
     * @var list<string>
     */
    private const OPS_ROLES = [
        RoleEnum::OPS->value,
        RoleEnum::ADMIN->value,
        RoleEnum::MANAGER->value,
    ];

    /**
     * POST /api/mobile/ops/scan
     *
     * Tra cứu đơn theo mã quét. Chỉ đọc, không thay đổi dữ liệu.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:191'],
        ], [
            'code.required' => 'Vui lòng nhập hoặc quét mã.',
        ]);

        [$order, $matchedBy, $matchedPackageCode] = $this->resolveOrderByCode(trim($validated['code']));

        if (! $order) {
            return $this->ok([
                'found' => false,
                'matched_by' => null,
                'matched_package_code' => null,
                'order' => null,
                'can_receive' => false,
                'reason' => 'Không tìm thấy đơn khớp mã.',
            ], 'Không tìm thấy đơn khớp mã.');
        }

        [$canReceive, $reason] = $this->evaluateReceivable($order);

        return $this->ok([
            'found' => true,
            'matched_by' => $matchedBy,
            'matched_package_code' => $matchedPackageCode,
            'order' => $this->orderPayload($order),
            'can_receive' => $canReceive,
            'reason' => $reason,
        ], 'Tìm thấy đơn hàng.');
    }

    /**
     * POST /api/mobile/ops/orders/{order}/receive
     *
     * Xác nhận nhập kho một đơn.
     */
    public function receive(Request $request, Order $order): JsonResponse
    {
        [$canReceive, $reason] = $this->evaluateReceivable($order);

        if (! $canReceive) {
            return $this->fail($reason ?? 'Không thể nhập kho đơn này.', 409);
        }

        $this->confirmReceive($order);

        return $this->ok([
            'order' => [
                'id' => $order->id,
                'id_bill' => $order->id_bill,
                'status' => $this->statusPayload($order->bill_status),
                'received_at' => optional($order->ngaynhanhang)->toIso8601String(),
            ],
        ], 'Đã nhập kho đơn '.$order->id_bill.'.');
    }

    /**
     * POST /api/mobile/ops/orders/bulk-receive
     *
     * Nhập kho hàng loạt. Mỗi đơn xử lý độc lập (một đơn lỗi không rollback đơn khác).
     */
    public function bulkReceive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codes' => ['array', 'max:50'],
            'codes.*' => ['string', 'max:191'],
            'order_ids' => ['array', 'max:50'],
            'order_ids.*' => ['integer'],
        ]);

        $codes = $validated['codes'] ?? [];
        $orderIds = $validated['order_ids'] ?? [];

        if ($codes === [] && $orderIds === []) {
            return $this->fail('Vui lòng gửi danh sách mã hoặc id đơn.', 422, [
                'codes' => ['Cần ít nhất một mã hoặc id đơn.'],
            ]);
        }

        $succeeded = [];
        $failed = [];

        // Xử lý theo codes (cần resolve mã)
        foreach ($codes as $code) {
            $code = trim((string) $code);
            [$order] = $this->resolveOrderByCode($code);

            if (! $order) {
                $failed[] = ['code' => $code, 'order_id' => null, 'reason' => 'Không tìm thấy đơn khớp mã.'];

                continue;
            }

            $this->processBulkItem($order, $code, $succeeded, $failed);
        }

        // Xử lý theo order_ids (truy thẳng PK)
        foreach ($orderIds as $orderId) {
            $order = Order::query()->find($orderId);

            if (! $order) {
                $failed[] = ['code' => null, 'order_id' => (int) $orderId, 'reason' => 'Không tìm thấy đơn.'];

                continue;
            }

            $this->processBulkItem($order, null, $succeeded, $failed);
        }

        $total = count($succeeded) + count($failed);

        return $this->ok([
            'succeeded' => $succeeded,
            'failed' => $failed,
        ], sprintf('Đã xử lý %d đơn: %d thành công, %d lỗi.', $total, count($succeeded), count($failed)));
    }

    /**
     * Resolve đơn theo mã quét, thứ tự: id_bill → tracking_code → mathamchieu → order_package.code.
     *
     * @return array{0: ?Order, 1: ?string, 2: ?string}  [order, matched_by, matched_package_code]
     */
    private function resolveOrderByCode(string $code): array
    {
        if ($code === '') {
            return [null, null, null];
        }

        if ($order = Order::query()->where('id_bill', $code)->first()) {
            return [$order, 'id_bill', null];
        }

        if ($order = Order::query()->where('tracking_code', $code)->first()) {
            return [$order, 'tracking_code', null];
        }

        if ($order = Order::query()->where('mathamchieu', $code)->first()) {
            return [$order, 'mathamchieu', null];
        }

        $package = OrderPackage::query()->where('code', $code)->first();
        if ($package && $package->order) {
            return [$package->order, 'package_code', $package->code];
        }

        return [null, null, null];
    }

    /**
     * Đánh giá đơn có thể nhập kho hay không.
     *
     * @return array{0: bool, 1: ?string}  [can_receive, reason]
     */
    private function evaluateReceivable(Order $order): array
    {
        if (! $this->userIsOpsCapable()) {
            return [false, 'Tài khoản không có quyền nhập kho.'];
        }

        if ($order->lock_order) {
            return [false, 'Đơn đã bị khóa, không thể nhập kho.'];
        }

        $status = $order->bill_status;

        if ($status === OrderStatusEnum::DA_NHAN_HANG) {
            return [false, 'Đơn đang ở trạng thái Đã nhận hàng, không cần nhập lại.'];
        }

        if ($status === OrderStatusEnum::HUY) {
            return [false, 'Đơn đã hủy.'];
        }

        if ($status !== OrderStatusEnum::DA_XAC_NHAN) {
            return [false, 'Đơn đang ở trạng thái '.($status?->label() ?? 'không xác định').', không thể nhập kho.'];
        }

        return [true, null];
    }

    /**
     * Thực hiện nhập kho trong transaction: cập nhật trạng thái, ghi ngaynhanhang, ghi tracking history.
     */
    private function confirmReceive(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $update = ['bill_status' => OrderStatusEnum::DA_NHAN_HANG];

            if ($order->ngaynhanhang === null) {
                $update['ngaynhanhang'] = now();
            }

            $order->update($update);

            RecordTrackingHistoryAction::execute($order, OrderStatusEnum::DA_NHAN_HANG, now());
        });

        $order->refresh();
    }

    /**
     * Xử lý một đơn trong batch, gom vào succeeded/failed.
     *
     * @param  array<int, array<string, mixed>>  $succeeded
     * @param  array<int, array<string, mixed>>  $failed
     */
    private function processBulkItem(Order $order, ?string $code, array &$succeeded, array &$failed): void
    {
        [$canReceive, $reason] = $this->evaluateReceivable($order);

        if (! $canReceive) {
            $failed[] = ['code' => $code, 'order_id' => $order->id, 'reason' => $reason];

            return;
        }

        $this->confirmReceive($order);

        $succeeded[] = [
            'code' => $code ?? $order->id_bill,
            'order_id' => $order->id,
            'status' => $order->bill_status?->value,
        ];
    }

    /**
     * Đóng gói thông tin đơn cho app (KHÔNG trả field tài chính).
     *
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order): array
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
            'package_count' => $order->packages()->count(),
            'weight_kg' => (float) $order->packages()->sum(DB::raw('COALESCE(row_c_weight, c_weight)')),
            'sale_name' => $order->sale?->fullname ?? $order->sale?->username,
            'locked' => (bool) $order->lock_order,
            'received_at' => optional($order->ngaynhanhang)->toIso8601String(),
        ];
    }

    private function userIsOpsCapable(): bool
    {
        return auth()->user()?->hasAnyRole(self::OPS_ROLES) ?? false;
    }
}
