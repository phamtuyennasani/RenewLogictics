<div
    id="order-index-page"
    class="space-y-5"
    data-routes='@json($routes)'
    data-capabilities='@json($capabilities)'
>
    @include('pages.order.⚡index.partials.header')
    @include('pages.order.⚡index.partials.bulk-actions')
    @include('pages.order.⚡index.partials.filter-panel')
    @include('pages.order.⚡index.partials.table')
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
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/dataTables.tailwindcss.js') }}"></script>
    <script>
        (() => {
            const initOrderIndex = () => {
                const root = document.getElementById('order-index-page');
                const tableEl = document.getElementById('orders-table');
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

                const field = (name) => document.querySelector(`[data-order-filter="${name}"]`);
                const filters = () => ({
                    status: field('status')?.value || '',
                    fromDate: field('fromDate')?.value || '',
                    toDate: field('toDate')?.value || '',
                    saleId: field('saleId')?.value || '',
                    customerId: field('customerId')?.value || '',
                    serviceId: field('serviceId')?.value || '',
                    branchId: field('branchId')?.value || '',
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
                    ajax: {
                        url: routes.datatable,
                        data: (data) => Object.assign(data, filters(), {
                            search: { value: document.getElementById('orders-search')?.value || '' },
                        }),
                    },
                    order: [],
                    language: { url: '{{ asset('assets/datatables/vi.json') }}' },
                    columns: [
                        { data: 'check', orderable: false, searchable: false },
                        { data: 'order_code', name: 'id_bill',orderable: false, searchable: false },
                        { data: 'status_badge', name: 'bill_status', orderable: false, searchable: false },
                        { data: 'dates', name: 'created_at', orderable: false, searchable: false },
                        { data: 'assignee', orderable: false, searchable: false },
                        { data: 'sender_info', orderable: false, searchable: false },
                        { data: 'receiver_info', orderable: false, searchable: false },
                        { data: 'service_info', orderable: false, searchable: false },
                        { data: 'package_info', orderable: false, searchable: false },
                        { data: 'sale_total', orderable: false, searchable: false },
                        { data: 'cost_total', orderable: false, searchable: false },
                        { data: 'profit_total', orderable: false, searchable: false },
                        { data: 'payment_state', orderable: false, searchable: false },
                        { data: 'actions', orderable: false, searchable: false },
                    ],
                });

                const updateBulkState = () => {
                    document.querySelectorAll('[data-selected-count]').forEach((el) => el.textContent = selected.size);
                    document.querySelectorAll('[data-delete-cancelled]').forEach((el) => el.disabled = selected.size === 0 || !capabilities.canDeleteCancelled);
                };

                const reload = () => {
                    selected.clear();
                    document.getElementById('orders-check-all').checked = false;
                    updateBulkState();
                    table.ajax.reload();
                };

                jQuery(tableEl).on('xhr.dt', (_event, _settings, json) => {
                    const counts = json?.statusCounts || {};
                    document.querySelectorAll('[data-status-count]').forEach((el) => {
                        const key = el.dataset.statusCount || '';
                        el.textContent = counts[key] ?? counts.all ?? 0;
                    });
                });

                const syncActiveStatus = () => {
                    const currentStatus = field('status')?.value || '';
                    document.querySelectorAll('[data-status-tab]').forEach((button) => {
                        button.dataset.active = button.dataset.statusTab === currentStatus ? 'true' : 'false';
                    });
                };

                jQuery(tableEl).on('draw.dt', () => {
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
                    document.querySelector('[data-status-tab=""]')?.click();
                });
                document.getElementById('orders-search')?.addEventListener('input', debounce(reload, 350));
                document.getElementById('orders-page-size')?.addEventListener('change', (event) => table.page.len(Number(event.target.value)).draw());

                document.querySelectorAll('[data-bulk-status]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (selected.size === 0) {
                            notify('Vui lòng chọn ít nhất một order trước khi cập nhật trạng thái.');
                            return;
                        }

                        postJson(routes.bulkStatus, { ids: [...selected], status: button.dataset.bulkStatus }).then(reload);
                    });
                });

                document.querySelector('[data-delete-cancelled]')?.addEventListener('click', () => {
                    if (selected.size === 0) {
                        notify('Vui lòng chọn ít nhất một order đã hủy trước khi xóa.');
                        return;
                    }

                    postJson(routes.deleteCancelled, { ids: [...selected] }).then(reload);
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

                updateBulkState();
                syncActiveStatus();
            };

            document.addEventListener('DOMContentLoaded', initOrderIndex);
            document.addEventListener('livewire:navigated', initOrderIndex);
            setTimeout(initOrderIndex, 0);
        })();
    </script>
@endpush
