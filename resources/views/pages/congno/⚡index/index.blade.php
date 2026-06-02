<div
    id="congno-index-page"
    class="space-y-4"
    data-component-cloak
    data-ready="false"
    data-routes='@json($this->routes())'
>
    <div class="component-cloak-content space-y-4">
        <section class="space-y-4">
            <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm text-neutral-500">Công nợ / Khách hàng</p>
                    <h1 class="mt-1 text-2xl font-bold text-neutral-900">Quản lý công nợ khách hàng</h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:modal.trigger name="congno-filter">
                        <flux:button type="button" variant="outline" icon="funnel" data-congno-filter-open>
                            Bộ lọc
                        </flux:button>
                    </flux:modal.trigger>

                        <flux:button type="button" id="debts-export" variant="outline" icon="arrow-down-tray">
                            Xuất Excel
                        </flux:button>

                    @if ($this->canDeleteDebt())
                        <flux:button type="button" id="debts-delete-selected" variant="danger" icon="trash">
                            Xóa
                        </flux:button>
                    @endif

                    @if ($this->canCreateDebt())
                        <flux:button type="button" data-congno-create-open variant="primary" icon="plus">
                            Tạo công nợ
                        </flux:button>
                    @endif
                </div>
            </div>
        </section>
        <div class="grid gap-3 lg:grid-cols-3">
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 pl-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-neutral-700">Tổng công nợ</p>
                        </div>
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="total">{{ $this->money($this->summary['total']) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500"><span data-status-count="all">{{ $this->statusCounts['all'] }}</span> phiếu theo bộ lọc</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <flux:icon.document-currency-dollar class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-blue-100">
                    <div class="h-full rounded-full bg-blue-500" data-summary-bar="total" style="width: {{ $this->summary['total'] > 0 ? 100 : 0 }}%"></div>
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
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="paid">{{ $this->money($this->summary['paid']) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500">Tiền khách đã thanh toán</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <flux:icon.check-circle class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                    <div class="h-full rounded-full bg-emerald-500" data-summary-bar="paid" style="width: {{ $this->summary['paid_percent'] }}%"></div>
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
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="remaining">{{ $this->money($this->summary['remaining']) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500">Dư nợ đang cần thu</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <flux:icon.clock class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-amber-100">
                    <div class="h-full rounded-full bg-amber-500" data-summary-bar="remaining" style="width: {{ $this->summary['remaining_percent'] }}%"></div>
                </div>
            </div>

        </div>
        <section class="debt-status-nav">
            <div class="debt-status-nav-header">
                <div>
                    <h3>Trạng thái công nợ</h3>
                    <p>Lọc nhanh theo tiến trình thu tiền khách hàng</p>
                </div>
            </div>
            <div class="debt-status-tabs">
                <button type="button" data-debt-status-tab="" data-active="true" class="debt-status-tab debt-status-tab-all text-neutral-700">
                    <span class="debt-status-dot"></span>
                    <span class="debt-status-text">
                        <span class="debt-status-label">Tất cả</span>
                        <span class="debt-status-meta">Toàn bộ công nợ</span>
                    </span>
                    <span class="debt-status-count" data-status-count="all">{{ $this->statusCounts['all'] }}</span>
                </button>

                @foreach ($this->debtStatuses as $debtStatus)
                    <button type="button"
                            data-debt-status-tab="{{ $debtStatus->value }}"
                            data-active="false"
                            class="{{ $debtStatus->color() }} debt-status-tab">
                        <span class="debt-status-dot"></span>
                        <span class="debt-status-text">
                            <span class="debt-status-label">{{ $debtStatus->label() }}</span>
                            <span class="debt-status-meta">Nhấn để lọc</span>
                        </span>
                        <span class="debt-status-count" data-status-count="{{ $debtStatus->value }}">{{ $this->statusCounts[$debtStatus->value] ?? 0 }}</span>
                    </button>
                @endforeach
            </div>
        </section>
        <div class="space-y-3 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <flux:input type="search" id="debts-search" icon="magnifying-glass" placeholder="Tìm mã công nợ, sale, khách hàng..." class="lg:max-w-md" />
                <div class="flex items-center gap-2 text-sm text-neutral-600">
                    <span><span data-selected-count>0</span> công nợ đã chọn</span>
                    <span class="h-1 w-1 rounded-full bg-neutral-300"></span>
                    <label class="inline-flex items-center gap-2">
                        <span>Hiển thị</span>
                        <select id="debts-page-size" class="h-8 rounded-lg border border-neutral-200 bg-white px-2 text-sm font-medium text-neutral-700">
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
                    <table id="debts-table" class="w-full min-w-[1280px] text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="w-12 px-4 py-3 text-center">
                                    <label class="relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                                        <input type="checkbox" id="debts-check-all" class="peer sr-only">
                                        <span class="flex h-[18px] w-[18px] items-center justify-center rounded-md border border-neutral-300 bg-white transition peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-hover:border-primary-400"></span>
                                        <svg class="pointer-events-none absolute hidden h-3 w-3 text-white peer-checked:block" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.5l5 5 10-11" />
                                        </svg>
                                    </label>
                                </th>
                                <th class="px-3 py-3">Mã công nợ</th>
                                <th class="px-3 py-3">Số hóa đơn</th>
                                <th class="px-3 py-3">Trạng thái</th>
                                <th class="px-3 py-3">Khách hàng</th>
                                <th class="px-3 py-3">Sale phụ trách</th>
                                <th class="px-3 py-3 text-center">Tổng cước</th>
                                <th class="px-3 py-3 text-center">Đã thu</th>
                                <th class="px-3 py-3 text-center">Còn lại</th>
                                <th class="px-3 py-3">Hạn thanh toán</th>
                                <th class="px-3 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <flux:modal name="congno-filter" class="w-full max-w-5xl !overflow-visible">
            <div class="debt-filter-panel">
                <div class="flex items-start gap-3 border-b border-neutral-100 pb-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                        <flux:icon.funnel class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">Bộ lọc công nợ</flux:heading>
                        <flux:subheading>Lọc theo thời gian tạo, trạng thái, sale phụ trách và khách hàng / CTV.</flux:subheading>
                    </div>
                </div>

                <section class="debt-filter-section">
                    <div class="debt-filter-section-heading">
                        <div>
                            <h3>Thời gian</h3>
                            <p>Khoảng ngày tạo phiếu công nợ</p>
                        </div>
                        <div class="debt-filter-presets">
                            <button type="button" data-debt-date-preset="today">Hôm nay</button>
                            <button type="button" data-debt-date-preset="7">7 ngày</button>
                            <button type="button" data-debt-date-preset="30">30 ngày</button>
                        </div>
                    </div>
                    <div class="debt-filter-grid debt-filter-grid-2">
                        <div class="debt-filter-field debt-filter-date-field">
                            <label class="debt-filter-label">Từ ngày</label>
                            <input type="text" value="{{ $fromDate }}" data-debt-filter="fromDate" data-congno-filter-date autocomplete="off" class="debt-filter-control">
                        </div>
                        <div class="debt-filter-field debt-filter-date-field">
                            <label class="debt-filter-label">Đến ngày</label>
                            <input type="text" value="{{ $toDate }}" data-debt-filter="toDate" data-congno-filter-date autocomplete="off" class="debt-filter-control">
                        </div>
                    </div>
                </section>

                <section class="debt-filter-section">
                    <div class="debt-filter-section-heading">
                        <div>
                            <h3>{{ $isSaleUser ? 'Khách hàng / CTV' : 'Đối tượng' }}</h3>
                            <p>{{ $isSaleUser ? 'Lọc theo khách hàng / CTV của bạn' : 'Nhân sự phụ trách và khách hàng cần thu' }}</p>
                        </div>
                    </div>
                    <div class="debt-filter-grid{{ !$isSaleUser ? ' debt-filter-grid-2' : '' }}">
                        @if (!$isSaleUser)
                        <div class="debt-filter-field">
                            <label class="debt-filter-label">Sale phụ trách</label>
                            <select
                                wire:key="filter-sale-{{ $saleId ?: 'empty' }}"
                                class="tomselectEml debt-filter-tomselect"
                                data-placeholder="Tất cả sale"
                                data-debt-filter="saleId"
                            >
                                <option value="">Tất cả sale</option>
                                @foreach ($this->sales as $sale)
                                    <option value="{{ $sale->id }}" @selected((int) $saleId === (int) $sale->id)>{{ $sale->fullname ?: $sale->username }}{{ $sale->code ? ' - '.$sale->code : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="debt-filter-field">
                            <label class="debt-filter-label">Khách hàng / CTV</label>
                            <select
                                wire:key="filter-customer-{{ $saleId ?: 'all' }}-{{ $customerId ?: 'empty' }}"
                                class="tomselectEml debt-filter-tomselect"
                                data-placeholder="Tất cả khách hàng"
                                data-debt-filter="customerId"
                            >
                                <option value="">Tất cả khách hàng</option>
                                @foreach ($this->customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((int) $customerId === (int) $customer->id)>{{ $customer->fullname ?: $customer->username }}{{ $customer->code ? ' - '.$customer->code : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <section class="debt-filter-section">
                    <div class="debt-filter-section-heading">
                        <div>
                            <h3>Trạng thái</h3>
                            <p>Tiến trình chốt cước và thanh toán</p>
                        </div>
                    </div>
                    <div class="debt-filter-grid debt-filter-grid-3">
                        <div class="debt-filter-field">
                            <label class="debt-filter-label">Trạng thái công nợ</label>
                            <select
                                wire:key="filter-status-{{ $status ?: 'all' }}"
                                class="tomselectEml debt-filter-tomselect"
                                data-placeholder="Tất cả trạng thái"
                                data-debt-filter="status"
                            >
                                <option value="">Tất cả trạng thái</option>
                                @foreach ($this->debtStatuses as $debtStatus)
                                    <option value="{{ $debtStatus->value }}" @selected($status === $debtStatus->value)>{{ $debtStatus->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <flux:button type="button" id="debts-reset-filter" variant="ghost">Làm mới</flux:button>
                    <flux:modal.close>
                        <flux:button type="button" id="debts-apply-filter" variant="primary">Áp dụng</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
        @if ($this->canManage())
            <div class="fixed inset-0 z-[60] flex items-center justify-center p-4" data-congno-create-modal hidden aria-hidden="true">
                <div class="fixed inset-0 bg-neutral-950/45 backdrop-blur-sm" data-congno-create-close></div>
                <div class="relative w-full max-w-4xl overflow-visible rounded-2xl bg-white shadow-2xl">
                    <div class="border-b border-neutral-100 px-6 py-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                                    <flux:icon.plus class="size-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-neutral-950">Tạo công nợ khách hàng</h2>
                                    <p class="mt-1 text-sm text-neutral-500">Hệ thống gom các order chưa nằm trong công nợ đang mở.</p>
                                </div>
                            </div>
                            <button type="button" data-congno-create-close class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700">
                                <flux:icon.x-mark class="size-5" />
                            </button>
                        </div>
                    </div>
                    <div class="grid gap-4 p-6 md:grid-cols-2">
                        @if (!$isSaleUser)
                        <div class="debt-create-field">
                            <label class="debt-create-label">Sale phụ trách</label>
                            <select
                                wire:key="create-sale-{{ $createSaleId ?: 'empty' }}"
                                class="tomselectEml debt-create-tomselect"
                                data-placeholder="Chọn sale"
                                data-debt-create-field="createSaleId"
                            >
                                <option value="">Chọn sale</option>
                                @foreach ($this->sales as $sale)
                                    <option value="{{ $sale->id }}" @selected((int) $createSaleId === (int) $sale->id)>{{ $sale->fullname ?: $sale->username }}{{ $sale->code ? ' - '.$sale->code : '' }}</option>
                                @endforeach
                            </select>
                            @error('createSaleId') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <input type="hidden" data-debt-create-field="createSaleId" value="{{ $createSaleId }}">
                    @endif

                        <div class="debt-create-field {{ $isSaleUser ? 'md:col-span-2' : '' }}">
                            <label class="debt-create-label">Khách hàng / CTV</label>
                            <select
                                wire:key="create-customer-{{ $createSaleId ?: 'all' }}-{{ $createCustomerId ?: 'empty' }}"
                                class="tomselectEml debt-create-tomselect"
                                data-placeholder="Chọn khách hàng"
                                data-debt-create-field="createCustomerId"
                            >
                                <option value="">Chọn khách hàng</option>
                                @foreach ($this->customers as $customer)
                                    <option value="{{ $customer->id }}" @selected((int) $createCustomerId === (int) $customer->id)>{{ $customer->fullname ?: $customer->username }}{{ $customer->code ? ' - '.$customer->code : '' }}</option>
                                @endforeach
                            </select>
                            @error('createCustomerId') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="debt-create-field debt-date-picker-field">
                            <label class="debt-create-label">Từ ngày</label>
                            <input type="text" value="{{ $createFromDate }}" data-congno-create-date data-debt-create-field="createFromDate" autocomplete="off" class="debt-create-control">
                            @error('createFromDate') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="debt-create-field debt-date-picker-field">
                            <label class="debt-create-label">Đến ngày</label>
                            <input type="text" value="{{ $createToDate }}" data-congno-create-date data-debt-create-field="createToDate" autocomplete="off" class="debt-create-control">
                            @error('createToDate') <p class="text-xs font-medium text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="md:col-span-2">
                            <div class="debt-create-field">
                                <label class="debt-create-label">Ghi chú</label>
                                <textarea rows="3" data-debt-create-field="note" class="debt-create-control">{{ $note }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-neutral-100 px-6 py-4">
                        <flux:button type="button" data-congno-create-close variant="ghost">Hủy</flux:button>
                        <flux:button type="button" data-congno-create-submit variant="primary" icon="plus">Tạo công nợ</flux:button>
                    </div>
                </div>
            </div>
        @endif
    </div>

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
            const defaultDates = {
                fromDate: formatDate(thirtyDaysAgo),
                toDate: formatDate(today),
            };
            let datePickerRetryCount = 0;

            const field = (name) => document.querySelector(`[data-debt-filter="${name}"]`);

            const markReady = () => {
                const root = document.getElementById('congno-index-page');
                if (!root) return;
                requestAnimationFrame(() => requestAnimationFrame(() => root.dataset.ready = 'true'));
            };

            function setFilterDate(name, value) {
                const input = field(name);
                if (!input) return;

                if (input._flatpickr) {
                    input._flatpickr.setDate(value, false);
                    return;
                }

                input.value = value;
            }

            function ensureDefaultDateRange() {
                if (!field('fromDate')?.value) setFilterDate('fromDate', defaultDates.fromDate);
                if (!field('toDate')?.value) setFilterDate('toDate', defaultDates.toDate);
            }

            const initDatePickers = () => {
                ensureDefaultDateRange();

                if (!window.flatpickr) {
                    if (datePickerRetryCount < 20) {
                        datePickerRetryCount++;
                        setTimeout(initDatePickers, 100);
                    }
                    return;
                }

                document.querySelectorAll('input[data-congno-filter-date], input[data-congno-create-date]').forEach((input) => {
                    if (input._flatpickr) return;
                    const isFilterDate = input.hasAttribute('data-congno-filter-date');

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
                            instance.calendarContainer.classList.add(isFilterDate ? 'debt-filter-calendar' : 'debt-create-calendar');
                        },
                        onChange: (_selectedDates, dateStr) => {
                            const model = input.dataset.livewireModel;
                            if (!model || !window.Livewire?.find) return;

                            const componentId = input.closest('[wire\\:id]')?.getAttribute('wire:id');
                            if (componentId) window.Livewire.find(componentId)?.set(model, dateStr || null, false);
                        },
                    });
                });
            };

                const initCongNoIndex = () => {
                const root = document.getElementById('congno-index-page');
                const tableEl = document.getElementById('debts-table');
                const routes = root ? JSON.parse(root.dataset.routes || '{}') : {};
                initDatePickers();
                if (root) window.TomSelectHelper?.init(root);
                bindCreateModalControls(root);

                if (!root || !tableEl || !window.jQuery || !jQuery.fn.DataTable || tableEl.dataset.ready === 'true') {
                    markReady();
                    return;
                }

                tableEl.dataset.ready = 'true';
                jQuery.extend(true, jQuery.fn.dataTable.ext.classes, {
                    table: 'dataTable w-full text-left text-sm align-middle',
                    paging: {
                        active: 'border-primary-600 bg-primary-600 text-white',
                        notActive: 'border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50',
                        button: 'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2.5 text-sm font-medium leading-none no-underline transition',
                        first: '',
                        last: '',
                        enabled: '',
                        notEnabled: 'pointer-events-none border-transparent bg-transparent text-neutral-300',
                    },
                });

                const selected = new Set();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                let customerRequestId = 0;

                const filters = () => ({
                    status: field('status')?.value || '',
                    fromDate: field('fromDate')?.value || '',
                    toDate: field('toDate')?.value || '',
                    saleId: field('saleId')?.value || '',
                    customerId: field('customerId')?.value || '',
                });

                const table = jQuery(tableEl).DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ordering: false,
                    lengthChange: false,
                    pageLength: Number(document.getElementById('debts-page-size')?.value || 25),
                    pagingType: 'simple_numbers',
                    scrollX: true,
                    ajax: {
                        url: routes.datatable,
                        data: (data) => Object.assign(data, filters(), {
                            search: { value: document.getElementById('debts-search')?.value || '' },
                        }),
                    },
                    order: [],
                    language: {
                        url: '{{ asset('assets/datatables/vi.json') }}',
                        processing: 'Đang tải dữ liệu',
                        emptyTable: emptyStateHtml(),
                        zeroRecords: emptyStateHtml('Không có công nợ phù hợp', 'Thử đổi từ khóa, nới rộng bộ lọc hoặc tạo công nợ mới từ các order chưa chốt.'),
                    },
                    columns: [
                        { data: 'check', orderable: false, searchable: false },
                        { data: 'debt_code', orderable: false, searchable: false },
                        { data: 'einvoice_info', orderable: false, searchable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'customer_info', orderable: false, searchable: false },
                        { data: 'sale_info', orderable: false, searchable: false },
                        { data: 'total_amount', orderable: false, searchable: false },
                        { data: 'paid_amount_html', orderable: false, searchable: false },
                        { data: 'remaining_amount_html', orderable: false, searchable: false },
                        { data: 'due_date', orderable: false, searchable: false },
                        { data: 'actions', orderable: false, searchable: false },
                    ],
                    columnDefs: [
                        { targets: 0, width: '40px' },
                        { targets: 1, width: '100px' },
                        { targets: 2, width: '100px' },
                        { targets: 3, width: '200px' },
                        { targets: 4, width: '300px' },
                        { targets: 5, width: '200px' },
                        { targets: 6, width: '150px' },
                        { targets: 7, width: '150px' },
                        { targets: 8, width: '150px' },
                        { targets: 9, width: '100px' },
                        { targets: 10, width: '100px' },
                    ],
                    initComplete: markReady,
                });

                const reload = () => {
                    selected.clear();
                    const checkAll = document.getElementById('debts-check-all');
                    if (checkAll) checkAll.checked = false;
                    updateBulkState();
                    table.ajax.reload();
                };

                const updateBulkState = () => {
                    document.querySelectorAll('[data-selected-count]').forEach((el) => el.textContent = selected.size);
                    const deleteButton = document.getElementById('debts-delete-selected');
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

                    document.querySelectorAll('.debt-check').forEach((checkbox) => {
                        checkbox.checked = selected.has(String(checkbox.value));
                        checkbox.addEventListener('change', () => {
                            checkbox.checked ? selected.add(String(checkbox.value)) : selected.delete(String(checkbox.value));
                            updateBulkState();
                        });
                    });
                });

                document.getElementById('debts-check-all')?.addEventListener('change', (event) => {
                    document.querySelectorAll('.debt-check').forEach((checkbox) => {
                        checkbox.checked = event.target.checked;
                        event.target.checked ? selected.add(String(checkbox.value)) : selected.delete(String(checkbox.value));
                    });
                    updateBulkState();
                });

                document.querySelectorAll('[data-debt-status-tab]').forEach((button) => {
                    button.addEventListener('click', () => {
                        setFilterValue('status', button.dataset.debtStatusTab || '');
                        syncActiveStatus();
                        reload();
                    });
                });

                document.getElementById('debts-apply-filter')?.addEventListener('click', () => {
                    syncActiveStatus();
                    reload();
                });

                document.getElementById('debts-reset-filter')?.addEventListener('click', () => {
                    document.querySelectorAll('[data-debt-filter], #debts-search').forEach((el) => el.value = '');
                    setTomSelectValue('status', '');
                    setTomSelectValue('customerId', '');
                    loadCustomersBySale('');
                    setFilterDate('fromDate', defaultDates.fromDate);
                    setFilterDate('toDate', defaultDates.toDate);
                    syncActiveStatus();
                    reload();
                });

                document.getElementById('debts-search')?.addEventListener('input', debounce(reload, 350));
                document.getElementById('debts-page-size')?.addEventListener('change', (event) => table.page.len(Number(event.target.value)).draw());
                document.querySelectorAll('[data-debt-date-preset]').forEach((button) => {
                    button.addEventListener('click', () => setDatePreset(button.dataset.debtDatePreset || '30'));
                });

                field('saleId')?.addEventListener('change', (event) => {
                    setTomSelectValue('customerId', '');
                    loadCustomersBySale(event.target.value || '');
                });

                document.getElementById('debts-delete-selected')?.addEventListener('click', () => {
                    if (selected.size === 0) {
                        notify('Vui lòng chọn ít nhất một công nợ trước khi xóa.');
                        return;
                    }

                    window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
                        title: 'Xác nhận xóa',
                        message: `Bạn có chắc chắn muốn xóa ${selected.size} công nợ đã chọn? Công nợ đã thanh toán sẽ được bỏ qua.`,
                        variant: 'danger',
                        confirmText: 'Xóa',
                        onConfirm: () => postJson(routes.deleteSelected, { ids: [...selected] }).then(reload),
                    }}));
                });

                document.getElementById('debts-export')?.addEventListener('click', () => {
                    const params = new URLSearchParams(filters());
                    params.set('search[value]', document.getElementById('debts-search')?.value || '');
                    window.location.href = `${routes.export}?${params.toString()}`;
                });

                function updateCounts(counts) {
                    document.querySelectorAll('[data-status-count]').forEach((el) => {
                        const key = el.dataset.statusCount || '';
                        el.textContent = key === 'all' ? (counts.all ?? 0) : (counts[key] ?? 0);
                    });
                }

                function updateSummary(summary) {
                    document.querySelector('[data-summary-money="total"]')?.replaceChildren(document.createTextNode(money(summary.total || 0)));
                    document.querySelector('[data-summary-money="paid"]')?.replaceChildren(document.createTextNode(money(summary.paid || 0)));
                    document.querySelector('[data-summary-money="remaining"]')?.replaceChildren(document.createTextNode(money(summary.remaining || 0)));
                    setBar('total', Number(summary.total || 0) > 0 ? 100 : 0);
                    setBar('paid', summary.paid_percent || 0);
                    setBar('remaining', summary.remaining_percent || 0);
                }

                function setBar(name, value) {
                    const bar = document.querySelector(`[data-summary-bar="${name}"]`);
                    if (bar) bar.style.width = `${Number(value || 0)}%`;
                }

                function syncActiveStatus() {
                    const currentStatus = field('status')?.value || '';
                    document.querySelectorAll('[data-debt-status-tab]').forEach((button) => {
                        button.dataset.active = button.dataset.debtStatusTab === currentStatus ? 'true' : 'false';
                    });
                }

                function setFilterValue(name, value) {
                    const input = field(name);
                    if (!input) return;
                    input.value = value;
                    input.tomselect?.setValue(value, true);
                }

                function setTomSelectValue(name, value) {
                    setFilterValue(name, value);
                }

                function setFilterDate(name, value) {
                    const input = field(name);
                    if (!input) return;
                    if (input._flatpickr) {
                        input._flatpickr.setDate(value, false);
                        return;
                    }
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

                function loadCustomersBySale(saleId) {
                    const customerSelect = field('customerId');
                    if (!customerSelect || !routes.customers) return;
                    const requestId = ++customerRequestId;
                    const params = new URLSearchParams();
                    if (saleId) params.set('saleId', saleId);
                    customerSelect.tomselect?.disable();

                    fetch(`${routes.customers}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                        .then((response) => {
                            if (!response.ok) throw new Error('Request failed');
                            return response.json();
                        })
                        .then((payload) => {
                            if (requestId !== customerRequestId) return;
                            replaceCustomerOptions(payload.customers || []);
                        })
                        .catch(() => notify('Không tải được danh sách khách hàng theo sale.'))
                        .finally(() => {
                            if (requestId === customerRequestId) customerSelect.tomselect?.enable();
                        });
                }

                function replaceCustomerOptions(customers) {
                    const select = field('customerId');
                    if (!select) return;

                    select.innerHTML = '<option value="">Tất cả khách hàng</option>';
                    customers.forEach((customer) => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = customer.label;
                        select.appendChild(option);
                    });

                    if (!select.tomselect) return;
                    select.tomselect.clear(true);
                    select.tomselect.clearOptions();
                    select.tomselect.addOption({ value: '', text: 'Tất cả khách hàng' });
                    customers.forEach((customer) => select.tomselect.addOption({ value: String(customer.id), text: customer.label }));
                    select.tomselect.refreshOptions(false);
                    select.tomselect.setValue('', true);
                }

                function bindCreateModalControls(root) {
                    if (!root || root.dataset.createModalBound === 'true') return;
                    root.dataset.createModalBound = 'true';

                    root.addEventListener('click', (event) => {
                        const openButton = event.target.closest?.('[data-congno-create-open]');
                        if (openButton) {
                            event.preventDefault();
                            openCreateModal(root);
                            return;
                        }

                        const closeButton = event.target.closest?.('[data-congno-create-close]');
                        if (!closeButton) return;

                        event.preventDefault();
                        closeCreateModal(root);
                    });

                    root.addEventListener('change', (event) => {
                        const saleSelect = event.target.closest?.('select[data-debt-create-field="createSaleId"]');
                        if (!saleSelect) return;

                        const customerSelect = root.querySelector('select[data-debt-create-field="createCustomerId"]');
                        if (!customerSelect || !routes.customers) return;

                        loadCreateCustomersBySale(saleSelect.value || '', customerSelect);
                    });

                    root.addEventListener('click', (event) => {
                        const submitButton = event.target.closest?.('[data-congno-create-submit]');
                        if (!submitButton) return;

                        const componentId = submitButton.closest('[wire\\:id]')?.getAttribute('wire:id');
                        const component = componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
                        if (!component) return;

                        submitButton.disabled = true;
                        Promise.resolve(component.call('createDebt', collectCreatePayload(root)))
                            .finally(() => {
                                submitButton.disabled = false;
                            });
                    });
                }

                function openCreateModal(root) {
                    const modal = root.querySelector('[data-congno-create-modal]');
                    if (!modal) return;

                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');
                    resetCreateForm(root);
                    requestAnimationFrame(() => {
                        window.TomSelectHelper?.init(modal);
                        initDatePickers();
                    });
                }

                function closeCreateModal(root) {
                    const modal = root.querySelector('[data-congno-create-modal]');
                    if (!modal) return;

                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                }

                function resetCreateForm(root) {
                    const today = new Date();
                    const start = new Date(today);
                    start.setDate(today.getDate() - 30);

                    setCreateFieldValue(root, 'createFromDate', formatDate(start));
                    setCreateFieldValue(root, 'createToDate', formatDate(today));
                    setCreateFieldValue(root, 'paymentTermDays', root.querySelector('[data-debt-create-field="paymentTermDays"]')?.defaultValue || '7');
                    setCreateFieldValue(root, 'note', '');
                }

                function setCreateFieldValue(root, name, value) {
                    const input = root.querySelector(`[data-debt-create-field="${name}"]`);
                    if (!input) return;

                    input.value = value || '';

                    if (input._flatpickr) {
                        input._flatpickr.setDate(value || null, false);
                    }

                    if (input.tomselect) {
                        input.tomselect.setValue(value || '', true);
                    }
                }

                function collectCreatePayload(root) {
                    const payload = {};
                    root.querySelectorAll('[data-debt-create-field]').forEach((field) => {
                        payload[field.dataset.debtCreateField] = field.value || null;
                    });

                    return payload;
                }

                function loadCreateCustomersBySale(saleId, customerSelect) {
                    const params = new URLSearchParams();
                    if (saleId) params.set('saleId', saleId);

                    customerSelect.tomselect?.disable();

                    fetch(`${routes.customers}?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                        .then((response) => {
                            if (!response.ok) throw new Error('Request failed');
                            return response.json();
                        })
                        .then((payload) => replaceCreateCustomerOptions(customerSelect, payload.customers || []))
                        .catch(() => notify('Không tải được danh sách khách hàng theo sale.'))
                        .finally(() => customerSelect.tomselect?.enable());
                }

                function replaceCreateCustomerOptions(select, customers) {
                    select.innerHTML = '<option value="">Chọn khách hàng</option>';
                    customers.forEach((customer) => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = customer.label;
                        select.appendChild(option);
                    });

                    if (!select.tomselect) return;
                    select.tomselect.clear(true);
                    select.tomselect.clearOptions();
                    select.tomselect.addOption({ value: '', text: 'Chọn khách hàng' });
                    customers.forEach((customer) => select.tomselect.addOption({ value: String(customer.id), text: customer.label }));
                    select.tomselect.refreshOptions(false);
                    select.tomselect.setValue('', true);
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

                function emptyStateHtml(title = 'Chưa có công nợ nào', text = 'Không có dữ liệu phù hợp với bộ lọc hiện tại.') {
                    return `
                        <div class="debt-empty-state">
                            <div class="debt-empty-state-icon">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632A2.25 2.25 0 0117.379 20.25H6.621a2.25 2.25 0 01-2.246-2.118L3.75 7.5M9 11.25h6M9 15h6M8.25 7.5V5.25A2.25 2.25 0 0110.5 3h3A2.25 2.25 0 0115.75 5.25V7.5" />
                                </svg>
                            </div>
                            <div>
                                <p class="font-bold text-neutral-900">${title}</p>
                                <p class="mt-1 text-sm text-neutral-500">${text}</p>
                            </div>
                        </div>
                    `;
                }

                function debounce(fn, wait) {
                    let timer;
                    return (...args) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => fn(...args), wait);
                    };
                }

                function notify(message) {
                    window.alert(message);
                }

                function money(value) {
                    return `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))} đ`;
                }

                updateBulkState();
                syncActiveStatus();
            };

            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            const scheduleCongNoIndexInit = (attempt = 0) => {
                initCongNoIndex();

                const tableEl = document.getElementById('debts-table');
                if (tableEl?.dataset.ready === 'true' || attempt >= 20) {
                    return;
                }

                setTimeout(() => scheduleCongNoIndexInit(attempt + 1), 100);
            };

            document.addEventListener('DOMContentLoaded', scheduleCongNoIndexInit);
            document.addEventListener('livewire:navigated', () => scheduleCongNoIndexInit());
            document.addEventListener('livewire:initialized', () => {
                Livewire.hook('morph.updated', () => setTimeout(() => scheduleCongNoIndexInit(), 0));
            });
            scheduleCongNoIndexInit();
        })();
    </script>
@endpush
