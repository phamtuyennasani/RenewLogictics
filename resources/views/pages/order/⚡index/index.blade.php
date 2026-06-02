<div
    id="order-index-page"
    class="space-y-5"
    data-routes='@json($routes)'
    data-capabilities='@json($capabilities)'
>
    @include('pages.order.⚡index.partials.header')
    @if (! ($capabilities['isSaleUser'] ?? false))
        @include('pages.order.⚡index.partials.bulk-actions')
    @endif
    @include('pages.order.⚡index.partials.filter-panel')
    @include('pages.order.⚡index.partials.table')

    <flux:modal.trigger name="order-index-print-bill-confirm">
        <button type="button" class="hidden" data-order-print-bill-trigger></button>
    </flux:modal.trigger>

    <flux:modal name="order-index-print-bill-confirm" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Print Bill</flux:heading>
                <flux:subheading>Bạn có cần in kèm công văn cam kết nội dung hàng xuất không?</flux:subheading>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button type="button" variant="filled" data-order-print-bill-cvck="0">Không kèm CVCK</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button type="button" variant="primary" data-order-print-bill-cvck="1">In kèm CVCK</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <iframe id="order-print-frame" class="pointer-events-none fixed h-0 w-0 border-0 opacity-0" title="Order print renderer" aria-hidden="true"></iframe>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/datatables/dataTables.tailwindcss.css') }}">
    <style>
        #order-index-page .dt-tailwindcss .dt-layout-row {
            align-items: center;
            margin-top: 0.75rem;
        }

        #order-index-page .dt-tailwindcss .dt-layout-table {
            margin-top: 0;
        }

        #order-index-page .dt-tailwindcss .dt-info {
            color: #525252;
            font-size: 0.875rem;
        }

        #order-index-page #orders-table {
            min-width: 3238px;
        }

        #order-index-page #orders-table th,
        #order-index-page #orders-table td {
            white-space: nowrap;
            vertical-align: middle;
        }

        #order-index-page #orders-table .truncate {
            min-width: 0;
        }

        #order-index-page .order-status-nav {
            border-top: 1px solid #f5f5f5;
            background: linear-gradient(180deg, #fff 0%, #fafafa 100%);
            padding: 1rem;
        }

        #order-index-page .order-status-nav-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        #order-index-page .order-status-nav-header h3 {
            margin: 0;
            color: #171717;
            font-size: 0.875rem;
            font-weight: 750;
            line-height: 1.25rem;
        }

        #order-index-page .order-status-nav-header p {
            margin: 0.125rem 0 0;
            color: #737373;
            font-size: 0.75rem;
            line-height: 1rem;
        }

        #order-index-page .order-status-tabs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));
            gap: 0.625rem;
        }

        #order-index-page .order-special-status-tabs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr));
            gap: 0.625rem;
            margin-top: 0.625rem;
            border-top: 1px dashed #e5e5e5;
            padding-top: 0.625rem;
        }

        #order-index-page .order-special-status-tabs[hidden] {
            display: none;
        }

        #order-index-page .order-status-tab {
            display: grid;
            min-width: 0;
            min-height: 4rem;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 0.625rem;
            border: 1px solid #e5e5e5;
            border-radius: 0.875rem;
            background: rgb(255 255 255 / 0.92);
            padding: 0.625rem 0.75rem;
            text-align: left;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
            transition: transform 150ms ease, border-color 150ms ease, box-shadow 150ms ease, background-color 150ms ease;
        }

        #order-index-page .order-status-tab:hover {
            transform: translateY(-1px);
            border-color: #d4d4d4;
            box-shadow: 0 10px 24px rgb(15 23 42 / 0.08);
        }

        #order-index-page .order-status-tab[data-active="true"] {
            border-color: currentColor;
            background: #fff;
            box-shadow: 0 12px 30px rgb(15 23 42 / 0.12);
        }

        #order-index-page .order-status-tab-all[data-active="true"] {
            border-color: #737373;
            background: #f5f5f5;
        }

        #order-index-page .order-status-special-toggle {
            color: #525252;
        }

        #order-index-page .order-status-special-toggle[aria-expanded="true"] {
            border-color: #737373;
            background: #f5f5f5;
        }

        #order-index-page .order-status-dot {
            width: 0.625rem;
            height: 0.625rem;
            border-radius: 999px;
            background: currentColor;
            opacity: 0.75;
            box-shadow: 0 0 0 4px rgb(0 0 0 / 0.04);
        }

        #order-index-page .order-status-text {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 0.125rem;
        }

        #order-index-page .order-status-label {
            overflow: hidden;
            color: #171717;
            font-size: 0.8125rem;
            font-weight: 700;
            line-height: 1.125rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #order-index-page .order-status-meta {
            overflow: hidden;
            color: #737373;
            font-size: 0.6875rem;
            font-weight: 500;
            line-height: 0.875rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        #order-index-page .order-status-count {
            display: inline-flex;
            min-width: 2rem;
            height: 1.625rem;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e5e5;
            border-radius: 999px;
            background: #fff;
            padding: 0 0.5rem;
            color: #404040;
            font-size: 0.75rem;
            font-weight: 750;
            line-height: 1;
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.8);
        }

        #order-index-page .order-table-card .dt-container {
            position: relative;
        }

        #order-index-page .order-table-card .dt-layout-table {
            position: relative;
        }

        #order-index-page .order-table-card .dt-processing {
            position: absolute;
            inset: 0;
            z-index: 30;
            display: none;
            width: auto;
            min-height: 16rem;
            margin: 0;
            padding: 0;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            background: rgb(255 255 255 / 0.72);
            backdrop-filter: blur(1.5px);
            color: #374151;
            font-size: 0.875rem;
            font-weight: 600;
            line-height: 1.25rem;
            pointer-events: none;
        }

        #order-index-page .order-table-card .dt-processing[style*="display: block"],
        #order-index-page .order-table-card .dt-processing[style*="display:block"] {
            display: flex !important;
        }

        #order-index-page .order-table-card .dt-processing::before {
            content: "";
            width: 1rem;
            height: 1rem;
            flex: 0 0 auto;
            border: 2px solid #bfdbfe;
            border-top-color: #2563eb;
            border-radius: 999px;
            animation: order-processing-spin 0.75s linear infinite;
        }

        #order-index-page .order-table-card .dt-processing::after {
            content: "";
            position: absolute;
            z-index: -1;
            width: 13.5rem;
            height: 2.875rem;
            border: 1px solid #e5e7eb;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 18px 45px rgb(15 23 42 / 0.16);
        }

        #order-index-page .order-table-card .dt-processing > div:first-child {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        #order-index-page .order-table-card .dt-processing > div:last-child {
            display: none;
        }

        #order-index-page .order-table-card td.dt-empty {
            padding: 0 !important;
            border-bottom: 0;
        }

        #order-index-page .order-empty-state {
            display: flex;
            min-height: 15rem;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 2.5rem 1rem;
            color: #525252;
            text-align: center;
        }

        #order-index-page .order-empty-state-icon {
            display: flex;
            width: 3rem;
            height: 3rem;
            align-items: center;
            justify-content: center;
            border: 1px solid #e5e5e5;
            border-radius: 1rem;
            background: #fafafa;
            color: #737373;
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.8);
        }

        #order-index-page .order-empty-state-title {
            margin: 0;
            color: #171717;
            font-size: 0.9375rem;
            font-weight: 750;
            line-height: 1.25rem;
        }

        #order-index-page .order-empty-state-text {
            margin: 0;
            max-width: 28rem;
            color: #737373;
            font-size: 0.875rem;
            line-height: 1.375rem;
        }

        @keyframes order-processing-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .order-filter-panel {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .order-filter-header {
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 1rem;
        }

        .order-filter-title-row {
            display: flex;
            align-items: flex-start;
            gap: 0.875rem;
        }

        .order-filter-icon {
            display: flex;
            width: 2.5rem;
            height: 2.5rem;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: 0.875rem;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .order-filter-content {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
        }

        @media (min-width: 1024px) {
            .order-filter-content {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .order-filter-section {
            min-width: 0;
            border: 1px solid #e5e5e5;
            border-radius: 1rem;
            background: #fafafa;
            padding: 1rem;
        }

        .order-filter-section-wide {
            grid-column: 1 / -1;
        }

        .order-filter-section-heading {
            display: flex;
            min-height: 2.25rem;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .order-filter-section-heading h3 {
            margin: 0;
            color: #171717;
            font-size: 0.9375rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .order-filter-section-heading p {
            margin: 0.125rem 0 0;
            color: #737373;
            font-size: 0.8125rem;
            line-height: 1.125rem;
        }

        .order-filter-section-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .order-filter-section-grid-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .order-filter-section-grid-3 {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .order-filter-field {
            min-width: 0;
        }

        .order-filter-label {
            display: block;
            margin-bottom: 0.625rem;
            color: #262626;
            font-size: 0.875rem;
            font-weight: 650;
            line-height: 1.125rem;
        }

        .order-filter-presets {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 0.375rem;
        }

        .order-filter-presets button {
            min-height: 1.875rem;
            border: 1px solid #d4d4d4;
            border-radius: 999px;
            background: #fff;
            padding: 0.25rem 0.75rem;
            color: #404040;
            font-size: 0.8125rem;
            font-weight: 600;
            line-height: 1rem;
            transition: border-color 150ms ease, color 150ms ease, background-color 150ms ease;
        }

        .order-filter-presets button:hover {
            border-color: #93c5fd;
            background: #eff6ff;
            color: #1d4ed8;
        }

        .order-filter-control,
        .order-filter-panel .ts-control {
            width: 100%;
            height: 2.875rem;
            min-height: 2.875rem;
            border: 1px solid #d4d4d4;
            border-radius: 0.75rem;
            background-color: #fff;
            padding: 0.625rem 1rem;
            color: #171717;
            font-size: 0.9375rem;
            line-height: 1.375rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        .order-filter-control:focus,
        .order-filter-panel .ts-wrapper.focus .ts-control {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgb(59 130 246 / 0.14);
            outline: none;
        }

        .order-filter-select {
            appearance: auto;
        }

        .order-filter-panel .ts-wrapper {
            width: 100%;
        }

        .order-filter-panel .ts-wrapper.single .ts-control {
            display: flex;
            align-items: center;
            padding-right: 2.5rem;
        }

        .order-filter-panel .ts-control > input {
            min-width: 0;
            font-size: 0.9375rem;
        }

        .order-filter-panel .ts-control .item,
        .order-filter-panel .ts-control .items-placeholder {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .order-filter-panel .ts-dropdown {
            z-index: 999999;
            border-radius: 0.75rem;
            overflow: hidden;
        }

        .order-filter-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            border-top: 1px solid #e5e5e5;
            padding-top: 1rem;
        }

        .order-date-picker-field {
            position: relative;
            width: 100%;
        }

        .order-date-picker-field .flatpickr-wrapper {
            display: block;
            width: 100%;
        }

        .order-date-picker-field .flatpickr-input {
            width: 100%;
        }

        .order-date-picker-field .flatpickr-calendar.static {
            position: absolute;
            top: calc(100% + 0.5rem);
            left: 0;
            z-index: 60;
        }
    </style>
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

            const field = (name) => document.querySelector(`[data-order-filter="${name}"]`);

            const initOrderDatePickers = () => {
                ensureDefaultDateRange();

                if (!window.flatpickr) {
                    if (datePickerRetryCount < 20) {
                        datePickerRetryCount++;
                        setTimeout(initOrderDatePickers, 100);
                    }
                    return;
                }

                document.querySelectorAll('input[data-order-date-picker]').forEach((input) => {
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
                            instance.calendarContainer.classList.add('order-filter-calendar');
                        },
                    });
                });
            };

            function ensureDefaultDateRange() {
                if (!field('fromDate')?.value) setFilterDate('fromDate', defaultDates.fromDate);
                if (!field('toDate')?.value) setFilterDate('toDate', defaultDates.toDate);
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

            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');

                return `${year}-${month}-${day}`;
            }

            const initOrderIndex = () => {
                const root = document.getElementById('order-index-page');
                const tableEl = document.getElementById('orders-table');
                initOrderDatePickers();
                bindSpecialStatusToggle();

                if (!root || !tableEl || !window.jQuery || !jQuery.fn.DataTable || tableEl.dataset.ready === 'true') return;

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

                const routes = JSON.parse(root.dataset.routes || '{}');
                const capabilities = JSON.parse(root.dataset.capabilities || '{}');
                const selected = new Set();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                let customerRequestId = 0;
                let pendingPrintUrl = '';

                const printOrder = (url, target, withCvck = null) => {
                    const frame = document.getElementById('order-print-frame');
                    if (!frame || !url) return;

                    const printUrl = new URL(url, window.location.origin);
                    printUrl.searchParams.set('print', target);

                    if (withCvck === null) {
                        printUrl.searchParams.delete('cvck');
                    } else {
                        printUrl.searchParams.set('cvck', withCvck ? '1' : '0');
                    }

                    frame.src = printUrl.toString();
                };

                document.querySelectorAll('[data-order-print-bill-cvck]').forEach((button) => {
                    if (button.dataset.ready === 'true') return;

                    button.dataset.ready = 'true';
                    button.addEventListener('click', () => {
                        printOrder(pendingPrintUrl, 'bill', button.dataset.orderPrintBillCvck === '1');
                        pendingPrintUrl = '';
                    });
                });

                window.TomSelectHelper?.init(root);

                const filters = () => ({
                    status: field('status')?.value || '',
                    fromDate: field('fromDate')?.value || '',
                    toDate: field('toDate')?.value || '',
                    saleId: field('saleId')?.value || '',
                    customerId: field('customerId')?.value || '',
                    serviceId: field('serviceId')?.value || '',
                    branchId: field('branchId')?.value || '',
                    agencyId: field('agencyId')?.value || '',
                    airlineId: field('airlineId')?.value || '',
                    transitPartnerId: field('transitPartnerId')?.value || '',
                });

                const table = jQuery(tableEl).DataTable({
                    processing: true,
                    serverSide: true,
                    searching: false,
                    ordering: false,
                    lengthChange: false,
                    pageLength: Number(document.getElementById('orders-page-size')?.value || 25),
                    pagingType: 'simple_numbers',
                    scrollX: true,
                    autoWidth: false,
                    ajax: {
                        url: routes.datatable,
                        data: (data) => Object.assign(data, filters(), {
                            search: { value: document.getElementById('orders-search')?.value || '' },
                        }),
                    },
                    order: [],
                    language: {
                        url: '{{ asset('assets/datatables/vi.json') }}',
                        processing: 'Đang tải dữ liệu',
                        emptyTable: emptyStateHtml(),
                        zeroRecords: emptyStateHtml('Không tìm thấy order phù hợp', 'Thử đổi từ khóa tìm kiếm hoặc nới rộng bộ lọc để xem thêm dữ liệu.'),
                    },
                    columns: (() => {
                        const col = (data, name) => ({ data, ...(name ? { name } : {}), orderable: false, searchable: false });

                        // Cột chung đầu bảng
                        const head = [
                            col('order_code', 'id_bill'),
                            col('status_badge', 'bill_status'),
                            col('dates', 'created_at'),
                            col('assignee'),
                            col('sender_info'),
                            col('receiver_info'),
                            col('receiver_address'),
                            col('service_info'),
                            col('receiver_country'),
                        ];

                        if (capabilities.isSaleUser) {
                            return [
                                ...head,
                                col('package_info'),
                                col('sale_total'),
                                col('sale_commission'),
                                col('payment_client'),
                                col('actions'),
                            ];
                        }

                        // Admin / manager / ketoan
                        return [
                            col('check'),
                            ...head,
                            col('agency_info'),
                            col('package_info'),
                            col('sale_total'),
                            col('cost_total'),
                            col('profit_total'),
                            col('payment_client'),
                            col('payment_partner'),
                            col('actions'),
                        ];
                    })(),
                    columnDefs: (() => {
                        if (capabilities.isSaleUser) {
                            return [
                                { targets: 1, width: '180px' },
                                { targets: 2, width: '150px' },
                                { targets: 3, width: '170px' },
                                { targets: 4, width: '250px' },
                                { targets: 5, width: '250px' },
                                { targets: 6, width: '280px' },
                                { targets: 7, width: '150px' },
                                { targets: 8, width: '150px' },
                                { targets: 9, width: '100px' },
                                { targets: 10, width: '100px' },
                                { targets: 11, width: '130px' },
                                { targets: 12, width: '130px' },
                                { targets: 13, width: '100px' },
                            ];
                        }
                        return [
                            { targets: 0, width: '52px' },
                            { targets: 1, width: '180px' },
                            { targets: 2, width: '150px' },
                            { targets: 3, width: '170px' },
                            { targets: 4, width: '190px' },
                            { targets: 5, width: '280px' },
                            { targets: 6, width: '280px' },
                            { targets: 7, width: '320px' },
                            { targets: 8, width: '240px' },
                            { targets: 9, width: '140px' },
                            { targets: 10, width: '150px' },
                            { targets: 11, width: '100px' },
                            { targets: 12, width: '130px' },
                            { targets: 13, width: '130px' },
                            { targets: 14, width: '200px' },
                            { targets: 15, width: '200px' },
                            { targets: 16, width: '190px' },
                            { targets: 17, width: 'auto' },
                        ];
                    })(),
                });

                const updateBulkState = () => {
                    document.querySelectorAll('[data-selected-count]').forEach((el) => el.textContent = selected.size);
                    document.querySelectorAll('[data-delete-cancelled]').forEach((el) => el.disabled = selected.size === 0 || !capabilities.canDeleteCancelled);
                };

                const setSpecialStatusPanel = (open) => {
                    const panel = document.querySelector('[data-special-status-panel]');
                    const toggle = document.querySelector('[data-special-status-toggle]');

                    if (!panel || !toggle) {
                        return;
                    }

                    panel.hidden = !open;
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                const reload = () => {
                    selected.clear();
                    document.getElementById('orders-check-all').checked = false;
                    updateBulkState();
                    table.ajax.reload();
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
                    const counts = json?.statusCounts || {};
                    document.querySelectorAll('[data-status-count]').forEach((el) => {
                        const key = el.dataset.statusCount || '';
                        el.textContent = key === 'all' ? (counts.all ?? 0) : (counts[key] ?? 0);
                    });

                    const specialTotal = [...document.querySelectorAll('[data-special-status-tab] [data-status-count]')]
                        .reduce((total, el) => total + Number(counts[el.dataset.statusCount || ''] ?? 0), 0);
                    document.querySelectorAll('[data-special-status-count]').forEach((el) => el.textContent = specialTotal);
                });

                const syncActiveStatus = () => {
                    const currentStatus = field('status')?.value || '';
                    const specialToggle = document.querySelector('[data-special-status-toggle]');
                    const specialActive = !!document.querySelector(`[data-special-status-tab][data-status-tab="${currentStatus}"]`);

                    document.querySelectorAll('[data-status-tab]').forEach((button) => {
                        button.dataset.active = button.dataset.statusTab === currentStatus ? 'true' : 'false';
                    });

                    if (specialToggle) {
                        specialToggle.dataset.active = specialActive ? 'true' : 'false';
                    }

                    if (specialActive) {
                        setSpecialStatusPanel(true);
                    }
                };

                jQuery(tableEl).on('draw.dt', () => {
                    toggleEmptyTableChrome();

                    document.querySelectorAll('.order-check').forEach((checkbox) => {
                        checkbox.checked = selected.has(String(checkbox.value));
                        checkbox.addEventListener('change', () => {
                            checkbox.checked ? selected.add(String(checkbox.value)) : selected.delete(String(checkbox.value));
                            updateBulkState();
                        });
                    });
                });

                document.getElementById('orders-check-all')?.addEventListener('change', (event) => {
                    document.querySelectorAll('.order-check').forEach((checkbox) => {
                        checkbox.checked = event.target.checked;
                        event.target.checked ? selected.add(String(checkbox.value)) : selected.delete(String(checkbox.value));
                    });
                    updateBulkState();
                });

                document.querySelectorAll('[data-status-tab]').forEach((button) => {
                    button.addEventListener('click', () => {
                        field('status').value = button.dataset.statusTab || '';
                        document.querySelectorAll('[data-status-tab]').forEach((tab) => tab.dataset.active = 'false');
                        button.dataset.active = 'true';
                        if (button.dataset.statusTab === '') {
                            setSpecialStatusPanel(false);
                        }
                        if (button.hasAttribute('data-special-status-tab')) {
                            setSpecialStatusPanel(true);
                        }
                        syncActiveStatus();
                        reload();
                    });
                });

                document.getElementById('orders-apply-filter')?.addEventListener('click', () => {
                    syncActiveStatus();
                    reload();
                });
                document.getElementById('orders-reset-filter')?.addEventListener('click', () => {
                    document.querySelectorAll('[data-order-filter], #orders-search').forEach((el) => el.value = '');
                    setTomSelectValue('status', '');
                    setTomSelectValue('saleId', '');
                    setTomSelectValue('customerId', '');
                    setTomSelectValue('serviceId', '');
                    setTomSelectValue('branchId', '');
                    setTomSelectValue('agencyId', '');
                    setTomSelectValue('airlineId', '');
                    setTomSelectValue('transitPartnerId', '');
                    loadCustomersBySale('');
                    setFilterDate('fromDate', defaultDates.fromDate);
                    setFilterDate('toDate', defaultDates.toDate);
                    document.querySelector('[data-status-tab=""]')?.click();
                });
                document.getElementById('orders-search')?.addEventListener('input', debounce(reload, 350));
                document.getElementById('orders-page-size')?.addEventListener('change', (event) => table.page.len(Number(event.target.value)).draw());
                document.querySelectorAll('[data-order-date-preset]').forEach((button) => {
                    button.addEventListener('click', () => setDatePreset(button.dataset.orderDatePreset || '30'));
                });
                field('saleId')?.addEventListener('change', (event) => {
                    setTomSelectValue('customerId', '');
                    loadCustomersBySale(event.target.value || '');
                });

                document.querySelectorAll('[data-bulk-status]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (selected.size === 0) {
                            notify('Vui lòng chọn ít nhất một order trước khi cập nhật trạng thái.');
                            return;
                        }

                        const status = button.dataset.bulkStatus;
                        const label = button.querySelector('span.truncate')?.textContent?.trim() || status;
                        window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
                            title: `Xác nhận: ${label}`,
                            message: `Bạn có chắc chắn muốn chuyển ${selected.size} order sang trạng thái "${label}"?`,
                            variant: 'warning',
                            confirmText: label,
                            onConfirm: () => postJson(routes.bulkStatus, { ids: [...selected], status }).then(reload),
                        }}));
                    });
                });

                document.querySelector('[data-delete-cancelled]')?.addEventListener('click', () => {
                    if (selected.size === 0) {
                        notify('Vui lòng chọn ít nhất một order trước khi xóa.');
                        return;
                    }

                    window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
                        title: 'Xác nhận xóa',
                        message: `Bạn có chắc chắn muốn xóa ${selected.size} order? Chỉ các order còn được phép thao tác sẽ bị xóa. Hành động này không thể hoàn tác.`,
                        variant: 'danger',
                        confirmText: 'Xóa',
                        onConfirm: () => postJson(routes.deleteCancelled, { ids: [...selected] }).then(reload),
                    }}));
                });

                tableEl.addEventListener('click', (event) => {
                    const printButton = event.target.closest('[data-order-print]');
                    if (printButton) {
                        const target = printButton.dataset.orderPrint;
                        const url = printButton.dataset.orderPrintUrl;

                        if (target === 'bill') {
                            pendingPrintUrl = url;
                            document.querySelector('[data-order-print-bill-trigger]')?.click();
                        } else {
                            printOrder(url, 'label');
                        }
                        return;
                    }

                    const cancelButton = event.target.closest('[data-order-cancel]');
                    if (cancelButton) {
                        const id = cancelButton.dataset.orderCancel;
                        window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
                            title: 'Xác nhận hủy đơn',
                            message: 'Bạn có chắc chắn muốn hủy order này?',
                            variant: 'warning',
                            confirmText: 'Hủy đơn',
                            onConfirm: () => postJson(routes.bulkStatus, { ids: [id], status: 'huy' }).then(reload),
                        }}));
                        return;
                    }

                    const deleteButton = event.target.closest('[data-order-delete]');
                    if (deleteButton) {
                        const id = deleteButton.dataset.orderDelete;
                        window.dispatchEvent(new CustomEvent('open-confirm', { detail: {
                            title: 'Xác nhận xóa',
                            message: 'Bạn có chắc chắn muốn xóa order này? Hành động này không thể hoàn tác.',
                            variant: 'danger',
                            confirmText: 'Xóa',
                            onConfirm: () => postJson(routes.deleteCancelled, { ids: [id] }).then(reload),
                        }}));
                    }
                });

                document.getElementById('orders-export')?.addEventListener('click', () => {
                    const params = new URLSearchParams(filters());
                    params.set('search[value]', document.getElementById('orders-search')?.value || '');
                    window.location.href = `${routes.export}?${params.toString()}`;
                });

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

                function emptyStateHtml(
                    title = 'Chưa có order nào',
                    text = 'Không có dữ liệu phù hợp với bộ lọc hiện tại. Hãy thử đổi bộ lọc hoặc tạo order mới.'
                ) {
                    return `
                        <div class="order-empty-state">
                            <div class="order-empty-state-icon">
                                <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632A2.25 2.25 0 0117.379 20.25H6.621a2.25 2.25 0 01-2.246-2.118L3.75 7.5M9 11.25h6M9 15h6M8.25 7.5V5.25A2.25 2.25 0 0110.5 3h3A2.25 2.25 0 0115.75 5.25V7.5" />
                                </svg>
                            </div>
                            <div>
                                <p class="order-empty-state-title">${title}</p>
                                <p class="order-empty-state-text">${text}</p>
                            </div>
                        </div>
                    `;
                }

                function notify(message) {
                    if (window.Livewire?.dispatch) {
                        window.Livewire.dispatch('notify', { message });
                        return;
                    }
                    window.alert(message);
                }

                function debounce(fn, wait) {
                    let timer;
                    return (...args) => {
                        clearTimeout(timer);
                        timer = setTimeout(() => fn(...args), wait);
                    };
                }

                function setTomSelectValue(name, value) {
                    const select = field(name);
                    if (!select) return;

                    select.value = value;
                    select.tomselect?.setValue(value, true);
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

                function loadCustomersBySale(saleId) {
                    const customerSelect = field('customerId');
                    if (!customerSelect || !routes.customers) return;

                    const requestId = ++customerRequestId;
                    const params = new URLSearchParams();
                    if (saleId) params.set('saleId', saleId);

                    customerSelect.tomselect?.disable();

                    fetch(`${routes.customers}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                    })
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
                    if (select.tomselect) {
                        select.tomselect.clear(true);
                        select.tomselect.clearOptions();
                        select.tomselect.addOption({ value: '', text: 'Tất cả khách hàng' });
                        customers.forEach((customer) => {
                            select.tomselect.addOption({ value: String(customer.id), text: customer.label });
                        });
                        select.tomselect.refreshOptions(false);
                        select.tomselect.setValue('', true);
                    }
                }
                updateBulkState();
                syncActiveStatus();
            };

            function bindSpecialStatusToggle() {
                const panel = document.querySelector('[data-special-status-panel]');
                const toggle = document.querySelector('[data-special-status-toggle]');

                if (!panel || !toggle || toggle.dataset.ready === 'true') {
                    return;
                }

                toggle.dataset.ready = 'true';
                toggle.addEventListener('click', () => {
                    const open = panel.hidden;
                    panel.hidden = !open;
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
            }

            const scheduleOrderIndexInit = (attempt = 0) => {
                initOrderIndex();

                const tableEl = document.getElementById('orders-table');
                if (tableEl?.dataset.ready === 'true' || attempt >= 20) {
                    return;
                }

                setTimeout(() => scheduleOrderIndexInit(attempt + 1), 100);
            };

            document.addEventListener('DOMContentLoaded', scheduleOrderIndexInit);
            document.addEventListener('livewire:navigated', () => scheduleOrderIndexInit());
            scheduleOrderIndexInit();
        })();
    </script>
@endpush
