<div
    id="invoice-index-page"
    class="space-y-4"
    data-component-cloak
    data-ready="false"
    data-routes='@json($this->routes())'
>
    <div class="component-cloak-content space-y-4">
        <section class="space-y-4">
            <div class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm text-neutral-500">Công nợ / Hóa đơn thu</p>
                    <h1 class="mt-1 text-2xl font-bold text-neutral-900">Hóa đơn thu</h1>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <flux:modal.trigger name="invoice-filter">
                        <flux:button type="button" variant="outline" icon="funnel">
                            Bộ lọc
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </div>
        </section>

        <div class="grid gap-3 lg:grid-cols-4">
            <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 pl-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-neutral-700">Tổng hóa đơn</p>
                        </div>
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="total">{{ $this->money(0) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500"><span data-status-count="all">0</span> hóa đơn</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <flux:icon.document-text class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-blue-100">
                    <div class="h-full rounded-full bg-blue-500" data-summary-bar="total" style="width: 100%"></div>
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
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="paid">{{ $this->money(0) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500">Tiền đã thu</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <flux:icon.check-circle class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                    <div class="h-full rounded-full bg-emerald-500" data-summary-bar="paid" style="width: 0%"></div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-amber-500"></div>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 pl-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700">Đang chờ</p>
                        </div>
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="pending">{{ $this->money(0) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500">Chờ thanh toán</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <flux:icon.clock class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-amber-100">
                    <div class="h-full rounded-full bg-amber-500" data-summary-bar="pending" style="width: 0%"></div>
                </div>
            </div>

            <div class="relative overflow-hidden rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-red-500"></div>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 pl-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-red-700">Đã hủy</p>
                        </div>
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="cancelled">{{ $this->money(0) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500">Hóa đơn bị hủy</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <flux:icon.x-circle class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-red-100">
                    <div class="h-full rounded-full bg-red-500" data-summary-bar="cancelled" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <section class="debt-status-nav">
            <div class="debt-status-nav-header">
                <div>
                    <h3>Trạng thái hóa đơn</h3>
                    <p>Lọc nhanh theo trạng thái thanh toán</p>
                </div>
            </div>
            <div class="debt-status-tabs">
                <button type="button" data-invoice-status-tab="" data-active="true" class="debt-status-tab debt-status-tab-all text-neutral-700">
                    <span class="debt-status-dot"></span>
                    <span class="debt-status-text">
                        <span class="debt-status-label">Tất cả</span>
                        <span class="debt-status-meta">Toàn bộ hóa đơn</span>
                    </span>
                    <span class="debt-status-count" data-status-count="all">0</span>
                </button>

                @foreach ($this->invoiceStatuses() as $status)
                    <button type="button"
                            data-invoice-status-tab="{{ $status->value }}"
                            data-active="false"
                            class="debt-status-tab {{ $status->color() }}">
                        <span class="debt-status-dot"></span>
                        <span class="debt-status-text">
                            <span class="debt-status-label">{{ $status->label() }}</span>
                            <span class="debt-status-meta">Nhấn để lọc</span>
                        </span>
                        <span class="debt-status-count" data-status-count="{{ $status->value }}">0</span>
                    </button>
                @endforeach
            </div>
        </section>

        <div class="space-y-3 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <flux:input type="search" id="invoices-search" icon="magnifying-glass" placeholder="Tìm mã hóa đơn, mã công nợ, khách hàng..." class="lg:max-w-md" />
                <div class="flex items-center gap-2 text-sm text-neutral-600">
                    <label class="inline-flex items-center gap-2">
                        <span>Hiển thị</span>
                        <select id="invoices-page-size" class="h-8 rounded-lg border border-neutral-200 bg-white px-2 text-sm font-medium text-neutral-700">
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="invoices-table" class="w-full min-w-[1700px] text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-3 py-3">Mã hóa đơn</th>
                                <th class="px-3 py-3">Công nợ gốc</th>
                                <th class="px-3 py-3">Khách hàng</th>
                                <th class="px-3 py-3">Sale</th>
                                <th class="px-3 py-3 text-right">Số tiền</th>
                                <th class="px-3 py-3">Trạng thái</th>
                                <th class="px-3 py-3">Ngày tạo</th>
                                <th class="px-3 py-3">Ngày thanh toán</th>
                                <th class="px-3 py-3">Người tạo</th>
                                <th class="px-3 py-3">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <flux:modal name="invoice-filter" class="w-full max-w-5xl !overflow-visible">
            <div class="debt-filter-panel">
                <div class="flex items-start gap-3 border-b border-neutral-100 pb-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                        <flux:icon.funnel class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">Bộ lọc hóa đơn thu</flux:heading>
                        <flux:subheading>Lọc theo thời gian tạo, trạng thái, sale phụ trách và khách hàng.</flux:subheading>
                    </div>
                </div>

                <section class="debt-filter-section">
                    <div class="debt-filter-section-heading">
                        <div>
                            <h3>Thời gian</h3>
                            <p>Khoảng ngày tạo hóa đơn</p>
                        </div>
                        <div class="debt-filter-presets">
                            <button type="button" data-invoice-date-preset="today">Hôm nay</button>
                            <button type="button" data-invoice-date-preset="7">7 ngày</button>
                            <button type="button" data-invoice-date-preset="30">30 ngày</button>
                        </div>
                    </div>
                    <div class="debt-filter-grid debt-filter-grid-2">
                        <div class="debt-filter-field debt-filter-date-field">
                            <label class="debt-filter-label">Từ ngày</label>
                            <input type="text" value="{{ $fromDate }}" data-invoice-filter="fromDate" data-invoice-date autocomplete="off" class="debt-filter-control">
                        </div>
                        <div class="debt-filter-field debt-filter-date-field">
                            <label class="debt-filter-label">Đến ngày</label>
                            <input type="text" value="{{ $toDate }}" data-invoice-filter="toDate" data-invoice-date autocomplete="off" class="debt-filter-control">
                        </div>
                    </div>
                </section>

                <section class="debt-filter-section">
                    <div class="debt-filter-section-heading">
                        <div>
                            <h3>Đối tượng</h3>
                            <p>Nhân sự phụ trách và khách hàng</p>
                        </div>
                    </div>
                    <div class="debt-filter-grid debt-filter-grid-2">
                        <div class="debt-filter-field">
                            <label class="debt-filter-label">Sale phụ trách</label>
                            <select
                                wire:key="filter-sale-{{ $saleId ?: 'empty' }}"
                                class="tomselectEml debt-filter-tomselect"
                                data-placeholder="Tất cả sale"
                                data-invoice-filter="saleId"
                            >
                                <option value="">Tất cả sale</option>
                                @foreach ($this->sales() as $sale)
                                    <option value="{{ $sale->id }}" @selected((int) $saleId === (int) $sale->id)>{{ $sale->fullname ?: $sale->username }}{{ $sale->code ? ' - '.$sale->code : '' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="debt-filter-field">
                            <label class="debt-filter-label">Khách hàng / CTV</label>
                            <select
                                wire:key="filter-customer-{{ $saleId ?: 'all' }}-{{ $customerId ?: 'empty' }}"
                                class="tomselectEml debt-filter-tomselect"
                                data-placeholder="Tất cả khách hàng"
                                data-invoice-filter="customerId"
                            >
                                <option value="">Tất cả khách hàng</option>
                                @foreach ($this->customers() as $customer)
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
                            <p>Tiến trình thanh toán hóa đơn</p>
                        </div>
                    </div>
                    <div class="debt-filter-grid debt-filter-grid-3">
                        <div class="debt-filter-field">
                            <label class="debt-filter-label">Trạng thái hóa đơn</label>
                            <select
                                wire:key="filter-status-{{ $status ?: 'all' }}"
                                class="tomselectEml debt-filter-tomselect"
                                data-placeholder="Tất cả trạng thái"
                                data-invoice-filter="status"
                            >
                                <option value="">Tất cả trạng thái</option>
                                @foreach ($this->invoiceStatuses() as $s)
                                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <flux:button type="button" id="invoices-reset-filter" variant="ghost">Làm mới</flux:button>
                    <flux:modal.close>
                        <flux:button type="button" id="invoices-apply-filter" variant="primary">Áp dụng</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        <div id="invoice-cash-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <form id="invoice-cash-form" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-950">Gửi hóa đơn thanh toán tiền mặt</h2>
                        <p class="mt-1 text-sm text-neutral-500">
                            <span data-cash-modal-code>-</span>
                            <span class="mx-1">/</span>
                            <span data-cash-modal-amount>-</span>
                        </p>
                    </div>
                    <button type="button" id="invoice-cash-close" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-neutral-700">Ảnh hóa đơn thanh toán</span>
                        <input type="file" name="photo" accept="image/*" class="mt-2 block w-full rounded-lg border border-neutral-200 bg-white text-sm file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                    </label>
                    <p class="mt-2 hidden text-sm text-red-600" data-cash-modal-error></p>
                </div>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" id="invoice-cash-cancel" class="inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        Hủy
                    </button>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Gửi hóa đơn
                    </button>
                </div>
            </form>
        </div>

        <div id="invoice-cancel-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <form id="invoice-cancel-form" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-950">Hủy hóa đơn</h2>
                        <p class="mt-1 text-sm text-neutral-500">
                            <span data-cancel-modal-code>-</span>
                            <span class="mx-1">/</span>
                            <span data-cancel-modal-amount>-</span>
                        </p>
                    </div>
                    <button type="button" id="invoice-cancel-close" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-neutral-700">Ghi chú hủy</span>
                        <textarea name="cancel_reason" rows="4" class="mt-2 block w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm" placeholder="Nhập lý do hủy hóa đơn" required></textarea>
                    </label>
                    <p class="mt-2 hidden text-sm text-red-600" data-cancel-modal-error></p>
                </div>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" id="invoice-cancel-dismiss" class="inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        Đóng
                    </button>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-red-600 px-4 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Xác nhận hủy
                    </button>
                </div>
            </form>
        </div>
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

            const field = (name) => document.querySelector(`[data-invoice-filter="${name}"]`);

            const markReady = () => {
                const root = document.getElementById('invoice-index-page');
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

                document.querySelectorAll('input[data-invoice-date]').forEach((input) => {
                    if (input._flatpickr) return;

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
                            instance.calendarContainer.classList.add('debt-filter-calendar');
                        },
                    });
                });
            };

            const initInvoiceIndex = () => {
                const root = document.getElementById('invoice-index-page');
                const tableEl = document.getElementById('invoices-table');
                const routes = root ? JSON.parse(root.dataset.routes || '{}') : {};
                initDatePickers();
                if (root) window.TomSelectHelper?.init(root);

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

                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                const cashModal = document.getElementById('invoice-cash-modal');
                const cashForm = document.getElementById('invoice-cash-form');
                const cashState = { invoiceId: null };
                const cancelModal = document.getElementById('invoice-cancel-modal');
                const cancelForm = document.getElementById('invoice-cancel-form');
                const cancelState = { invoiceId: null };

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
                    pageLength: Number(document.getElementById('invoices-page-size')?.value || 25),
                    pagingType: 'simple_numbers',
                    scrollX: true,
                    ajax: {
                        url: routes.datatable,
                        data: (data) => Object.assign(data, filters(), {
                            search: { value: document.getElementById('invoices-search')?.value || '' },
                        }),
                    },
                    order: [],
                    language: {
                        url: '{{ asset('assets/datatables/vi.json') }}',
                        processing: 'Đang tải dữ liệu',
                        emptyTable: emptyStateHtml(),
                        zeroRecords: emptyStateHtml('Không có hóa đơn phù hợp', 'Thử đổi từ khóa hoặc nới rộng bộ lọc.'),
                    },
                    columns: [
                        { data: 'invoice_code', orderable: false, searchable: false },
                        { data: 'debt_code', orderable: false, searchable: false },
                        { data: 'customer_info', orderable: false, searchable: false },
                        { data: 'sale_info', orderable: false, searchable: false },
                        { data: 'amount', orderable: false, searchable: false },
                        { data: 'status_badge', orderable: false, searchable: false },
                        { data: 'created_at', orderable: false, searchable: false },
                        { data: 'paid_at', orderable: false, searchable: false },
                        { data: 'creator', orderable: false, searchable: false },
                        { data: 'actions', orderable: false, searchable: false },
                    ],
                    columnDefs: [
                        { targets: 0, width: '160px' },
                        { targets: 1, width: '140px' },
                        { targets: 2, width: '200px' },
                        { targets: 3, width: '150px' },
                        { targets: 4, width: '140px' },
                        { targets: 5, width: '180px' },
                        { targets: 6, width: '140px' },
                        { targets: 7, width: '140px' },
                        { targets: 8, width: '140px' },
                        { targets: 9, width: '360px' },
                    ],
                    initComplete: markReady,
                });

                const reload = () => table.ajax.reload();

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
                });

                root.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-invoice-approve]');
                    if (!btn) return;

                    const invoiceId = btn.dataset.invoiceApprove;
                    btn.disabled = true;

                    postJson(`${routes.approve}/${invoiceId}/approve`, {})
                        .then((payload) => {
                            notify(payload.message || 'Đã duyệt hóa đơn.');
                            reload();
                        })
                        .catch((err) => {
                            btn.disabled = false;
                            notify(err?.message || 'Không thể duyệt hóa đơn. Vui lòng thử lại.');
                        });
                });

                root.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-invoice-cash]');
                    if (!btn) return;

                    openCashModal(btn);
                });

                root.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-invoice-qr]');
                    if (!btn) return;

                    const invoiceId = btn.dataset.invoiceQr;
                    btn.disabled = true;

                    postJson(`${routes.qr}/${invoiceId}/qr`, {})
                        .then((payload) => {
                            notify(payload.message || 'Đã tạo QR thanh toán.');
                            reload();
                        })
                        .catch((err) => {
                            btn.disabled = false;
                            notify(err?.message || 'Không thể tạo QR thanh toán. Vui lòng thử lại.');
                        });
                });

                root.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-invoice-regenerate-qr]');
                    if (!btn) return;

                    const invoiceId = btn.dataset.invoiceRegenerateQr;
                    btn.disabled = true;

                    postJson(`${routes.regenerateQr}/${invoiceId}/regenerate-qr`, {})
                        .then((payload) => {
                            notify(payload.message || 'Đã tạo lại QR.');
                            reload();
                        })
                        .catch((err) => {
                            btn.disabled = false;
                            notify(err?.message || 'Không thể tạo lại QR. Vui lòng thử lại.');
                        });
                });

                root.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-invoice-confirm-cash]');
                    if (!btn) return;

                    const invoiceId = btn.dataset.invoiceConfirmCash;
                    if (!window.confirm('Xác nhận đã thu tiền mặt cho hóa đơn này?')) return;

                    btn.disabled = true;

                    postJson(`${routes.confirmCash}/${invoiceId}/confirm-cash`, {})
                        .then((payload) => {
                            notify(payload.message || 'Đã xác nhận thanh toán.');
                            reload();
                        })
                        .catch((err) => {
                            btn.disabled = false;
                            notify(err?.message || 'Không thể xác nhận thanh toán. Vui lòng thử lại.');
                        });
                });

                root.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-invoice-cancel]');
                    if (!btn) return;

                    openCancelModal(btn);
                });

                document.getElementById('invoice-cash-close')?.addEventListener('click', closeCashModal);
                document.getElementById('invoice-cash-cancel')?.addEventListener('click', closeCashModal);
                cashModal?.addEventListener('click', (event) => {
                    if (event.target === cashModal) closeCashModal();
                });
                document.getElementById('invoice-cancel-close')?.addEventListener('click', closeCancelModal);
                document.getElementById('invoice-cancel-dismiss')?.addEventListener('click', closeCancelModal);
                cancelModal?.addEventListener('click', (event) => {
                    if (event.target === cancelModal) closeCancelModal();
                });

                cashForm?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    if (!cashState.invoiceId) return;

                    const submitButton = cashForm.querySelector('button[type="submit"]');
                    const formData = new FormData(cashForm);
                    submitButton.disabled = true;
                    setCashError('');

                    postForm(`${routes.cash}/${cashState.invoiceId}/cash`, formData)
                        .then((payload) => {
                            notify(payload.message || 'Đã gửi hóa đơn thanh toán.');
                            closeCashModal();
                            reload();
                        })
                        .catch((err) => {
                            setCashError(err?.message || 'Không thể gửi hóa đơn thanh toán. Vui lòng thử lại.');
                        })
                        .finally(() => {
                            submitButton.disabled = false;
                        });
                });

                cancelForm?.addEventListener('submit', (event) => {
                    event.preventDefault();
                    if (!cancelState.invoiceId) return;

                    const submitButton = cancelForm.querySelector('button[type="submit"]');
                    const formData = new FormData(cancelForm);
                    submitButton.disabled = true;
                    setCancelError('');

                    postForm(`${routes.cancel}/${cancelState.invoiceId}/cancel`, formData)
                        .then((payload) => {
                            notify(payload.message || 'Đã hủy hóa đơn.');
                            closeCancelModal();
                            reload();
                        })
                        .catch((err) => {
                            setCancelError(err?.message || 'Không thể hủy hóa đơn. Vui lòng thử lại.');
                        })
                        .finally(() => {
                            submitButton.disabled = false;
                        });
                });

                document.querySelectorAll('[data-invoice-status-tab]').forEach((button) => {
                    button.addEventListener('click', () => {
                        setFilterValue('status', button.dataset.invoiceStatusTab || '');
                        syncActiveStatus();
                        reload();
                    });
                });

                document.getElementById('invoices-apply-filter')?.addEventListener('click', () => {
                    syncActiveStatus();
                    reload();
                });

                document.getElementById('invoices-reset-filter')?.addEventListener('click', () => {
                    document.querySelectorAll('[data-invoice-filter], #invoices-search').forEach((el) => el.value = '');
                    setTomSelectValue('status', '');
                    setTomSelectValue('saleId', '');
                    setTomSelectValue('customerId', '');
                    loadCustomersBySale('');
                    setFilterDate('fromDate', defaultDates.fromDate);
                    setFilterDate('toDate', defaultDates.toDate);
                    syncActiveStatus();
                    reload();
                });

                document.getElementById('invoices-search')?.addEventListener('input', debounce(reload, 350));
                document.getElementById('invoices-page-size')?.addEventListener('change', (event) => table.page.len(Number(event.target.value)).draw());

                document.querySelectorAll('[data-invoice-date-preset]').forEach((button) => {
                    button.addEventListener('click', () => setDatePreset(button.dataset.invoiceDatePreset || '30'));
                });

                field('saleId')?.addEventListener('change', (event) => {
                    setTomSelectValue('customerId', '');
                    loadCustomersBySale(event.target.value || '');
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
                    document.querySelector('[data-summary-money="pending"]')?.replaceChildren(document.createTextNode(money(summary.pending || 0)));
                    document.querySelector('[data-summary-money="cancelled"]')?.replaceChildren(document.createTextNode(money(summary.cancelled || 0)));
                    setBar('paid', summary.paid_percent || 0);
                    setBar('pending', summary.pending_percent || 0);
                    setBar('cancelled', summary.cancelled_percent || 0);
                }

                function setBar(name, value) {
                    const bar = document.querySelector(`[data-summary-bar="${name}"]`);
                    if (bar) bar.style.width = `${Number(value || 0)}%`;
                }

                function syncActiveStatus() {
                    const currentStatus = field('status')?.value || '';
                    document.querySelectorAll('[data-invoice-status-tab]').forEach((button) => {
                        button.dataset.active = button.dataset.invoiceStatusTab === currentStatus ? 'true' : 'false';
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

                let customerRequestId = 0;

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
                            replaceCustomerOptions(customerSelect, payload.customers || []);
                        })
                        .catch(() => {})
                        .finally(() => {
                            if (requestId === customerRequestId) customerSelect.tomselect?.enable();
                        });
                }

                function replaceCustomerOptions(select, customers) {
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

                function openCashModal(button) {
                    if (!cashModal || !cashForm) return;

                    cashState.invoiceId = button.dataset.invoiceCash || null;
                    cashForm.reset();
                    setCashError('');
                    cashModal.querySelector('[data-cash-modal-code]')?.replaceChildren(document.createTextNode(button.dataset.invoiceCode || '-'));
                    cashModal.querySelector('[data-cash-modal-amount]')?.replaceChildren(document.createTextNode(button.dataset.invoiceAmount || '-'));
                    cashModal.classList.remove('hidden');
                    cashModal.classList.add('flex');
                }

                function closeCashModal() {
                    if (!cashModal || !cashForm) return;

                    cashState.invoiceId = null;
                    cashForm.reset();
                    setCashError('');
                    cashModal.classList.add('hidden');
                    cashModal.classList.remove('flex');
                }

                function setCashError(message) {
                    const error = cashModal?.querySelector('[data-cash-modal-error]');
                    if (!error) return;

                    error.textContent = message || '';
                    error.classList.toggle('hidden', !message);
                }

                function openCancelModal(button) {
                    if (!cancelModal || !cancelForm) return;

                    cancelState.invoiceId = button.dataset.invoiceCancel || null;
                    cancelForm.reset();
                    setCancelError('');
                    cancelModal.querySelector('[data-cancel-modal-code]')?.replaceChildren(document.createTextNode(button.dataset.invoiceCode || '-'));
                    cancelModal.querySelector('[data-cancel-modal-amount]')?.replaceChildren(document.createTextNode(button.dataset.invoiceAmount || '-'));
                    cancelModal.classList.remove('hidden');
                    cancelModal.classList.add('flex');
                }

                function closeCancelModal() {
                    if (!cancelModal || !cancelForm) return;

                    cancelState.invoiceId = null;
                    cancelForm.reset();
                    setCancelError('');
                    cancelModal.classList.add('hidden');
                    cancelModal.classList.remove('flex');
                }

                function setCancelError(message) {
                    const error = cancelModal?.querySelector('[data-cancel-modal-error]');
                    if (!error) return;

                    error.textContent = message || '';
                    error.classList.toggle('hidden', !message);
                }

                function postJson(url, payload) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify(payload),
                    }).then((response) => response.json().catch(() => ({})).then((payload) => {
                        if (!response.ok) throw new Error(payload.message || 'Request failed');
                        return payload;
                    }));
                }

                function postForm(url, formData) {
                    return fetch(url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: formData,
                    }).then((response) => response.json().catch(() => ({})).then((payload) => {
                        if (!response.ok) throw new Error(payload.message || firstValidationError(payload.errors) || 'Request failed');
                        return payload;
                    }));
                }

                function firstValidationError(errors) {
                    if (!errors) return null;
                    const first = Object.values(errors)[0];
                    return Array.isArray(first) ? first[0] : first;
                }

                function notify(message) {
                    if (window.Livewire?.dispatch) {
                        window.Livewire.dispatch('notify', { message });
                        return;
                    }

                    window.alert(message);
                }

                function emptyStateHtml(title = 'Chưa có hóa đơn nào', text = 'Không có dữ liệu phù hợp với bộ lọc hiện tại.') {
                    return `
                        <div class="invoice-empty-state">
                            <div class="invoice-empty-state-icon">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
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

                function money(value) {
                    return `${new Intl.NumberFormat('vi-VN').format(Number(value || 0))} đ`;
                }

                syncActiveStatus();
            };

            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }

            document.addEventListener('DOMContentLoaded', initInvoiceIndex);
            document.addEventListener('livewire:navigated', initInvoiceIndex);
            document.addEventListener('livewire:initialized', () => {
                Livewire.hook('morph.updated', () => setTimeout(initInvoiceIndex, 0));
            });
            setTimeout(initInvoiceIndex, 0);
        })();
    </script>
@endpush
