<div
    id="order-index-page"
    class="space-y-5"
    data-routes='@json($routes)'
    data-capabilities='@json($capabilities)'
>
    @include('pages.order.⚡index.partials.header')
    @if (! in_array(($capabilities['role'] ?? null), ['sale', 'ops'], true))
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

@assets
    <link rel="stylesheet" href="{{ asset('assets/datatables/dataTables.tailwindcss.css') }}">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/dataTables.tailwindcss.js') }}"></script>
@endassets
@push('scripts')
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
                const role = capabilities.role || '';
                const selected = new Set();
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                let customerRequestId = 0;
                let pendingPrintUrl = '';
                let appliedStatus = field('status')?.value || '';

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
                    pickupStatus: field('pickupStatus')?.value || '',
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

                        if (role === 'sale') {
                            return [
                                ...head,
                                col('package_info'),
                                col('sale_total'),
                                col('sale_commission'),
                                col('payment_client'),
                                col('actions'),
                            ];
                        }
                        if (role === 'ctv') {
                            return [
                                ...head,
                                col('package_info'),
                                col('sale_total'),
                                col('sale_commission'),
                                col('payment_client'),
                                col('actions'),
                            ];
                        }

                        if (role === 'ops') {
                            return [
                                ...head,
                                col('package_info'),
                                col('actions'),
                            ];
                        }

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
                    columnDefs: (() => {})(),
                });

                const updateBulkState = () => {
                    document.querySelectorAll('[data-selected-count]').forEach((el) => el.textContent = selected.size);
                    document.querySelectorAll('[data-delete-cancelled]').forEach((el) => el.disabled = selected.size === 0 || !capabilities.canDeleteCancelled);
                    const currentStatus = appliedStatus;
                    const allowedBulkStatusByFilter = {
                        da_xac_nhan: 'da_nhan_hang',
                        da_nhan_hang: 'duyet_xuat_hang',
                        duyet_xuat_hang: 'dang_phat_hang',
                    };
                    const allowedBulkStatus = allowedBulkStatusByFilter[currentStatus] || '';

                    document.querySelectorAll('[data-bulk-status]').forEach((button) => {
                        const status = button.dataset.bulkStatus || '';
                        const isSequentialAction = ['da_nhan_hang', 'duyet_xuat_hang', 'dang_phat_hang'].includes(status);

                        if (isSequentialAction) {
                            button.disabled = selected.size === 0 || status !== allowedBulkStatus;
                        } else {
                            button.disabled = selected.size === 0;
                        }
                    });
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
                    appliedStatus = field('status')?.value || '';
                    selected.clear();
                    const checkAll = document.getElementById('orders-check-all');
                    if (checkAll) checkAll.checked = false;
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
                        setTomSelectValue('status', button.dataset.statusTab || '');
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
                    setTomSelectValue('pickupStatus', '');
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
