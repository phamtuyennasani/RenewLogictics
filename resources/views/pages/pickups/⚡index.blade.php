<?php

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\News;
use App\Models\Pickup;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Quản lý Pickup')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $status = '';
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public ?int $selectedPickupId = null;
    public ?int $selectedShipperId = null;

    public function mount(): void
    {
        abort_unless(\Gate::allows('pickups.index'), 403);
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->keyword = '';
        $this->status = '';
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function getPickupsProperty()
    {
        return Pickup::query()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username'])
            ->withCount('orders')
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
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->fromDate, fn ($query) => $query->whereDate('ngay_tao', '>=', $this->fromDate))
            ->when($this->toDate, fn ($query) => $query->whereDate('ngay_tao', '<=', $this->toDate))
            ->latest('ngay_tao')
            ->paginate(15);
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
        abort_unless(auth()->user()?->hasAnyRole(['admin', 'ops', 'manager']), 403);

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
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900">Quản lý Pickup</h1>
            <p class="mt-1 text-sm text-neutral-500">Theo dõi các phiếu lấy hàng đã tạo từ chi tiết đơn hàng.</p>
        </div>
        <a href="{{ route('orders.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
            Chọn đơn để tạo Pickup
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-5">
            <input type="text" wire:model.live.debounce.350ms="keyword" placeholder="Mã Pickup, mã đơn, người gửi" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none md:col-span-2">
            <select wire:model.live="status" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                @foreach(PickupStatusEnum::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="fromDate" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
            <input type="date" wire:model.live="toDate" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
        </div>
        <div class="mt-3 flex justify-end">
            <button type="button" wire:click="resetFilters" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Đặt lại</button>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mã Pickup</th>
                        <th class="px-4 py-3">Ngày tạo</th>
                        <th class="px-4 py-3">Người gửi</th>
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
                                <button type="button" wire:click="openDetails({{ $pickup->id }})" class="inline-flex rounded-lg border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-50">Chi tiết</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-neutral-500">Chưa có phiếu Pickup phù hợp.</td>
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
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Đóng</flux:button>
                    </flux:modal.close>
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

                @if($selectedPickup->status === PickupStatusEnum::DA_HUY && auth()->user()?->hasAnyRole(['admin', 'ops', 'manager']))
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
    {{-- Leaflet.js for Pickup Detail Map + Routing --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
</div>

<script>
//<![CDATA[
(function() {
    let detailMap = null;
    let detailMarker = null;
    let shipperMarker = null;
    let routeLine = null;

    function initDetailMap() {
        const mapEl = document.getElementById('pickup-detail-map');
        if (!mapEl || detailMap) return;
        if (mapEl.offsetParent === null) return;

        const lat = parseFloat(mapEl.dataset.pickupLat);
        const lng = parseFloat(mapEl.dataset.pickupLng);
        const hasCoords = !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;

        const center = hasCoords ? [lat, lng] : [10.7769, 106.7009];
        const zoom = hasCoords ? 16 : 12;

        detailMap = L.map('pickup-detail-map').setView(center, zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(detailMap);

        setTimeout(() => detailMap.invalidateSize(), 300);

        if (hasCoords) {
            detailMarker = L.marker([lat, lng]).addTo(detailMap)
                .bindPopup('<b>Điểm lấy hàng</b><br>' + (mapEl.dataset.pickupAddress || ''))
                .openPopup();
        } else {
            // Try geocode from address
            const address = mapEl.dataset.pickupAddress;
            if (address) {
                geocodeForDetail(address);
            }
        }

        // Direction button
        const dirBtn = document.getElementById('pickup-direction-btn');
        if (dirBtn) {
            dirBtn.addEventListener('click', function(e) {
                e.preventDefault();
                getDirections();
            });
        }
    }

    function geocodeForDetail(address) {
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address + ', Vietnam')}&limit=1&countrycodes=vn`;
        fetch(url, { headers: { 'Accept-Language': 'vi' } })
        .then(r => r.json())
        .then(data => {
            if (data && data.length > 0) {
                const lat = parseFloat(data[0].lat);
                const lng = parseFloat(data[0].lon);
                if (detailMap) {
                    detailMarker = L.marker([lat, lng]).addTo(detailMap)
                        .bindPopup('<b>Điểm lấy hàng</b><br>' + address)
                        .openPopup();
                    detailMap.setView([lat, lng], 16);
                    // Update button data
                    const btn = document.getElementById('pickup-direction-btn');
                    if (btn) { btn.dataset.lat = lat; btn.dataset.lng = lng; }
                    const statusEl = document.getElementById('pickup-detail-map-status');
                    if (statusEl) statusEl.textContent = 'Vị trí ước lượng từ địa chỉ';
                }
            }
        })
        .catch(() => {});
    }

    function getDirections() {
        const btn = document.getElementById('pickup-direction-btn');
        const destLat = parseFloat(btn?.dataset.lat);
        const destLng = parseFloat(btn?.dataset.lng);

        if (isNaN(destLat) || isNaN(destLng) || destLat === 0) {
            const statusEl = document.getElementById('pickup-detail-map-status');
            if (statusEl) statusEl.textContent = 'Chưa có tọa độ điểm lấy hàng.';
            return;
        }

        const statusEl = document.getElementById('pickup-detail-map-status');
        if (statusEl) statusEl.textContent = 'Đang lấy vị trí của bạn...';

        if (!navigator.geolocation) {
            if (statusEl) statusEl.textContent = 'Trình duyệt không hỗ trợ GPS.';
            openGoogleMapsNavigation(destLat, destLng);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const shipperLat = pos.coords.latitude;
                const shipperLng = pos.coords.longitude;
                if (statusEl) statusEl.textContent = 'Đang tìm đường đi...';
                fetchRoute(shipperLat, shipperLng, destLat, destLng);
            },
            function(err) {
                if (statusEl) statusEl.textContent = 'Không lấy được vị trí. Mở Google Maps...';
                openGoogleMapsNavigation(destLat, destLng);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    function fetchRoute(fromLat, fromLng, toLat, toLng) {
        // Add shipper marker
        if (detailMap) {
            if (shipperMarker) detailMap.removeLayer(shipperMarker);
            shipperMarker = L.marker([fromLat, fromLng], {
                icon: L.divIcon({
                    className: '',
                    html: '<div style="background:#3b82f6;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:bold;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);">🚚</div>',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16]
                })
            }).addTo(detailMap).bindPopup('Vị trí của bạn');
        }

        const url = `https://router.project-osrm.org/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`;

        fetch(url)
        .then(r => r.json())
        .then(data => {
            const statusEl = document.getElementById('pickup-detail-map-status');
            if (data.code !== 'Ok' || !data.routes || !data.routes.length) {
                if (statusEl) statusEl.textContent = 'Không tìm được đường đi. Thử Google Maps.';
                openGoogleMapsNavigation(toLat, toLng);
                return;
            }

            const route = data.routes[0];
            const distKm = (route.distance / 1000).toFixed(1);
            const durationMin = Math.round(route.duration / 60);

            // Draw route
            if (detailMap) {
                if (routeLine) detailMap.removeLayer(routeLine);
                const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
                routeLine = L.polyline(coords, {
                    color: '#3b82f6',
                    weight: 5,
                    opacity: 0.8
                }).addTo(detailMap);
                detailMap.fitBounds(routeLine.getBounds(), { padding: [40, 40] });
            }

            // Show route info
            if (statusEl) statusEl.textContent = `Khoảng cách: ${distKm} km — Thời gian: ~${durationMin} phút`;
            const routeInfo = document.getElementById('pickup-route-info');
            const routeText = document.getElementById('pickup-route-text');
            if (routeInfo && routeText) {
                routeText.textContent = `🚚 ${distKm} km — ~${durationMin} phút lái xe`;
                routeInfo.classList.remove('hidden');
            }
        })
        .catch(() => {
            const statusEl = document.getElementById('pickup-detail-map-status');
            if (statusEl) statusEl.textContent = 'Lỗi tìm đường. Mở Google Maps...';
            openGoogleMapsNavigation(toLat, toLng);
        });
    }

    function openGoogleMapsNavigation(lat, lng) {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`, '_blank');
    }

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
            routeLine = null;
        }
    });

    observer.observe(document.body, { attributes: true, subtree: true, childList: true });
    document.addEventListener('livewire:navigated', () => {
        detailMap = null; detailMarker = null; shipperMarker = null; routeLine = null;
    });
})();
</script>
