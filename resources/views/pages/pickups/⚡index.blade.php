<?php

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\News;
use App\Models\Pickup;
use App\Models\Province;
use App\Models\User;
use App\Models\Ward;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] #[Title('Quản lý Pickup')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $status = '';
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public ?int $filterOpsId = null;
    public ?int $filterShipperId = null;
    public ?int $selectedPickupId = null;
    public ?int $selectedShipperId = null;
    public ?int $editPickupId = null;
    public array $editForm = [
        'ops_id' => null,
        'shipper_id' => null,
        'company' => '',
        'fullname' => '',
        'phone' => '',
        'email' => '',
        'country' => 'VIETNAM',
        'address' => '',
        'id_city' => null,
        'id_ward' => null,
        'pickup_lat' => null,
        'pickup_lng' => null,
    ];

    public function mount(): void
    {
        abort_unless(\Gate::allows('pickups.index'), 403);
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status', 'fromDate', 'toDate', 'filterOpsId', 'filterShipperId'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->keyword = '';
        $this->status = '';
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
        $this->filterOpsId = null;
        $this->filterShipperId = null;
        $this->resetPage();
        $this->dispatch('pickup-filter-synced', filters: $this->filterPayload());
    }

    public function setStatusFilter(string $status = ''): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function setDatePreset(string $preset): void
    {
        if ($preset === 'today') {
            $this->fromDate = now()->format('Y-m-d');
            $this->toDate = now()->format('Y-m-d');
        } elseif ($preset === '7') {
            $this->fromDate = now()->subDays(6)->format('Y-m-d');
            $this->toDate = now()->format('Y-m-d');
        } else {
            $this->fromDate = now()->subDays(30)->format('Y-m-d');
            $this->toDate = now()->format('Y-m-d');
        }

        $this->resetPage();
        $this->dispatch('pickup-filter-synced', filters: $this->filterPayload());
    }

    protected function pickupsQuery(bool $includeStatus = true, bool $includeRelations = true)
    {
        return Pickup::query()
            ->when($includeRelations, fn ($query) => $query
                ->with(['user:id,fullname,username,code', 'shipper:id,fullname,username,code'])
                ->withCount('orders'))
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('ma_pickup', 'like', '%'.$keyword.'%')
                        ->orWhere('info_khachhang', 'like', '%'.$keyword.'%')
                        ->orWhereHas('orders', fn ($orderQuery) => $orderQuery
                            ->where('id_bill', 'like', '%'.$keyword.'%')
                            ->orWhere('tracking_code', 'like', '%'.$keyword.'%'));
                });
            })
            ->when($includeStatus && $this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->canFilterPickupOps() && $this->filterOpsId, fn ($query) => $query->where('id_user', $this->filterOpsId))
            ->when($this->canFilterPickupShipper() && $this->filterShipperId, fn ($query) => $query->where('id_shipper', $this->filterShipperId))
            ->when($this->fromDate, fn ($query) => $query->whereDate('ngay_tao', '>=', $this->fromDate))
            ->when($this->toDate, fn ($query) => $query->whereDate('ngay_tao', '<=', $this->toDate));
    }

    public function getPickupsProperty()
    {
        return $this->pickupsQuery()
            ->latest('ngay_tao')
            ->paginate(15);
    }

    public function getPickupStatusCountsProperty(): array
    {
        $counts = (clone $this->pickupsQuery(false, false))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'all' => array_sum($counts),
            ...$counts,
        ];
    }

    public function canFilterPickupOps(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'cs', 'manager', 'sale']) ?? false;
    }

    public function canFilterPickupShipper(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'cs', 'manager', 'sale', 'ops']) ?? false;
    }

    public function filterPayload(): array
    {
        return [
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'status' => $this->status,
            'filterOpsId' => $this->filterOpsId,
            'filterShipperId' => $this->filterShipperId,
        ];
    }

    public function openDetails(int $pickupId): void
    {
        $this->selectedPickupId = $pickupId;
        $this->selectedShipperId = $this->selectedPickup?->id_shipper;
        Flux::modal('pickup-details')->show();
    }

    public function closeDetails(): void
    {
        $this->selectedPickupId = null;
        $this->selectedShipperId = null;
    }

    public function openEdit(int $pickupId): void
    {
        $pickup = Pickup::query()->findOrFail($pickupId);
        abort_unless($this->canEditPickup($pickup), 403);

        $sender = $pickup->info_khachhang ?? [];

        $this->resetValidation();
        $this->editPickupId = $pickup->id;
        $this->editForm = [
            'ops_id' => $pickup->id_user,
            'shipper_id' => $pickup->id_shipper,
            'company' => data_get($sender, 'company', ''),
            'fullname' => data_get($sender, 'fullname', ''),
            'phone' => data_get($sender, 'phone', ''),
            'email' => data_get($sender, 'email', ''),
            'country' => data_get($sender, 'country', 'VIETNAM') ?: 'VIETNAM',
            'address' => data_get($sender, 'address', ''),
            'id_city' => data_get($sender, 'id_city', data_get($sender, 'city_id')),
            'id_ward' => data_get($sender, 'id_ward', data_get($sender, 'ward_id')),
            'pickup_lat' => data_get($sender, 'pickup_lat'),
            'pickup_lng' => data_get($sender, 'pickup_lng'),
        ];

        Flux::modal('pickup-details')->close();
        Flux::modal('pickup-edit')->show();
        $this->dispatch('pickup-edit-modal-opened');
    }

    public function closeEdit(): void
    {
        $this->editPickupId = null;
        $this->editForm = [
            'ops_id' => null,
            'shipper_id' => null,
            'company' => '',
            'fullname' => '',
            'phone' => '',
            'email' => '',
            'country' => 'VIETNAM',
            'address' => '',
            'id_city' => null,
            'id_ward' => null,
            'pickup_lat' => null,
            'pickup_lng' => null,
        ];
        $this->resetValidation();
    }

    public function updatedEditFormIdCity(): void
    {
        $this->editForm['id_ward'] = null;
    }

    public function saveEditPickup(): void
    {
        $pickup = $this->editingPickup;
        abort_unless($pickup && $this->canEditPickup($pickup), 403);

        $canEditOps = $this->canEditOpsForPickup($pickup);
        $canEditShipper = $this->canEditShipperForPickup($pickup);
        $canEditSender = $this->canEditSenderForPickup($pickup);

        try {
            $rules = [];

            if ($canEditOps) {
                $rules['editForm.ops_id'] = ['nullable', Rule::exists((new User())->getTable(), 'id')];
            }

            if ($canEditShipper) {
                $rules['editForm.shipper_id'] = [
                    $pickup->status === PickupStatusEnum::DA_HUY ? 'required' : 'nullable',
                    Rule::exists((new User())->getTable(), 'id'),
                ];
            }

            if ($canEditSender) {
                $rules += [
                    'editForm.company' => 'required|string|max:255',
                    'editForm.fullname' => 'required|string|max:255',
                    'editForm.phone' => 'required|string|max:50',
                    'editForm.email' => 'nullable|email|max:255',
                    'editForm.country' => 'required|string|max:100',
                    'editForm.address' => 'required|string|max:500',
                    'editForm.id_city' => 'required|exists:province,id',
                    'editForm.id_ward' => 'required|exists:wards,id',
                    'editForm.pickup_lat' => 'nullable|numeric',
                    'editForm.pickup_lng' => 'nullable|numeric',
                ];
            }

            $data = $this->validate($rules)['editForm'] ?? [];
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Flux::toast(duration: 3500, heading: 'Thiếu thông tin', text: $exception->validator->errors()->first(), variant: 'warning');
            return;
        }

        if ($canEditOps && filled($data['ops_id'] ?? null)) {
            $opsIsValid = User::query()
                ->whereKey($data['ops_id'] ?? null)
                ->whereHas('roles', fn ($query) => $query->where('name', 'ops'))
                ->exists();

            if (! $opsIsValid) {
                $this->addError('editForm.ops_id', 'Vui lòng chọn OPS hợp lệ.');
                return;
            }
        }

        if ($canEditShipper && filled($data['shipper_id'] ?? null)) {
            $shipperIsValid = User::query()
                ->whereKey($data['shipper_id'] ?? null)
                ->whereHas('roles', fn ($query) => $query->where('name', 'shipper'))
                ->exists();

            if (! $shipperIsValid) {
                $this->addError('editForm.shipper_id', 'Vui lòng chọn shipper hợp lệ.');
                return;
            }
        }

        if ($canEditSender) {
            $wardIsValid = Ward::query()
                ->whereKey($data['id_ward'] ?? null)
                ->where('parent_code', $data['id_city'] ?? null)
                ->exists();

            if (! $wardIsValid) {
                $this->addError('editForm.id_ward', 'Phường/xã không thuộc tỉnh/thành phố đã chọn.');
                return;
            }
        }

        $updates = [];

        if ($canEditOps && filled($data['ops_id'] ?? null)) {
            $updates['id_user'] = (int) $data['ops_id'];
        }

        if ($canEditShipper && filled($data['shipper_id'] ?? null)) {
            $updates['id_shipper'] = (int) $data['shipper_id'];

            if ($pickup->status === PickupStatusEnum::DA_HUY) {
                $updates['status'] = PickupStatusEnum::MOI_TAO_PICKUP;
            }
        }

        if ($canEditSender) {
            $currentSender = $pickup->info_khachhang ?? [];
            $updates['info_khachhang'] = array_merge($currentSender, [
                'company' => trim($data['company']),
                'fullname' => trim($data['fullname']),
                'phone' => trim($data['phone']),
                'email' => trim((string) ($data['email'] ?? '')),
                'country' => trim($data['country']),
                'address' => trim($data['address']),
                'id_city' => (int) $data['id_city'],
                'id_ward' => (int) $data['id_ward'],
                'pickup_lat' => is_numeric($data['pickup_lat'] ?? null) ? (float) $data['pickup_lat'] : data_get($currentSender, 'pickup_lat'),
                'pickup_lng' => is_numeric($data['pickup_lng'] ?? null) ? (float) $data['pickup_lng'] : data_get($currentSender, 'pickup_lng'),
            ]);
        }

        $pickup->forceFill($updates)->save();

        Flux::modal('pickup-edit')->close();
        $this->closeEdit();
        Flux::toast(duration: 2500, heading: 'Đã cập nhật Pickup', text: 'Thông tin Pickup đã được lưu.', variant: 'success');
    }

    public function updateStatus(string $status): void
    {
        $pickup = $this->selectedPickup;
        abort_unless($pickup, 404);

        try {
            TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::from($status));
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể cập nhật Pickup', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        Flux::toast(heading: 'Đã cập nhật Pickup', text: 'Trạng thái phiếu Pickup đã được cập nhật.', variant: 'success');
    }

    public function reassignShipper(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'manager']), 403);

        $pickup = $this->selectedPickup;
        abort_unless($pickup, 404);

        if ($pickup->status !== PickupStatusEnum::DA_HUY) {
            Flux::toast(heading: 'Không thể gán lại', text: 'Chỉ gán lại shipper cho phiếu Pickup đã hủy.', variant: 'warning');
            return;
        }

        $shipper = User::query()
            ->whereKey($this->selectedShipperId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'shipper'))
            ->first();

        if (! $shipper) {
            Flux::toast(heading: 'Thiếu shipper', text: 'Vui lòng chọn shipper hợp lệ.', variant: 'warning');
            return;
        }

        $pickup->forceFill([
            'id_shipper' => $shipper->id,
            'status' => PickupStatusEnum::MOI_TAO_PICKUP,
        ])->save();

        Flux::toast(heading: 'Đã gán lại shipper', text: 'Pickup đã chuyển về trạng thái Mới tạo.', variant: 'success');
    }

    public function getSelectedPickupProperty(): ?Pickup
    {
        if (! $this->selectedPickupId) {
            return null;
        }

        return Pickup::query()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username', 'orders'])
            ->withCount('orders')
            ->findOrFail($this->selectedPickupId);
    }

    public function getEditingPickupProperty(): ?Pickup
    {
        if (! $this->editPickupId) {
            return null;
        }

        return Pickup::query()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username'])
            ->findOrFail($this->editPickupId);
    }

    public function canEditPickup(?Pickup $pickup = null): bool
    {
        $pickup ??= $this->editingPickup;

        if (! $pickup) {
            return false;
        }

        return $this->canEditOpsForPickup($pickup)
            || $this->canEditShipperForPickup($pickup)
            || $this->canEditSenderForPickup($pickup);
    }

    public function canEditOpsForPickup(?Pickup $pickup = null): bool
    {
        return (bool) $pickup && auth()->user()?->hasAnyRole(['admin', 'manager', 'ops']);
    }

    public function canEditShipperForPickup(?Pickup $pickup = null): bool
    {
        if (! $pickup) {
            return false;
        }

        if ($pickup->status === PickupStatusEnum::MOI_TAO_PICKUP) {
            return auth()->user()?->hasAnyRole(['admin', 'manager', 'ops']);
        }

        if ($pickup->status === PickupStatusEnum::DA_HUY) {
            return auth()->user()?->hasAnyRole(['admin', 'manager']);
        }

        return false;
    }

    public function canEditSenderForPickup(?Pickup $pickup = null): bool
    {
        return (bool) $pickup
            && $pickup->status === PickupStatusEnum::MOI_TAO_PICKUP
            && auth()->user()?->hasAnyRole(['admin', 'manager', 'sale', 'ctv']);
    }

    public function getSelectedVehicleProperty(): ?News
    {
        $id = data_get($this->selectedPickup?->info_pickup, 'id_phuongtien');

        return $id ? News::query()->find($id) : null;
    }

    public function getSelectedBranchProperty(): ?News
    {
        $id = data_get($this->selectedPickup?->info_pickup, 'chinhanhnhanhang');

        return $id ? News::query()->find($id) : null;
    }

    public function getPickupShippersProperty()
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'shipper'))
            ->orderBy('fullname')
            ->orderBy('username')
            ->get(['id', 'fullname', 'username']);
    }

    public function getPickupOpsUsersProperty()
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'ops'))
            ->orderBy('fullname')
            ->orderBy('username')
            ->get(['id', 'fullname', 'username']);
    }

    public function pickupProvinces()
    {
        return Province::query()->orderBy('name')->get(['id', 'name']);
    }

    public function pickupWards()
    {
        return Ward::query()
            ->when(data_get($this->editForm, 'id_city'), fn ($query, $cityId) => $query->where('parent_code', $cityId))
            ->when(! data_get($this->editForm, 'id_city'), fn ($query) => $query->whereRaw('1 = 0'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm text-neutral-500">Tác vụ / Pickup</p>
            <h1 class="text-2xl font-bold text-neutral-900">Quản lý Pickup</h1>
            <p class="mt-1 text-sm text-neutral-500">Theo dõi các phiếu lấy hàng đã tạo từ chi tiết đơn hàng.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <flux:modal.trigger name="pickup-index-filter">
                <flux:button type="button" variant="outline" icon="funnel">Bộ lọc</flux:button>
            </flux:modal.trigger>

            <flux:button href="{{ route('orders.index') }}" variant="primary" icon="plus" wire:navigate>
                Tạo Pickup
            </flux:button>
        </div>
    </div>

    <section class="pickup-status-nav">
        <div class="pickup-status-nav-header">
            <div>
                <h3>Trạng thái Pickup</h3>
                <p>Lọc nhanh theo tiến trình lấy hàng</p>
            </div>
        </div>
        <div class="pickup-status-tabs">
            <button type="button" wire:click="setStatusFilter('')" data-active="{{ $status === '' ? 'true' : 'false' }}" class="pickup-status-tab pickup-status-tab-all">
                <span class="pickup-status-dot"></span>
                <span class="pickup-status-text">
                    <span class="pickup-status-label">Tất cả</span>
                    <span class="pickup-status-meta">Toàn bộ pickup</span>
                </span>
                <span class="pickup-status-count">{{ number_format($this->pickupStatusCounts['all'] ?? 0) }}</span>
            </button>

            @foreach(PickupStatusEnum::cases() as $option)
                <button type="button" wire:click="setStatusFilter('{{ $option->value }}')" data-active="{{ $status === $option->value ? 'true' : 'false' }}" class="pickup-status-tab">
                    <span class="pickup-status-dot {{ $option->color() }}"></span>
                    <span class="pickup-status-text">
                        <span class="pickup-status-label">{{ $option->label() }}</span>
                        <span class="pickup-status-meta">Nhấn để lọc</span>
                    </span>
                    <span class="pickup-status-count">{{ number_format($this->pickupStatusCounts[$option->value] ?? 0) }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <flux:modal name="pickup-index-filter" class="w-full max-w-7xl !overflow-visible">
        <div class="pickup-filter-panel" id="pickup-index-filter-panel">
            <div class="pickup-filter-header">
                <div class="pickup-filter-title-row">
                    <div class="pickup-filter-icon">
                        <flux:icon.funnel class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">Bộ lọc Pickup</flux:heading>
                        <flux:subheading>Lọc theo từ khóa, trạng thái và khoảng ngày tạo phiếu pickup.</flux:subheading>
                    </div>
                </div>
            </div>

            <div class="pickup-filter-content">
                <section class="pickup-filter-section">
                    <div class="pickup-filter-section-heading">
                        <div>
                            <h3>Tìm kiếm</h3>
                            <p>Mã pickup, mã đơn, người gửi</p>
                        </div>
                    </div>
                    <div class="pickup-filter-section-grid">
                        <div class="pickup-filter-field">
                            <label class="pickup-filter-label">Từ khóa</label>
                            <input type="text" wire:model.live.debounce.350ms="keyword" placeholder="Mã Pickup, mã đơn, người gửi" class="pickup-filter-control">
                        </div>
                    </div>
                </section>

                <section class="pickup-filter-section pickup-filter-section-time">
                    <div class="pickup-filter-section-heading">
                        <div>
                            <h3>Thời gian</h3>
                            <p>Khoảng ngày tạo pickup</p>
                        </div>
                        <div class="pickup-filter-presets">
                            <button type="button" wire:click="setDatePreset('today')">Hôm nay</button>
                            <button type="button" wire:click="setDatePreset('7')">7 ngày</button>
                            <button type="button" wire:click="setDatePreset('30')">30 ngày</button>
                        </div>
                    </div>
                    <div class="pickup-filter-section-grid pickup-filter-section-grid-2">
                        <div class="pickup-filter-field">
                            <label class="pickup-filter-label">Từ ngày</label>
                            <div class="pickup-date-picker-field">
                                <input type="text" value="{{ $fromDate }}" data-pickup-date-picker data-livewire-model="fromDate" autocomplete="off" class="pickup-filter-control">
                            </div>
                        </div>
                        <div class="pickup-filter-field">
                            <label class="pickup-filter-label">Đến ngày</label>
                            <div class="pickup-date-picker-field">
                                <input type="text" value="{{ $toDate }}" data-pickup-date-picker data-livewire-model="toDate" autocomplete="off" class="pickup-filter-control">
                            </div>
                        </div>
                    </div>
                </section>

                <section class="pickup-filter-section pickup-filter-section-wide">
                    <div class="pickup-filter-section-heading">
                        <div>
                            <h3>Vận hành</h3>
                            <p>Trạng thái xử lý pickup</p>
                        </div>
                    </div>
                    <div class="pickup-filter-section-grid pickup-filter-section-grid-ops">
                        <div class="pickup-filter-field">
                            <label class="pickup-filter-label">Trạng thái</label>
                            <select wire:model.live="status" data-pickup-filter-key="status" data-placeholder="Tất cả trạng thái" class="tomselectEml pickup-filter-tomselect">
                                <option value="">Tất cả trạng thái</option>
                                @foreach(PickupStatusEnum::cases() as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($this->canFilterPickupOps())
                            <div class="pickup-filter-field">
                                <label class="pickup-filter-label">OPS ph&#7909; tr&#225;ch</label>
                                <select wire:model.live="filterOpsId" data-livewire-model="filterOpsId" data-placeholder="T&#7845;t c&#7843; OPS" class="tomselectEml pickup-filter-tomselect">
                                    <option value="">T&#7845;t c&#7843; OPS</option>
                                    @foreach($this->pickupOpsUsers as $ops)
                                        <option value="{{ $ops->id }}">{{ $ops->fullname ?: $ops->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if($this->canFilterPickupShipper())
                            <div class="pickup-filter-field">
                                <label class="pickup-filter-label">Shipper ph&#7909; tr&#225;ch</label>
                                <select wire:model.live="filterShipperId" data-livewire-model="filterShipperId" data-placeholder="T&#7845;t c&#7843; shipper" class="tomselectEml pickup-filter-tomselect">
                                    <option value="">T&#7845;t c&#7843; shipper</option>
                                    @foreach($this->pickupShippers as $shipper)
                                        <option value="{{ $shipper->id }}">{{ $shipper->fullname ?: $shipper->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                </section>
            </div>

            <div class="pickup-filter-actions">
                <flux:button type="button" wire:click="resetFilters" variant="ghost">Làm mới</flux:button>
                <flux:modal.close>
                    <flux:button type="button" variant="primary">Áp dụng</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mã Pickup</th>
                        <th class="px-4 py-3">Ngày tạo</th>
                        <th class="px-4 py-3">Người gửi</th>
                        <th class="px-4 py-3">OPS phụ trách</th>
                        <th class="px-4 py-3">Shipper phụ trách</th>
                        <th class="px-4 py-3">Ngày hẹn</th>
                        <th class="px-4 py-3 text-right">Số đơn</th>
                        <th class="px-4 py-3 text-right">Số kiện</th>
                        <th class="px-4 py-3 text-right">Cân nặng</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($this->pickups as $pickup)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-4 py-3 font-semibold text-neutral-900">{{ $pickup->ma_pickup }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pickup->ngay_tao?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-neutral-900">{{ data_get($pickup->info_khachhang, 'company') ?: data_get($pickup->info_khachhang, 'fullname', '-') }}</p>
                                <p class="text-xs text-neutral-500">{{ data_get($pickup->info_khachhang, 'phone', '') }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-neutral-900">{{ $pickup->user?->fullname ?: $pickup->user?->username ?: '-' }}</p>
                                <p class="text-xs text-neutral-500">{{ $pickup->user?->code ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-neutral-900">{{ $pickup->shipper?->fullname ?: $pickup->shipper?->username ?: '-' }}</p>
                                <p class="text-xs text-neutral-500">{{ $pickup->shipper?->code ?: '-' }}</p>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ data_get($pickup->info_pickup, 'ngayhen') ? \Carbon\Carbon::parse(data_get($pickup->info_pickup, 'ngayhen'))->format('d/m/Y H:i') : '-' }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format($pickup->orders_count) }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format($pickup->numb) }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format((float) $pickup->total_c_weight, 2, ',', '.') }} kg</td>
                            <td class="px-4 py-3">
                                @if($pickup->status)
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $pickup->status->color() }}">{{ $pickup->status->label() }}</span>
                                @else
                                    <span class="text-xs text-neutral-400">Chưa xác định</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    @if($this->canEditPickup($pickup))
                                        <button type="button" wire:click="openEdit({{ $pickup->id }})" class="inline-flex rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-50">Sửa</button>
                                    @endif
                                    <button type="button" wire:click="openDetails({{ $pickup->id }})" class="inline-flex rounded-lg border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-50">Chi tiết</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-sm text-neutral-500">Chưa có phiếu Pickup phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-100 px-4 py-3">
            {{ $this->pickups->links() }}
        </div>
    </div>

    <flux:modal name="pickup-details" class="w-full max-w-5xl" @close="$wire.closeDetails()">
        @if($this->selectedPickup)
            @php
                $selectedPickup = $this->selectedPickup;
            @endphp
            <div class="space-y-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:heading size="lg">{{ $selectedPickup->ma_pickup }}</flux:heading>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $selectedPickup->status->color() }}">{{ $selectedPickup->status->label() }}</span>
                        </div>
                        <flux:subheading class="mt-1">Tạo bởi {{ $selectedPickup->user?->fullname ?: $selectedPickup->user?->username ?: '-' }} lúc {{ $selectedPickup->ngay_tao?->format('d/m/Y H:i') ?: '-' }}</flux:subheading>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Số lượng đơn</p>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format($selectedPickup->orders_count) }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Số lượng kiện</p>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format($selectedPickup->numb) }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Cân nặng</p>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format((float) $selectedPickup->total_c_weight, 2, ',', '.') }} kg</p>
                    </div>
                </div>

                @if($selectedPickup->status === PickupStatusEnum::DA_HUY && auth()->user()?->hasAnyRole(['admin', 'manager']))
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                            <div class="flex-1">
                                <label class="text-xs font-semibold uppercase text-amber-700">Gán lại shipper</label>
                                <select wire:model="selectedShipperId" class="mt-1 w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm text-neutral-900 focus:border-amber-500 focus:outline-none">
                                    <option value="">Chọn shipper mới</option>
                                    @foreach($this->pickupShippers as $shipper)
                                        <option value="{{ $shipper->id }}">{{ $shipper->fullname ?: $shipper->username }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-amber-700">Khi lưu, Pickup sẽ chuyển về trạng thái Mới tạo để shipper mới tiếp nhận.</p>
                            </div>
                            <flux:button type="button" wire:click="reassignShipper" wire:loading.attr="disabled" variant="primary">
                                Cập nhật shipper
                            </flux:button>
                        </div>
                    </div>
                @endif

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="rounded-lg border border-neutral-200 p-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Thông tin lấy hàng</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Ngày hẹn</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_pickup, 'ngayhen') ? \Carbon\Carbon::parse(data_get($selectedPickup->info_pickup, 'ngayhen'))->format('d/m/Y H:i') : '-' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Phương tiện</dt><dd class="mt-1 text-neutral-900">{{ $this->selectedVehicle?->namevi ?: '-' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Chi nhánh nhận hàng</dt><dd class="mt-1 text-neutral-900">{{ $this->selectedBranch?->namevi ?: '-' }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase text-neutral-500">Ghi chú</dt><dd class="mt-1 whitespace-pre-line text-neutral-900">{{ $selectedPickup->note ?: '-' }}</dd></div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-neutral-200 p-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Người gửi</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Công ty</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'company', '-') }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Người liên hệ</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'fullname', '-') }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Điện thoại</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'phone', '-') }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Email</dt><dd class="mt-1 break-words text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'email', '-') }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase text-neutral-500">Địa chỉ</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'address', '-') }}</dd></div>
                        </dl>
                    </div>
                </div>

                {{-- Pickup Location Map + Routing --}}
                @php
                    $pickupLat = data_get($selectedPickup->info_khachhang, 'pickup_lat');
                    $pickupLng = data_get($selectedPickup->info_khachhang, 'pickup_lng');
                @endphp
                <div class="rounded-lg border border-neutral-200 p-4" wire:ignore>
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-neutral-900">Bản đồ vị trí lấy hàng</h3>
                            <p class="text-xs text-neutral-500" id="pickup-detail-map-status">
                                @if($pickupLat && $pickupLng)
                                    Tọa độ: {{ $pickupLat }}, {{ $pickupLng }}
                                @else
                                    Chưa có tọa độ lưu trữ
                                @endif
                            </p>
                        </div>
                        <button type="button" id="pickup-direction-btn"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100"
                            data-lat="{{ $pickupLat }}" data-lng="{{ $pickupLng }}">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            Chỉ đường
                        </button>
                    </div>
                    <div id="pickup-detail-map" style="height: 350px; border-radius: 8px; z-index: 0;"
                         data-pickup-lat="{{ $pickupLat }}" data-pickup-lng="{{ $pickupLng }}"
                         data-pickup-address="{{ data_get($selectedPickup->info_khachhang, 'address', '') }}"></div>
                    <div id="pickup-route-info" class="mt-2 hidden rounded-lg bg-blue-50 border border-blue-200 px-3 py-2">
                        <p class="text-sm font-semibold text-blue-800" id="pickup-route-text"></p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-neutral-200">
                    <div class="border-b border-neutral-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-neutral-900">Đơn hàng trong Pickup</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                                <tr><th class="px-4 py-3">Mã đơn</th><th class="px-4 py-3">Tracking</th><th class="px-4 py-3">Người nhận</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3 text-right">Thao tác</th></tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @forelse($selectedPickup->orders as $order)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-neutral-900">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</td>
                                        <td class="px-4 py-3 text-neutral-600">{{ $order->tracking_code ?: '-' }}</td>
                                        <td class="px-4 py-3 text-neutral-600">{{ data_get($order->receiver, 'company') ?: data_get($order->receiver, 'fullname', '-') }}</td>
                                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->bill_status->color() }}">{{ $order->bill_status->label() }}</span></td>
                                        <td class="px-4 py-3 text-right"><a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="text-xs font-semibold text-primary-700 hover:text-primary-800">Xem đơn</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-neutral-500">Pickup chưa có đơn hàng.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-100 pt-4">
                    @if($this->canEditPickup($selectedPickup))
                        <flux:button type="button" wire:click="openEdit({{ $selectedPickup->id }})" wire:loading.attr="disabled" variant="outline">Sửa</flux:button>
                    @endif

                    @if($selectedPickup->status === PickupStatusEnum::MOI_TAO_PICKUP)
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::DA_XAC_NHAN->value }}')" wire:loading.attr="disabled" variant="primary">Đã xác nhận</flux:button>
                    @elseif($selectedPickup->status === PickupStatusEnum::DA_XAC_NHAN)
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::PICKUP_DANG_LAY->value }}')" wire:loading.attr="disabled" variant="primary">Đang lấy hàng</flux:button>
                    @elseif($selectedPickup->status === PickupStatusEnum::PICKUP_DANG_LAY)
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::PICKUP_DA_LAY->value }}')" wire:loading.attr="disabled" variant="primary">Đã lấy hàng</flux:button>
                    @endif

                    @if(! $selectedPickup->status->isFinal())
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::DA_HUY->value }}')" wire:confirm="Hủy phiếu Pickup này?" wire:loading.attr="disabled" variant="danger">Hủy</flux:button>
                    @else
                        <p class="self-center text-sm font-medium text-neutral-500">Phiếu đã khóa thao tác.</p>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="pickup-edit" class="w-full max-w-5xl" @close="$wire.closeEdit()">
        @if($this->editingPickup)
            @php
                $editingPickup = $this->editingPickup;
                $canEditOps = $this->canEditOpsForPickup($editingPickup);
                $canEditShipper = $this->canEditShipperForPickup($editingPickup);
                $canEditSender = $this->canEditSenderForPickup($editingPickup);
            @endphp

            <form id="pickup-edit-form" wire:submit="saveEditPickup" class="space-y-5">
                <div>
                    <flux:heading size="lg">Sửa Pickup {{ $editingPickup->ma_pickup }}</flux:heading>
                    <flux:subheading>Trạng thái hiện tại: {{ $editingPickup->status?->label() ?: 'Chưa xác định' }}. Các chỉnh sửa bên dưới chỉ áp dụng cho phiếu Pickup.</flux:subheading>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    @if($canEditOps)
                        <flux:field class="pickup-create-field">
                            <flux:label>OPS phụ trách</flux:label>
                            <select
                                wire:model="editForm.ops_id"
                                data-livewire-model="editForm.ops_id"
                                data-livewire-live="false"
                                data-placeholder="Chọn OPS"
                                class="tomselectEml pickup-create-select"
                                autocomplete="off"
                            >
                                <option value="">Chọn OPS</option>
                                @foreach($this->pickupOpsUsers as $ops)
                                    <option value="{{ $ops->id }}">{{ $ops->fullname ?: $ops->username }}</option>
                                @endforeach
                            </select>
                            @error('editForm.ops_id') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    @endif

                    @if($canEditShipper)
                        <flux:field class="pickup-create-field">
                            <flux:label>{{ $editingPickup->status === PickupStatusEnum::DA_HUY ? 'Chọn lại shipper' : 'Shipper phụ trách' }}</flux:label>
                            <select
                                wire:model="editForm.shipper_id"
                                data-livewire-model="editForm.shipper_id"
                                data-livewire-live="false"
                                data-placeholder="Chọn shipper"
                                class="tomselectEml pickup-create-select"
                                autocomplete="off"
                            >
                                <option value="">Chọn shipper</option>
                                @foreach($this->pickupShippers as $shipper)
                                    <option value="{{ $shipper->id }}">{{ $shipper->fullname ?: $shipper->username }}</option>
                                @endforeach
                            </select>
                            @error('editForm.shipper_id') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    @endif

                    @if($canEditSender)
                        @if($canEditOps || $canEditShipper)
                            <div class="md:col-span-3 border-t border-neutral-100"></div>
                        @endif

                        <div class="md:col-span-3">
                            <h3 class="text-sm font-semibold text-neutral-900">Thông tin người gửi</h3>
                        </div>

                        <flux:field class="md:col-span-3">
                            <flux:label>Tên công ty *</flux:label>
                            <flux:input wire:model="editForm.company" />
                            @error('editForm.company') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Tên người gửi *</flux:label>
                            <flux:input wire:model="editForm.fullname" />
                            @error('editForm.fullname') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Số điện thoại *</flux:label>
                            <flux:input wire:model="editForm.phone" />
                            @error('editForm.phone') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Email</flux:label>
                            <flux:input type="email" wire:model="editForm.email" />
                            @error('editForm.email') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Quốc gia</flux:label>
                            <flux:input wire:model="editForm.country" readonly />
                            @error('editForm.country') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field class="pickup-create-field">
                            <flux:label>Tỉnh / Thành phố *</flux:label>
                            <select
                                wire:model.live="editForm.id_city"
                                data-livewire-model="editForm.id_city"
                                data-placeholder="Chọn Tỉnh / Thành phố"
                                class="tomselectEml pickup-create-select"
                                autocomplete="off"
                            >
                                <option value="">Chọn Tỉnh / Thành phố</option>
                                @foreach($this->pickupProvinces() as $province)
                                    <option value="{{ $province->id }}">{{ $province->name }}</option>
                                @endforeach
                            </select>
                            @error('editForm.id_city') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field class="pickup-create-field" wire:key="pickup-edit-ward-{{ $editForm['id_city'] ?? 'none' }}">
                            <flux:label>Phường / Xã *</flux:label>
                            <select
                                wire:model="editForm.id_ward"
                                data-livewire-model="editForm.id_ward"
                                data-livewire-live="false"
                                data-placeholder="Chọn Phường / Xã"
                                class="tomselectEml pickup-create-select"
                                autocomplete="off"
                                @disabled(empty($editForm['id_city']))
                            >
                                <option value="">Chọn Phường / Xã</option>
                                @foreach($this->pickupWards() as $ward)
                                    <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                                @endforeach
                            </select>
                            @error('editForm.id_ward') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field class="md:col-span-3">
                            <flux:label>Địa chỉ *</flux:label>
                            <flux:input wire:model="editForm.address" />
                            @error('editForm.address') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <input type="hidden" wire:model="editForm.pickup_lat">
                        <input type="hidden" wire:model="editForm.pickup_lng">
                    @endif
                </div>

                @if($canEditSender)
                    <div class="rounded-lg border border-neutral-200 p-4" wire:ignore>
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">Vị trí lấy hàng</p>
                                <p class="text-xs text-neutral-500" id="pickup-edit-map-status">
                                    @if($editForm['pickup_lat'] && $editForm['pickup_lng'])
                                        Tọa độ: {{ $editForm['pickup_lat'] }}, {{ $editForm['pickup_lng'] }}
                                    @else
                                        Đang chờ xác định vị trí...
                                    @endif
                                </p>
                            </div>
                            <button type="button" id="pickup-edit-geocode-btn" class="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-3 py-1.5 text-xs font-semibold text-teal-700 hover:bg-teal-100">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Tìm vị trí
                            </button>
                        </div>
                        <div id="pickup-edit-map"
                             style="height: 300px; border-radius: 8px; z-index: 0;"
                             data-pickup-lat="{{ $editForm['pickup_lat'] }}"
                             data-pickup-lng="{{ $editForm['pickup_lng'] }}"></div>
                        <p class="mt-2 text-xs text-neutral-400">Kéo icon đến vị trí của bạn, nếu hệ thống nhận diện không chính xác.</p>
                    </div>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Thoát</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="saveEditPickup">
                        <span wire:loading.remove wire:target="saveEditPickup">Lưu thay đổi</span>
                        <span wire:loading wire:target="saveEditPickup">Đang lưu...</span>
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>

