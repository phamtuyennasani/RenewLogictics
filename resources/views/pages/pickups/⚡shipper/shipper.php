<?php


use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\OrderPackage;
use App\Models\Pickup;
use App\Models\PickupImage;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.mobile')] #[Title('Danh sách Pickup')] class extends Component
{
    use WithPagination, WithoutUrlPagination, WithFileUploads;

    /** Số ảnh bằng chứng tối đa cho mỗi pickup. */
    public const MAX_IMAGES = 8;

    public string $keyword = '';
    public string $tab = 'new'; // new | accepted | picking | done
    public ?int $expandedId = null;
    public string $scanBarcodeInput = '';
    public ?int $scannedPickupId = null;
    public ?int $scannedOrderId = null;

    // Modal up ảnh bằng chứng khi xác nhận đã lấy hàng.
    public bool $showProofModal = false;
    public ?int $proofPickupId = null;
    public string $proofSource = 'card'; // card | scan
    public array $proofImages = []; // input tạm mỗi lần chọn (bị reset sau khi gom)
    public array $proofPhotos = []; // ảnh đã tích lũy qua nhiều lần chụp/chọn

    public array $scanResult = [
        'message' => '',
        'type' => 'neutral',
        'package_code' => '',
        'order_code' => '',
        'pickup_code' => '',
        'pickup_status' => '',
        'customer' => '',
        'address' => '',
        'phone' => '',
        'package_count' => 0,
    ];

    public function mount(): void
    {
        abort_unless(\Gate::allows('pickups.index'), 403);
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'tab'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Map tab sang các status tương ứng.
     *  - new      = MOI_TAO_PICKUP    (Mới giao)
     *  - accepted = DA_XAC_NHAN       (Tiếp nhận)
     *  - picking  = PICKUP_DANG_LAY   (Đang lấy)
     *  - done     = PICKUP_DA_LAY     (Đã lấy)
     */
    protected function statusesForTab(): ?array
    {
        return match ($this->tab) {
            'new'      => [PickupStatusEnum::MOI_TAO_PICKUP->value],
            'accepted' => [PickupStatusEnum::DA_XAC_NHAN->value],
            'picking'  => [PickupStatusEnum::PICKUP_DANG_LAY->value],
            'done'     => [PickupStatusEnum::PICKUP_DA_LAY->value],
            default    => [PickupStatusEnum::MOI_TAO_PICKUP->value],
        };
    }

    #[Computed]
    public function pickups()
    {
        $statuses = $this->statusesForTab();

        return Pickup::query()
            ->where('id_shipper', auth()->id())
            ->with(['user:id,fullname,username', 'orders:id,id_bill,tracking_code,uuid'])
            ->withCount('orders')
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('ma_pickup', 'like', "%{$keyword}%")
                        ->orWhere('info_khachhang', 'like', "%{$keyword}%");
                });
            })
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when(! $statuses, fn ($q) => $q->where(function ($q2) {
                $q2->whereNull('status')
                   ->orWhere('status', '!=', PickupStatusEnum::DA_HUY->value);
            }))
            ->latest('ngay_tao')
            ->paginate(15);
    }

    /**
     * Summary stats cho header.
     */
    public function getSummaryProperty(): array
    {
        $baseQuery = Pickup::query()->where('id_shipper', auth()->id());

        $pendingCount = (clone $baseQuery)->whereIn('status', [
            PickupStatusEnum::MOI_TAO_PICKUP->value,
            PickupStatusEnum::DA_XAC_NHAN->value,
            PickupStatusEnum::PICKUP_DANG_LAY->value,
        ])->count();

        $nearestSchedule = (clone $baseQuery)->whereIn('status', [
            PickupStatusEnum::MOI_TAO_PICKUP->value,
            PickupStatusEnum::DA_XAC_NHAN->value,
            PickupStatusEnum::PICKUP_DANG_LAY->value,
        ])->whereNotNull('info_pickup->ngayhen')
          ->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(info_pickup, '$.ngayhen')) ASC")
          ->value('info_pickup');

        $nearestTime = data_get($nearestSchedule, 'ngayhen');

        return [
            'pending_count' => $pendingCount,
            'nearest_time' => $nearestTime,
        ];
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function updateStatus(int $pickupId, string $status): void
    {
        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->findOrFail($pickupId);

        try {
            TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::from($status));
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'warning');
            return;
        }

        Flux::toast(heading: 'Thành công', text: 'Đã cập nhật trạng thái.', variant: 'success');
    }

    /**
     * Mở modal up ảnh bằng chứng trước khi xác nhận đã lấy hàng.
     * $source: 'card' (nút trong card) hoặc 'scan' (luồng quét mã).
     */
    public function openProofModal(int $pickupId, string $source = 'card'): void
    {
        // Xác thực pickup thuộc shipper hiện tại.
        $exists = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->whereKey($pickupId)
            ->exists();

        if (! $exists) {
            Flux::toast(heading: 'Lỗi', text: 'Không tìm thấy phiếu pickup.', variant: 'warning');
            return;
        }

        $this->proofPickupId = $pickupId;
        $this->proofSource = $source === 'scan' ? 'scan' : 'card';
        $this->proofImages = [];
        $this->proofPhotos = [];
        $this->resetValidation();
        $this->showProofModal = true;
    }

    /**
     * Mỗi lần shipper chọn/chụp ảnh, gom vào mảng tích lũy rồi reset input tạm
     * để có thể chụp tiếp (Livewire multiple thay thế mảng — nên phải gom tay).
     */
    public function updatedProofImages(): void
    {
        // Validate nhanh từng ảnh mới trước khi gom.
        $this->validate([
            'proofImages.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'proofImages.*.image' => 'File không phải ảnh hợp lệ.',
            'proofImages.*.mimes' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.',
            'proofImages.*.max' => 'Mỗi ảnh tối đa 4MB.',
        ]);

        foreach ($this->proofImages as $img) {
            if (count($this->proofPhotos) >= self::MAX_IMAGES) {
                break;
            }
            $this->proofPhotos[] = $img;
        }

        $this->proofImages = [];
    }

    public function removeProofPhoto(int $index): void
    {
        unset($this->proofPhotos[$index]);
        $this->proofPhotos = array_values($this->proofPhotos);
    }

    public function closeProofModal(): void
    {
        $this->showProofModal = false;
        $this->proofPickupId = null;
        $this->proofImages = [];
        $this->proofPhotos = [];
        $this->resetValidation();
    }

    /**
     * Xác nhận đã lấy hàng KÈM ảnh bằng chứng (bắt buộc ≥ 1 ảnh).
     * Lưu ảnh vào pickup_images rồi chuyển trạng thái sang PICKUP_DA_LAY.
     */
    public function confirmReceivedWithProof(): void
    {
        $this->validate([
            'proofPhotos' => ['required', 'array', 'min:1', 'max:'.self::MAX_IMAGES],
            'proofPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'proofPhotos.required' => 'Vui lòng chụp hoặc chọn ít nhất 1 ảnh chứng minh.',
            'proofPhotos.min' => 'Vui lòng cung cấp ít nhất 1 ảnh chứng minh.',
            'proofPhotos.max' => 'Tối đa '.self::MAX_IMAGES.' ảnh.',
            'proofPhotos.*.image' => 'File không phải ảnh hợp lệ.',
            'proofPhotos.*.mimes' => 'Chỉ chấp nhận ảnh JPG, PNG, WEBP.',
            'proofPhotos.*.max' => 'Mỗi ảnh tối đa 4MB.',
        ]);

        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->withCount('images')
            ->find($this->proofPickupId);

        if (! $pickup) {
            Flux::toast(heading: 'Lỗi', text: 'Không tìm thấy phiếu pickup.', variant: 'warning');
            $this->closeProofModal();
            return;
        }

        if ($pickup->images_count + count($this->proofPhotos) > self::MAX_IMAGES) {
            Flux::toast(heading: 'Lỗi', text: 'Mỗi phiếu chỉ lưu tối đa '.self::MAX_IMAGES.' ảnh.', variant: 'warning');
            return;
        }

        // Move file ra đĩa TRƯỚC (ngoài DB transaction). Giữ danh sách đường dẫn
        // tuyệt đối để dọn rác nếu phần DB phía sau thất bại.
        try {
            $stored = $this->moveProofImages($pickup);
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Lỗi', text: 'Không lưu được ảnh. Vui lòng thử lại.', variant: 'warning');
            return;
        }

        // DB: tạo bản ghi ảnh + chuyển trạng thái trong 1 transaction.
        try {
            DB::transaction(function () use ($pickup, $stored) {
                foreach ($stored as $item) {
                    PickupImage::create([
                        'pickup_id' => $pickup->id,
                        'path' => $item['path'],
                        'uploaded_by' => auth()->id(),
                    ]);
                }
                $this->markPickupAsReceived($pickup);
            });
        } catch (\RuntimeException $e) {
            $this->deleteStoredFiles($stored);
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'warning');
            return;
        } catch (\Throwable $e) {
            $this->deleteStoredFiles($stored);
            Flux::toast(heading: 'Lỗi', text: 'Không lưu được ảnh. Vui lòng thử lại.', variant: 'warning');
            return;
        }

        $fromScan = $this->proofSource === 'scan';
        $this->closeProofModal();

        if ($fromScan) {
            $packageCount = max(1, (int) ($this->scanResult['package_count'] ?? 1));
            $this->scanResult['pickup_status'] = PickupStatusEnum::PICKUP_DA_LAY->label();
            $this->scanResult['message'] = $packageCount > 1
                ? "Shiper đã nhận đủ {$packageCount} kiện hàng"
                : 'Shiper đã nhận hàng thành công';
            $this->scanResult['type'] = 'success';
            $this->clearScannedPickup();
        }

        $this->tab = 'done';
        $this->resetPage();

        Flux::toast(heading: 'Thành công', text: 'Đã nhận hàng và lưu ảnh chứng minh.', variant: 'success');
    }

    /**
     * Move các ảnh tạm vào public/uploads/pickup/{id}. CHỈ thao tác đĩa,
     * KHÔNG ghi DB (để gọi trước transaction). Trả về [['path'=>.., 'absolute'=>..]].
     */
    protected function moveProofImages(Pickup $pickup): array
    {
        $relativeDir = 'uploads/pickup/'.$pickup->id;
        $uploadDir = public_path($relativeDir);

        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0755, true) && ! is_dir($uploadDir)) {
            throw new \RuntimeException('Không tạo được thư mục lưu ảnh.');
        }

        $stored = [];
        foreach ($this->proofPhotos as $i => $image) {
            $filename = $pickup->id.'_'.time().'_'.$i.'_'.mt_rand(1000, 9999).'.'.$image->getClientOriginalExtension();
            $absolute = $uploadDir.DIRECTORY_SEPARATOR.$filename;
            // Dùng copy(getRealPath()) thay vì ->move(): ảnh tạm của Livewire là
            // TemporaryUploadedFile (storage/app/private/livewire-tmp), không phải
            // HTTP upload thật nên UploadedFile::move() ném lỗi. Đồng bộ pattern
            // avatar shipper đã chạy ổn (taikhoan/shipper.blade.php).
            if (! @copy($image->getRealPath(), $absolute)) {
                throw new \RuntimeException('Không lưu được ảnh ra đĩa.');
            }
            $stored[] = [
                'path' => '/'.$relativeDir.'/'.$filename,
                'absolute' => $absolute,
            ];
        }

        return $stored;
    }

    /**
     * Dọn các file đã move khi phần DB thất bại (tránh file mồ côi).
     */
    protected function deleteStoredFiles(array $stored): void
    {
        foreach ($stored as $item) {
            $absolute = $item['absolute'] ?? null;
            if ($absolute && is_file($absolute)) {
                @unlink($absolute);
            }
        }
    }

    public function submitShipperScan(): void
    {
        $code = trim($this->scanBarcodeInput);
        $this->scanBarcodeInput = '';

        if (blank($code)) {
            return;
        }

        $this->processShipperScan($code);
    }

    public function processShipperScan(string $code): void
    {
        $code = trim(strtoupper($code));

        $package = OrderPackage::query()
            ->with(['order:id,id_bill,tracking_code'])
            ->where('code', $code)
            ->first();

        if (! $package || ! $package->order) {
            $this->clearScannedPickup();
            $this->setScanResult('Không tìm thấy đơn hàng từ mã kiện này.', 'error', $code);
            return;
        }

        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->whereHas('orders', fn ($query) => $query->whereKey($package->order->id))
            ->where('status', '!=', PickupStatusEnum::DA_HUY->value)
            ->with(['orders' => fn ($query) => $query->whereKey($package->order->id)->withCount('packages')])
            ->first();

        if (! $pickup) {
            $this->clearScannedPickup();
            $this->setScanResult('Không tìm thấy pickup được gán cho bạn từ mã kiện này.', 'error', $code);
            return;
        }

        $order = $pickup->orders->first() ?: $package->order->loadCount('packages');
        $packageCount = max(1, (int) ($order->packages_count ?: 1));

        $this->scannedPickupId = $pickup->id;
        $this->scannedOrderId = $order->id;
        $this->scanResult = [
            'message' => $pickup->status === PickupStatusEnum::PICKUP_DA_LAY
                ? 'Pickup này đã được nhận hàng.'
                : 'Đã tìm thấy pickup. Kiểm tra thông tin rồi bấm nhận hàng.',
            'type' => 'success',
            'package_code' => $package->code,
            'order_code' => $order->id_bill ?: $order->tracking_code ?: '-',
            'pickup_code' => $pickup->ma_pickup,
            'pickup_status' => $pickup->status?->label() ?? '-',
            'customer' => data_get($pickup->info_khachhang, 'company')
                ?: data_get($pickup->info_khachhang, 'fullname')
                ?: '-',
            'address' => data_get($pickup->info_khachhang, 'address', ''),
            'phone' => data_get($pickup->info_khachhang, 'phone', ''),
            'package_count' => $packageCount,
        ];
    }

    public function receiveScannedPickup(): void
    {
        if (! $this->scannedPickupId || ! $this->scannedOrderId) {
            Flux::toast(heading: 'Lỗi', text: 'Vui lòng quét mã kiện trước khi nhận hàng.', variant: 'warning');
            return;
        }

        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->whereHas('orders', fn ($query) => $query->whereKey($this->scannedOrderId))
            ->find($this->scannedPickupId);

        if (! $pickup) {
            Flux::toast(heading: 'Lỗi', text: 'Không tìm thấy phiếu pickup.', variant: 'warning');
            return;
        }

        // Yêu cầu ảnh chứng minh trước khi nhận hàng → mở modal up ảnh.
        $this->openProofModal($pickup->id, 'scan');
    }

    public function clearScannedPickup(): void
    {
        $this->scannedPickupId = null;
        $this->scannedOrderId = null;
    }

    protected function setScanResult(string $message, string $type = 'neutral', string $code = ''): void
    {
        $this->scanResult = [
            'message' => $message,
            'type' => $type,
            'package_code' => $code,
            'order_code' => '',
            'pickup_code' => '',
            'pickup_status' => '',
            'customer' => '',
            'address' => '',
            'phone' => '',
            'package_count' => 0,
        ];
    }

    protected function markPickupAsReceived(Pickup $pickup): Pickup
    {
        $pickup = $pickup->fresh();

        if ($pickup->status === PickupStatusEnum::PICKUP_DA_LAY) {
            return $pickup;
        }

        foreach ([PickupStatusEnum::DA_XAC_NHAN, PickupStatusEnum::PICKUP_DANG_LAY, PickupStatusEnum::PICKUP_DA_LAY] as $nextStatus) {
            if ($pickup->status->canTransitionTo($nextStatus)) {
                $pickup = TransitionPickupStatusAction::execute($pickup, $nextStatus);
            }
        }

        if ($pickup->status !== PickupStatusEnum::PICKUP_DA_LAY) {
            throw new \RuntimeException('Không thể chuyển pickup sang trạng thái đã lấy hàng.');
        }

        if (blank($pickup->ngay_nhanhang)) {
            $pickup->forceFill(['ngay_nhanhang' => now()])->save();
            $pickup = $pickup->fresh();
        }

        return $pickup;
    }

    public function cancelPickup(int $pickupId): void
    {
        $pickup = Pickup::query()
            ->where('id_shipper', auth()->id())
            ->findOrFail($pickupId);

        $cancellable = [
            PickupStatusEnum::MOI_TAO_PICKUP,
            PickupStatusEnum::DA_XAC_NHAN,
            PickupStatusEnum::PICKUP_DANG_LAY,
        ];

        if (! in_array($pickup->status, $cancellable, true)) {
            Flux::toast(heading: 'Lỗi', text: 'Không thể hủy phiếu ở trạng thái này.', variant: 'warning');
            return;
        }

        try {
            TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::DA_HUY);
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'warning');
            return;
        }

        Flux::toast(heading: 'Đã hủy', text: 'Phiếu pickup đã được hủy.', variant: 'success');
    }

};
