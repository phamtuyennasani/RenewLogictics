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

    const isCongNoDataTableReady = (tableEl) => {
        return !!(
            tableEl
            && window.jQuery
            && jQuery.fn.DataTable
            && jQuery.fn.DataTable.isDataTable(tableEl)
        );
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
        const datatablesLanguageUrl = root?.getAttribute('data-datatables-language-url')
            || root?.dataset.datatablesLanguageUrl
            || '/assets/datatables/vi.json';
        initDatePickers();
        if (root) window.TomSelectHelper?.init(root);
        bindCreateModalControls(root);

        if (!root || !tableEl) {
            markReady();
            return;
        }

        if (!window.jQuery || !jQuery.fn.DataTable) {
            return;
        }

        if (tableEl.dataset.ready === 'true') {
            if (isCongNoDataTableReady(tableEl)) {
                markReady();
                return;
            }

            tableEl.dataset.ready = 'false';
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
                url: datatablesLanguageUrl,
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
                { data: 'actions', orderable: false, searchable: false },
            ],
            columnDefs: [
                { targets: 0, width: '40px' },
                { targets: 1, width: '100px' },
                { targets: 2, width: '100px' },
                { targets: 3, width: '200px' },
                { targets: 4, width: '300px' },
                { targets: 5, width: '200px' },
                { targets: 6, width: '100px' },
                { targets: 7, width: '100px' },
                { targets: 8, width: '100px' },
                { targets: 9, width: '100px' },
            ],
            initComplete: markReady,
        });
        markReady();

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
        if (tableEl?.dataset.ready === 'true' && isCongNoDataTableReady(tableEl)) {
            return;
        }

        if (attempt >= 20) {
            markReady();
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
