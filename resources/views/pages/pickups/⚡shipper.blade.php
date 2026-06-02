<?php

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\Pickup;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.mobile')] #[Title('Danh sách Pickup')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $status = '';
    public ?int $expandedId = null;

    public function mount(): void
    {
        abort_unless(\Gate::allows('pickups.index'), 403);
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function getPickupsProperty()
    {
        return Pickup::query()
            ->where('id_shipper', auth()->id())
            ->with(['orders:id,id_bill,tracking_code,uuid'])
            ->withCount('orders')
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('ma_pickup', 'like', "%{$keyword}%")
                        ->orWhere('info_khachhang', 'like', "%{$keyword}%");
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->where(function ($q) {
                $q->whereNull('status')
                  ->orWhere('status', '!=', PickupStatusEnum::DA_HUY->value);
            })
            ->latest('ngay_tao')
            ->paginate(10);
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

    public function openMap(int $pickupId): void
    {
        $pickup = Pickup::find($pickupId);
        if (! $pickup) return;

        $lat = data_get($pickup->info_khachhang, 'pickup_lat');
        $lng = data_get($pickup->info_khachhang, 'pickup_lng');
        $address = data_get($pickup->info_khachhang, 'address', '');

        if ($lat && $lng) {
            $this->dispatch('open-navigation', lat: $lat, lng: $lng);
        } elseif ($address) {
            $this->dispatch('open-navigation-address', address: $address);
        }
    }
};
?>

<div class="pb-20">
    {{-- Search & Filter Bar --}}
    <div class="sticky top-14 z-30 bg-white border-b border-neutral-200 px-4 py-3 space-y-2">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.400ms="keyword"
                   placeholder="Tìm mã pickup, tên, SĐT..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-neutral-200 bg-neutral-50 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500">
        </div>
        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1 scrollbar-hide">
            <button wire:click="$set('status', '')"
                    class="shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ $status === '' ? 'bg-primary-600 text-white' : 'bg-neutral-100 text-neutral-600' }}">
                Tất cả
            </button>
            @foreach(PickupStatusEnum::cases() as $option)
                @if($option !== PickupStatusEnum::DA_HUY)
                    <button wire:click="$set('status', '{{ $option->value }}')"
                            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-medium transition-colors {{ $status === $option->value ? 'bg-primary-600 text-white' : 'bg-neutral-100 text-neutral-600' }}">
                        {{ $option->label() }}
                    </button>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Pickup Cards --}}
    <div class="px-4 pt-3 space-y-3">
        @forelse($this->pickups as $pickup)
            @php
                $customer = $pickup->info_khachhang ?? [];
                $info = $pickup->info_pickup ?? [];
                $isExpanded = $this->expandedId === $pickup->id;
                $phone = data_get($customer, 'phone', '');
                $address = data_get($customer, 'address', '');
                $scheduledAt = data_get($info, 'ngayhen');
            @endphp

            <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden transition-all"
                 wire:key="pickup-{{ $pickup->id }}">

                {{-- Card Header - Always visible --}}
                <div class="px-4 pt-3 pb-2" wire:click="toggleExpand({{ $pickup->id }})">
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-bold text-neutral-900">{{ $pickup->ma_pickup }}</span>
                                @if($pickup->status)
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $pickup->status->color() }}">
                                        {{ $pickup->status->label() }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm font-medium text-neutral-800 truncate">
                                {{ data_get($customer, 'company') ?: data_get($customer, 'fullname', '-') }}
                            </p>
                        </div>
                        <svg class="w-5 h-5 text-neutral-400 shrink-0 mt-1 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </div>

                    {{-- Quick Info Row --}}
                    <div class="mt-2 flex items-center gap-4 text-xs text-neutral-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            {{ $pickup->orders_count }} đơn · {{ $pickup->numb }} kiện
                        </span>
                        @if($scheduledAt)
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ \Carbon\Carbon::parse($scheduledAt)->format('d/m H:i') }}
                            </span>
                        @endif
                        @if($pickup->total_c_weight)
                            <span>{{ number_format((float) $pickup->total_c_weight, 1, ',', '.') }} kg</span>
                        @endif
                    </div>
                </div>

                {{-- Quick Action Buttons - Always visible --}}
                <div class="px-4 pb-3 flex gap-2">
                    @if($phone)
                        <a href="tel:{{ $phone }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-semibold active:bg-emerald-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            Gọi
                        </a>
                    @endif
                    <button wire:click="openMap({{ $pickup->id }})" class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-blue-50 text-blue-700 text-xs font-semibold active:bg-blue-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Chỉ đường
                    </button>
                    {{-- Status action button --}}
                    @if($pickup->status === PickupStatusEnum::DA_XAC_NHAN)
                        <button wire:click="updateStatus({{ $pickup->id }}, '{{ PickupStatusEnum::PICKUP_DANG_LAY->value }}')"
                                wire:loading.attr="disabled"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-amber-500 text-white text-xs font-semibold active:bg-amber-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Bắt đầu lấy
                        </button>
                    @elseif($pickup->status === PickupStatusEnum::PICKUP_DANG_LAY)
                        <button wire:click="updateStatus({{ $pickup->id }}, '{{ PickupStatusEnum::PICKUP_DA_LAY->value }}')"
                                wire:loading.attr="disabled"
                                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold active:bg-emerald-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Đã lấy xong
                        </button>
                    @endif
                </div>

                {{-- Expanded Details --}}
                @if($isExpanded)
                    <div class="border-t border-neutral-100 px-4 py-3 space-y-3 bg-neutral-50/50">
                        {{-- Address --}}
                        @if($address)
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">Địa chỉ lấy hàng</p>
                                <p class="mt-0.5 text-sm text-neutral-700 leading-snug">{{ $address }}</p>
                            </div>
                        @endif

                        {{-- Contact Info --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">Liên hệ</p>
                                <p class="mt-0.5 text-sm text-neutral-700">{{ data_get($customer, 'fullname', '-') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">SĐT</p>
                                <p class="mt-0.5 text-sm text-neutral-700">{{ $phone ?: '-' }}</p>
                            </div>
                        </div>

                        {{-- Schedule & Weight --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">Ngày hẹn</p>
                                <p class="mt-0.5 text-sm text-neutral-700">{{ $scheduledAt ? \Carbon\Carbon::parse($scheduledAt)->format('d/m/Y H:i') : '-' }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">Số kiện</p>
                                <p class="mt-0.5 text-sm font-medium text-neutral-700">{{ $pickup->numb }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">Cân nặng</p>
                                <p class="mt-0.5 text-sm font-medium text-neutral-700">{{ number_format((float) $pickup->total_c_weight, 1, ',', '.') }} kg</p>
                            </div>
                        </div>

                        {{-- Note --}}
                        @if($pickup->note)
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider">Ghi chú</p>
                                <p class="mt-0.5 text-sm text-neutral-700 whitespace-pre-line">{{ $pickup->note }}</p>
                            </div>
                        @endif

                        {{-- Orders list --}}
                        @if($pickup->orders->isNotEmpty())
                            <div>
                                <p class="text-[10px] font-semibold uppercase text-neutral-400 tracking-wider mb-1.5">Đơn hàng ({{ $pickup->orders_count }})</p>
                                <div class="space-y-1.5">
                                    @foreach($pickup->orders as $order)
                                        <div class="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-neutral-100">
                                            <div>
                                                <p class="text-xs font-semibold text-neutral-800">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</p>
                                                @if($order->tracking_code)
                                                    <p class="text-[10px] text-neutral-500">{{ $order->tracking_code }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-neutral-400">
                <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p class="text-sm font-medium">Không có phiếu pickup nào</p>
                <p class="text-xs mt-1">Kéo xuống để làm mới</p>
            </div>
        @endforelse

        {{-- Pagination --}}
        @if($this->pickups->hasPages())
            <div class="py-3">
                {{ $this->pickups->links() }}
            </div>
        @endif
    </div>
</div>

@script
<script>
    $wire.on('open-navigation', ({ lat, lng }) => {
        // Try native maps first (works great on mobile)
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
        if (isIOS) {
            window.location.href = `maps://maps.apple.com/?daddr=${lat},${lng}&dirflg=d`;
        } else {
            window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`, '_blank');
        }
    });

    $wire.on('open-navigation-address', ({ address }) => {
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(address)}&travelmode=driving`, '_blank');
    });
</script>
@endscript
