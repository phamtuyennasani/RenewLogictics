<?php


use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\OrderPackage;
use App\Models\Pickup;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.mobile')] #[Title('Danh sách Pickup')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $tab = 'new'; // new | accepted | picking | done
    public ?int $expandedId = null;
    public string $scanBarcodeInput = '';
    public ?int $scannedPickupId = null;
    public ?int $scannedOrderId = null;
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
            ->findOrFail($this->scannedPickupId);

        try {
            $pickup = $this->markPickupAsReceived($pickup);
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'warning');
            return;
        }

        $packageCount = max(1, (int) ($this->scanResult['package_count'] ?? 1));
        $this->scanResult['pickup_status'] = $pickup->status?->label() ?? PickupStatusEnum::PICKUP_DA_LAY->label();
        $this->scanResult['message'] = $packageCount > 1
            ? "Shiper đã nhận đủ {$packageCount} kiện hàng"
            : 'Shiper đã nhận hàng thành công';
        $this->scanResult['type'] = 'success';
        $this->tab = 'done';
        $this->resetPage();

        Flux::toast(heading: 'Thành công', text: $this->scanResult['message'], variant: 'success');
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
