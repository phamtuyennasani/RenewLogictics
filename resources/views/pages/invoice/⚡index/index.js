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

    const isInvoiceDataTableReady = (tableEl) => {
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
        const enabledProviders = root ? JSON.parse(root.dataset.enabledProviders || '{}') : {};
        const allProviders = root ? JSON.parse(root.dataset.allProviders || '[]') : [];
        const datatablesLanguageUrl = root?.getAttribute('data-datatables-language-url')
            || root?.dataset.datatablesLanguageUrl
            || '/assets/datatables/vi.json';
        initDatePickers();
        if (root) window.TomSelectHelper?.init(root);

        if (!root || !tableEl) {
            markReady();
            return;
        }

        if (!window.jQuery || !jQuery.fn.DataTable) {
            return;
        }

        if (tableEl.dataset.ready === 'true') {
            if (isInvoiceDataTableReady(tableEl)) {
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

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const detailModal = document.getElementById('invoice-detail-modal');

        // Hide disabled provider cards
        if (detailModal) {
            detailModal.querySelectorAll('[data-detail-provider-card]').forEach((el) => {
                const provider = el.dataset.detailProviderCard;
                if (enabledProviders[provider] === false) {
                    el.style.display = 'none';
                }
            });
        }
        const defaultProvider = allProviders.find(p => enabledProviders[p]) || allProviders[0] || 'sepay';
        const cashModal = document.getElementById('invoice-cash-modal');
        const cashForm = document.getElementById('invoice-cash-form');
        const cashState = { invoiceId: null };
        const cancelModal = document.getElementById('invoice-cancel-modal');
        const cancelForm = document.getElementById('invoice-cancel-form');
        const cancelState = { invoiceId: null };
        const deleteModal = document.getElementById('invoice-delete-modal');
        const deleteForm = document.getElementById('invoice-delete-form');
        const deleteState = { invoiceId: null };
        const rejectModal = document.getElementById('invoice-reject-modal');
        const rejectForm = document.getElementById('invoice-reject-form');
        const rejectState = { invoiceId: null };
        const markPaidModal = document.getElementById('invoice-mark-paid-modal');
        const markPaidForm = document.getElementById('invoice-mark-paid-form');
        const markPaidState = { invoiceId: null };
        const resetChannelModal = document.getElementById('invoice-reset-channel-modal');
        const resetChannelForm = document.getElementById('invoice-reset-channel-form');
        const resetChannelState = { invoiceId: null };
        const detailState = { invoice: null, method: null, provider: null, regenerateTimer: null };

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
                url: datatablesLanguageUrl,
                processing: 'Đang tải dữ liệu',
                emptyTable: emptyStateHtml(),
                zeroRecords: emptyStateHtml('Không có hóa đơn phù hợp', 'Thử đổi từ khóa hoặc nới rộng bộ lọc.'),
            },
            columns: [
                { data: 'invoice_code', orderable: false, searchable: false },
                { data: 'debt_code', orderable: false, searchable: false },
                { data: 'status_badge', orderable: false, searchable: false },
                { data: 'customer_info', orderable: false, searchable: false },
                { data: 'sale_info', orderable: false, searchable: false },
                { data: 'amount', orderable: false, searchable: false },
                { data: 'date_timeline', orderable: false, searchable: false },
                { data: 'creator', orderable: false, searchable: false },
                { data: 'actions', orderable: false, searchable: false },
            ],
            columnDefs: [
                { targets: 0, width: '160px' },
                { targets: 1, width: '140px' },
                { targets: 2, width: '150px' },
                { targets: 3, width: '240px' },
                { targets: 4, width: '150px' },
                { targets: 5, width: '100px'},
                { targets: 6, width: '200px' },
                { targets: 7, width: '120px' },
                { targets: 8, width: '100px' },
            ],
            initComplete: markReady,
        });
        markReady();

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
            const btn = event.target.closest('[data-invoice-detail]');
            if (!btn) return;

            const rowData = table.row(btn.closest('tr')).data();
            openDetailModal(rowData?.detail_payload || null);
        });

        detailModal?.addEventListener('click', (event) => {
            if (event.target.closest('[data-detail-copy-link]')) {
                copyDetailPaymentLink();
                return;
            }

            if (event.target.closest('[data-detail-download-qr]')) {
                downloadDetailQr();
                return;
            }

            const methodCard = event.target.closest('[data-detail-payment-method]');
            if (methodCard) {
                event.preventDefault();
                setDetailMethod(methodCard.dataset.detailPaymentMethod || null);
                return;
            }

            const providerCard = event.target.closest('[data-detail-provider-card]');
            if (providerCard) {
                setDetailProvider(providerCard.dataset.detailProviderCard || null);
                return;
            }

            const momoRequestCard = event.target.closest('[data-detail-momo-request-card]');
            if (momoRequestCard) {
                setDetailMomoRequestType(momoRequestCard.dataset.detailMomoRequestCard || 'captureWallet');
                return;
            }

            const action = event.target.closest('[data-detail-action]')?.dataset.detailAction;
            if (action) handleDetailAction(action, event.target.closest('[data-detail-action]'));
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
            const btn = event.target.closest('[data-invoice-qr-sepay], [data-invoice-qr-momo], [data-invoice-qr-vnpay]');
            if (!btn) return;

            const invoiceId = btn.dataset.invoiceQrSepay || btn.dataset.invoiceQrMomo || btn.dataset.invoiceQrVnpay;
            const provider = btn.dataset.invoiceQrVnpay ? 'vnpay' : (btn.dataset.invoiceQrMomo ? 'momo' : 'sepay');
            btn.disabled = true;

            postJson(`${routes.qr}/${invoiceId}/qr`, { provider })
                .then((payload) => {
                    notify(payload.message || 'Đã tạo yêu cầu thanh toán online.');
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

        root.addEventListener('click', (event) => {
            const btn = event.target.closest('[data-invoice-reject-payment]');
            if (!btn) return;

            openRejectModal(btn);
        });

        document.getElementById('invoice-detail-close')?.addEventListener('click', closeDetailModal);
        detailModal?.addEventListener('click', (event) => {
            if (event.target === detailModal) closeDetailModal();
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
        document.getElementById('invoice-delete-close')?.addEventListener('click', closeDeleteModal);
        document.getElementById('invoice-delete-dismiss')?.addEventListener('click', closeDeleteModal);
        deleteModal?.addEventListener('click', (event) => {
            if (event.target === deleteModal) closeDeleteModal();
        });
        document.getElementById('invoice-reject-close')?.addEventListener('click', closeRejectModal);
        document.getElementById('invoice-reject-dismiss')?.addEventListener('click', closeRejectModal);
        rejectModal?.addEventListener('click', (event) => {
            if (event.target === rejectModal) closeRejectModal();
        });
        document.getElementById('invoice-mark-paid-close')?.addEventListener('click', closeMarkPaidModal);
        document.getElementById('invoice-mark-paid-dismiss')?.addEventListener('click', closeMarkPaidModal);
        markPaidModal?.addEventListener('click', (event) => {
            if (event.target === markPaidModal) closeMarkPaidModal();
        });
        document.getElementById('invoice-reset-channel-close')?.addEventListener('click', closeResetChannelModal);
        document.getElementById('invoice-reset-channel-dismiss')?.addEventListener('click', closeResetChannelModal);
        resetChannelModal?.addEventListener('click', (event) => {
            if (event.target === resetChannelModal) closeResetChannelModal();
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
                    closeDetailModal();
                    reload();
                })
                .catch((err) => {
                    setCancelError(err?.message || 'Không thể hủy hóa đơn. Vui lòng thử lại.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
        });

        deleteForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!deleteState.invoiceId) return;

            const submitButton = deleteForm.querySelector('button[type="submit"]');
            const password = deleteForm.querySelector('input[name="password"]')?.value || '';
            if (!password) {
                setDeleteError('Vui lòng nhập mật khẩu để xác nhận.');
                return;
            }
            submitButton.disabled = true;
            setDeleteError('');

            deleteJson(`${routes.destroy}/${deleteState.invoiceId}`, { password })
                .then((payload) => {
                    notify(payload.message || 'Đã xóa hóa đơn.');
                    closeDeleteModal();
                    closeDetailModal();
                    reload();
                })
                .catch((err) => {
                    setDeleteError(err?.message || 'Không thể xóa hóa đơn. Vui lòng thử lại.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
        });

        rejectForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!rejectState.invoiceId) return;

            const submitButton = rejectForm.querySelector('button[type="submit"]');
            const formData = new FormData(rejectForm);
            submitButton.disabled = true;
            setRejectError('');

            postForm(`${routes.rejectCash}/${rejectState.invoiceId}/reject-cash`, formData)
                .then((payload) => {
                    notify(payload.message || 'Đã từ chối chứng từ thanh toán.');
                    closeRejectModal();
                    closeDetailModal();
                    reload();
                })
                .catch((err) => {
                    setRejectError(err?.message || 'Không thể từ chối chứng từ. Vui lòng thử lại.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
        });

        markPaidForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!markPaidState.invoiceId) return;

            const submitButton = markPaidForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setMarkPaidError('');

            postJson(`${routes.markPaid}/${markPaidState.invoiceId}/mark-paid`, {})
                .then((payload) => {
                    notify(payload.message || 'Đã xác nhận thanh toán.');
                    closeMarkPaidModal();
                    closeDetailModal();
                    reload();
                })
                .catch((err) => {
                    setMarkPaidError(err?.message || 'Không thể xác nhận thanh toán. Vui lòng thử lại.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                });
        });

        resetChannelForm?.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!resetChannelState.invoiceId) return;

            const submitButton = resetChannelForm.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            setResetChannelError('');

            postJson(`${routes.resetPaymentChannel}/${resetChannelState.invoiceId}/reset-payment-channel`, {})
                .then((payload) => {
                    notify(payload.message || 'Đã reset hóa đơn về trạng thái Đã duyệt.');
                    closeResetChannelModal();
                    closeDetailModal();
                    reload();
                })
                .catch((err) => {
                    setResetChannelError(err?.message || 'Không thể reset kênh thanh toán. Vui lòng thử lại.');
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
            document.querySelector('[data-summary-money="awaiting"]')?.replaceChildren(document.createTextNode(money(summary.awaiting || 0)));
            setBar('paid', summary.paid_percent || 0);
            setBar('pending', summary.pending_percent || 0);
            setBar('awaiting', summary.awaiting_percent || 0);
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

        function openDetailModal(invoice) {
            if (!detailModal || !invoice) return;

            stopDetailRegenerateCountdown();
            detailState.invoice = invoice;
            detailState.method = null;
            detailState.provider = null;
            setDetailError('');
            setDetailPaymentResult(invoice.payment || {});
            renderDetailInfo(invoice);
            renderDetailWorkflow(invoice);
            detailModal.classList.remove('hidden');
            detailModal.classList.add('flex');
        }

        function closeDetailModal() {
            if (!detailModal) return;

            stopDetailRegenerateCountdown();
            detailState.invoice = null;
            detailState.method = null;
            detailState.provider = null;
            detailModal.querySelector('[data-detail-cash-form]')?.reset();
            setDetailError('');
            detailModal.classList.add('hidden');
            detailModal.classList.remove('flex');
        }

        function renderDetailInfo(invoice) {
            setDetailText('[data-detail-modal-code]', invoice.invoice_code || '-');
            setDetailText('[data-detail-overview-code]', invoice.invoice_code || '-');
            setDetailText('[data-detail-summary-code]', invoice.invoice_code || '-');
            setDetailText('[data-detail-debt-code]', invoice.debt_code || '-');
            setDetailText('[data-detail-amount]', invoice.amount_text || money(invoice.amount || 0));
            setDetailText('[data-detail-customer-company]', invoice.customer?.company || '-');
            setDetailText('[data-detail-customer-contact]', invoice.customer?.contact || '-');
            setDetailText('[data-detail-customer-phone]', invoice.customer?.phone || '-');
            setDetailText('[data-detail-customer-email]', invoice.customer?.email || '-');
            setDetailText('[data-detail-customer-address]', invoice.customer?.address || '-');
            setDetailText('[data-detail-created-at]', invoice.dates?.created || '-');
            setDetailText('[data-detail-approved-at]', invoice.dates?.approved || '-');
            setDetailText('[data-detail-paid-at]', invoice.dates?.paid || '-');
            renderDetailCashProof(invoice);
            renderDetailTransaction(invoice);

            const statusEl = detailModal.querySelector('[data-detail-status]');
            if (statusEl) {
                statusEl.className = `inline-flex rounded-md px-2.5 py-1 text-xs font-bold ${invoice.status_class || 'bg-neutral-100 text-neutral-700'}`;
                statusEl.replaceChildren(document.createTextNode(invoice.status_label || '-'));
            }
        }

        function renderDetailWorkflow(invoice) {
            stopDetailRegenerateCountdown();
            detailModal.querySelectorAll('[data-detail-section]').forEach((el) => el.classList.add('hidden'));
            detailModal.querySelectorAll('[data-detail-action]').forEach((el) => {
                el.hidden = true;
                el.disabled = false;
            });

            const status = invoice.status;
            setDetailText('[data-detail-next-step]', detailNextStep(status, invoice));
            toggleDetailAction('close', true);

            // Nút xóa cứng (admin) — hiện ở mọi trạng thái khi đủ điều kiện xóa.
            toggleDetailAction('delete', !!invoice.permissions?.delete);

            if (status === 'cho_duyet') {
                toggleDetailAction('approve', true);
                toggleDetailAction('cancel', true);
                return;
            }

            if (status === 'da_duyet' || status === 'khong_chap_nhan') {
                detailModal.querySelector('[data-detail-section="approved"]')?.classList.remove('hidden');
                toggleDetailAction('cancel', true);
                setDetailMethod(null);
                return;
            }

            if (status === 'da_gui_hoa_don_tt') {
                if (invoice.permissions?.confirm_cash) toggleDetailAction('confirm-cash', true);
                toggleDetailAction('reject', true);
                if (invoice.permissions?.reset_payment_channel) toggleDetailAction('reset-payment-channel', true);
                return;
            }

            if (status === 'da_gui_yeu_cau_tt') {
                toggleDetailAction('regenerate', true);
                toggleDetailAction('cancel', !!invoice.payment?.can_regenerate);
                toggleDetailAction('reset-payment-channel', !!invoice.permissions?.reset_payment_channel && !!invoice.payment?.can_regenerate);
                toggleDetailAction('mark-paid', !!invoice.permissions?.admin_mark_paid);
                const button = detailModal.querySelector('[data-detail-action="regenerate"]');
                if (button && invoice.payment && !invoice.payment.can_regenerate) {
                    startDetailRegenerateCountdown(button, invoice);
                } else if (button) {
                    button.disabled = false;
                    button.textContent = 'Tạo lại mã thanh toán';
                }
            }
        }

        function startDetailRegenerateCountdown(button, invoice) {
            const target = invoice.payment?.next_regenerate_at_iso
                ? new Date(invoice.payment.next_regenerate_at_iso).getTime()
                : null;

            if (!target || Number.isNaN(target)) {
                button.disabled = true;
                button.textContent = invoice.payment?.next_regenerate_at
                    ? `Tạo lại sau ${invoice.payment.next_regenerate_at}`
                    : 'Chưa thể tạo lại';
                return;
            }

            const tick = () => {
                const remaining = target - Date.now();
                if (remaining <= 0) {
                    stopDetailRegenerateCountdown();
                    invoice.payment.can_regenerate = true;
                    button.disabled = false;
                    button.textContent = 'Tạo lại mã thanh toán';
                    toggleDetailAction('cancel', true);
                    toggleDetailAction('reset-payment-channel', !!invoice.permissions?.reset_payment_channel);
                    setDetailText('[data-detail-next-step]', detailNextStep(invoice.status, invoice));
                    return;
                }

                button.disabled = true;
                button.textContent = `Tạo lại sau ${formatCountdown(remaining)}`;
            };

            tick();
            detailState.regenerateTimer = window.setInterval(tick, 1000);
        }

        function stopDetailRegenerateCountdown() {
            if (!detailState.regenerateTimer) return;

            window.clearInterval(detailState.regenerateTimer);
            detailState.regenerateTimer = null;
        }

        function formatCountdown(milliseconds) {
            const totalSeconds = Math.max(0, Math.ceil(milliseconds / 1000));
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }

        function detailNextStep(status, invoice) {
            if (status === 'cho_duyet') return 'Kiểm tra thông tin và duyệt hoặc hủy hóa đơn.';
            if (status === 'da_duyet' || status === 'khong_chap_nhan') return 'Chọn hình thức thanh toán và gửi xác nhận hoặc tạo mã thanh toán.';
            if (status === 'da_gui_hoa_don_tt') return 'Đang chờ duyệt bằng chứng thanh toán tiền mặt.';
            if (status === 'da_gui_yeu_cau_tt') {
                return invoice.payment?.can_regenerate
                    ? 'Có thể tạo lại mã thanh toán hoặc hủy hóa đơn.'
                    : `Đã gửi yêu cầu thanh toán. Có thể tạo lại sau ${invoice.payment?.next_regenerate_at || '15 phút'}.`;
            }
            if (status === 'da_thanh_toan') return 'Hóa đơn đã thanh toán, chỉ cần theo dõi lưu trữ.';
            if (status === 'huy') return 'Hóa đơn đã hủy, không còn thao tác xử lý.';
            return 'Theo dõi trạng thái và xử lý theo quyền hiện tại.';
        }

        function setDetailMethod(method) {
            detailState.method = method;
            syncDetailMethodCards(method);
            requestAnimationFrame(() => syncDetailMethodCards(method));

            detailModal.querySelector('[data-detail-cash-form]')?.classList.toggle('hidden', method !== 'cash');
            detailModal.querySelector('[data-detail-online-panel]')?.classList.toggle('hidden', method !== 'online');
            toggleDetailAction('cash-submit', method === 'cash');
            toggleDetailAction('online-submit', method === 'online');
            if (method === 'online' && !detailState.provider) setDetailProvider(defaultProvider);
        }

        function syncDetailMethodCards(method) {
            detailModal.querySelectorAll('[data-detail-payment-method]').forEach((el) => {
                const checked = el.dataset.detailPaymentMethod === method;
                el.dataset.checked = checked ? 'true' : 'false';
                el.setAttribute('aria-checked', checked ? 'true' : 'false');
                el.querySelectorAll('[role="radio"]').forEach((radio) => {
                    radio.setAttribute('aria-checked', checked ? 'true' : 'false');
                    radio.dataset.checked = checked ? 'true' : 'false';
                });
                el.querySelectorAll('input[type="radio"]').forEach((input) => {
                    input.checked = checked;
                    input.toggleAttribute('checked', checked);
                });
            });
        }

        function setDetailProvider(provider) {
            detailState.provider = provider;
            detailModal.querySelectorAll('[data-detail-provider-card]').forEach((el) => {
                const active = el.dataset.detailProviderCard === provider;
                el.classList.toggle('border-primary-500', active);
                el.classList.toggle('ring-2', active);
                el.classList.toggle('ring-primary-100', active);
                el.querySelector('input[type="radio"]')?.toggleAttribute('checked', active);
            });
            detailModal.querySelector('[data-detail-momo-request]')?.classList.toggle('hidden', provider !== 'momo');
            if (provider !== 'momo') {
                setDetailMomoRequestType('captureWallet');
            }
        }

        function setDetailMomoRequestType(requestType) {
            const value = requestType || 'captureWallet';
            const hidden = detailModal.querySelector('[data-detail-momo-request-type]');
            if (hidden) hidden.value = value;
            detailModal.querySelectorAll('[data-detail-momo-request-card]').forEach((el) => {
                const active = el.dataset.detailMomoRequestCard === value;
                el.classList.toggle('border-primary-500', active);
                el.classList.toggle('ring-2', active);
                el.classList.toggle('ring-primary-100', active);
                el.classList.toggle('border-neutral-200', !active);
                el.querySelector('input[type="radio"]')?.toggleAttribute('checked', active);
            });
        }

        function handleDetailAction(action, button) {
            const invoice = detailState.invoice;
            if (!invoice) return;

            if (action === 'close') {
                closeDetailModal();
                return;
            }

            if (action === 'cancel') {
                openCancelModal(detailDataset('invoiceCancel'));
                return;
            }

            if (action === 'delete') {
                openDeleteModal(detailDataset('invoiceDelete'));
                return;
            }

            if (action === 'reject') {
                openRejectModal(detailDataset('invoiceRejectPayment'));
                return;
            }

            if (action === 'approve') {
                runDetailJson(button, `${routes.approve}/${invoice.id}/approve`, {}, 'Đã duyệt hóa đơn.');
                return;
            }

            if (action === 'cash-submit') {
                const form = detailModal.querySelector('[data-detail-cash-form]');
                if (!form) return;
                runDetailForm(button, `${routes.cash}/${invoice.id}/cash`, new FormData(form), 'Đã gửi xác nhận thanh toán.');
                return;
            }

            if (action === 'confirm-cash') {
                if (!window.confirm('Xác nhận đã thu tiền mặt cho hóa đơn này?')) return;
                runDetailJson(button, `${routes.confirmCash}/${invoice.id}/confirm-cash`, {}, 'Đã xác nhận thanh toán.');
                return;
            }

            if (action === 'online-submit') {
                const provider = detailState.provider || 'sepay';
                const payload = { provider };
                if (provider === 'momo') {
                    payload.request_type = detailModal.querySelector('[data-detail-momo-request-type]')?.value || 'captureWallet';
                }
                runDetailJson(button, `${routes.qr}/${invoice.id}/qr`, payload, 'Đã tạo yêu cầu thanh toán online.', true);
                return;
            }

            if (action === 'regenerate') {
                runDetailJson(button, `${routes.regenerateQr}/${invoice.id}/regenerate-qr`, {}, 'Đã tạo lại yêu cầu thanh toán online.', true);
                return;
            }

            if (action === 'reset-payment-channel') {
                openResetChannelModal(detailDataset('invoiceResetChannel'));
                return;
            }

            if (action === 'mark-paid') {
                openMarkPaidModal(detailDataset('invoiceMarkPaid'));
                return;
            }
        }

        function renderDetailCashProof(invoice) {
            const box = detailModal.querySelector('[data-detail-cash-proof]');
            const image = detailModal.querySelector('[data-detail-cash-proof-img]');
            const link = detailModal.querySelector('[data-detail-cash-proof-link]');
            const title = detailModal.querySelector('[data-detail-cash-proof-title]');
            const note = detailModal.querySelector('[data-detail-cash-proof-note]');
            const photoUrl = invoice.payment?.photo_url || '';
            // Hiện ảnh đối chiếu khi:
            // - Đang chờ duyệt thanh toán tiền mặt (da_gui_hoa_don_tt), hoặc
            // - Đã thanh toán bằng tiền mặt (da_thanh_toan + method cash) → để xem lại sau này.
            const isCash = invoice.payment?.method === 'cash';
            const show = !!photoUrl && (
                invoice.status === 'da_gui_hoa_don_tt'
                || (invoice.status === 'da_thanh_toan' && isCash)
            );

            box?.classList.toggle('hidden', !show);
            if (image) image.src = show ? photoUrl : '';
            if (link) link.href = show ? photoUrl : '#';

            // Đổi tiêu đề/ghi chú theo ngữ cảnh: đang chờ duyệt vs đã lưu trữ.
            if (show && invoice.status === 'da_thanh_toan') {
                if (title) title.textContent = 'Ảnh chứng từ đã đối chiếu';
                if (note) note.textContent = 'Ảnh khách hàng đã gửi, lưu lại để tra cứu sau này.';
            } else if (show) {
                if (title) title.textContent = 'Ảnh khách hàng đã gửi';
                if (note) note.textContent = 'Kiểm tra ảnh chứng từ trước khi xác nhận thanh toán.';
            }
        }

        function renderDetailTransaction(invoice) {
            const box = detailModal.querySelector('[data-detail-txn]');
            if (!box) return;

            const payment = invoice.payment || {};
            const txnId = payment.provider_transaction_id || payment.sepay_transaction_id || '';
            const reference = payment.reference || payment.provider_intent_id || '';
            // Chỉ hiện cho hóa đơn online ĐÃ thanh toán (không phải tiền mặt) và có mã giao dịch.
            const isOnline = payment.method && payment.method !== 'cash';
            const show = invoice.status === 'da_thanh_toan' && isOnline && (!!txnId || !!reference);

            box.classList.toggle('hidden', !show);
            if (!show) return;

            setDetailText('[data-detail-txn-provider]', providerLabelOf(payment.provider));
            setDetailText('[data-detail-txn-paid-at]', payment.paid_at || invoice.dates?.paid || '-');

            const idRow = box.querySelector('[data-detail-txn-id-row]');
            idRow?.classList.toggle('hidden', !txnId);
            if (txnId) setDetailText('[data-detail-txn-id]', txnId);

            const refRow = box.querySelector('[data-detail-txn-ref-row]');
            refRow?.classList.toggle('hidden', !reference);
            if (reference) setDetailText('[data-detail-txn-ref]', reference);
        }

        function providerLabelOf(provider) {
            if (!provider) return '-';
            const labels = root ? JSON.parse(root.dataset.providerLabels || '{}') : {};
            const entry = labels[provider];
            if (!entry) return provider;
            return (typeof entry === 'object' ? entry.name : entry) || provider;
        }

        function runDetailJson(button, url, payload, fallbackMessage, keepOpen = false) {
            setDetailBusy(button, true);
            setDetailError('');
            postJson(url, payload)
                .then((response) => {
                    notify(response.message || fallbackMessage);
                    if (keepOpen) {
                        updateDetailAfterPaymentRequest(response);
                    } else {
                        closeDetailModal();
                    }
                    reload();
                })
                .catch((err) => setDetailError(err?.message || 'Không thể xử lý yêu cầu. Vui lòng thử lại.'))
                .finally(() => setDetailBusy(button, false));
        }

        function runDetailForm(button, url, formData, fallbackMessage) {
            setDetailBusy(button, true);
            setDetailError('');
            postForm(url, formData)
                .then((response) => {
                    notify(response.message || fallbackMessage);
                    closeDetailModal();
                    reload();
                })
                .catch((err) => setDetailError(err?.message || 'Không thể xử lý yêu cầu. Vui lòng thử lại.'))
                .finally(() => setDetailBusy(button, false));
        }

        function setDetailPaymentResult(payment) {
            const qrUrl = payment.qr_url || null;
            const paymentUrl = payment.payment_url || null;
            const provider = payment.provider || detailState.provider || detailState.invoice?.payment?.provider || null;
            const showQr = !!qrUrl && provider === 'sepay';
            const linkValue = showQr ? '' : (paymentUrl || qrUrl || '');
            const box = detailModal.querySelector('[data-detail-payment-result]');
            const qr = detailModal.querySelector('[data-detail-payment-qr]');
            const qrContainer = detailModal.querySelector('[data-detail-qr-container]');
            const link = detailModal.querySelector('[data-detail-payment-link]');
            const download = detailModal.querySelector('[data-detail-download-qr]');
            const copy = detailModal.querySelector('[data-detail-copy-link]');

            // Ẩn link/QR thanh toán nếu hóa đơn đã thanh toán hoặc đã hủy
            const invoiceStatus = detailState.invoice?.status || '';
            const hiddenStatuses = ['da_thanh_toan', 'huy'];
            const forceHide = hiddenStatuses.includes(invoiceStatus);

            const decodedQrUrl = qrUrl ? decodeHTMLEntities(qrUrl) : '';
            const decodedLinkValue = linkValue ? decodeHTMLEntities(linkValue) : '';
            box?.classList.toggle('hidden', forceHide || (!qrUrl && !paymentUrl));
            setDetailText('[data-detail-payment-result-title]', showQr ? 'Mã QR thanh toán' : 'Link thanh toán');
            if (qrContainer) {
                qrContainer.style.display = showQr ? '' : 'none';
            }
            if (qr) {
                qr.src = decodedQrUrl;
                qr.hidden = !showQr;
                qr.classList.toggle('hidden', !showQr);
            }
            if (download) {
                download.hidden = !showQr;
                download.classList.toggle('hidden', !showQr);
            }
            if (link) {
                link.value = decodedLinkValue;
                link.hidden = !decodedLinkValue;
                link.classList.toggle('hidden', !decodedLinkValue);
            }
            if (copy) {
                copy.hidden = !decodedLinkValue;
                copy.classList.toggle('hidden', !decodedLinkValue);
            }
        }

        function updateDetailAfterPaymentRequest(response) {
            if (!detailState.invoice) return;

            detailState.invoice.status = 'da_gui_yeu_cau_tt';
            detailState.invoice.status_label = 'Đã gửi yêu cầu thanh toán';
            detailState.invoice.status_class = 'bg-indigo-100 text-indigo-700';
            detailState.invoice.payment = {
                ...(detailState.invoice.payment || {}),
                provider: response.provider || detailState.provider,
                qr_url: response.qr_url || null,
                payment_url: response.payment_url || null,
                can_regenerate: false,
                next_regenerate_at: null,
                next_regenerate_at_iso: response.next_regenerate_at_iso || null,
            };
            renderDetailInfo(detailState.invoice);
            renderDetailWorkflow(detailState.invoice);
            setDetailPaymentResult(detailState.invoice.payment);
        }

        function copyDetailPaymentLink() {
            const value = detailModal.querySelector('[data-detail-payment-link]')?.value || '';
            if (!value) return;

            navigator.clipboard?.writeText(value)
                .then(() => notify('Đã copy link thanh toán.'))
                .catch(() => window.prompt('Copy link thanh toán', value));
        }

        function downloadDetailQr() {
            const url = detailModal.querySelector('[data-detail-payment-qr]')?.src || '';
            if (!url) return;

            fetch(url)
                .then((response) => {
                    if (!response.ok) throw new Error('Không thể tải ảnh QR.');
                    return response.blob();
                })
                .then((blob) => {
                    const objectUrl = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = objectUrl;
                    link.download = `${detailState.invoice?.invoice_code || 'ma-qr-thanh-toan'}.png`;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(objectUrl);
                })
                .catch(() => {
                    window.open(url, '_blank', 'noopener');
                });
        }

        function setDetailText(selector, value) {
            detailModal.querySelector(selector)?.replaceChildren(document.createTextNode(value || '-'));
        }

        function setDetailError(message) {
            const error = detailModal?.querySelector('[data-detail-error]');
            if (!error) return;
            error.textContent = message || '';
            error.classList.toggle('hidden', !message);
        }

        function setDetailBusy(button, busy) {
            if (button) button.disabled = busy;
        }

        function toggleDetailAction(action, show) {
            const el = detailModal.querySelector(`[data-detail-action="${action}"]`);
            if (el) el.hidden = !show;
        }

        function detailDataset(actionKey) {
            const invoice = detailState.invoice || {};
            return {
                dataset: {
                    [actionKey]: invoice.id || '',
                    invoiceCode: invoice.invoice_code || '-',
                    invoiceAmount: invoice.amount_text || money(invoice.amount || 0),
                },
            };
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

        function openDeleteModal(button) {
            if (!deleteModal || !deleteForm) return;

            deleteState.invoiceId = button.dataset.invoiceDelete || null;
            deleteForm.reset();
            setDeleteError('');
            deleteModal.querySelector('[data-delete-modal-code]')?.replaceChildren(document.createTextNode(button.dataset.invoiceCode || '-'));
            deleteModal.querySelector('[data-delete-modal-amount]')?.replaceChildren(document.createTextNode(button.dataset.invoiceAmount || '-'));
            deleteModal.classList.remove('hidden');
            deleteModal.classList.add('flex');
            deleteForm.querySelector('input[name="password"]')?.focus();
        }

        function closeDeleteModal() {
            if (!deleteModal || !deleteForm) return;

            deleteState.invoiceId = null;
            deleteForm.reset();
            setDeleteError('');
            deleteModal.classList.add('hidden');
            deleteModal.classList.remove('flex');
        }

        function setDeleteError(message) {
            const error = deleteModal?.querySelector('[data-delete-modal-error]');
            if (!error) return;

            error.textContent = message || '';
            error.classList.toggle('hidden', !message);
        }

        function openRejectModal(button) {
            if (!rejectModal || !rejectForm) return;

            rejectState.invoiceId = button.dataset.invoiceRejectPayment || null;
            rejectForm.reset();
            setRejectError('');
            rejectModal.querySelector('[data-reject-modal-code]')?.replaceChildren(document.createTextNode(button.dataset.invoiceCode || '-'));
            rejectModal.querySelector('[data-reject-modal-amount]')?.replaceChildren(document.createTextNode(button.dataset.invoiceAmount || '-'));
            rejectModal.classList.remove('hidden');
            rejectModal.classList.add('flex');
        }

        function closeRejectModal() {
            if (!rejectModal || !rejectForm) return;

            rejectState.invoiceId = null;
            rejectForm.reset();
            setRejectError('');
            rejectModal.classList.add('hidden');
            rejectModal.classList.remove('flex');
        }

        function setRejectError(message) {
            const error = rejectModal?.querySelector('[data-reject-modal-error]');
            if (!error) return;

            error.textContent = message || '';
            error.classList.toggle('hidden', !message);
        }

        function openMarkPaidModal(button) {
            if (!markPaidModal || !markPaidForm) return;

            markPaidState.invoiceId = button.dataset.invoiceMarkPaid || null;
            markPaidForm.reset();
            setMarkPaidError('');
            markPaidModal.querySelector('[data-mark-paid-modal-code]')?.replaceChildren(document.createTextNode(button.dataset.invoiceCode || '-'));
            markPaidModal.querySelector('[data-mark-paid-modal-amount]')?.replaceChildren(document.createTextNode(button.dataset.invoiceAmount || '-'));
            markPaidModal.classList.remove('hidden');
            markPaidModal.classList.add('flex');
        }

        function closeMarkPaidModal() {
            if (!markPaidModal || !markPaidForm) return;

            markPaidState.invoiceId = null;
            markPaidForm.reset();
            setMarkPaidError('');
            markPaidModal.classList.add('hidden');
            markPaidModal.classList.remove('flex');
        }

        function setMarkPaidError(message) {
            const error = markPaidModal?.querySelector('[data-mark-paid-modal-error]');
            if (!error) return;

            error.textContent = message || '';
            error.classList.toggle('hidden', !message);
        }

        function openResetChannelModal(button) {
            if (!resetChannelModal || !resetChannelForm) return;

            resetChannelState.invoiceId = button.dataset.invoiceResetChannel || null;
            resetChannelForm.reset();
            setResetChannelError('');
            resetChannelModal.querySelector('[data-reset-channel-modal-code]')?.replaceChildren(document.createTextNode(button.dataset.invoiceCode || '-'));
            resetChannelModal.querySelector('[data-reset-channel-modal-amount]')?.replaceChildren(document.createTextNode(button.dataset.invoiceAmount || '-'));
            resetChannelModal.classList.remove('hidden');
            resetChannelModal.classList.add('flex');
        }

        function closeResetChannelModal() {
            if (!resetChannelModal || !resetChannelForm) return;

            resetChannelState.invoiceId = null;
            resetChannelForm.reset();
            setResetChannelError('');
            resetChannelModal.classList.add('hidden');
            resetChannelModal.classList.remove('flex');
        }

        function setResetChannelError(message) {
            const error = resetChannelModal?.querySelector('[data-reset-channel-modal-error]');
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

        function deleteJson(url, payload = {}) {
            return fetch(url, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            }).then((response) => response.json().catch(() => ({})).then((payload) => {
                if (!response.ok) throw new Error(payload.message || 'Request failed');
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
                        <p class="invoice-empty-state-title">${title}</p>
                        <p class="invoice-empty-state-text">${text}</p>
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

        function decodeHTMLEntities(str) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = str;
            return textarea.value;
        }

        syncActiveStatus();
    };

    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    const scheduleInvoiceIndexInit = (attempt = 0) => {
        initInvoiceIndex();

        const tableEl = document.getElementById('invoices-table');
        if (tableEl?.dataset.ready === 'true' && isInvoiceDataTableReady(tableEl)) {
            return;
        }

        if (attempt >= 20) {
            markReady();
            return;
        }

        setTimeout(() => scheduleInvoiceIndexInit(attempt + 1), 100);
    };

    document.addEventListener('DOMContentLoaded', scheduleInvoiceIndexInit);
    document.addEventListener('livewire:navigated', () => scheduleInvoiceIndexInit());
    document.addEventListener('livewire:initialized', () => {
        Livewire.hook('morph.updated', () => setTimeout(() => scheduleInvoiceIndexInit(), 0));
    });
    scheduleInvoiceIndexInit();
})();
