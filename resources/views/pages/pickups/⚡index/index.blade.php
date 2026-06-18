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

            @foreach(\App\Enums\PickupStatusEnum::cases() as $option)
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
                                @foreach(\App\Enums\PickupStatusEnum::cases() as $option)
                                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($this->canFilterPickupOps())
                            <div class="pickup-filter-field">
                                <label class="pickup-filter-label">OPS phụ trách</label>
                                <select wire:model.live="filterOpsId" data-livewire-model="filterOpsId" data-placeholder="Tất cả OPS" class="tomselectEml pickup-filter-tomselect">
                                    <option value="">Tất cả OPS</option>
                                    @foreach($this->pickupOpsUsers as $ops)
                                        <option value="{{ $ops->id }}">{{ $ops->fullname ?: $ops->username }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if($this->canFilterPickupShipper())
                            <div class="pickup-filter-field">
                                <label class="pickup-filter-label">Shipper phụ trách</label>
                                <select wire:model.live="filterShipperId" data-livewire-model="filterShipperId" data-placeholder="Tất cả shipper" class="tomselectEml pickup-filter-tomselect">
                                    <option value="">Tất cả shipper</option>
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

                @if($selectedPickup->status === \App\Enums\PickupStatusEnum::DA_HUY && auth()->user()?->hasAnyRole(['admin', 'manager']))
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

                @if($selectedPickup->images->isNotEmpty())
                    <div class="overflow-hidden rounded-lg border border-neutral-200">
                        <div class="border-b border-neutral-100 px-4 py-3">
                            <h3 class="text-sm font-semibold text-neutral-900">Ảnh bằng chứng lấy hàng</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 md:grid-cols-4">
                            @foreach($selectedPickup->images as $image)
                                <a href="{{ $image->url }}" target="_blank" rel="noopener"
                                   class="group relative block aspect-square overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50">
                                    <img src="{{ $image->url }}" alt="Ảnh lấy hàng"
                                         loading="lazy"
                                         class="h-full w-full object-cover transition group-hover:scale-105" />
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-4">
                    <div>
                        @if($this->canDeletePickup($selectedPickup))
                            <flux:button type="button" wire:click="deletePickup" wire:confirm="Xóa hẳn phiếu Pickup này? Hành động không thể hoàn tác." wire:loading.attr="disabled" variant="danger" icon="trash">Xóa phiếu</flux:button>
                        @endif
                    </div>

                    <div class="flex flex-wrap justify-end gap-2">
                        @if($this->canEditPickup($selectedPickup))
                            <flux:button type="button" wire:click="openEdit({{ $selectedPickup->id }})" wire:loading.attr="disabled" variant="outline">Sửa</flux:button>
                        @endif

                        @if($this->canManagePickupStatus($selectedPickup))
                            @if($selectedPickup->status === \App\Enums\PickupStatusEnum::MOI_TAO_PICKUP)
                                <flux:button type="button" wire:click="updateStatus('{{ \App\Enums\PickupStatusEnum::DA_XAC_NHAN->value }}')" wire:loading.attr="disabled" variant="primary">Đã xác nhận</flux:button>
                            @elseif($selectedPickup->status === \App\Enums\PickupStatusEnum::DA_XAC_NHAN)
                                <flux:button type="button" wire:click="updateStatus('{{ \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY->value }}')" wire:loading.attr="disabled" variant="primary">Đang lấy hàng</flux:button>
                            @elseif($selectedPickup->status === \App\Enums\PickupStatusEnum::PICKUP_DANG_LAY)
                                <flux:button type="button" wire:click="updateStatus('{{ \App\Enums\PickupStatusEnum::PICKUP_DA_LAY->value }}')" wire:loading.attr="disabled" variant="primary">Đã lấy hàng</flux:button>
                            @endif

                            <flux:button type="button" wire:click="updateStatus('{{ \App\Enums\PickupStatusEnum::DA_HUY->value }}')" wire:confirm="Hủy phiếu Pickup này?" wire:loading.attr="disabled" variant="danger">Hủy</flux:button>
                        @elseif($selectedPickup->status->isFinal())
                            <p class="self-center text-sm font-medium text-neutral-500">Phiếu đã khóa thao tác.</p>
                        @endif
                    </div>
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
                $canEditStatus = $this->canEditStatusForPickup($editingPickup);
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
                            <flux:label>{{ $editingPickup->status === \App\Enums\PickupStatusEnum::DA_HUY ? 'Chọn lại shipper' : 'Shipper phụ trách' }}</flux:label>
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

                    @if($canEditStatus)
                        <flux:field class="pickup-create-field">
                            <flux:label>Trạng thái phiếu</flux:label>
                            <select
                                wire:model="editForm.status"
                                data-livewire-model="editForm.status"
                                data-livewire-live="false"
                                data-placeholder="Chọn trạng thái"
                                class="tomselectEml pickup-create-select"
                                autocomplete="off"
                            >
                                @foreach(\App\Enums\PickupStatusEnum::cases() as $statusCase)
                                    <option value="{{ $statusCase->value }}">{{ $statusCase->label() }}</option>
                                @endforeach
                            </select>
                            @error('editForm.status') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    @endif

                    @if($canEditSender)
                        @if($canEditOps || $canEditShipper || $canEditStatus)
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