<style>
    .pickup-status-nav {
        border: 1px solid #e5e5e5;
        border-radius: 0.875rem;
        background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
        padding: 1rem;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
    }

    .pickup-status-nav-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }

    .pickup-status-nav-header h3 {
        margin: 0;
        color: #171717;
        font-size: 0.875rem;
        font-weight: 750;
        line-height: 1.25rem;
    }

    .pickup-status-nav-header p {
        margin: 0.125rem 0 0;
        color: #737373;
        font-size: 0.75rem;
        line-height: 1rem;
    }

    .pickup-status-tabs {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));
        gap: 0.625rem;
    }

    .pickup-status-tab {
        display: grid;
        min-width: 0;
        min-height: 4rem;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 0.625rem;
        border: 1px solid #e5e5e5;
        border-radius: 0.875rem;
        background: rgb(255 255 255 / 0.92);
        padding: 0.625rem 0.75rem;
        text-align: left;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
    }

    .pickup-status-tab:hover {
        transform: translateY(-1px);
        border-color: #d4d4d4;
        box-shadow: 0 10px 24px rgb(15 23 42 / 0.08);
    }

    .pickup-status-tab[data-active="true"] {
        border-color: currentColor;
        background: #fff;
        box-shadow: 0 12px 30px rgb(15 23 42 / 0.12);
    }

    .pickup-status-tab-all[data-active="true"] {
        border-color: #737373;
        background: #f5f5f5;
    }

    .pickup-status-dot {
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 999px;
        background: currentColor;
        opacity: 0.75;
        box-shadow: 0 0 0 4px rgb(0 0 0 / 0.04);
    }

    .pickup-status-text {
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .pickup-status-label {
        overflow: hidden;
        color: #171717;
        font-size: 0.8125rem;
        font-weight: 700;
        line-height: 1.125rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pickup-status-meta {
        overflow: hidden;
        color: #737373;
        font-size: 0.6875rem;
        font-weight: 500;
        line-height: 0.875rem;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pickup-status-count {
        display: inline-flex;
        min-width: 2rem;
        height: 1.625rem;
        align-items: center;
        justify-content: center;
        border: 1px solid #e5e5e5;
        border-radius: 999px;
        background: #fff;
        padding: 0 0.5rem;
        color: #404040;
        font-size: 0.75rem;
        font-weight: 750;
        line-height: 1;
        box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.8);
    }

    .pickup-filter-panel {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .pickup-filter-header {
        border-bottom: 1px solid #e5e5e5;
        padding-bottom: 1rem;
    }

    .pickup-filter-title-row {
        display: flex;
        align-items: flex-start;
        gap: 0.875rem;
    }

    .pickup-filter-icon {
        display: flex;
        width: 2.5rem;
        height: 2.5rem;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        border-radius: 0.875rem;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .pickup-filter-content {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 1rem;
    }

    @media (min-width: 1024px) {
        .pickup-filter-content {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    .pickup-filter-section {
        min-width: 0;
        border: 1px solid #e5e5e5;
        border-radius: 1rem;
        background: #fafafa;
        padding: 0.875rem;
    }

    .pickup-filter-section-wide {
        grid-column: 1 / -1;
    }

    .pickup-filter-section-heading {
        display: flex;
        min-height: 2.25rem;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.875rem;
    }

    .pickup-filter-section-heading h3 {
        margin: 0;
        color: #171717;
        font-size: 0.9375rem;
        font-weight: 700;
        line-height: 1.25rem;
    }

    .pickup-filter-section-heading p {
        margin: 0.125rem 0 0;
        color: #737373;
        font-size: 0.8125rem;
        line-height: 1.125rem;
    }

    .pickup-filter-section-grid {
        display: grid;
        grid-template-columns: repeat(1, minmax(0, 1fr));
        gap: 1rem;
    }

    @media (min-width: 768px) {
        .pickup-filter-section-grid-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pickup-filter-section-grid-ops {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (min-width: 1024px) {
        .pickup-filter-section-grid-ops {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    .pickup-filter-field {
        min-width: 0;
    }

    .pickup-filter-label {
        display: block;
        margin-bottom: 0.625rem;
        color: #262626;
        font-size: 0.875rem;
        font-weight: 650;
        line-height: 1.125rem;
    }

    .pickup-filter-presets {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.375rem;
    }

    @media (min-width: 768px) {
        .pickup-filter-section-time .pickup-filter-presets {
            flex-wrap: nowrap;
        }
    }

    .pickup-filter-presets button {
        min-height: 1.875rem;
        border: 1px solid #d4d4d4;
        border-radius: 999px;
        background: #fff;
        padding: 0.25rem 0.75rem;
        color: #404040;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1rem;
        transition: border-color 150ms ease, color 150ms ease, background-color 150ms ease;
    }

    .pickup-filter-presets button:hover {
        border-color: #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
    }

    .pickup-filter-control,
    .pickup-filter-panel .ts-control {
        width: 100%;
        height: 2.875rem;
        min-height: 2.875rem;
        border: 1px solid #d4d4d4;
        border-radius: 0.75rem;
        background-color: #fff;
        padding: 0.625rem 1rem;
        color: #171717;
        font-size: 0.9375rem;
        line-height: 1.375rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        transition: border-color 150ms ease, box-shadow 150ms ease;
    }

    .pickup-filter-control:focus,
    .pickup-filter-panel .ts-wrapper.focus .ts-control {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgb(59 130 246 / 0.14);
        outline: none;
    }

    .pickup-filter-panel .ts-wrapper {
        width: 100%;
    }

    .pickup-filter-panel .ts-wrapper.single .ts-control {
        box-sizing: border-box;
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        padding-right: 2.5rem;
    }

    .pickup-filter-panel .ts-control > input {
        min-width: 0;
        font-size: 0.9375rem;
    }

    .pickup-filter-panel .ts-control .item,
    .pickup-filter-panel .ts-control .items-placeholder {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pickup-filter-panel .ts-dropdown {
        z-index: 999999;
        border-radius: 0.75rem;
        overflow: hidden;
    }

    .pickup-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
        border-top: 1px solid #e5e5e5;
        padding-top: 1rem;
    }

    .pickup-date-picker-field {
        position: relative;
        width: 100%;
    }

    .pickup-date-picker-field .flatpickr-wrapper {
        display: block;
        width: 100%;
    }

    .pickup-date-picker-field .flatpickr-input {
        width: 100%;
    }

    .pickup-date-picker-field .flatpickr-calendar.static {
        position: absolute;
        top: calc(100% + 0.5rem);
        left: 0;
        z-index: 60;
    }

    #pickup-edit-form .pickup-create-field .ts-wrapper {
        width: 100%;
    }

    #pickup-edit-form .pickup-create-field .ts-wrapper.single .ts-control,
    #pickup-edit-form .pickup-create-field .ts-control {
        box-sizing: border-box;
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        height: 40px;
        min-height: 40px;
        border-color: rgb(229 229 229);
        border-radius: 0.5rem;
        padding: 0.375rem 2.25rem 0.375rem 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.05);
        font-size: 0.875rem;
        line-height: 1.25rem;
    }

    #pickup-edit-form .pickup-create-field .ts-wrapper.focus .ts-control {
        border-color: rgb(20 184 166);
        box-shadow: 0 0 0 1px rgb(20 184 166);
    }

    #pickup-edit-form .pickup-create-field .ts-control > input {
        height: 1.25rem;
        min-width: 0;
        font-size: 0.875rem;
        line-height: 1.25rem;
    }

    #pickup-edit-form .pickup-create-field .ts-control .item,
    #pickup-edit-form .pickup-create-field .ts-control .items-placeholder {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>

<script>
//<![CDATA[
(function() {
    let pickupFilterRetryCount = 0;

    const pickupFilterRoot = () => document.getElementById('pickup-index-filter-panel');

    const findLivewireComponent = (element) => {
        const componentEl = element?.closest('[wire\\:id]');
        const componentId = componentEl?.getAttribute('wire:id');

        return componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
    };

    const setLivewireModel = (input, value) => {
        const model = input?.dataset.livewireModel;
        const component = findLivewireComponent(input);
        const normalizedValue = value || '';

        if (model && component) {
            if (input.dataset.lastLivewireValue === normalizedValue) return;
            input.dataset.lastLivewireValue = normalizedValue;
            component.set(model, normalizedValue || null, true);
        }
    };

    const initPickupFilterControls = () => {
        const root = pickupFilterRoot();
        if (!root) return;

        if (!window.flatpickr || !window.TomSelectHelper) {
            if (pickupFilterRetryCount < 20) {
                pickupFilterRetryCount++;
                setTimeout(initPickupFilterControls, 100);
            }
            return;
        }

        root.querySelectorAll('input[data-pickup-date-picker]').forEach((input) => {
            if (input._flatpickr) return;

            window.flatpickr(input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: true,
                defaultDate: input.value || null,
                static: true,
                position: 'below left',
                positionElement: input,
                disableMobile: true,
                clickOpens: true,
                onReady: (_selectedDates, _dateStr, instance) => {
                    instance.calendarContainer.classList.add('pickup-filter-calendar');
                },
                onChange: (_selectedDates, dateStr) => {
                    setLivewireModel(input, dateStr);
                },
                onClose: (_selectedDates, dateStr) => {
                    setLivewireModel(input, dateStr);
                },
            });
        });

        window.TomSelectHelper.init(root);
    };

    const syncPickupFilterControls = (filters = {}) => {
        const root = pickupFilterRoot();
        if (!root) return;

        root.querySelectorAll('input[data-pickup-date-picker]').forEach((input) => {
            const model = input.dataset.livewireModel;
            const value = filters[model] || '';

            if (input._flatpickr) {
                input._flatpickr.setDate(value || null, false);
            } else {
                input.value = value;
            }

            input.dataset.lastLivewireValue = value;
        });

        root.querySelectorAll('select.pickup-filter-tomselect').forEach((select) => {
            const model = select.dataset.livewireModel || select.dataset.pickupFilterKey;
            const value = filters[model] || '';

            if (select.tomselect) {
                select.tomselect.setValue(value, true);
            } else {
                select.value = value;
            }
        });
    };

    const initPickupEditControls = () => {
        const form = document.getElementById('pickup-edit-form');
        if (!form || !window.TomSelectHelper) return;

        window.TomSelectHelper.init(form);
    };

    setTimeout(initPickupFilterControls, 75);

    document.addEventListener('pickup-filter-synced', (event) => {
        setTimeout(() => syncPickupFilterControls(event.detail?.filters || {}), 50);
    });

    document.addEventListener('pickup-edit-modal-opened', () => {
        setTimeout(initPickupEditControls, 75);
        setTimeout(initPickupEditControls, 200);
    });

    document.addEventListener('livewire:navigated', () => {
        setTimeout(initPickupFilterControls, 75);
        setTimeout(initPickupEditControls, 75);
    });

    new MutationObserver(() => {
        if (document.getElementById('pickup-index-filter-panel')) {
            setTimeout(initPickupFilterControls, 50);
        }

        if (document.getElementById('pickup-edit-form')) {
            setTimeout(initPickupEditControls, 50);
        }
    }).observe(document.body, { childList: true, subtree: true });
})();

(function() {
    const VIETMAP_GL_VERSION = '6.0.1';
    const VIETMAP_GL_CSS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.css`;
    const VIETMAP_GL_JS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.js`;
    const DEFAULT_CENTER = [106.66817068179284, 10.803866192772915];
    const ROUTE_SOURCE_ID = 'pickup-detail-route';
    const ROUTE_LAYER_ID = 'pickup-detail-route-line';

    let vietmapPromise = null;
    let detailMap = null;
    let detailMarker = null;
    let shipperMarker = null;
    let directionButtonBound = false;
    let editMap = null;
    let editMarker = null;
    let editGeocodeButtonBound = false;
    let editMapDragActive = false;

    function getTileApiKey() {
        return window.__VIETMAP_CONFIG__?.tileApiKey || '';
    }

    function getRouteApiKey() {
        return window.__VIETMAP_CONFIG__?.geocodeApiKey || '';
    }

    function ensureVietmap() {
        if (window.vietmapgl) return Promise.resolve(window.vietmapgl);
        if (vietmapPromise) return vietmapPromise;

        if (!document.querySelector(`link[href="${VIETMAP_GL_CSS_URL}"]`)) {
            const stylesheet = document.createElement('link');
            stylesheet.rel = 'stylesheet';
            stylesheet.href = VIETMAP_GL_CSS_URL;
            stylesheet.crossOrigin = '';
            document.head.appendChild(stylesheet);
        }

        vietmapPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector(`script[src="${VIETMAP_GL_JS_URL}"]`);
            const script = existingScript || document.createElement('script');

            script.addEventListener('load', () => {
                window.vietmapgl ? resolve(window.vietmapgl) : reject(new Error('VietMap GL missing.'));
            }, { once: true });
            script.addEventListener('error', reject, { once: true });

            if (!existingScript) {
                script.src = VIETMAP_GL_JS_URL;
                script.crossOrigin = '';
                document.head.appendChild(script);
            } else if (window.vietmapgl) {
                resolve(window.vietmapgl);
            }
        }).catch((error) => {
            vietmapPromise = null;
            throw error;
        });

        return vietmapPromise;
    }

    function getVietmapStreetStyleUrl(apiKey) {
        return `https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=${apiKey}`;
    }

    function setStatus(text) {
        const statusEl = document.getElementById('pickup-detail-map-status');
        if (statusEl && text) statusEl.textContent = text;
    }

    function waitForDetailMapReady() {
        if (!detailMap || detailMap.loaded?.()) return Promise.resolve();

        return new Promise((resolve) => {
            let resolved = false;
            const done = () => {
                if (resolved) return;
                resolved = true;
                resolve();
            };

            detailMap.once?.('load', done);
            detailMap.once?.('styledata', done);
            setTimeout(done, 1200);
        });
    }

    function markerElement(color, label) {
        const element = document.createElement('div');
        element.style.cssText = `
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #fff;
            border-radius: 999px;
            background: ${color};
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 12px 28px rgba(15,23,42,.28);
        `;
        element.textContent = label;
        return element;
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function findLivewireComponent(element) {
        const componentId = element?.closest('[wire\\:id]')?.getAttribute('wire:id');

        return componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
    }

    function updateEditMapStatus(text) {
        const status = document.getElementById('pickup-edit-map-status');

        if (status && text) status.textContent = text;
    }

    function editMarkerElement() {
        const element = document.createElement('div');
        element.style.cssText = `
            position: relative;
            width: 44px;
            height: 54px;
            cursor: grab;
            transform: translateY(-4px);
            filter: drop-shadow(0 14px 18px rgba(15, 23, 42, 0.28));
        `;
        element.innerHTML = `
            <span style="
                position:absolute;
                left:50%;
                bottom:2px;
                width:30px;
                height:10px;
                border-radius:999px;
                background:rgba(15,23,42,.18);
                transform:translateX(-50%);
                filter:blur(3px);
            "></span>
            <span style="
                position:absolute;
                left:50%;
                top:2px;
                display:flex;
                align-items:center;
                justify-content:center;
                width:42px;
                height:42px;
                border:3px solid #fff;
                border-radius:50% 50% 50% 12px;
                background:linear-gradient(135deg,#ef4444,#dc2626 52%,#991b1b);
                color:#fff;
                transform:translateX(-50%) rotate(-45deg);
                box-shadow:inset 0 1px 0 rgba(255,255,255,.35),0 10px 24px rgba(220,38,38,.36);
            ">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="transform:rotate(45deg);">
                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </span>
        `;

        return element;
    }

    function syncEditCoordinates(lat, lng) {
        const form = document.getElementById('pickup-edit-form');
        const component = findLivewireComponent(form);

        if (!component) return;

        component.set('editForm.pickup_lat', lat.toFixed(7), false);
        component.set('editForm.pickup_lng', lng.toFixed(7), false);
    }

    function placeEditMarker(lat, lng) {
        if (!editMap) return;

        if (editMarker) {
            editMarker.setLngLat([lng, lat]);
        } else {
            const element = editMarkerElement();
            editMarker = new window.vietmapgl.Marker({ element, draggable: true, anchor: 'bottom' })
                .setLngLat([lng, lat])
                .addTo(editMap);

            element.addEventListener('mousedown', () => {
                element.style.cursor = 'grabbing';
            });

            editMarker.on('dragstart', () => {
                editMapDragActive = true;
                updateEditMapStatus('Đang di chuyển marker...');
            });

            editMarker.on('dragend', () => {
                const lngLat = editMarker.getLngLat();
                editMapDragActive = false;
                syncEditCoordinates(lngLat.lat, lngLat.lng);
                updateEditMapStatus('Đã di chuyển marker');
            });
        }

        editMap.flyTo({ center: [lng, lat], zoom: 16, duration: 500 });
        syncEditCoordinates(lat, lng);
    }

    function addEditCenterPinButton() {
        const mapElement = document.getElementById('pickup-edit-map');
        if (!mapElement || document.getElementById('pickup-edit-center-pin-btn')) return;

        mapElement.style.position = 'relative';

        const button = document.createElement('button');
        button.id = 'pickup-edit-center-pin-btn';
        button.type = 'button';
        button.setAttribute('aria-label', 'Ghim tâm bản đồ');
        button.title = 'Ghim tâm bản đồ';
        button.innerHTML = `
            <span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:999px;background:linear-gradient(135deg,#0f766e,#14b8a6);color:#fff;box-shadow:inset 0 1px 0 rgba(255,255,255,.28),0 8px 18px rgba(20,184,166,.28);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0Z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
            </span>
            <span style="white-space:nowrap;">Ghim tâm</span>
        `;
        button.style.cssText = `
            position: absolute;
            left: 10px;
            top: 10px;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            border: 1px solid rgba(15, 118, 110, 0.18);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            color: #0f766e;
            cursor: pointer;
            font-size: 13px;
            font-weight: 800;
            line-height: 1;
            padding: 6px 13px 6px 6px;
            backdrop-filter: blur(10px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18), 0 1px 2px rgba(15, 23, 42, 0.12);
        `;

        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (!editMap || editMapDragActive) return;

            const center = editMap.getCenter();
            placeEditMarker(center.lat, center.lng);
            updateEditMapStatus('Đã ghim vị trí trung tâm bản đồ. Kéo marker nếu cần điều chỉnh vị trí chính xác hơn.');
        });

        mapElement.appendChild(button);
    }

    async function initEditMap() {
        const mapEl = document.getElementById('pickup-edit-map');
        if (!mapEl || editMap) return;
        if (mapEl.offsetParent === null) return;

        const tileApiKey = getTileApiKey();
        if (!tileApiKey) {
            updateEditMapStatus('Chưa cấu hình VietMap Tile API Key.');
            return;
        }

        try {
            await ensureVietmap();
        } catch {
            updateEditMapStatus('Không tải được bản đồ VietMap.');
            return;
        }

        const lat = parseFloat(mapEl.dataset.pickupLat);
        const lng = parseFloat(mapEl.dataset.pickupLng);
        const hasCoords = Number.isFinite(lat) && Number.isFinite(lng) && lat !== 0 && lng !== 0;

        editMap = new window.vietmapgl.Map({
            container: mapEl,
            style: getVietmapStreetStyleUrl(tileApiKey),
            center: hasCoords ? [lng, lat] : DEFAULT_CENTER,
            zoom: hasCoords ? 16 : 9,
        });
        editMap.addControl(new window.vietmapgl.NavigationControl(), 'top-right');
        editMap.on('error', () => updateEditMapStatus('Không tải được tile VietMap. Vui lòng kiểm tra Tile API Key.'));
        editMap.on('load', () => {
            addEditCenterPinButton();
            requestAnimationFrame(() => editMap?.resize());
            setTimeout(() => editMap?.resize(), 200);

            if (hasCoords) {
                placeEditMarker(lat, lng);
                updateEditMapStatus(`Tọa độ: ${lat}, ${lng}`);
            }
        });

        const button = document.getElementById('pickup-edit-geocode-btn');
        if (button && !editGeocodeButtonBound) {
            editGeocodeButtonBound = true;
            button.addEventListener('click', (event) => {
                event.preventDefault();
                geocodeEditAddress();
            });
        }
    }

    async function geocodeEditAddress() {
        const form = document.getElementById('pickup-edit-form');
        const component = findLivewireComponent(form);
        const editForm = component?.get('editForm') || {};
        const address = editForm.address || '';
        const citySelect = form?.querySelector('select[data-livewire-model="editForm.id_city"]');
        const wardSelect = form?.querySelector('select[data-livewire-model="editForm.id_ward"]');
        const cityName = citySelect?.selectedIndex > 0 ? citySelect.options[citySelect.selectedIndex].text : '';
        const wardName = wardSelect?.selectedIndex > 0 ? wardSelect.options[wardSelect.selectedIndex].text : '';
        const fullAddress = [address, wardName, cityName, 'Vietnam'].filter(Boolean).join(', ');
        const apiKey = getRouteApiKey();

        if (!address && !cityName) {
            updateEditMapStatus('Chưa có địa chỉ để tìm kiếm. Kéo bản đồ rồi bấm Ghim tâm để chọn.');
            return;
        }

        if (!apiKey) {
            updateEditMapStatus('Chưa cấu hình VietMap Geocode API Key.');
            return;
        }

        updateEditMapStatus('Đang tìm kiếm vị trí...');

        try {
            const response = await fetch(`https://maps.vietmap.vn/api/search/v4?apikey=${apiKey}&text=${encodeURIComponent(fullAddress)}&focus=10.803866192772915,106.66817068179284&display_type=5`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('VietMap search failed.');

            const results = normalizeGeocodeResults(await response.json());
            const first = results[0];
            const place = await fetchPlaceByRefId(getGeocodeRefId(first));
            const coordinate = getGeocodeCoordinate(place) || getGeocodeCoordinate(first);

            if (!coordinate) {
                updateEditMapStatus('Không lấy được tọa độ từ VietMap Place. Kéo bản đồ rồi bấm Ghim tâm để chọn.');
                return;
            }

            placeEditMarker(coordinate.lat, coordinate.lng);
            updateEditMapStatus('Đã tìm thấy vị trí. Kéo marker nếu cần điều chỉnh.');
        } catch {
            updateEditMapStatus('Lỗi khi tìm vị trí. Kéo bản đồ rồi bấm Ghim tâm để chọn.');
        }
    }

    async function initDetailMap() {
        const mapEl = document.getElementById('pickup-detail-map');
        if (!mapEl || detailMap) return;
        if (mapEl.offsetParent === null) return;

        const lat = parseFloat(mapEl.dataset.pickupLat);
        const lng = parseFloat(mapEl.dataset.pickupLng);
        const hasCoords = !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;
        const tileApiKey = getTileApiKey();

        if (!tileApiKey) {
            setStatus('Chưa cấu hình VietMap Tile API Key.');
            return;
        }

        try {
            await ensureVietmap();
        } catch {
            setStatus('Không tải được bản đồ VietMap.');
            return;
        }

        const center = hasCoords ? [lng, lat] : DEFAULT_CENTER;
        const zoom = hasCoords ? 16 : 12;

        detailMap = new window.vietmapgl.Map({
            container: mapEl,
            style: getVietmapStreetStyleUrl(tileApiKey),
            center,
            zoom,
        });
        detailMap.addControl(new window.vietmapgl.NavigationControl(), 'top-right');
        detailMap.on('error', () => setStatus('Không tải được tile VietMap. Vui lòng kiểm tra Tile API Key.'));
        detailMap.on('load', () => {
            requestAnimationFrame(() => detailMap?.resize());
            setTimeout(() => detailMap?.resize(), 200);
        });

        if (hasCoords) {
            placePickupMarker(lat, lng, mapEl.dataset.pickupAddress || '');
        } else {
            const address = mapEl.dataset.pickupAddress;
            if (address) {
                geocodeForDetail(address);
            }
        }

        // Direction button
        const dirBtn = document.getElementById('pickup-direction-btn');
        if (dirBtn && !directionButtonBound) {
            directionButtonBound = true;
            dirBtn.addEventListener('click', function(e) {
                e.preventDefault();
                getDirections();
            });
        }
    }

    function normalizeGeocodeResults(payload) {
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.data)) return payload.data;
        if (Array.isArray(payload?.results)) return payload.results;
        return payload ? [payload] : [];
    }

    function getGeocodeCoordinate(result) {
        const lat = parseFloat(result?.lat ?? result?.location?.lat);
        const lng = parseFloat(result?.lng ?? result?.lon ?? result?.location?.lng ?? result?.location?.lon);

        if (Number.isNaN(lat) || Number.isNaN(lng)) return null;

        return { lat, lng };
    }

    function getGeocodeRefId(result) {
        return result?.ref_id || result?.refid || result?.data_new?.ref_id || result?.data_old?.ref_id || null;
    }

    async function fetchPlaceByRefId(refId) {
        const apiKey = getRouteApiKey();
        if (!apiKey || !refId) return null;

        try {
            const response = await fetch(
                `https://maps.vietmap.vn/api/place/v4?apikey=${apiKey}&refid=${encodeURIComponent(refId)}`,
                { headers: { Accept: 'application/json' } }
            );

            if (!response.ok) return null;

            return response.json();
        } catch {
            return null;
        }
    }

    async function geocodeForDetail(address) {
        const apiKey = getRouteApiKey();

        if (!apiKey) {
            setStatus('Chưa cấu hình VietMap Geocode API Key.');
            return;
        }

        try {
            const response = await fetch(
                `https://maps.vietmap.vn/api/search/v4?apikey=${apiKey}&text=${encodeURIComponent(address + ', Vietnam')}&display_type=5`,
                { headers: { Accept: 'application/json' } }
            );

            if (!response.ok) throw new Error('VietMap search failed.');

            const results = normalizeGeocodeResults(await response.json());
            const first = results[0];
            const place = await fetchPlaceByRefId(getGeocodeRefId(first));
            const coordinate = getGeocodeCoordinate(place) || getGeocodeCoordinate(first);

            if (!coordinate) {
                setStatus('Không lấy được tọa độ từ địa chỉ.');
                return;
            }

            placePickupMarker(coordinate.lat, coordinate.lng, address);
            detailMap?.flyTo({ center: [coordinate.lng, coordinate.lat], zoom: 16, duration: 500 });

            const btn = document.getElementById('pickup-direction-btn');
            if (btn) {
                btn.dataset.lat = coordinate.lat;
                btn.dataset.lng = coordinate.lng;
            }
            setStatus('Vị trí ước lượng từ địa chỉ bằng VietMap.');
        } catch {
            setStatus('Không tìm được vị trí trên VietMap từ địa chỉ.');
        }
    }

    function placePickupMarker(lat, lng, address) {
        if (!detailMap) return;

        detailMarker?.remove();
        detailMarker = new window.vietmapgl.Marker({ element: markerElement('#dc2626', 'P') })
            .setLngLat([lng, lat])
            .setPopup(
                new window.vietmapgl.Popup({ offset: 24 })
                    .setHTML(`<strong>Điểm lấy hàng</strong><br>${escapeHtml(address)}`)
            )
            .addTo(detailMap);
    }

    function getDirections() {
        const btn = document.getElementById('pickup-direction-btn');
        const destLat = parseFloat(btn?.dataset.lat);
        const destLng = parseFloat(btn?.dataset.lng);

        if (isNaN(destLat) || isNaN(destLng) || destLat === 0) {
            setStatus('Chưa có tọa độ điểm lấy hàng.');
            return;
        }

        setStatus('Đang lấy vị trí của bạn...');

        if (!navigator.geolocation) {
            setStatus('Trình duyệt không hỗ trợ GPS.');
            openGoogleMapsNavigation(destLat, destLng);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const shipperLat = pos.coords.latitude;
                const shipperLng = pos.coords.longitude;
                setStatus('Đang tìm đường bằng VietMap...');
                fetchRoute(shipperLat, shipperLng, destLat, destLng);
            },
            function() {
                setStatus('Không lấy được vị trí. Mở Google Maps...');
                openGoogleMapsNavigation(destLat, destLng);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    async function fetchRoute(fromLat, fromLng, toLat, toLng) {
        const apiKey = getRouteApiKey();

        if (!apiKey) {
            setStatus('Chưa cấu hình VietMap Geocode/Route API Key.');
            openGoogleMapsNavigation(toLat, toLng);
            return;
        }

        shipperMarker?.remove();
        if (detailMap) {
            shipperMarker = new window.vietmapgl.Marker({ element: markerElement('#2563eb', 'S') })
                .setLngLat([fromLng, fromLat])
                .addTo(detailMap);
        }

        try {
            const params = new URLSearchParams({
                apikey: apiKey,
                points_encoded: 'false',
                vehicle: 'motorcycle',
            });
            params.append('point', `${fromLat},${fromLng}`);
            params.append('point', `${toLat},${toLng}`);

            const response = await fetch(`https://maps.vietmap.vn/api/route/v3?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('VietMap route failed.');

            const data = await response.json();
            if (data?.code !== 'OK' || !Array.isArray(data?.paths) || !data.paths[0]) {
                throw new Error(data?.messages || 'Không tìm thấy tuyến đường.');
            }

            const path = data.paths[0];
            await waitForDetailMapReady();
            drawRoute({ lat: fromLat, lng: fromLng }, { lat: toLat, lng: toLng }, path);

            const distance = formatDistance(path.distance);
            const duration = formatDuration(path.time);
            setStatus(`Khoảng cách: ${distance} - Thời gian: ~${duration}`);

            const routeInfo = document.getElementById('pickup-route-info');
            const routeText = document.getElementById('pickup-route-text');
            if (routeInfo && routeText) {
                routeText.textContent = `${distance} - ~${duration} lái xe`;
                routeInfo.classList.remove('hidden');
            }
        } catch {
            setStatus('Lỗi tìm đường bằng VietMap. Mở Google Maps...');
            openGoogleMapsNavigation(toLat, toLng);
        }
    }

    function formatDistance(meters) {
        const value = Number(meters || 0);
        if (!value) return '-';
        return value >= 1000 ? `${(value / 1000).toFixed(1)} km` : `${Math.round(value)} m`;
    }

    function formatDuration(ms) {
        const minutes = Math.max(1, Math.round(Number(ms || 0) / 60000));
        if (minutes < 60) return `${minutes} phút`;
        return `${Math.floor(minutes / 60)} giờ ${minutes % 60} phút`;
    }

    function routeCoordinates(path) {
        const points = path?.points?.coordinates || path?.points;
        if (!Array.isArray(points)) return [];

        return points
            .map((point) => {
                if (!Array.isArray(point) || point.length < 2) return null;
                const a = Number(point[0]);
                const b = Number(point[1]);
                if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
                return Math.abs(a) <= 90 && Math.abs(b) <= 180 ? [b, a] : [a, b];
            })
            .filter(Boolean);
    }

    function drawRoute(origin, destination, path) {
        if (!detailMap) return;

        if (detailMap.getLayer(ROUTE_LAYER_ID)) detailMap.removeLayer(ROUTE_LAYER_ID);
        if (detailMap.getSource(ROUTE_SOURCE_ID)) detailMap.removeSource(ROUTE_SOURCE_ID);

        const coordinates = routeCoordinates(path);

        if (coordinates.length) {
            detailMap.addSource(ROUTE_SOURCE_ID, {
                type: 'geojson',
                data: {
                    type: 'Feature',
                    geometry: { type: 'LineString', coordinates },
                    properties: {},
                },
            });
            detailMap.addLayer({
                id: ROUTE_LAYER_ID,
                type: 'line',
                source: ROUTE_SOURCE_ID,
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '#0f766e', 'line-width': 5, 'line-opacity': 0.88 },
            });
        }

        const bounds = new window.vietmapgl.LngLatBounds([origin.lng, origin.lat], [origin.lng, origin.lat]);
        bounds.extend([destination.lng, destination.lat]);
        coordinates.forEach((coordinate) => bounds.extend(coordinate));
        detailMap.fitBounds(bounds, { padding: 72, maxZoom: 16, duration: 600 });
    }

    function openGoogleMapsNavigation(lat, lng) {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`, '_blank');
    }

    document.addEventListener('pickup-edit-modal-opened', () => {
        setTimeout(initEditMap, 100);
        setTimeout(() => editMap?.resize(), 300);
    });

    // Observe modal visibility
    const observer = new MutationObserver(() => {
        const mapEl = document.getElementById('pickup-detail-map');
        if (mapEl && mapEl.offsetParent !== null && !detailMap) {
            setTimeout(initDetailMap, 250);
        }
        // Cleanup when modal closes
        if (detailMap && (!mapEl || mapEl.offsetParent === null)) {
            detailMap.remove();
            detailMap = null;
            detailMarker = null;
            shipperMarker = null;
            directionButtonBound = false;
            document.getElementById('pickup-route-info')?.classList.add('hidden');
        }

        const editMapEl = document.getElementById('pickup-edit-map');
        if (editMapEl && editMapEl.offsetParent !== null && !editMap) {
            setTimeout(initEditMap, 120);
        }

        if (editMap && (!editMapEl || editMapEl.offsetParent === null)) {
            editMap.remove();
            editMap = null;
            editMarker = null;
            editGeocodeButtonBound = false;
            editMapDragActive = false;
            document.getElementById('pickup-edit-center-pin-btn')?.remove();
        }
    });

    observer.observe(document.body, { attributes: true, subtree: true, childList: true });
    document.addEventListener('livewire:navigated', () => {
        detailMap = null;
        detailMarker = null;
        shipperMarker = null;
        directionButtonBound = false;
        editMap = null;
        editMarker = null;
        editGeocodeButtonBound = false;
        editMapDragActive = false;
    });
})();
</script>
