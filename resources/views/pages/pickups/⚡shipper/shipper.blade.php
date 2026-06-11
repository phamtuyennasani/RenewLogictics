<div class="pb-20">
    @php $summary = $this->summary; @endphp
    <div class="bg-gradient-to-br from-primary-600 to-primary-800 text-white px-4 py-4 relative overflow-hidden">
        <p class="text-xs font-medium opacity-90">Đơn hàng chưa nhận</p>
        <p class="text-3xl font-bold mt-0.5">{{ $summary['pending_count'] }} đơn hàng</p>
        <p class="text-xs mt-1 opacity-80">
            Thời gian lấy hàng gần nhất:
            <span class="font-semibold">
                {{ $summary['nearest_time'] ? \Carbon\Carbon::parse($summary['nearest_time'])->format('h:i A (d/m/Y)') : 'Chưa có' }}
            </span>
        </p>
        <div class="absolute right-4 top-4 rounded-full border border-white/20 bg-white/10 p-2">
            <svg class="w-9 h-9 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
    </div>
    <div class="bg-white border-b border-neutral-200 px-4 py-3">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.400ms="keyword"
                   placeholder="Tìm mã pickup, tên, SĐT..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-neutral-200 bg-neutral-50 text-sm focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary-500">
        </div>
    </div>
    <div class="bg-neutral-50 px-4 pt-3 pb-1">
        <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wider mb-2">Danh sách địa chỉ lấy hàng</p>
        <div class="flex border-b border-neutral-200">
            @foreach([
                'new'      => 'Mới giao',
                'accepted' => 'Tiếp nhận',
                'picking'  => 'Đang lấy',
                'done'     => 'Đã lấy',
            ] as $tabKey => $tabLabel)
                @php
                    $tabStatus = match ($tabKey) {
                        'new' => \App\Enums\PickupStatusEnum::MOI_TAO_PICKUP,
                        'accepted' => \App\Enums\PickupStatusEnum::DA_XAC_NHAN,
                        'picking' => \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY,
                        'done' => \App\Enums\PickupStatusEnum::PICKUP_DA_LAY,
                    };
                @endphp
                <button wire:click="$set('tab', '{{ $tabKey }}')"
                        class="flex-1 py-2.5 text-xs font-semibold text-center border-b-2 rounded-t-lg transition-colors
                            {{ $tab === $tabKey
                                ? 'border-current '.$tabStatus->color()
                                : 'border-transparent text-neutral-500 hover:text-neutral-700' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>
    </div>
    <div class="px-4 pt-3 space-y-3">
        @forelse($this->pickups as $index => $pickup)
            @php
                $customer = $pickup->info_khachhang ?? [];
                $info = $pickup->info_pickup ?? [];
                $isExpanded = $this->expandedId === $pickup->id;
                $phone = data_get($customer, 'phone', '');
                $address = data_get($customer, 'address', '');
                $scheduledAt = data_get($info, 'ngayhen');
                $companyName = data_get($customer, 'company') ?: data_get($customer, 'fullname', '-');
                $country = data_get($customer, 'country', '');
                $creatorName = $pickup->user?->fullname ?: $pickup->user?->username ?: '-';
                $pickupLat = data_get($customer, 'pickup_lat');
                $pickupLng = data_get($customer, 'pickup_lng');
                $hasPickupLocation = is_numeric($pickupLat) && is_numeric($pickupLng);
                $scheduledLabel = $scheduledAt ? \Carbon\Carbon::parse($scheduledAt)->format('d/m/Y H:i') : '';
                $weightLabel = number_format((float) $pickup->total_c_weight, 0, ',', '.').' Kg';
            @endphp
            <div class="bg-white rounded-xl border border-neutral-200 shadow-sm overflow-hidden" wire:key="pickup-{{ $pickup->id }}">
                <div class="px-4 pt-3 pb-2">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center {{ $pickup->status?->color() ?? 'bg-neutral-100 text-neutral-700' }}">
                            <span class="text-sm font-bold">{{ $this->pickups->firstItem() + $index }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between">
                                <p class="text-sm font-bold text-neutral-900 leading-tight">{{ $companyName }}</p>
                            </div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="inline-flex items-center gap-1 text-xs">
                                    <svg class="w-3 h-3 text-primary-500" fill="currentColor" viewBox="0 0 20 20"><rect width="20" height="14" rx="2" y="3"/></svg>
                                    <span class="font-semibold text-primary-700">{{ $pickup->ma_pickup }}</span>
                                </span>
                                @if($country)
                                    <span class="text-xs font-medium text-accent-700 bg-accent-50 px-1.5 py-0.5 rounded">{{ $country }}</span>
                                @endif
                            </div>
                            @if($address || $phone)
                                <div class="mt-1.5 flex items-start gap-1 text-xs text-neutral-600">
                                    <svg class="w-3 h-3 text-primary-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="leading-snug">
                                        {{ $address }}
                                        @if($phone)
                                            / <a href="tel:{{ $phone }}" class="text-primary-700 font-medium">{{ $phone }}</a>
                                            @if(data_get($customer, 'fullname'))
                                                ({{ data_get($customer, 'fullname') }})
                                            @endif
                                        @endif
                                    </span>
                                </div>
                            @endif
                            <div class="mt-2 flex items-center gap-3 text-xs text-neutral-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    Kiện hàng: <span class="font-semibold text-neutral-700">{{ $pickup->numb }}</span>
                                </span>
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                    Cân nặng: <span class="font-semibold text-neutral-700">{{ number_format((float) $pickup->total_c_weight, 0, ',', '.') }} Kg</span>
                                </span>
                                @if($pickup->status)
                                    <span class="ml-auto inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $pickup->status->color() }}">
                                        {{ $pickup->status->label() }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-2 flex items-center gap-4 text-xs text-neutral-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    {{ $creatorName }}
                                </span>
                                @if($scheduledAt)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ \Carbon\Carbon::parse($scheduledAt)->format('(d/m/Y) h:i A') }}
                                    </span>
                                @endif
                            </div>
                            @if($pickup->note)
                                <div class="mt-1.5 flex items-start gap-1 text-xs text-neutral-500">
                                    <svg class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span>Ghi chú: {{ $pickup->note }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($pickup->status === \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY)
                    <div class="border-t border-neutral-100 px-4 py-3 space-y-3 bg-neutral-50/50">
                        <div class="flex gap-2">
                            @if($phone)
                                <a href="tel:{{ $phone }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl bg-primary-50 text-primary-700 text-xs font-semibold active:bg-primary-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    Gọi điện
                                </a>
                            @endif
                            <button type="button"
                                    data-shipper-route-button
                                    data-pickup-id="{{ $pickup->id }}"
                                    data-pickup-code="{{ e($pickup->ma_pickup) }}"
                                    data-pickup-company="{{ e($companyName) }}"
                                    data-pickup-address="{{ e($address) }}"
                                    data-pickup-phone="{{ e($phone) }}"
                                    data-pickup-packages="{{ e((string) $pickup->numb) }}"
                                    data-pickup-weight="{{ e($weightLabel) }}"
                                    data-pickup-scheduled="{{ e($scheduledLabel) }}"
                                    data-pickup-lat="{{ $hasPickupLocation ? $pickupLat : '' }}"
                                    data-pickup-lng="{{ $hasPickupLocation ? $pickupLng : '' }}"
                                    @disabled(! $hasPickupLocation)
                                    class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl text-xs font-semibold
                                        {{ $hasPickupLocation
                                            ? 'bg-accent-50 text-accent-700 active:bg-accent-100'
                                            : 'bg-neutral-100 text-neutral-400 cursor-not-allowed' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $hasPickupLocation ? 'Chỉ đường' : 'Chưa có vị trí' }}
                            </button>
                        </div>
                    </div>
                @endif
                <div class="flex border-t border-neutral-100">
                @if($pickup->status === \App\Enums\PickupStatusEnum::MOI_TAO_PICKUP)
                    <button wire:click="updateStatus({{ $pickup->id }}, '{{ \App\Enums\PickupStatusEnum::DA_XAC_NHAN->value }}')"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 bg-primary-600 text-white text-[0] font-bold uppercase tracking-wide active:bg-primary-700">
                        <span class="text-sm">Tiếp nhận</span>
                    </button>
                @elseif($pickup->status === \App\Enums\PickupStatusEnum::DA_XAC_NHAN)
                    <button wire:click="updateStatus({{ $pickup->id }}, '{{ \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY->value }}')"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 bg-primary-700 text-white text-sm font-bold uppercase tracking-wide active:bg-primary-800">
                        <span class="text-sm">Bắt đầu lấy hàng</span>
                    </button>
                @elseif($pickup->status === \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY)
                    <button wire:click="updateStatus({{ $pickup->id }}, '{{ \App\Enums\PickupStatusEnum::PICKUP_DA_LAY->value }}')"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 bg-emerald-600 text-white text-sm font-bold uppercase tracking-wide active:bg-emerald-700">
                        <span class="text-sm">Đã nhận hàng</span>
                    </button>
                @elseif($pickup->status === \App\Enums\PickupStatusEnum::PICKUP_DA_LAY)
                    <div class="flex-1 py-2.5 bg-emerald-50 text-emerald-700 text-xs font-semibold text-center">
                        <span class="text-sm">✓ Đã nhận hàng</span>
                    </div>
                @endif
                @if(! $pickup->status->isFinal())
                    <button wire:click="cancelPickup({{ $pickup->id }})"
                            wire:confirm="Bạn có chắc muốn hủy phiếu pickup này?"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2 border-l border-red-100 bg-red-50 text-red-600 text-[0] font-bold uppercase tracking-wide active:bg-red-100">
                        <span class="text-sm">Hủy</span>
                    </button>
                @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-neutral-400">
                <svg class="w-16 h-16 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
                <p class="text-sm font-medium">Không có phiếu pickup nào</p>
            </div>
        @endforelse
        {{-- Pagination --}}
        @if($this->pickups->hasPages())
            <div class="py-3">
                {{ $this->pickups->links() }}
            </div>
        @endif
    </div>

    <div id="shipper-route-overlay"
         class="fixed inset-0 z-[100] hidden bg-neutral-950"
         aria-hidden="true">
        <div id="shipper-route-map" class="absolute inset-0 h-dvh min-h-screen w-full" style="height: 100dvh; min-height: 100vh;"></div>

        <button type="button"
                id="shipper-route-close"
                class="absolute left-3 top-3 z-20 flex h-11 w-11 items-center justify-center rounded-full bg-white text-neutral-800 shadow-lg active:bg-neutral-100"
                aria-label="Đóng bản đồ">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        <div class="absolute bottom-3 left-3 right-3 z-20 overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-4 py-3">
                <div class="min-w-0">
                    <p id="shipper-route-code" class="truncate text-xs font-bold text-primary-700">-</p>
                    <p id="shipper-route-company" class="truncate text-sm font-bold text-neutral-950">-</p>
                </div>
                <button type="button"
                        id="shipper-route-info-toggle"
                        class="shrink-0 rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-bold text-neutral-700 active:bg-neutral-50">
                    <span id="shipper-route-info-toggle-label">Ẩn</span>
                </button>
            </div>

            <div id="shipper-route-info-body" class="space-y-3 px-4 py-3">
                <button type="button"
                        id="shipper-route-location"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-sm font-bold text-white active:bg-primary-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657A8 8 0 105.64 5.64a8 8 0 0012.017 11.017z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11h.01"/>
                    </svg>
                    Tìm đường
                </button>

                <button type="button"
                        id="shipper-route-native"
                        class="hidden w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white active:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 18l6-6-6-6"/>
                    </svg>
                    Chỉ đường
                </button>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Quãng đường</p>
                        <p id="shipper-route-distance" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Thời gian</p>
                        <p id="shipper-route-duration" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Kiện hàng</p>
                        <p id="shipper-route-packages" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="text-neutral-500">Cân nặng</p>
                        <p id="shipper-route-weight" class="mt-0.5 font-bold text-neutral-950">-</p>
                    </div>
                </div>

                <div class="space-y-1.5 text-xs text-neutral-600">
                    <p class="font-semibold text-neutral-900">Địa chỉ</p>
                    <p id="shipper-route-address" class="leading-snug">-</p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 pt-1">
                        <span>SĐT: <span id="shipper-route-phone" class="font-semibold text-primary-700">-</span></span>
                        <span>Hẹn: <span id="shipper-route-scheduled" class="font-semibold text-neutral-800">-</span></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
