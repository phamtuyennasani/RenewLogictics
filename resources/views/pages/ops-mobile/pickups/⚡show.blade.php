<?php

use App\Enums\PickupStatusEnum;
use App\Models\News;
use App\Models\Pickup;
use App\Models\Province;
use App\Models\User;
use App\Models\Ward;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile')] #[Title('Chi tiết PickUp')] class extends Component
{
    public Pickup $pickupModel;
    public bool $showEditForm = false;
    public array $editForm = [];

    public function mount(int $pickup): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['ops', 'admin', 'manager', 'cs']), 403);
        abort_unless(\Gate::allows('pickups.index'), 403);

        $this->pickupModel = $this->pickupQuery()->findOrFail($pickup);
    }

    protected function pickupQuery()
    {
        return Pickup::query()
            ->where('id_user', auth()->id())
            ->with(['shipper:id,fullname,username,phone', 'orders:id,id_bill,tracking_code,uuid,bill_status']);
    }

    public function refreshPickup(): void
    {
        $this->pickupModel = $this->pickupQuery()->findOrFail($this->pickupModel->id);
    }

    public function canEditPickup(): bool
    {
        return in_array($this->pickupModel->status, [
            PickupStatusEnum::MOI_TAO_PICKUP,
            PickupStatusEnum::DA_HUY,
        ], true);
    }

    public function openEditForm(): void
    {
        abort_unless($this->canEditPickup(), 403);

        $customer = $this->pickupModel->info_khachhang ?? [];
        $info = $this->pickupModel->info_pickup ?? [];

        $this->editForm = [
            'shipper_id' => $this->pickupModel->status === PickupStatusEnum::DA_HUY ? null : $this->pickupModel->id_shipper,
            'company' => data_get($customer, 'company', ''),
            'fullname' => data_get($customer, 'fullname', ''),
            'phone' => data_get($customer, 'phone', ''),
            'email' => data_get($customer, 'email', ''),
            'country' => data_get($customer, 'country', 'VIETNAM') ?: 'VIETNAM',
            'address' => data_get($customer, 'address', ''),
            'id_city' => data_get($customer, 'id_city', data_get($customer, 'city_id')),
            'id_ward' => data_get($customer, 'id_ward', data_get($customer, 'ward_id')),
            'vehicle_id' => data_get($info, 'id_phuongtien'),
            'scheduled_at' => data_get($info, 'ngayhen') ? \Carbon\Carbon::parse(data_get($info, 'ngayhen'))->format('Y-m-d\TH:i') : null,
            'branch_id' => data_get($info, 'chinhanhnhanhang'),
            'note' => (string) ($this->pickupModel->note ?? ''),
            'pickup_lat' => data_get($customer, 'pickup_lat'),
            'pickup_lng' => data_get($customer, 'pickup_lng'),
        ];

        $this->resetValidation();
        $this->showEditForm = true;
        $this->dispatch('ops-pickup-edit-map-opened');
    }

    public function closeEditForm(): void
    {
        $this->showEditForm = false;
        $this->editForm = [];
        $this->resetValidation();
    }

    public function updatedEditFormIdCity(): void
    {
        $this->editForm['id_ward'] = null;
    }

    public function saveEdit(): void
    {
        abort_unless($this->canEditPickup(), 403);

        $isCancelled = $this->pickupModel->status === PickupStatusEnum::DA_HUY;

        try {
            $rules = [
                'editForm.shipper_id' => [
                    $isCancelled ? 'required' : 'nullable',
                    Rule::exists((new User())->getTable(), 'id'),
                ],
            ];

            if (! $isCancelled) {
                $rules += [
                    'editForm.company' => 'required|string|max:255',
                    'editForm.fullname' => 'required|string|max:255',
                    'editForm.phone' => 'required|string|max:50',
                    'editForm.email' => 'nullable|email|max:255',
                    'editForm.country' => 'required|string|max:100',
                    'editForm.address' => 'required|string|max:500',
                    'editForm.id_city' => 'required|exists:province,id',
                    'editForm.id_ward' => 'required|exists:wards,id',
                    'editForm.vehicle_id' => 'nullable|exists:news,id',
                    'editForm.scheduled_at' => 'nullable|date',
                    'editForm.branch_id' => 'nullable|exists:news,id',
                    'editForm.note' => 'nullable|string|max:1000',
                    'editForm.pickup_lat' => 'nullable|numeric',
                    'editForm.pickup_lng' => 'nullable|numeric',
                ];
            }

            $data = $this->validate($rules, [
                'editForm.shipper_id.required' => 'Vui lòng chọn shipper mới.',
            ])['editForm'];
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Flux::toast(duration: 3500, heading: 'Thiếu thông tin', text: $exception->validator->errors()->first(), variant: 'warning');
            return;
        }

        if (filled($data['shipper_id'] ?? null)) {
            $shipperIsValid = User::query()
                ->whereKey($data['shipper_id'])
                ->whereHas('roles', fn ($query) => $query->where('name', 'shipper'))
                ->exists();

            if (! $shipperIsValid) {
                $this->addError('editForm.shipper_id', 'Vui lòng chọn shipper hợp lệ.');
                return;
            }
        }

        if (! $isCancelled) {
            $wardIsValid = Ward::query()
                ->whereKey($data['id_ward'] ?? null)
                ->where('parent_code', $data['id_city'] ?? null)
                ->exists();

            if (! $wardIsValid) {
                $this->addError('editForm.id_ward', 'Phường/xã không thuộc tỉnh/thành đã chọn.');
                return;
            }
        }

        DB::transaction(function () use ($data, $isCancelled): void {
            $pickup = Pickup::query()
                ->where('id_user', auth()->id())
                ->whereKey($this->pickupModel->id)
                ->lockForUpdate()
                ->firstOrFail();

            $updates = [
                'id_shipper' => filled($data['shipper_id'] ?? null) ? (int) $data['shipper_id'] : null,
            ];

            if ($isCancelled) {
                $updates['status'] = PickupStatusEnum::MOI_TAO_PICKUP;
            } else {
                $customer = $pickup->info_khachhang ?? [];
                $info = $pickup->info_pickup ?? [];

                $updates['note'] = trim((string) ($data['note'] ?? ''));
                $updates['info_khachhang'] = array_merge($customer, [
                    'company' => trim($data['company']),
                    'fullname' => trim($data['fullname']),
                    'phone' => trim($data['phone']),
                    'email' => trim((string) ($data['email'] ?? '')),
                    'country' => trim($data['country']),
                    'address' => trim($data['address']),
                    'id_city' => (int) $data['id_city'],
                    'id_ward' => (int) $data['id_ward'],
                    'pickup_lat' => is_numeric($data['pickup_lat'] ?? null) ? (float) $data['pickup_lat'] : data_get($customer, 'pickup_lat'),
                    'pickup_lng' => is_numeric($data['pickup_lng'] ?? null) ? (float) $data['pickup_lng'] : data_get($customer, 'pickup_lng'),
                ]);
                $updates['info_pickup'] = array_merge($info, [
                    'id_phuongtien' => filled($data['vehicle_id'] ?? null) ? (int) $data['vehicle_id'] : null,
                    'ngayhen' => $data['scheduled_at'] ?? null,
                    'chinhanhnhanhang' => filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null,
                ]);
            }

            $pickup->forceFill($updates)->save();
        });

        $this->refreshPickup();
        $this->closeEditForm();

        Flux::toast(
            duration: 2500,
            heading: $isCancelled ? 'Đã gán lại shipper' : 'Đã cập nhật PickUp',
            text: $isCancelled ? 'PickUp đã chuyển về trạng thái Mới tạo.' : 'Thông tin PickUp đã được lưu.',
            variant: 'success'
        );
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

    public function pickupVehicles()
    {
        return News::query()->where('type', 'phuongtien')->orderBy('namevi')->get(['id', 'namevi']);
    }

    public function pickupBranches()
    {
        return News::query()->where('type', 'chinhanh')->orderBy('namevi')->get(['id', 'namevi']);
    }

    public function pickupShippers()
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'shipper'))
            ->orderBy('fullname')
            ->orderBy('username')
            ->get(['id', 'fullname', 'username']);
    }
};
?>

