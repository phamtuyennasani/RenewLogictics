<div id="congnodaily-index-page" class="space-y-4" data-routes='@json($this->routes())'>
<section class="space-y-4">
        <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm text-neutral-500">Công nợ / Đại lý</p>
                <h1 class="mt-1 text-2xl font-bold text-neutral-900">Quản lý công nợ đại lý</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:modal.trigger name="congnodaily-filter">
                    <flux:button type="button" variant="outline" icon="funnel" data-congnodaily-filter-open>
                        Bộ lọc
                    </flux:button>
                </flux:modal.trigger>

                <flux:button type="button" id="daily-debts-export" variant="outline" icon="arrow-down-tray">
                    Xuất Excel
                </flux:button>

                @if ($this->canManage())
                    <flux:button type="button" id="daily-debts-delete-selected" variant="danger" icon="trash">
                        Xóa
                    </flux:button>

                    <flux:button type="button" data-daily-create-open variant="primary" icon="plus">
                        Tạo công nợ
                    </flux:button>
                @endif
            </div>
        </div>
    </section>
    <div class="grid gap-3 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-neutral-700">Tổng công nợ vốn</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-daily-summary-money="total">{{ $this->money($this->summary['total']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500"><span data-daily-status-count="all">{{ $this->statusCounts['all'] }}</span> phiếu theo bộ lọc</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                    <flux:icon.document-currency-dollar class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-blue-100">
                <div class="h-full rounded-full bg-blue-500" data-daily-summary-bar="total" style="width: {{ $this->summary['total_percent'] }}%"></div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-emerald-500"></div>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-emerald-700">Đã thanh toán</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-daily-summary-money="paid">{{ $this->money($this->summary['paid']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">Đã trả cho đại lý</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <flux:icon.check-circle class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                <div class="h-full rounded-full bg-emerald-500" data-daily-summary-bar="paid" style="width: {{ $this->summary['paid_percent'] }}%"></div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-amber-500"></div>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Còn lại</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-daily-summary-money="remaining">{{ $this->money($this->summary['remaining']) }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">Còn phải trả đại lý</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <flux:icon.clock class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-amber-100">
                <div class="h-full rounded-full bg-amber-500" data-daily-summary-bar="remaining" style="width: {{ $this->summary['remaining_percent'] }}%"></div>
            </div>
        </div>

        <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
            <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-red-500"></div>
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 pl-2">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">Chưa thanh toán</p>
                    </div>
                    <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-daily-summary-count="unpaid">{{ $this->summary['unpaid_count'] }}</p>
                    <p class="mt-2 text-xs font-medium text-neutral-500">Phiếu công nợ chưa trả</p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                    <flux:icon.exclamation-triangle class="size-5" />
                </div>
            </div>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-red-100">
                <div class="h-full rounded-full bg-red-500" data-daily-summary-bar="unpaid" style="width: {{ $this->summary['unpaid_percent'] }}%"></div>
            </div>
        </div>
    </div>
    <section class="debt-status-nav">
        <div class="debt-status-nav-header">
            <div>
                <h3>Trạng thái công nợ đại lý</h3>
                <p>Lọc nhanh theo tiến trình thanh toán cho đại lý/NCC</p>
            </div>
        </div>
        <div class="debt-status-tabs">
            <button type="button" data-daily-debt-status-tab="" data-active="true" class="debt-status-tab debt-status-tab-all text-neutral-700">
                <span class="debt-status-dot"></span>
                <span class="debt-status-text">
                    <span class="debt-status-label">Tất cả</span>
                    <span class="debt-status-meta">Toàn bộ công nợ</span>
                </span>
                <span class="debt-status-count" data-daily-status-count="all">{{ $this->statusCounts['all'] }}</span>
            </button>

            @foreach ($this->activeStatuses() as $debtStatus)
                <button type="button"
                        data-daily-debt-status-tab="{{ $debtStatus->value }}"
                        data-active="false"
                        class="{{ $debtStatus->color() }} debt-status-tab">
                    <span class="debt-status-dot"></span>
                    <span class="debt-status-text">
                        <span class="debt-status-label">{{ $debtStatus->label() }}</span>
                        <span class="debt-status-meta">Nhấn để lọc</span>
                    </span>
                    <span class="debt-status-count" data-daily-status-count="{{ $debtStatus->value }}">{{ $this->statusCounts[$debtStatus->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>
    </section>
    <div class="space-y-3 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <flux:input type="search" id="daily-debts-search" icon="magnifying-glass" placeholder="Tìm mã công nợ, đại lý..." class="lg:max-w-md" />
            <div class="flex items-center gap-2 text-sm text-neutral-600">
                <span><span data-daily-selected-count>0</span> công nợ đã chọn</span>
                <span class="h-1 w-1 rounded-full bg-neutral-300"></span>
                <label class="inline-flex items-center gap-2">
                    <span>Hiển thị</span>
                    <select id="daily-debts-page-size" class="h-8 rounded-lg border border-neutral-200 bg-white px-2 text-sm font-medium text-neutral-700">
                        <option value="12">12</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="debt-table-frame debt-table-card overflow-hidden">
            <div class="overflow-x-auto">
                <table id="daily-debts-table" class="w-full min-w-[1180px] text-left text-sm">
                    <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                        <tr>
                            <th class="w-12 px-4 py-3 text-center">
                                <label class="relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                                    <input type="checkbox" id="daily-debts-check-all" class="peer sr-only">
                                    <span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span>
                                    <svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" />
                                    </svg>
                                </label>
                            </th>
                            <th class="px-3 py-3">Mã công nợ</th>
                            <th class="px-3 py-3">Trạng thái</th>
                            <th class="px-3 py-3">Đại lý</th>
                            <th class="px-3 py-3 text-right">Tổng cước vốn</th>
                            <th class="px-3 py-3 text-right">Đã trả</th>
                            <th class="px-3 py-3 text-right">Còn lại</th>
                            <th class="px-3 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 bg-white"></tbody>
                </table>
            </div>
        </div>
    </div>
    <flux:modal name="congnodaily-filter" class="w-full max-w-5xl !overflow-visible">
        <div class="debt-filter-panel">
            <div class="flex items-start gap-3 border-b border-neutral-100 pb-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                    <flux:icon.funnel class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Bộ lọc công nợ đại lý</flux:heading>
                    <flux:subheading>Lọc theo thời gian tạo, trạng thái và đại lý cần thanh toán.</flux:subheading>
                </div>
            </div>

            <section class="debt-filter-section">
                <div class="debt-filter-section-heading">
                    <div>
                        <h3>Thời gian</h3>
                        <p>Khoảng ngày tạo phiếu công nợ</p>
                    </div>
                    <div class="debt-filter-presets">
                        <button type="button" data-daily-debt-date-preset="today">Hôm nay</button>
                        <button type="button" data-daily-debt-date-preset="7">7 ngày</button>
                        <button type="button" data-daily-debt-date-preset="30">30 ngày</button>
                    </div>
                </div>
                <div class="debt-filter-grid debt-filter-grid-2">
                    <div class="debt-filter-field debt-filter-date-field">
                        <label class="debt-filter-label">Từ ngày</label>
                        <input type="text" value="{{ $fromDate }}" data-daily-debt-filter="fromDate" data-congnodaily-filter-date autocomplete="off" class="debt-filter-control">
                    </div>
                    <div class="debt-filter-field debt-filter-date-field">
                        <label class="debt-filter-label">Đến ngày</label>
                        <input type="text" value="{{ $toDate }}" data-daily-debt-filter="toDate" data-congnodaily-filter-date autocomplete="off" class="debt-filter-control">
                    </div>
                </div>
            </section>

            <section class="debt-filter-section">
                <div class="debt-filter-section-heading">
                    <div>
                        <h3>Đối tượng &amp; trạng thái</h3>
                        <p>Đại lý/NCC và tiến trình thanh toán</p>
                    </div>
                </div>
                <div class="debt-filter-grid debt-filter-grid-2">
                    <div class="debt-filter-field">
                        <label class="debt-filter-label">Đại lý</label>
                        <select
                            wire:key="filter-daily-{{ $dailyId ?: 'empty' }}"
                            class="tomselectEml debt-filter-tomselect-daily"
                            data-placeholder="Tất cả đại lý"
                            data-daily-debt-filter="dailyId"
                        >
                            <option value="">Tất cả đại lý</option>
                            @foreach ($this->dailies as $daily)
                                <option value="{{ $daily->id }}" @selected((int) $dailyId === (int) $daily->id)>{{ $daily->namevi ?: $daily->nameen }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="debt-filter-field">
                        <label class="debt-filter-label">Trạng thái công nợ</label>
                        <select
                            wire:key="filter-status-{{ $status ?: 'all' }}"
                            class="tomselectEml debt-filter-tomselect-daily"
                            data-placeholder="Tất cả trạng thái"
                            data-daily-debt-filter="status"
                        >
                            <option value="">Tất cả trạng thái</option>
                            @foreach ($this->activeStatuses() as $debtStatus)
                                <option value="{{ $debtStatus->value }}" @selected($status === $debtStatus->value)>{{ $debtStatus->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:button type="button" id="daily-debts-reset-filter" variant="ghost">Làm mới</flux:button>
                <flux:modal.close>
                    <flux:button type="button" id="daily-debts-apply-filter" variant="primary">Áp dụng</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
    @if ($this->canManage())
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" data-daily-create-modal hidden aria-hidden="true">
            <div class="fixed inset-0 bg-neutral-950/45 backdrop-blur-sm" data-daily-create-close></div>
            <div class="relative w-full max-w-4xl overflow-visible rounded-2xl bg-white shadow-2xl">
                <div class="border-b border-neutral-100 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                <flux:icon.plus class="size-5" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-neutral-950">Tạo công nợ đại lý</h2>
                                <p class="mt-1 text-sm text-neutral-500">Hệ thống gom các order theo đại lý chưa nằm trong công nợ đang mở.</p>
                            </div>
                        </div>
                        <button type="button" data-daily-create-close class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700">
                            <flux:icon.x-mark class="size-5" />
                        </button>
                    </div>
                </div>
                <div class="grid gap-4 p-6 md:grid-cols-2">
                    <div class="debt-create-field md:col-span-2">
                        <label class="debt-create-label">Đại lý</label>
                        <select
                            wire:key="create-daily-{{ $createDailyId ?: 'empty' }}"
                            class="tomselectEml debt-create-tomselect-daily"
                            data-placeholder="Chọn đại lý"
                            data-livewire-model="createDailyId"
                            data-livewire-live="false"
                        >
                            <option value="">Chọn đại lý</option>
                            @foreach ($this->dailies as $daily)
                                <option value="{{ $daily->id }}" @selected((int) $createDailyId === (int) $daily->id)>{{ $daily->namevi ?: $daily->nameen }}</option>
                            @endforeach
                        </select>
                        @error('createDailyId') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="debt-create-field debt-date-picker-field">
                        <label class="debt-create-label">Từ ngày</label>
                        <input type="text" value="{{ $createFromDate }}" data-congnodaily-create-date data-livewire-model="createFromDate" autocomplete="off" class="debt-create-control">
                        @error('createFromDate') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="debt-create-field debt-date-picker-field">
                        <label class="debt-create-label">Đến ngày</label>
                        <input type="text" value="{{ $createToDate }}" data-congnodaily-create-date data-livewire-model="createToDate" autocomplete="off" class="debt-create-control">
                        @error('createToDate') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <div class="debt-create-field">
                            <label class="debt-create-label">Ghi chú</label>
                            <textarea wire:model="note" rows="3" class="debt-create-control"></textarea>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-neutral-100 px-6 py-4">
                    <flux:button type="button" data-daily-create-close variant="ghost">Hủy</flux:button>
                    <flux:button type="button" wire:click="createDebt" wire:loading.attr="disabled" variant="primary" icon="plus">Tạo công nợ</flux:button>
                </div>
            </div>
        </div>
    @endif
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/datatables/dataTables.tailwindcss.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/dataTables.tailwindcss.js') }}"></script>
    <script>
        (() => {
            const today = new Date();
            const thirtyDaysAgo = new Date(today);
            thirtyDaysAgo.setDate(today.getDate() - 30);
            const defaultDates = { fromDate: formatDate(thirtyDaysAgo), toDate: formatDate(today) };
            const field = (name) => document.querySelector(`[data-daily-debt-filter="${name}"]`);
            let datePickerRetryCount = 0;

            function setFilterDate(name, value) {
                const input = field(name);
                if (!input) return;
                if (input._flatpickr) return input._flatpickr.setDate(value, false);
                input.value = value;
            }

            function ensureDefaultDateRange() {
                if (!field('fromDate')?.value) setFilterDate('fromDate', defaultDates.fromDate);
                if (!field('toDate')?.value) setFilterDate('toDate', defaultDates.toDate);
            }

            function initDatePickers() {
                ensureDefaultDateRange();
                if (!window.flatpickr) {
                    if (datePickerRetryCount < 20) setTimeout(initDatePickers, 100);
                    datePickerRetryCount++;
                    return;
                }
                document.querySelectorAll('input[data-congnodaily-filter-date], input[data-congnodaily-create-date]').forEach((input) => {
                    if (input._flatpickr) return;
                    const isFilterDate = input.hasAttribute('data-congnodaily-filter-date');
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
                        onReady: (_dates, _dateStr, instance) => instance.calendarContainer.classList.add(isFilterDate ? 'debt-filter-calendar-daily' : 'debt-create-calendar-daily'),
                        onChange: (_dates, dateStr) => {
                            const model = input.dataset.livewireModel;
                            const componentId = input.closest('[wire\\:id]')?.getAttribute('wire:id');
                            if (model && componentId && window.Livewire?.find) window.Livewire.find(componentId)?.set(model, dateStr || null, false);
                        },
                    });
                });
            }

            function initDailyDebtIndex() {
                const root = document.getElementById('congnodaily-index-page');
                const tableEl = document.getElementById('daily-debts-table');
                initDatePickers();
                if (root) window.TomSelectHelper?.init(root);
                bindDailyCreateModalControls(root);
                if (!root || !tableEl || !window.jQuery || !jQuery.fn.DataTable || tableEl.dataset.ready === 'true') return;
                tableEl.dataset.ready = 'true';

                const routes = JSON.parse(root.dataset.routes || '{}');
                const selected = new Set();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                const filters = () => ({
                    status: field('status')?.value || '',
                    fromDate: field('fromDate')?.value || '',
                    toDate: field('toDate')?.value || '',
                    dailyId: field('dailyId')?.value || '',
                });

                const table = jQuery(tableEl).DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ordering: false,
                    lengthChange: false,
                    pageLength: Number(document.getElementById('daily-debts-page-size')?.value || 25),
                    pagingType: 'simple_numbers',
                    scrollX: true,
                    ajax: {
                        url: routes.datatable,
                        data: (data) => Object.assign(data, filters(), {
                            search: { value: document.getElementById('daily-debts-search')?.value || '' },
                        }),
                    },
                    language: {
                        url: '{{ asset('assets/datatables/vi.json') }}',
                        processing: 'Đang tải dữ liệu',
                        emptyTable: emptyStateHtml(),
                        zeroRecords: emptyStateHtml('Không có công nợ đại lý phù hợp', 'Thử đổi từ khóa hoặc nới rộng bộ lọc.'),
                    },
                    columns: [
                        { data: 'check', orderable: false, searchable: false },
                        { data: 'debt_code', orderable: false, searchable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'daily_info', orderable: false, searchable: false },
                        { data: 'total_amount', orderable: false, searchable: false },
                        { data: 'paid_amount_html', orderable: false, searchable: false },
                        { data: 'remaining_amount_html', orderable: false, searchable: false },
                        { data: 'actions', orderable: false, searchable: false },
                    ],
                });

                const reload = () => {
                    selected.clear();
                    const checkAll = document.getElementById('daily-debts-check-all');
                    if (checkAll) checkAll.checked = false;
                    updateBulkState();
                    table.ajax.reload();
                };
                const updateBulkState = () => {
                    document.querySelectorAll('[data-daily-selected-count]').forEach((el) => el.textContent = selected.size);
                    const deleteButton = document.getElementById('daily-debts-delete-selected');
                    if (deleteButton) deleteButton.disabled = selected.size === 0;
                };

                const toggleEmptyTableChrome = () => {
                    const wrapper = tableEl.closest('.dt-container');
                    if (!wrapper) return;

                    const hasRows = table.page.info().recordsDisplay > 0;
                    wrapper.querySelectorAll('.dt-info, .dt-paging').forEach((el) => {
                        el.hidden = !hasRows;
                    });
                };

                jQuery(tableEl).on('xhr.dt', (_event, _settings, json) => {
                    updateCounts(json?.statusCounts || {});
                    updateSummary(json?.summary || {});
                });
                jQuery(tableEl).on('draw.dt', () => {
                    toggleEmptyTableChrome();

                    document.querySelectorAll('.daily-debt-check').forEach((checkbox) => {
                        checkbox.checked = selected.has(String(checkbox.value));
                        checkbox.addEventListener('change', () => {
                            checkbox.checked ? selected.add(String(checkbox.value)) : selected.delete(String(checkbox.value));
                            updateBulkState();
                        });
                    });
                });
                document.getElementById('daily-debts-check-all')?.addEventListener('change', (event) => {
                    document.querySelectorAll('.daily-debt-check').forEach((checkbox) => {
                        checkbox.checked = event.target.checked;
                        event.target.checked ? selected.add(String(checkbox.value)) : selected.delete(String(checkbox.value));
                    });
                    updateBulkState();
                });
                document.querySelectorAll('[data-daily-debt-status-tab]').forEach((button) => {
                    button.addEventListener('click', () => {
                        setFilterValue('status', button.dataset.dailyDebtStatusTab || '');
                        syncActiveStatus();
                        reload();
                    });
                });
                document.getElementById('daily-debts-apply-filter')?.addEventListener('click', () => {
                    syncActiveStatus();
                    reload();
                });
                document.getElementById('daily-debts-reset-filter')?.addEventListener('click', () => {
                    document.querySelectorAll('[data-daily-debt-filter], #daily-debts-search').forEach((el) => el.value = '');
                    setFilterValue('status', '');
                    setFilterValue('dailyId', '');
                    setFilterDate('fromDate', defaultDates.fromDate);
                    setFilterDate('toDate', defaultDates.toDate);
                    syncActiveStatus();
                    reload();
                });
                document.getElementById('daily-debts-search')?.addEventListener('input', debounce(reload, 350));
                document.getElementById('daily-debts-page-size')?.addEventListener('change', (event) => table.page.len(Number(event.target.value)).draw());
                document.querySelectorAll('[data-daily-debt-date-preset]').forEach((button) => button.addEventListener('click', () => setDatePreset(button.dataset.dailyDebtDatePreset || '30')));
                document.getElementById('daily-debts-delete-selected')?.addEventListener('click', () => {
                    if (selected.size === 0) return alert('Vui lòng chọn ít nhất một công nợ trước khi xóa.');
                    window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
                        title: 'Xác nhận xóa',
                        message: `Bạn có chắc chắn muốn xóa ${selected.size} công nợ đại lý đã chọn?`,
                        variant: 'danger',
                        confirmText: 'Xóa',
                        onConfirm: () => postJson(routes.deleteSelected, { ids: [...selected] }).then(reload),
                    }}));
                });
                document.getElementById('daily-debts-export')?.addEventListener('click', () => {
                    const params = new URLSearchParams(filters());
                    params.set('search[value]', document.getElementById('daily-debts-search')?.value || '');
                    window.location.href = `${routes.export}?${params.toString()}`;
                });

                function updateCounts(counts) {
                    document.querySelectorAll('[data-daily-status-count]').forEach((el) => {
                        const key = el.dataset.dailyStatusCount || '';
                        el.textContent = key === 'all' ? (counts.all ?? 0) : (counts[key] ?? 0);
                    });
                }
                function updateSummary(summary) {
                    document.querySelector('[data-daily-summary-money="total"]')?.replaceChildren(document.createTextNode(money(summary.total || 0)));
                    document.querySelector('[data-daily-summary-money="paid"]')?.replaceChildren(document.createTextNode(money(summary.paid || 0)));
                    document.querySelector('[data-daily-summary-money="remaining"]')?.replaceChildren(document.createTextNode(money(summary.remaining || 0)));
                    document.querySelector('[data-daily-summary-count="unpaid"]')?.replaceChildren(document.createTextNode(summary.unpaid_count || 0));
                    setBar('total', summary.total_percent || 0);
                    setBar('paid', summary.paid_percent || 0);
                    setBar('remaining', summary.remaining_percent || 0);
                    setBar('unpaid', summary.unpaid_percent || 0);
                }
                function setBar(name, value) {
                    const bar = document.querySelector(`[data-daily-summary-bar="${name}"]`);
                    if (bar) bar.style.width = `${Number(value || 0)}%`;
                }
                function syncActiveStatus() {
                    const currentStatus = field('status')?.value || '';
                    document.querySelectorAll('[data-daily-debt-status-tab]').forEach((button) => button.dataset.active = button.dataset.dailyDebtStatusTab === currentStatus ? 'true' : 'false');
                }
                function setFilterValue(name, value) {
                    const input = field(name);
                    if (!input) return;
                    input.value = value;
                    input.tomselect?.setValue(value, true);
                }
                function setFilterDate(name, value) {
                    const input = field(name);
                    if (!input) return;
                    if (input._flatpickr) return input._flatpickr.setDate(value, false);
                    input.value = value;
                }
                function setDatePreset(preset) {
                    const endDate = new Date();
                    const startDate = new Date(endDate);
                    if (preset === 'today') {
                        setFilterDate('fromDate', formatDate(endDate));
                        setFilterDate('toDate', formatDate(endDate));
                        return;
                    }
                    startDate.setDate(endDate.getDate() - Number(preset || 30));
                    setFilterDate('fromDate', formatDate(startDate));
                    setFilterDate('toDate', formatDate(endDate));
                }
                function ensureDefaultDateRange() {
                    if (!field('fromDate')?.value) setFilterDate('fromDate', defaultDates.fromDate);
                    if (!field('toDate')?.value) setFilterDate('toDate', defaultDates.toDate);
                }
                function postJson(url, payload) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify(payload),
                    }).then((response) => {
                        if (!response.ok) throw new Error('Request failed');
                        return response.json();
                    });
                }
                function emptyStateHtml(title = 'Chưa có công nợ đại lý nào', text = 'Không có dữ liệu phù hợp với bộ lọc hiện tại.') {
                    return `<div class="debt-empty-state"><div class="debt-empty-state-icon"><svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632A2.25 2.25 0 0117.379 20.25H6.621a2.25 2.25 0 01-2.246-2.118L3.75 7.5M9 11.25h6M9 15h6M8.25 7.5V5.25A2.25 2.25 0 0110.5 3h3A2.25 2.25 0 0115.75 5.25V7.5" /></svg></div><div><p class="font-bold text-neutral-900">${title}</p><p class="mt-1 text-sm text-neutral-500">${text}</p></div></div>`;
                }
                function debounce(fn, wait) {
                    let timer;
                    return (...args) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => fn(...args), wait);
                    };
                }
                function money(value) {
                    return `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))} đ`;
                }
                function bindDailyCreateModalControls(root) {
                    if (!root || root.dataset.createModalBound === 'true') return;
                    root.dataset.createModalBound = 'true';

                    root.addEventListener('click', (event) => {
                        const openButton = event.target.closest?.('[data-daily-create-open]');
                        if (openButton) {
                            event.preventDefault();
                            openDailyCreateModal(root);
                            return;
                        }

                        const closeButton = event.target.closest?.('[data-daily-create-close]');
                        if (!closeButton) return;

                        event.preventDefault();
                        closeDailyCreateModal(root);
                    });
                }

                function openDailyCreateModal(root) {
                    const modal = root.querySelector('[data-daily-create-modal]');
                    if (!modal) return;

                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');

                    requestAnimationFrame(() => {
                        window.TomSelectHelper?.init(modal);
                        initDatePickers();
                    });
                }

                function closeDailyCreateModal(root) {
                    const modal = root.querySelector('[data-daily-create-modal]');
                    if (!modal) return;

                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                }

                updateBulkState();
                syncActiveStatus();
            }

            function formatDate(date) {
                return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
            }
            document.addEventListener('DOMContentLoaded', initDailyDebtIndex);
            document.addEventListener('livewire:navigated', initDailyDebtIndex);
            document.addEventListener('livewire:initialized', () => Livewire.hook('morph.updated', () => setTimeout(initDailyDebtIndex, 0)));
            setTimeout(initDailyDebtIndex, 0);
        })();
    </script>
@endpush
