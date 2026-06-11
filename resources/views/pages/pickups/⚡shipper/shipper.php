<?php


use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
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