@php
    $pickup = $pickupModel;
    $customer = $pickup->info_khachhang ?? [];
    $info = $pickup->info_pickup ?? [];
    $isCancelled = $pickup->status === \App\Enums\PickupStatusEnum::DA_HUY;
    $inputClass = 'h-11 w-full rounded-xl border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100';
@endphp

<div class="min-h-screen bg-neutral-50 pb-24">
    <section class="relative overflow-hidden px-4 pb-5 pt-4 text-white"
             style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
        <div class="absolute -right-12 -top-16 h-40 w-40 rounded-full bg-white/10"></div>
        <div class="absolute -bottom-20 left-8 h-36 w-36 rounded-full bg-white/10"></div>

        <div class="relative flex items-start justify-between gap-3">
            <a href="{{ route('ops.mobile.pickups.index') }}" wire:navigate class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 text-white backdrop-blur active:bg-white/25" aria-label="Quay lại">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>

            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-wide text-white/70">Chi tiết PickUp</p>
                <h1 class="mt-1 truncate font-mono text-2xl font-bold leading-tight">{{ $pickup->ma_pickup }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if($pickup->status)
                        <span class="rounded-full bg-white/18 px-2.5 py-1 text-xs font-bold text-white ring-1 ring-white/20">{{ $pickup->status->label() }}</span>
                    @endif
                    <span class="rounded-full bg-white/12 px-2.5 py-1 text-xs font-semibold text-white/80">{{ $pickup->orders->count() }} order</span>
                </div>
            </div>

            @if($this->canEditPickup() && ! $showEditForm)
                <button type="button"
                        wire:click="openEditForm"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-primary-700 shadow-lg shadow-primary-950/15 active:bg-white/90"
                        aria-label="{{ $isCancelled ? 'Chọn lại shipper' : 'Sửa PickUp' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $isCancelled ? 'M17 8l4 4m0 0l-4 4m4-4H7' : 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5M18.5 2.5a2.1 2.1 0 013 3L12 15l-4 1 1-4 9.5-9.5z' }}"/>
                    </svg>
                </button>
            @endif
        </div>

        <div class="relative mt-4 rounded-2xl bg-white/12 p-3 ring-1 ring-white/15 backdrop-blur">
            <p class="truncate text-base font-bold">{{ data_get($customer, 'company') ?: data_get($customer, 'fullname', '-') }}</p>
            <p class="mt-1 line-clamp-2 text-sm leading-5 text-white/78">{{ data_get($customer, 'address', 'Chưa có địa chỉ') }}</p>
            @if(data_get($customer, 'phone'))
                <a href="tel:{{ data_get($customer, 'phone') }}" class="mt-3 inline-flex h-9 items-center gap-2 rounded-full bg-white px-3 text-sm font-bold text-primary-700 shadow-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.3a1 1 0 01.95.68l1.1 3.3a1 1 0 01-.5 1.2l-2.1 1.05a11 11 0 005 5l1.05-2.1a1 1 0 011.2-.5l3.3 1.1a1 1 0 01.7.95V19a2 2 0 01-2 2h-1C8.8 21 3 15.2 3 8V5z"/>
                    </svg>
                    {{ data_get($customer, 'phone') }}
                </a>
            @endif
        </div>
    </section>

    <section class="space-y-4 px-4 py-4">
        @if($isCancelled)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold leading-6 text-amber-900">
                Phiếu PickUp đã bị hủy. OPS có thể chọn shipper mới, sau đó phiếu sẽ quay về trạng thái Mới tạo.
            </div>
        @endif

        <div class="grid grid-cols-4 gap-2">
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 text-center shadow-sm">
                <p class="text-[10px] font-bold uppercase text-neutral-400">Hẹn</p>
                <p class="mt-1 text-xs font-bold leading-tight text-neutral-900">{{ data_get($info, 'ngayhen') ? \Carbon\Carbon::parse(data_get($info, 'ngayhen'))->format('H:i') : '-' }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 text-center shadow-sm">
                <p class="text-[10px] font-bold uppercase text-neutral-400">Order</p>
                <p class="mt-1 text-lg font-bold leading-none text-neutral-900">{{ $pickup->orders->count() }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 text-center shadow-sm">
                <p class="text-[10px] font-bold uppercase text-neutral-400">Kiện</p>
                <p class="mt-1 text-lg font-bold leading-none text-neutral-900">{{ (int) $pickup->numb }}</p>
            </div>
            <div class="rounded-2xl border border-neutral-200 bg-white p-3 text-center shadow-sm">
                <p class="text-[10px] font-bold uppercase text-neutral-400">Kg</p>
                <p class="mt-1 text-sm font-bold leading-none text-neutral-900">{{ number_format((float) $pickup->total_weight, 1) }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-neutral-400">Shipper phụ trách</p>
                    <p class="mt-1 truncate text-base font-bold text-neutral-950">{{ $pickup->shipper?->fullname ?: $pickup->shipper?->username ?: 'Chưa gán' }}</p>
                    @if($pickup->shipper?->phone)
                        <p class="mt-0.5 text-sm text-neutral-500">{{ $pickup->shipper->phone }}</p>
                    @endif
                </div>
                @if($pickup->shipper?->phone)
                    <a href="tel:{{ $pickup->shipper->phone }}" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700 active:bg-primary-100" aria-label="Gọi shipper">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.3a1 1 0 01.95.68l1.1 3.3a1 1 0 01-.5 1.2l-2.1 1.05a11 11 0 005 5l1.05-2.1a1 1 0 011.2-.5l3.3 1.1a1 1 0 01.7.95V19a2 2 0 01-2 2h-1C8.8 21 3 15.2 3 8V5z"/>
                        </svg>
                    </a>
                @endif
            </div>
        </div>

        @if($pickup->note || data_get($info, 'ngayhen'))
            <div class="rounded-2xl border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-bold uppercase tracking-wide text-neutral-400">Ghi chú & lịch hẹn</p>
                <p class="mt-2 text-sm font-semibold text-neutral-800">{{ data_get($info, 'ngayhen') ? \Carbon\Carbon::parse(data_get($info, 'ngayhen'))->format('H:i d/m/Y') : 'Chưa có lịch hẹn' }}</p>
                @if($pickup->note)
                    <p class="mt-2 rounded-xl bg-neutral-50 px-3 py-2 text-sm leading-5 text-neutral-700">{{ $pickup->note }}</p>
                @endif
            </div>
        @endif

        @if($showEditForm)
            <form wire:submit.prevent="saveEdit" class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-neutral-950">{{ $isCancelled ? 'Chọn lại shipper' : 'Sửa PickUp' }}</p>
                        <p class="mt-1 text-xs text-neutral-500">{{ $isCancelled ? 'Sau khi lưu, phiếu chuyển về Mới tạo.' : 'Chỉ áp dụng khi phiếu còn ở trạng thái Mới tạo.' }}</p>
                    </div>
                    <button type="button" wire:click="closeEditForm" class="rounded-lg bg-neutral-100 px-3 py-1.5 text-xs font-bold text-neutral-600">Đóng</button>
                </div>

                <div class="mt-4 space-y-3">
                    <select class="{{ $inputClass }}" wire:model.defer="editForm.shipper_id">
                        <option value="">{{ $isCancelled ? 'Chọn shipper mới' : 'Chọn shipper' }}</option>
                        @foreach($this->pickupShippers() as $shipper)
                            <option value="{{ $shipper->id }}">{{ $shipper->fullname ?: $shipper->username }}</option>
                        @endforeach
                    </select>
                    @error('editForm.shipper_id') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    @if(! $isCancelled)
                        <input class="{{ $inputClass }}" wire:model.defer="editForm.company" placeholder="Công ty">
                        @error('editForm.company') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                        <input class="{{ $inputClass }}" wire:model.defer="editForm.fullname" placeholder="Người liên hệ">
                        <input class="{{ $inputClass }}" wire:model.defer="editForm.phone" inputmode="tel" placeholder="Số điện thoại">
                        <input class="{{ $inputClass }}" wire:model.defer="editForm.email" type="email" placeholder="Email">

                        <div class="grid grid-cols-2 gap-2">
                            <select class="{{ $inputClass }}" wire:model.live="editForm.id_city">
                                <option value="">Tỉnh/thành</option>
                                @foreach($this->pickupProvinces() as $province)
                                    <option value="{{ $province->id }}">{{ $province->name }}</option>
                                @endforeach
                            </select>
                            <select class="{{ $inputClass }}" wire:model.defer="editForm.id_ward" @disabled(empty($editForm['id_city'] ?? null))>
                                <option value="">Phường/xã</option>
                                @foreach($this->pickupWards() as $ward)
                                    <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @error('editForm.id_ward') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                        <textarea class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-3 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100" rows="3" wire:model.defer="editForm.address" placeholder="Địa chỉ lấy hàng"></textarea>

                        <input type="hidden" wire:model="editForm.pickup_lat">
                        <input type="hidden" wire:model="editForm.pickup_lng">

                        <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-50" wire:ignore>
                            <div class="flex items-center justify-between gap-3 border-b border-neutral-200 bg-white px-3 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-neutral-950">Vị trí lấy hàng</p>
                                    <p id="ops-pickup-edit-map-status" class="mt-0.5 truncate text-xs font-medium text-neutral-500">
                                        @if($editForm['pickup_lat'] && $editForm['pickup_lng'])
                                            Tọa độ: {{ $editForm['pickup_lat'] }}, {{ $editForm['pickup_lng'] }}
                                        @else
                                            Tìm theo địa chỉ hoặc ghim trực tiếp trên bản đồ.
                                        @endif
                                    </p>
                                </div>
                                <button type="button"
                                        id="ops-pickup-edit-geocode-btn"
                                        class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-primary-50 px-3 text-xs font-bold text-primary-700 active:bg-primary-100">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Tìm
                                </button>
                            </div>
                            <div id="ops-pickup-edit-map"
                                 class="h-72 w-full"
                                 data-lat="{{ $editForm['pickup_lat'] }}"
                                 data-lng="{{ $editForm['pickup_lng'] }}"></div>
                            <div class="border-t border-neutral-200 bg-white px-3 py-2 text-xs font-medium text-neutral-500">
                                Chạm bản đồ hoặc kéo marker để chọn đúng vị trí lấy hàng.
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <select class="{{ $inputClass }}" wire:model.defer="editForm.vehicle_id">
                                <option value="">Phương tiện</option>
                                @foreach($this->pickupVehicles() as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ $vehicle->namevi }}</option>
                                @endforeach
                            </select>
                            <input class="{{ $inputClass }}" wire:model.defer="editForm.scheduled_at" type="datetime-local">
                        </div>

                        <select class="{{ $inputClass }}" wire:model.defer="editForm.branch_id">
                            <option value="">Chi nhánh nhận hàng</option>
                            @foreach($this->pickupBranches() as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->namevi }}</option>
                            @endforeach
                        </select>

                        <textarea class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-3 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100" rows="2" wire:model.defer="editForm.note" placeholder="Ghi chú"></textarea>
                    @endif

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="saveEdit"
                            class="flex h-12 w-full items-center justify-center rounded-xl {{ $isCancelled ? 'bg-amber-600 active:bg-amber-700' : 'bg-emerald-600 active:bg-emerald-700' }} text-sm font-bold text-white shadow-sm disabled:opacity-60">
                        <span wire:loading.remove wire:target="saveEdit">{{ $isCancelled ? 'Gán lại shipper' : 'Lưu PickUp' }}</span>
                        <span wire:loading wire:target="saveEdit">Đang lưu...</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
</div>

@push('scripts')
<script>
(() => {
    const MAP_ID = 'ops-pickup-edit-map';
    const STATUS_ID = 'ops-pickup-edit-map-status';
    const GEOCODE_BTN_ID = 'ops-pickup-edit-geocode-btn';
    const VIETMAP_GL_VERSION = '6.0.1';
    const VIETMAP_GL_CSS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.css`;
    const VIETMAP_GL_JS_URL = `https://unpkg.com/@vietmap/vietmap-gl-js@${VIETMAP_GL_VERSION}/dist/vietmap-gl.js`;
    const VIETMAP_PROXY_BASE = '/api/vietmap';
    const DEFAULT_CENTER = [106.7009, 10.7769];
    const MARKER_PRIMARY = '#ef4444';
    const MARKER_ACCENT = '#b91c1c';

    let vietmapPromise = null;
    let map = null;
    let marker = null;
    let setupTimer = null;

    function mapElement() {
        return document.getElementById(MAP_ID);
    }

    function componentRoot() {
        return mapElement()?.closest('[wire\\:id]') ?? null;
    }

    function component() {
        const id = componentRoot()?.getAttribute('wire:id');
        return id && window.Livewire?.find ? window.Livewire.find(id) : null;
    }

    function status(text) {
        const el = document.getElementById(STATUS_ID);
        if (el) el.textContent = text;
    }

    function tileKey() {
        return window.__VIETMAP_PUBLIC_CONFIG__?.tileApiKey || '';
    }

    function styleUrl() {
        return `https://maps.vietmap.vn/maps/styles/tm/style.json?apikey=${encodeURIComponent(tileKey())}`;
    }

    function apiUrl(path, params = {}) {
        const url = new URL(`${VIETMAP_PROXY_BASE}/${path}`, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, value);
        });
        return url.toString();
    }

    async function fetchJson(path, params = {}) {
        const response = await fetch(apiUrl(path, params), { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(response.status === 503 ? 'missing-config' : 'request-failed');
        return response.json();
    }

    function ensureVietmap() {
        if (window.vietmapgl) return Promise.resolve(window.vietmapgl);
        if (vietmapPromise) return vietmapPromise;

        if (!document.querySelector(`link[href="${VIETMAP_GL_CSS_URL}"]`)) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = VIETMAP_GL_CSS_URL;
            document.head.appendChild(css);
        }

        vietmapPromise = new Promise((resolve, reject) => {
            const existing = document.querySelector(`script[src="${VIETMAP_GL_JS_URL}"]`);
            const script = existing ?? document.createElement('script');

            script.addEventListener('load', () => {
                window.vietmapgl ? resolve(window.vietmapgl) : reject(new Error('vietmap-missing'));
            }, { once: true });
            script.addEventListener('error', reject, { once: true });

            if (!existing) {
                script.src = VIETMAP_GL_JS_URL;
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

    function markerElement() {
        const el = document.createElement('div');
        const gradientId = `opsPickupMarkerGradient${Math.random().toString(36).slice(2)}`;
        el.style.cssText = 'width:42px;height:52px;cursor:grab;filter:drop-shadow(0 12px 14px rgba(15,23,42,.28));';
        el.innerHTML = `
            <svg width="42" height="52" viewBox="0 0 42 52" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <defs>
                    <linearGradient id="${gradientId}" x1="7" y1="5" x2="34" y2="40" gradientUnits="userSpaceOnUse">
                        <stop stop-color="${MARKER_PRIMARY}"/>
                        <stop offset="1" stop-color="${MARKER_ACCENT}"/>
                    </linearGradient>
                </defs>
                <path d="M21 49C18.5 45.6 9 33.6 9 21.8C9 14.6 14.4 9 21 9C27.6 9 33 14.6 33 21.8C33 33.6 23.5 45.6 21 49Z" fill="rgba(15,23,42,.18)"/>
                <path d="M21 45C18.1 41.1 5 24.9 5 17.6C5 8.8 12.2 2 21 2C29.8 2 37 8.8 37 17.6C37 24.9 23.9 41.1 21 45Z" fill="url(#${gradientId})" stroke="white" stroke-width="4" stroke-linejoin="round"/>
                <circle cx="21" cy="17.5" r="6.5" fill="white"/>
                <circle cx="21" cy="17.5" r="3" fill="${MARKER_PRIMARY}"/>
            </svg>
        `;
        return el;
    }

    function sync(lat, lng) {
        const c = component();
        if (!c) return;
        c.set('editForm.pickup_lat', Number(lat).toFixed(7));
        c.set('editForm.pickup_lng', Number(lng).toFixed(7));
        status(`Tọa độ: ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`);
    }

    function placeMarker(lat, lng, fly = true) {
        if (!map) return;
        if (marker) {
            marker.setLngLat([lng, lat]);
        } else {
            marker = new window.vietmapgl.Marker({ element: markerElement(), draggable: true, anchor: 'bottom' })
                .setLngLat([lng, lat])
                .addTo(map);
            marker.on('dragend', () => {
                const point = marker.getLngLat();
                sync(point.lat, point.lng);
            });
        }
        if (fly) map.flyTo({ center: [lng, lat], zoom: 16, duration: 400 });
        sync(lat, lng);
    }

    function normalizedResults(payload) {
        if (Array.isArray(payload)) return payload;
        if (Array.isArray(payload?.data)) return payload.data;
        if (Array.isArray(payload?.results)) return payload.results;
        return payload ? [payload] : [];
    }

    function coordinate(result) {
        const lat = parseFloat(result?.lat ?? result?.location?.lat);
        const lng = parseFloat(result?.lng ?? result?.lon ?? result?.location?.lng ?? result?.location?.lon);
        return Number.isNaN(lat) || Number.isNaN(lng) ? null : { lat, lng };
    }

    function refId(result) {
        return result?.ref_id || result?.refid || result?.data_new?.ref_id || result?.data_old?.ref_id || null;
    }

    async function geocode() {
        const root = componentRoot();
        const c = component();
        if (!root || !c) return;

        const form = c.get('editForm') || {};
        const city = root.querySelector('[wire\\:model\\.live="editForm.id_city"]');
        const ward = root.querySelector('[wire\\:model\\.defer="editForm.id_ward"]');
        const cityName = city?.selectedIndex > 0 ? city.options[city.selectedIndex].text : '';
        const wardName = ward?.selectedIndex > 0 ? ward.options[ward.selectedIndex].text : '';
        const text = [form.address, wardName, cityName, 'Vietnam'].filter(Boolean).join(', ');

        if (!text.trim()) {
            status('Nhập địa chỉ hoặc chạm trực tiếp trên bản đồ để ghim vị trí.');
            return;
        }

        status('Đang tìm vị trí...');
        try {
            const results = normalizedResults(await fetchJson('search', {
                text,
                focus: '10.7769,106.7009',
                display_type: 5,
            }));
            if (!results.length) {
                status('Không tìm thấy vị trí. Chạm bản đồ để ghim thủ công.');
                return;
            }

            let point = coordinate(results[0]);
            const id = refId(results[0]);
            if (id) {
                try {
                    point = coordinate(await fetchJson('place', { refid: id })) || point;
                } catch {}
            }

            if (!point) {
                status('Không lấy được tọa độ. Chạm bản đồ để ghim thủ công.');
                return;
            }

            placeMarker(point.lat, point.lng);
        } catch (error) {
            status(error.message === 'missing-config'
                ? 'Chưa cấu hình VietMap Geocode API Key.'
                : 'Không thể tìm vị trí. Chạm bản đồ để ghim thủ công.');
        }
    }

    function bindGeocodeButton() {
        const button = document.getElementById(GEOCODE_BTN_ID);
        if (!button || button.dataset.bound === 'true') return;
        button.dataset.bound = 'true';
        button.addEventListener('click', (event) => {
            event.preventDefault();
            geocode();
        });
    }

    function cleanup() {
        clearTimeout(setupTimer);
        if (map) {
            map.remove();
            map = null;
            marker = null;
        }
    }

    async function setup() {
        const el = mapElement();
        if (!el || el.offsetParent === null) {
            cleanup();
            return;
        }
        if (map) {
            requestAnimationFrame(() => map?.resize());
            return;
        }
        if (!tileKey()) {
            status('Chưa cấu hình VietMap Tile API Key.');
            return;
        }

        try {
            await ensureVietmap();
        } catch {
            status('Không tải được bản đồ VietMap.');
            return;
        }

        const lat = parseFloat(el.dataset.lat);
        const lng = parseFloat(el.dataset.lng);
        const center = (!Number.isNaN(lat) && !Number.isNaN(lng)) ? [lng, lat] : DEFAULT_CENTER;

        map = new window.vietmapgl.Map({
            container: el,
            style: styleUrl(),
            center,
            zoom: (!Number.isNaN(lat) && !Number.isNaN(lng)) ? 15 : 11,
        });
        map.addControl(new window.vietmapgl.NavigationControl(), 'top-right');
        map.on('load', () => {
            bindGeocodeButton();
            map.resize();
            if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                placeMarker(lat, lng, false);
            }
        });
        map.on('click', (event) => placeMarker(event.lngLat.lat, event.lngLat.lng, false));
        map.on('error', () => status('Không tải được tile bản đồ. Kiểm tra VietMap key.'));
    }

    function scheduleSetup(delay = 80) {
        clearTimeout(setupTimer);
        setupTimer = setTimeout(setup, delay);
    }

    document.addEventListener('DOMContentLoaded', () => scheduleSetup());
    document.addEventListener('livewire:navigated', () => scheduleSetup());
    window.addEventListener('ops-pickup-edit-map-opened', () => scheduleSetup(180));
    document.addEventListener('livewire:updated', () => scheduleSetup(120));
})();
</script>
@endpush
