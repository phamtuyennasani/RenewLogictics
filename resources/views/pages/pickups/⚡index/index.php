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
        'status' => null,
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

    protected function shouldScopeToCurrentOps(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('ops') && ! $user->hasAnyRole(['admin', 'manager']);
    }

    protected function shouldScopeToCurrentSale(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('sale') && ! $user->hasAnyRole(['admin', 'manager']);
    }

    protected function shouldScopeToCurrentCtv(): bool
    {
        $user = auth()->user();

        return $user?->hasRole('ctv') && ! $user->hasAnyRole(['admin', 'manager', 'cs']);
    }

    protected function pickupAccessQuery()
    {
        return Pickup::query()
            ->when($this->shouldScopeToCurrentOps(), function ($query) {
                $query->where(function ($scope) {
                    $scope->whereNull('id_user')
                        ->orWhere('id_user', 0)
                        ->orWhere('id_user', auth()->id());
                });
            })
            ->when($this->shouldScopeToCurrentSale(), function ($query) {
                // Sale: chỉ thấy pickup thuộc order mà sale phụ trách.
                $query->whereHas('orders', fn ($orderQuery) => $orderQuery->where('id_sale', auth()->id()));
            })
            ->when($this->shouldScopeToCurrentCtv(), function ($query) {
                // CTV: chỉ thấy pickup do chính mình tạo (pivot pickup_orders.added_by).
                $query->whereHas('orders', fn ($orderQuery) => $orderQuery->where('pickup_orders.added_by', auth()->id()));
            });
    }

    protected function currentOpsCanAccessPickup(?Pickup $pickup): bool
    {
        if (! $pickup || ! $this->shouldScopeToCurrentOps()) {
            return false;
        }

        return blank($pickup->id_user)
            || (int) $pickup->id_user === 0
            || (int) $pickup->id_user === (int) auth()->id();
    }

    /**
     * Sale chỉ được sửa pickup do chính mình tạo (pivot pickup_orders.added_by = sale.id).
     * Pickup do người khác tạo (dù thuộc order sale quản lý) thì sale chỉ được xem.
     */
    protected function currentSaleCreatedPickup(?Pickup $pickup): bool
    {
        if (! $pickup || ! $this->shouldScopeToCurrentSale()) {
            return false;
        }

        return $pickup->orders()
            ->wherePivot('added_by', auth()->id())
            ->exists();
    }

    /**
     * CTV chỉ được sửa pickup do chính mình tạo (pivot pickup_orders.added_by = ctv.id).
     */
    protected function currentCtvCreatedPickup(?Pickup $pickup): bool
    {
        if (! $pickup || ! $this->shouldScopeToCurrentCtv()) {
            return false;
        }

        return $pickup->orders()
            ->wherePivot('added_by', auth()->id())
            ->exists();
    }

    protected function pickupsQuery(bool $includeStatus = true, bool $includeRelations = true)
    {
        return $this->pickupAccessQuery()
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
        $pickup = $this->pickupAccessQuery()->findOrFail($pickupId);
        $this->selectedPickupId = $pickup->id;
        $this->selectedShipperId = $pickup->id_shipper;
        Flux::modal('pickup-details')->show();
    }

    public function closeDetails(): void
    {
        $this->selectedPickupId = null;
        $this->selectedShipperId = null;
    }

    public function openEdit(int $pickupId): void
    {
        $pickup = $this->pickupAccessQuery()->findOrFail($pickupId);
        abort_unless($this->canEditPickup($pickup), 403);

        $sender = $pickup->info_khachhang ?? [];

        $this->resetValidation();
        $this->editPickupId = $pickup->id;
        $this->editForm = [
            'ops_id' => $pickup->id_user,
            'shipper_id' => $pickup->id_shipper,
            'status' => $pickup->status?->value,
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
            'status' => null,
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
        $canEditStatus = $this->canEditStatusForPickup($pickup);

        try {
            $rules = [];

            if ($canEditOps) {
                $rules['editForm.ops_id'] = ['nullable', Rule::exists((new User())->getTable(), 'id')];
            }

            if ($canEditStatus) {
                $rules['editForm.status'] = ['nullable', Rule::in(PickupStatusEnum::values())];
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

        if ($canEditOps && $this->shouldScopeToCurrentOps() && filled($data['ops_id'] ?? null) && (int) $data['ops_id'] !== (int) auth()->id()) {
            $this->addError('editForm.ops_id', 'OPS chỉ được nhận Pickup cho chính mình.');
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

        if ($this->shouldScopeToCurrentOps()) {
            $updates['id_user'] = auth()->id();
        }

        if ($canEditOps && filled($data['ops_id'] ?? null)) {
            $updates['id_user'] = (int) $data['ops_id'];
        }

        // Admin/Manager đổi trạng thái trực tiếp. Nếu đổi shipper bên dưới thì rule
        // "về Mới tạo" sẽ ghi đè lựa chọn này.
        if ($canEditStatus && filled($data['status'] ?? null)) {
            $updates['status'] = PickupStatusEnum::from($data['status']);
        }

        if ($canEditShipper && filled($data['shipper_id'] ?? null)) {
            $updates['id_shipper'] = (int) $data['shipper_id'];

            // Đổi shipper (khác shipper hiện tại) → đưa phiếu về Mới tạo để shipper mới xác nhận lại.
            if ((int) $data['shipper_id'] !== (int) $pickup->id_shipper
                && $pickup->status !== PickupStatusEnum::MOI_TAO_PICKUP) {
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
        abort_unless($this->canManagePickupStatus($pickup), 403);

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

        return $this->pickupAccessQuery()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username', 'orders'])
            ->withCount('orders')
            ->findOrFail($this->selectedPickupId);
    }

    public function getEditingPickupProperty(): ?Pickup
    {
        if (! $this->editPickupId) {
            return null;
        }

        return $this->pickupAccessQuery()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username'])
            ->findOrFail($this->editPickupId);
    }

    public function canEditPickup(?Pickup $pickup = null): bool
    {
        $pickup ??= $this->editingPickup;

        if (! $pickup) {
            return false;
        }

        // Mở được modal Sửa nếu có quyền chỉnh ít nhất một nhóm field.
        return $this->canEditOpsForPickup($pickup)
            || $this->canEditShipperForPickup($pickup)
            || $this->canEditSenderForPickup($pickup)
            || $this->canEditStatusForPickup($pickup);
    }

    /**
     * Quyền đổi trực tiếp trạng thái phiếu trong modal Sửa.
     * Chỉ Admin/Manager, và chỉ khi phiếu chưa ở trạng thái cuối (đã lấy / đã hủy).
     */
    public function canEditStatusForPickup(?Pickup $pickup = null): bool
    {
        if (! $pickup || $pickup->status?->isFinal()) {
            return false;
        }

        return auth()->user()?->hasAnyRole(['admin', 'manager']) ?? false;
    }

    /**
     * Quyền chọn OPS phụ trách phiếu.
     * - Admin/Manager: mọi trạng thái.
     * - CS: mọi phiếu, chỉ khi phiếu còn Mới tạo.
     * - Sale (scoped): chỉ phiếu do mình tạo, khi còn Mới tạo.
     * - OPS tự nhận phiếu bằng logic riêng trong saveEditPickup (không qua field này).
     */
    public function canEditOpsForPickup(?Pickup $pickup = null): bool
    {
        if (! $pickup) {
            return false;
        }

        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        if ($user->hasRole('cs')) {
            return $pickup->status === PickupStatusEnum::MOI_TAO_PICKUP;
        }

        if ($this->shouldScopeToCurrentSale()) {
            return $this->currentSaleCreatedPickup($pickup)
                && $pickup->status === PickupStatusEnum::MOI_TAO_PICKUP;
        }

        return false;
    }

    /**
     * Cập nhật trạng thái / hủy phiếu Pickup.
     * - Sale: KHÔNG bao giờ được (chỉ tạo & sửa thông tin pickup của order mình).
     * - OPS: chỉ pickup mình phụ trách (chưa có người hoặc của chính mình).
     * - Admin/Manager: toàn quyền.
     * Phiếu ở trạng thái cuối thì không ai thao tác.
     */
    public function canManagePickupStatus(?Pickup $pickup = null): bool
    {
        if (! $pickup || $pickup->status?->isFinal()) {
            return false;
        }

        if ($this->shouldScopeToCurrentSale()) {
            return false;
        }

        if ($this->shouldScopeToCurrentOps()) {
            return $this->currentOpsCanAccessPickup($pickup);
        }

        return auth()->user()?->hasAnyRole(['admin', 'manager', 'ops']) ?? false;
    }

    /**
     * Quyền chọn shipper cho phiếu.
     * - Admin/Manager: mọi trạng thái.
     * - OPS sở hữu phiếu: chỉ Mới tạo / Đã xác nhận.
     * - CS: KHÔNG được chọn shipper.
     * - Sale/CTV: KHÔNG được chọn shipper.
     */
    public function canEditShipperForPickup(?Pickup $pickup = null): bool
    {
        if (! $pickup) {
            return false;
        }

        if (auth()->user()?->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        if ($this->currentOpsCanAccessPickup($pickup)) {
            return in_array($pickup->status, [
                PickupStatusEnum::MOI_TAO_PICKUP,
                PickupStatusEnum::DA_XAC_NHAN,
            ], true);
        }

        return false;
    }

    /**
     * Quyền sửa thông tin người gửi.
     * - Admin/Manager: mọi trạng thái.
     * - OPS sở hữu phiếu: chỉ Mới tạo / Đã xác nhận.
     * - CS: chỉ Mới tạo.
     * - Sale/CTV (scoped): chỉ phiếu do mình tạo, khi còn Mới tạo.
     */
    public function canEditSenderForPickup(?Pickup $pickup = null): bool
    {
        if (! $pickup) {
            return false;
        }

        if (auth()->user()?->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        if ($this->currentOpsCanAccessPickup($pickup)) {
            return in_array($pickup->status, [
                PickupStatusEnum::MOI_TAO_PICKUP,
                PickupStatusEnum::DA_XAC_NHAN,
            ], true);
        }

        if ($this->shouldScopeToCurrentSale()) {
            return $this->currentSaleCreatedPickup($pickup)
                && $pickup->status === PickupStatusEnum::MOI_TAO_PICKUP;
        }

        if ($this->shouldScopeToCurrentCtv()) {
            return $this->currentCtvCreatedPickup($pickup)
                && $pickup->status === PickupStatusEnum::MOI_TAO_PICKUP;
        }

        if (auth()->user()?->hasRole('cs')) {
            return $pickup->status === PickupStatusEnum::MOI_TAO_PICKUP;
        }

        return false;
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
            ->when($this->shouldScopeToCurrentOps(), fn ($query) => $query->whereKey(auth()->id()))
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
