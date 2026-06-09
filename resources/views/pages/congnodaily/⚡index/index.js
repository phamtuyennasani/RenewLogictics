(() => {
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    const defaultDates = { fromDate: formatDate(thirtyDaysAgo), toDate: formatDate(today) };
    const field = (name) => document.querySelector(`[data-daily-debt-filter="${name}"]`);
    let datePickerRetryCount = 0;
    const markReady = () => {
        const root = document.getElementById('congnodaily-index-page');
        if (!root) return;
        requestAnimationFrame(() => requestAnimationFrame(() => root.dataset.ready = 'true'));
    };

    const isDailyDebtDataTableReady = (tableEl) => {
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
        const datatablesLanguageUrl = root?.getAttribute('data-datatables-language-url')
            || root?.dataset.datatablesLanguageUrl
            || '/assets/datatables/vi.json';
        initDatePickers();
        if (root) window.TomSelectHelper?.init(root);
        bindDailyCreateModalControls(root);
        if (!root || !tableEl) {
            markReady();
            return;
        }

        if (!window.jQuery || !jQuery.fn.DataTable) {
            return;
        }

        if (tableEl.dataset.ready === 'true') {
            if (isDailyDebtDataTableReady(tableEl)) {
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
            order: [],
            language: {
                url: datatablesLanguageUrl,
                processing: 'Đang tải dữ liệu',
                emptyTable: emptyStateHtml(),
                zeroRecords: emptyStateHtml('Không có công nợ đại lý phù hợp', 'Thử đổi từ khóa hoặc nới rộng bộ lọc.'),
            },
            columns: [
                { data: 'check', orderable: false, searchable: false },
                { data: 'debt_code', orderable: false, searchable: false },
                { data: 'status_badge', orderable: false, searchable: false },
                { data: 'daily_info', orderable: false, searchable: false },
                { data: 'creator_info', orderable: false, searchable: false },
                { data: 'period_info', orderable: false, searchable: false },
                { data: 'volume_info', orderable: false, searchable: false },
                { data: 'total_amount', orderable: false, searchable: false },
                { data: 'actions', orderable: false, searchable: false },
            ],
            columnDefs: [
                { targets: 0, width: '40px' },
                { targets: 1, width: '150px' },
                { targets: 2, width: '130px' },
                { targets: 3, width: '180px' },
                { targets: 4, width: '100px' },
                { targets: 5, width: '180px' },
                { targets: 6, width: '100px' },
                { targets: 7, width: '100px' },
                { targets: 8, width: '100px' },
            ],
            initComplete: markReady,
        });
        markReady();

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
            setBar('total', summary.total_percent || 0);
            setBar('paid', summary.paid_percent || 0);
            setBar('remaining', summary.remaining_percent || 0);
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
    const scheduleDailyDebtIndexInit = (attempt = 0) => {
        initDailyDebtIndex();

        const tableEl = document.getElementById('daily-debts-table');
        if (tableEl?.dataset.ready === 'true' && isDailyDebtDataTableReady(tableEl)) {
            return;
        }

        if (attempt >= 20) {
            markReady();
            return;
        }

        setTimeout(() => scheduleDailyDebtIndexInit(attempt + 1), 100);
    };

    document.addEventListener('DOMContentLoaded', scheduleDailyDebtIndexInit);
    document.addEventListener('livewire:navigated', () => scheduleDailyDebtIndexInit());
    document.addEventListener('livewire:initialized', () => Livewire.hook('morph.updated', () => setTimeout(() => scheduleDailyDebtIndexInit(), 0)));
    scheduleDailyDebtIndexInit();
})();
