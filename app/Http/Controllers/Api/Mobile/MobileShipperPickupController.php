<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Api\Mobile\Concerns\PicksPickupPayload;
use App\Http\Controllers\Controller;
use App\Models\OrderPackage;
use App\Models\Pickup;
use App\Models\PickupImage;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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

    /** Số ảnh bằng chứng tối đa cho mỗi pickup. */
    private const MAX_IMAGES = 8;

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
            ->with(['user:id,fullname,username', 'orders:id,id_bill,tracking_code,uuid', 'images'])
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
        $daLayValue = PickupStatusEnum::PICKUP_DA_LAY->value;

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', PickupStatusEnum::values())],
            'reason' => ['nullable', 'string', 'max:255'],
            // GPS bắt buộc khi xác nhận đã lấy hàng (check-in chống khai khống).
            'lat' => [Rule::requiredIf(fn () => $request->input('status') === $daLayValue), 'nullable', 'numeric'],
            'lng' => [Rule::requiredIf(fn () => $request->input('status') === $daLayValue), 'nullable', 'numeric'],
        ], [
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'lat.required' => 'Cần vị trí GPS để xác nhận đã lấy hàng.',
            'lng.required' => 'Cần vị trí GPS để xác nhận đã lấy hàng.',
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

        // Lưu toạ độ check-in nếu app gửi kèm (bắt buộc ở pickup_da_lay).
        $lat = $validated['lat'] ?? null;
        $lng = $validated['lng'] ?? null;
        if ($lat !== null && $lng !== null) {
            $model->forceFill([
                'pickup_lat' => $lat,
                'pickup_lng' => $lng,
                'pickup_checkin_at' => now(),
            ])->save();
            $model = $model->fresh();
        }

        // Audit log truy vết: ai đổi trạng thái nào, kèm toạ độ check-in.
        ActivityLog::record(
            'pickup.status_change',
            'Shipper đổi trạng thái pickup '.($model->ma_pickup ?? $model->id),
            subject: $model,
            metadata: [
                'from' => $fromStatus?->value,
                'to' => $toStatus->value,
                'lat' => $lat,
                'lng' => $lng,
            ],
            note: $validated['reason'] ?? null,
        );

        return $this->ok([
            'id' => $model->id,
            'status' => $this->statusPayload($model->status),
            'allowed_transitions' => $this->transitionsPayload($model->status),
            'checkin' => $this->checkinPayload($model),
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
     * GET /api/mobile/shipper/pickups/{pickup}/images
     *
     * Danh sách ảnh bằng chứng của một pickup (scope theo shipper).
     */
    public function images(Request $request, int $pickup): JsonResponse
    {
        $model = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->with('images')
            ->find($pickup);

        if (! $model) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        return $this->ok([
            'items' => $model->images->map(fn (PickupImage $img) => $this->imagePayload($img))->all(),
        ], 'OK');
    }

    /**
     * POST /api/mobile/shipper/pickups/{pickup}/images
     *
     * Upload ảnh bằng chứng (multipart, field `image`). Tối đa MAX_IMAGES ảnh/pickup.
     */
    public function storeImage(Request $request, int $pickup): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'image.required' => 'Vui lòng chọn ảnh.',
            'image.image' => 'File không phải ảnh hợp lệ.',
            'image.mimes' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.',
            'image.max' => 'Ảnh tối đa 4MB.',
        ]);

        $model = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->withCount('images')
            ->find($pickup);

        if (! $model) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        if ($model->images_count >= self::MAX_IMAGES) {
            return $this->fail('Mỗi phiếu pickup chỉ lưu tối đa '.self::MAX_IMAGES.' ảnh.', 422);
        }

        $file = $request->file('image');
        $relativeDir = 'uploads'.DIRECTORY_SEPARATOR.'pickup'.DIRECTORY_SEPARATOR.$model->id;
        $uploadDir = public_path($relativeDir);
        $filename = $model->id.'_'.time().'_'.mt_rand(1000, 9999).'.'.$file->getClientOriginalExtension();

        try {
            if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0755, true) && ! is_dir($uploadDir)) {
                throw new RuntimeException('Không tạo được thư mục lưu ảnh.');
            }
            $file->move($uploadDir, $filename);
        } catch (\Throwable $e) {
            return $this->fail('Không lưu được ảnh. Vui lòng thử lại.', 500);
        }

        $image = PickupImage::create([
            'pickup_id' => $model->id,
            'path' => '/uploads/pickup/'.$model->id.'/'.$filename,
            'uploaded_by' => $request->user()->id,
        ]);

        Log::info('mobile.shipper.pickup.image.store', [
            'pickup_id' => $model->id,
            'image_id' => $image->id,
            'shipper_id' => $request->user()->id,
        ]);

        return $this->ok($this->imagePayload($image), 'Đã tải ảnh lên.', 201);
    }

    /**
     * DELETE /api/mobile/shipper/pickups/{pickup}/images/{image}
     *
     * Xóa ảnh bằng chứng. Chỉ cho xóa ảnh do chính shipper upload.
     */
    public function destroyImage(Request $request, int $pickup, int $image): JsonResponse
    {
        $model = Pickup::query()
            ->where('id_shipper', $request->user()->id)
            ->find($pickup);

        if (! $model) {
            return $this->fail('Không tìm thấy phiếu pickup.', 404);
        }

        $picture = PickupImage::query()
            ->where('pickup_id', $model->id)
            ->where('uploaded_by', $request->user()->id)
            ->find($image);

        if (! $picture) {
            return $this->fail('Không tìm thấy ảnh.', 404);
        }

        // Xóa file local trong /uploads/pickup/ nếu còn tồn tại.
        $path = (string) $picture->path;
        if (str_starts_with($path, '/uploads/pickup/')) {
            $absolute = public_path(ltrim($path, '/'));
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }

        $picture->delete();

        return $this->ok(null, 'Đã xóa ảnh.');
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
