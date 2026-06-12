<?php

use App\Actions\Pickup\CreatePickupAction;
use App\Enums\OrderStatusEnum;
use App\Models\News;
use App\Models\Order;
use App\Models\Province;
use App\Models\User;
use App\Models\Ward;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile')] #[Title('Chi tiết order')] class extends Component
{
    public Order $orderModel;
    public bool $showPickupForm = false;
    public array $pickupForm = [];

    public function mount(int $order): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['ops', 'admin', 'manager', 'cs']), 403);
        abort_unless(\Gate::allows('orders.index'), 403);

        $this->orderModel = Order::query()
            ->where('id_ops', auth()->id())
            ->with(['sale:id,fullname,username', 'packages', 'pickups.shipper:id,fullname,username'])
            ->findOrFail($order);
    }

    public function getCurrentPickupProperty()
    {
        return $this->orderModel->pickups->first();
    }

    public function canCreatePickup(): bool
    {
        return ! $this->orderModel->lock_order
            && in_array($this->orderModel->bill_status, [OrderStatusEnum::MOI_TAO, OrderStatusEnum::DA_XAC_NHAN], true)
            && ! $this->currentPickup;
    }

    public function openPickupForm(): void
    {
        abort_unless($this->canCreatePickup(), 403);

        $sender = $this->orderModel->sender ?? [];
        $this->pickupForm = [
            'company' => data_get($sender, 'company', ''),
            'fullname' => data_get($sender, 'fullname', data_get($sender, 'tenlienhe', '')),
            'phone' => data_get($sender, 'phone', ''),
            'email' => data_get($sender, 'email', ''),
            'country' => data_get($sender, 'country', 'VIETNAM') ?: 'VIETNAM',
            'address' => data_get($sender, 'address', ''),
            'id_city' => data_get($sender, 'id_city', data_get($sender, 'city_id')),
            'id_ward' => data_get($sender, 'id_ward', data_get($sender, 'ward_id')),
            'vehicle_id' => null,
            'scheduled_at' => now()->format('Y-m-d\TH:i'),
            'branch_id' => null,
            'shipper_id' => null,
            'note' => '',
            'pickup_lat' => null,
            'pickup_lng' => null,
        ];
        $this->resetValidation();
        $this->showPickupForm = true;
    }

    public function closePickupForm(): void
    {
        $this->showPickupForm = false;
        $this->pickupForm = [];
        $this->resetValidation();
    }

    public function updatedPickupFormIdCity(): void
    {
        $this->pickupForm['id_ward'] = null;
    }

    public function createPickup(): void
    {
        abort_unless($this->canCreatePickup(), 403);

        try {
            $data = $this->validate([
                'pickupForm.company' => 'required|string|max:255',
                'pickupForm.fullname' => 'required|string|max:255',
                'pickupForm.phone' => 'required|string|max:50',
                'pickupForm.email' => 'nullable|email|max:255',
                'pickupForm.country' => 'required|string|max:100',
                'pickupForm.address' => 'required|string|max:500',
                'pickupForm.id_city' => 'required|exists:province,id',
                'pickupForm.id_ward' => 'required|exists:wards,id',
                'pickupForm.vehicle_id' => 'required|exists:news,id',
                'pickupForm.scheduled_at' => 'required|date',
                'pickupForm.branch_id' => 'nullable|exists:news,id',
                'pickupForm.shipper_id' => ['required', Rule::exists((new User())->getTable(), 'id')],
                'pickupForm.note' => 'nullable|string|max:1000',
                'pickupForm.pickup_lat' => 'nullable|numeric',
                'pickupForm.pickup_lng' => 'nullable|numeric',
            ], [
                'pickupForm.vehicle_id.required' => 'Vui lòng chọn phương tiện.',
                'pickupForm.shipper_id.required' => 'Vui lòng chọn shipper phụ trách.',
            ])['pickupForm'];
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Flux::toast(duration: 3500, heading: 'Thiếu thông tin', text: $exception->validator->errors()->first(), variant: 'warning');
            return;
        }

        $wardIsValid = Ward::query()
            ->whereKey($data['id_ward'])
            ->where('parent_code', $data['id_city'])
            ->exists();

        if (! $wardIsValid) {
            $this->addError('pickupForm.id_ward', 'Phường/xã không thuộc tỉnh/thành đã chọn.');
            return;
        }

        $shipperIsValid = User::query()
            ->whereKey($data['shipper_id'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'shipper'))
            ->exists();

        if (! $shipperIsValid) {
            $this->addError('pickupForm.shipper_id', 'Vui lòng chọn shipper hợp lệ.');
            return;
        }

        try {
            $pickup = CreatePickupAction::execute($this->orderModel, [
                'ops_id' => auth()->id(),
                'sender_snapshot' => [
                    'company' => trim($data['company']),
                    'fullname' => trim($data['fullname']),
                    'phone' => trim($data['phone']),
                    'email' => trim((string) ($data['email'] ?? '')),
                    'country' => trim($data['country']),
                    'address' => trim($data['address']),
                    'id_city' => (int) $data['id_city'],
                    'id_ward' => (int) $data['id_ward'],
                ],
                'vehicle_id' => (int) $data['vehicle_id'],
                'scheduled_at' => $data['scheduled_at'],
                'packages_count' => (int) $this->orderModel->packages->sum('number_of_package'),
                'total_weight' => (float) $this->orderModel->packages->sum('c_weight'),
                'branch_id' => filled($data['branch_id'] ?? null) ? (int) $data['branch_id'] : null,
                'note' => trim((string) ($data['note'] ?? '')),
                'pickup_lat' => is_numeric($data['pickup_lat'] ?? null) ? (float) $data['pickup_lat'] : null,
                'pickup_lng' => is_numeric($data['pickup_lng'] ?? null) ? (float) $data['pickup_lng'] : null,
                'shipper_id' => (int) $data['shipper_id'],
            ], auth()->id());
        } catch (\Throwable $exception) {
            report($exception);
            Flux::toast(duration: 3500, heading: 'Không thể tạo PickUp', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        $this->orderModel = $this->orderModel->fresh(['sale:id,fullname,username', 'packages', 'pickups.shipper:id,fullname,username']);
        $this->closePickupForm();
        Flux::toast(duration: 2500, heading: 'Đã tạo PickUp', text: $pickup->ma_pickup, variant: 'success');
    }

    public function pickupProvinces()
    {
        return Province::query()->orderBy('name')->get(['id', 'name']);
    }

    public function pickupWards()
    {
        return Ward::query()
            ->when(data_get($this->pickupForm, 'id_city'), fn ($query, $cityId) => $query->where('parent_code', $cityId))
            ->when(! data_get($this->pickupForm, 'id_city'), fn ($query) => $query->whereRaw('1 = 0'))
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
    $order = $orderModel;
    $sender = $order->sender ?? [];
    $receiver = $order->receiver ?? [];
    $pickup = $this->currentPickup;
    $weight = (float) $order->packages->sum('c_weight');
    $inputClass = 'h-11 w-full rounded-xl border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100';
@endphp

<div class="min-h-screen bg-neutral-50 pb-24">
    <section class="bg-white px-4 py-4 shadow-sm ring-1 ring-neutral-200">
        <div class="flex items-start gap-3">
            <a href="{{ route('ops.mobile.orders.index') }}" wire:navigate class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700 active:bg-neutral-200" aria-label="Quay lại">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Chi tiết order</p>
                <h1 class="mt-0.5 truncate font-mono text-2xl font-bold text-neutral-950">{{ $order->id_bill ?: $order->tracking_code ?: '#'.$order->id }}</h1>
                @if($order->bill_status)
                    <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $order->bill_status->color() }}">{{ $order->bill_status->label() }}</span>
                @endif
            </div>
        </div>
    </section>

    <section class="space-y-4 px-4 py-4">
        @if($pickup)
            <a href="{{ route('ops.mobile.pickups.show', $pickup->id) }}" wire:navigate class="block rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase text-emerald-700">PickUp đã tạo</p>
                <div class="mt-1 flex items-center justify-between gap-3">
                    <p class="font-mono text-lg font-bold text-emerald-900">{{ $pickup->ma_pickup }}</p>
                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $pickup->status?->label() ?? '-' }}</span>
                </div>
                <p class="mt-1 text-sm font-medium text-emerald-800">Shipper: {{ $pickup->shipper?->fullname ?: $pickup->shipper?->username ?: 'Chưa gán' }}</p>
            </a>
        @elseif($this->canCreatePickup())
            <button type="button"
                    wire:click="openPickupForm"
                    class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 text-sm font-bold text-white shadow-sm active:bg-emerald-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tạo phiếu PickUp
            </button>
        @endif

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-bold text-neutral-950">Thông tin vận hành</p>
            <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-lg bg-neutral-50 px-3 py-2">
                    <p class="font-semibold text-neutral-500">Tracking</p>
                    <p class="mt-0.5 truncate font-bold text-neutral-800">{{ $order->tracking_code ?: '-' }}</p>
                </div>
                <div class="rounded-lg bg-neutral-50 px-3 py-2">
                    <p class="font-semibold text-neutral-500">Mã tham chiếu</p>
                    <p class="mt-0.5 truncate font-bold text-neutral-800">{{ $order->mathamchieu ?: '-' }}</p>
                </div>
                <div class="rounded-lg bg-neutral-50 px-3 py-2">
                    <p class="font-semibold text-neutral-500">Số kiện</p>
                    <p class="mt-0.5 font-bold text-neutral-800">{{ (int) $order->packages->sum('number_of_package') }}</p>
                </div>
                <div class="rounded-lg bg-neutral-50 px-3 py-2">
                    <p class="font-semibold text-neutral-500">Cân nặng</p>
                    <p class="mt-0.5 font-bold text-neutral-800">{{ number_format($weight, 2) }} kg</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-bold text-neutral-950">Người gửi</p>
            <div class="mt-3 space-y-2 text-sm text-neutral-700">
                <p class="font-bold text-neutral-950">{{ data_get($sender, 'company') ?: data_get($sender, 'fullname', '-') }}</p>
                <p>{{ data_get($sender, 'fullname', data_get($sender, 'tenlienhe', '-')) }}</p>
                @if(data_get($sender, 'phone'))
                    <p><a class="font-semibold text-primary-700" href="tel:{{ data_get($sender, 'phone') }}">{{ data_get($sender, 'phone') }}</a></p>
                @endif
                <p class="leading-5">{{ data_get($sender, 'address', '-') }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-bold text-neutral-950">Người nhận</p>
            <div class="mt-3 space-y-2 text-sm text-neutral-700">
                <p class="font-bold text-neutral-950">{{ data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', '-')) }}</p>
                <p>{{ data_get($receiver, 'country', '-') }}</p>
            </div>
        </div>

        @if($order->packages->isNotEmpty())
            <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <p class="text-sm font-bold text-neutral-950">Kiện hàng</p>
                <div class="mt-3 divide-y divide-neutral-100">
                    @foreach($order->packages as $package)
                        <div class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="font-mono font-bold text-neutral-800">{{ $package->code ?: '#'.$package->id }}</span>
                            <span class="shrink-0 text-xs font-semibold text-neutral-500">{{ (int) $package->number_of_package }} kiện / {{ number_format((float) $package->c_weight, 2) }} kg</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($showPickupForm)
            <form wire:submit.prevent="createPickup" class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-neutral-950">Tạo phiếu PickUp</p>
                        <p class="mt-1 text-xs text-neutral-500">Thông tin đã được lấy từ người gửi, kiểm tra lại trước khi lưu.</p>
                    </div>
                    <button type="button" wire:click="closePickupForm" class="rounded-lg bg-neutral-100 px-3 py-1.5 text-xs font-bold text-neutral-600">Đóng</button>
                </div>

                <div class="mt-4 space-y-3">
                    <input class="{{ $inputClass }}" wire:model.defer="pickupForm.company" placeholder="Công ty">
                    @error('pickupForm.company') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <input class="{{ $inputClass }}" wire:model.defer="pickupForm.fullname" placeholder="Người liên hệ">
                    @error('pickupForm.fullname') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <input class="{{ $inputClass }}" wire:model.defer="pickupForm.phone" inputmode="tel" placeholder="Số điện thoại">
                    @error('pickupForm.phone') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <input class="{{ $inputClass }}" wire:model.defer="pickupForm.email" type="email" placeholder="Email">

                    <div class="grid grid-cols-2 gap-2">
                        <select class="{{ $inputClass }}" wire:model.live="pickupForm.id_city">
                            <option value="">Tỉnh/thành</option>
                            @foreach($this->pickupProvinces() as $province)
                                <option value="{{ $province->id }}">{{ $province->name }}</option>
                            @endforeach
                        </select>
                        <select class="{{ $inputClass }}" wire:model.defer="pickupForm.id_ward" @disabled(empty($pickupForm['id_city'] ?? null))>
                            <option value="">Phường/xã</option>
                            @foreach($this->pickupWards() as $ward)
                                <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @error('pickupForm.id_city') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    @error('pickupForm.id_ward') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <textarea class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-3 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100" rows="3" wire:model.defer="pickupForm.address" placeholder="Địa chỉ lấy hàng"></textarea>
                    @error('pickupForm.address') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-2">
                        <select class="{{ $inputClass }}" wire:model.defer="pickupForm.vehicle_id">
                            <option value="">Phương tiện</option>
                            @foreach($this->pickupVehicles() as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->namevi }}</option>
                            @endforeach
                        </select>
                        <input class="{{ $inputClass }}" wire:model.defer="pickupForm.scheduled_at" type="datetime-local">
                    </div>
                    @error('pickupForm.vehicle_id') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror
                    @error('pickupForm.scheduled_at') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <select class="{{ $inputClass }}" wire:model.defer="pickupForm.shipper_id">
                        <option value="">Chọn shipper</option>
                        @foreach($this->pickupShippers() as $shipper)
                            <option value="{{ $shipper->id }}">{{ $shipper->fullname ?: $shipper->username }}</option>
                        @endforeach
                    </select>
                    @error('pickupForm.shipper_id') <p class="text-xs font-semibold text-red-600">{{ $message }}</p> @enderror

                    <select class="{{ $inputClass }}" wire:model.defer="pickupForm.branch_id">
                        <option value="">Chi nhánh nhận hàng</option>
                        @foreach($this->pickupBranches() as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->namevi }}</option>
                        @endforeach
                    </select>

                    <textarea class="w-full rounded-xl border border-neutral-200 bg-white px-3 py-3 text-sm font-semibold text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100" rows="2" wire:model.defer="pickupForm.note" placeholder="Ghi chú"></textarea>

                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="createPickup"
                            class="flex h-12 w-full items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold text-white shadow-sm disabled:opacity-60 active:bg-emerald-700">
                        <span wire:loading.remove wire:target="createPickup">Tạo PickUp</span>
                        <span wire:loading wire:target="createPickup">Đang tạo...</span>
                    </button>
                </div>
            </form>
        @endif
    </section>
</div>
