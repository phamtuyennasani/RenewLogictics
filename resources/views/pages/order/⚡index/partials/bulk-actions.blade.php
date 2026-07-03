<section class="rounded-lg border border-neutral-200 bg-white shadow-sm overflow-hidden">
    @php
        $statusByValue = collect($statusOptions)->keyBy('value');
        $quickActions = [
            ['label' => 'Hủy', 'status' => 'huy', 'icon' => 'x-circle', 'capability' => 'canCancel'],
            ['label' => 'Nhận hàng', 'status' => 'da_nhan_hang', 'icon' => 'archive-box-arrow-down', 'capability' => 'canReceive'],
            ['label' => 'Duyệt Xuất', 'status' => 'duyet_xuat_hang', 'icon' => 'clipboard-document-check', 'capability' => 'canApproveExport'],
            ['label' => 'Xuất hàng', 'status' => 'dang_phat_hang', 'icon' => 'truck', 'capability' => 'canShip'],
        ];
        $quickActions = collect($quickActions)
            ->filter(fn (array $action) => $capabilities[$action['capability']] ?? false)
            ->values();
        // +2: nút PDF hàng loạt (mọi role thấy danh sách đều in được đơn trong phạm vi mình).
        $bulkActionCount = $quickActions->count() + (!empty($capabilities['canDeleteCancelled']) ? 1 : 0) + 2;
    @endphp

    @if ($bulkActionCount > 0)
        <div class="flex flex-col gap-3 border-b border-neutral-100 px-4 py-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                    <flux:icon.check-circle class="size-5" />
                </div>
                <div>
                    <div class="text-sm font-semibold text-neutral-900">
                        <span data-selected-count>0</span> order đã chọn
                    </div>
                    <div class="text-xs text-neutral-500">Chọn đơn hàng trong bảng để sử dụng thao tác hàng loạt.</div>
                </div>
            </div>

            <div class="grid gap-2 lg:min-w-[560px]" style="grid-template-columns: repeat({{ $bulkActionCount }}, minmax(0, 1fr));">
                @if (!empty($capabilities['canDeleteCancelled']))
                    <button
                        type="button"
                        data-delete-cancelled
                        disabled
                        class="inline-flex min-h-9 min-w-0 items-center justify-center gap-1.5 rounded-lg bg-red-600 px-3 py-2 text-center text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        <flux:icon.trash class="size-4 shrink-0" />
                        <span class="truncate">Xóa</span>
                    </button>
                @endif

                @foreach ($quickActions as $action)
                    @php($status = $statusByValue->get($action['status']))
                    <button
                        type="button"
                        data-bulk-status="{{ $action['status'] }}"
                        disabled
                        class="{{ $status['textClass'] ?? 'text-neutral-700' }} {{ isset($status['bgClass']) ? 'hover:'.$status['bgClass'] : 'hover:bg-neutral-100' }} inline-flex min-h-9 min-w-0 items-center justify-center gap-1.5 rounded-lg border border-neutral-200 bg-transparent px-3 py-2 text-center text-sm font-medium transition hover:border-transparent disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:border-neutral-200 disabled:hover:bg-transparent"
                    >
                        <flux:icon :icon="$action['icon']" class="size-4 shrink-0" />
                        <span class="truncate">{{ $action['label'] }}</span>
                    </button>
                @endforeach

                {{-- PDF hàng loạt (tính năng mới) — mở 1 file PDF gộp các đơn đã chọn --}}
                <button
                    type="button"
                    data-bulk-pdf="label"
                    disabled
                    class="inline-flex min-h-9 min-w-0 items-center justify-center gap-1.5 rounded-lg border border-fuchsia-200 bg-transparent px-3 py-2 text-center text-sm font-medium text-fuchsia-700 transition hover:border-transparent hover:bg-fuchsia-50 disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:border-fuchsia-200 disabled:hover:bg-transparent"
                >
                    <flux:icon.document-arrow-down class="size-4 shrink-0" />
                    <span class="truncate">PDF Tem</span>
                </button>
                <button
                    type="button"
                    data-bulk-pdf="bill"
                    disabled
                    class="inline-flex min-h-9 min-w-0 items-center justify-center gap-1.5 rounded-lg border border-cyan-200 bg-transparent px-3 py-2 text-center text-sm font-medium text-cyan-700 transition hover:border-transparent hover:bg-cyan-50 disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:border-cyan-200 disabled:hover:bg-transparent"
                >
                    <flux:icon.document-arrow-down class="size-4 shrink-0" />
                    <span class="truncate">PDF Bill</span>
                </button>
            </div>
        </div>
    @endif
    <div class="order-status-nav">
        <div class="order-status-nav-header">
            <div>
                <h3>Trạng thái đơn hàng</h3>
                <p>Lọc nhanh theo tiến trình xử lý</p>
            </div>
        </div>
        <div class="order-status-tabs">
            <button
                type="button"
                data-status-tab=""
                data-active="true"
                class="order-status-tab order-status-tab-all"
            >
                <span class="order-status-dot"></span>
                <span class="order-status-text">
                    <span class="order-status-label">Tất cả</span>
                    <span class="order-status-meta">Toàn bộ order</span>
                </span>
                <span class="order-status-count" data-status-count="all">0</span>
            </button>
            @foreach ($mainStatusOptions as $status)
                <button
                    type="button"
                    data-status-tab="{{ $status['value'] }}"
                    data-active="false"
                    class="{{ $status['textClass'] }} {{ 'hover:'.$status['bgClass'] }} {{ 'data-[active=true]:'.$status['bgClass'] }} order-status-tab"
                >
                    <span class="order-status-dot"></span>
                    <span class="order-status-text">
                        <span class="order-status-label">{{ $status['label'] }}</span>
                        <span class="order-status-meta">Nhấn để lọc</span>
                    </span>
                    <span class="order-status-count" data-status-count="{{ $status['value'] }}">0</span>
                </button>
            @endforeach

            <button
                type="button"
                data-special-status-toggle
                aria-controls="order-special-status-panel"
                aria-expanded="false"
                class="order-status-tab order-status-special-toggle"
            >
                <span class="order-status-dot"></span>
                <span class="order-status-text">
                    <span class="order-status-label">Trạng thái đặc biệt</span>
                    <span class="order-status-meta">Bấm để mở bộ lọc phụ</span>
                </span>
                <span class="order-status-count" data-special-status-count>0</span>
            </button>
        </div>

        <div id="order-special-status-panel" class="order-special-status-tabs" data-special-status-panel hidden>
            @foreach ($specialStatusOptions as $status)
                <button
                    type="button"
                    data-status-tab="{{ $status['value'] }}"
                    data-special-status-tab
                    data-active="false"
                    class="{{ $status['textClass'] }} {{ 'hover:'.$status['bgClass'] }} {{ 'data-[active=true]:'.$status['bgClass'] }} order-status-tab"
                >
                    <span class="order-status-dot"></span>
                    <span class="order-status-text">
                        <span class="order-status-label">{{ $status['label'] }}</span>
                        <span class="order-status-meta">Nhấn để lọc</span>
                    </span>
                    <span class="order-status-count" data-status-count="{{ $status['value'] }}">0</span>
                </button>
            @endforeach
        </div>
    </div>
</section>
