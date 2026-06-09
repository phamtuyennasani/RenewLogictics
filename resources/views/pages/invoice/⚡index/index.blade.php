<div
    id="invoice-index-page"
    class="space-y-4"
    data-component-cloak
    data-ready="false"
    data-routes='@json($this->routes())'
    data-enabled-providers='@json($this->enabledProviders())'
    data-provider-labels='@json($this->providerLabels())'
    data-all-providers='@json(\App\Services\Payments\PaymentProviderManager::allProviders())'
    data-datatables-language-url="{{ asset('assets/datatables/vi.json') }}"
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
                <div class="absolute inset-y-4 left-0 w-1 rounded-r-full bg-blue-500"></div>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 pl-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                            <p class="text-[11px] font-bold uppercase tracking-wide text-blue-700">Chờ duyệt</p>
                        </div>
                        <p class="mt-3 truncate text-3xl font-black leading-none tracking-normal text-neutral-950" data-summary-money="awaiting">{{ $this->money(0) }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-500">Hóa đơn chờ duyệt</p>
                    </div>
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                        <flux:icon.clock class="size-5" />
                    </div>
                </div>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-blue-100">
                    <div class="h-full rounded-full bg-blue-500" data-summary-bar="awaiting" style="width: 0%"></div>
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

            <div class="debt-table-frame debt-table-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="invoices-table" class="w-full min-w-[1500px] text-left text-sm">
                        <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                            <tr>
                                <th class="px-3 py-3">Mã hóa đơn</th>
                                <th class="px-3 py-3">Mã Công nợ</th>
                                <th class="px-3 py-3">Trạng thái</th>
                                <th class="px-3 py-3">Khách hàng</th>
                                <th class="px-3 py-3">Sale phụ trách</th>
                                <th class="px-3 py-3 text-right">Số tiền thanh toán</th>
                                <th class="px-3 py-3">Ngày tạo / Duyệt / Thanh toán</th>
                                <th class="px-3 py-3">Người tạo</th>
                                <th class="px-3 py-3">Chi tiết</th>
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

        <div id="invoice-detail-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-neutral-950/55 p-3 backdrop-blur-[2px] sm:p-4">
            <div class="flex max-h-[94vh] w-full max-w-[92rem] flex-col overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-black/10">
                <div class="border-b border-neutral-200 bg-white px-5 py-4 sm:px-7">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="inline-flex h-7 items-center rounded-md bg-neutral-100 px-2.5 text-xs font-bold uppercase text-neutral-600">Hóa đơn thu</p>
                                <span data-detail-status class="inline-flex rounded-md px-2.5 py-1 text-xs font-bold"></span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-end gap-x-3 gap-y-1">
                                <h2 class="text-2xl font-black tracking-normal text-neutral-950 sm:text-3xl">Chi tiết hóa đơn</h2>
                                <span class="pb-1 font-mono text-sm font-semibold text-neutral-500" data-detail-modal-code>-</span>
                            </div>
                        </div>
                        <button type="button" id="invoice-detail-close" class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-950">
                            <span class="sr-only">Đóng</span>
                            <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="overflow-y-auto bg-[#f6f7f9] px-5 py-5 sm:px-7">
                    <section class="grid gap-4 xl:grid-cols-[1fr_1fr]">
                        <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase text-neutral-500">Tổng quan thanh toán</p>
                                    <p class="mt-2 whitespace-nowrap text-5xl font-black leading-none tracking-normal text-neutral-950 sm:text-6xl" data-detail-amount>-</p>
                                </div>
                                <div class="hidden rounded-lg bg-primary-50 px-3 py-2 text-right sm:block">
                                    <p class="text-[11px] font-bold uppercase text-primary-600">Hóa đơn</p>
                                    <p class="mt-1 font-mono text-sm font-black text-primary-900" data-detail-overview-code>-</p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-3">
                                    <p class="text-[11px] font-bold uppercase text-neutral-500">Mã hóa đơn</p>
                                    <p class="mt-1 break-all font-mono text-sm font-black text-neutral-950" data-detail-summary-code>-</p>
                                </div>
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-3">
                                    <p class="text-[11px] font-bold uppercase text-neutral-500">Mã công nợ</p>
                                    <p class="mt-1 break-all font-mono text-sm font-black text-neutral-950" data-detail-debt-code>-</p>
                                </div>
                                <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-3">
                                    <p class="text-[11px] font-bold uppercase text-neutral-500">Kênh xử lý</p>
                                    <p class="mt-1 truncate text-sm font-black text-neutral-950">Hóa đơn thu</p>
                                </div>
                            </div>
                            <div class="mt-3 rounded-lg border border-primary-100 bg-primary-50 px-3 py-3">
                                <p class="text-[11px] font-bold uppercase text-primary-600">Việc cần làm</p>
                                <p class="mt-1 text-sm font-bold text-primary-950" data-detail-next-step>-</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                            <div class="flex items-start gap-3">
                                <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-neutral-950 text-sm font-black text-white">KH</div>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase text-neutral-500">Khách hàng</p>
                                    <p class="mt-1 text-xl font-black leading-7 text-neutral-950" data-detail-customer-company>-</p>
                                </div>
                            </div>
                            <dl class="mt-5 grid gap-x-4 gap-y-3 text-sm sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="text-xs font-bold uppercase text-neutral-500">Người phụ trách</dt>
                                    <dd class="mt-1 break-words font-semibold text-neutral-950" data-detail-customer-contact>-</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-bold uppercase text-neutral-500">Số điện thoại</dt>
                                    <dd class="mt-1 break-words font-semibold text-neutral-950" data-detail-customer-phone>-</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-bold uppercase text-neutral-500">Email</dt>
                                    <dd class="mt-1 break-all font-semibold text-neutral-950" data-detail-customer-email>-</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-bold uppercase text-neutral-500">Địa chỉ</dt>
                                    <dd class="mt-1 font-semibold leading-5 text-neutral-950" data-detail-customer-address>-</dd>
                                </div>
                            </dl>
                        </div>
                    </section>

                    <section class="mt-4 rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase text-neutral-500">Mốc xử lý</p>
                                <h3 class="mt-1 text-base font-black text-neutral-950">Ngày tạo / Duyệt / Thanh toán</h3>
                            </div>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                                <p class="text-xs font-bold uppercase text-neutral-500">Ngày tạo</p>
                                <p class="mt-1 font-semibold text-neutral-950" data-detail-created-at>-</p>
                            </div>
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                                <p class="text-xs font-bold uppercase text-neutral-500">Ngày duyệt</p>
                                <p class="mt-1 font-semibold text-neutral-950" data-detail-approved-at>-</p>
                            </div>
                            <div class="rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3">
                                <p class="text-xs font-bold uppercase text-neutral-500">Ngày thanh toán</p>
                                <p class="mt-1 font-semibold text-neutral-950" data-detail-paid-at>-</p>
                            </div>
                        </div>
                    </section>

                    <section class="mt-4 hidden rounded-lg border border-neutral-200 bg-white p-4" data-detail-payment-result>
                        <div class="mb-3 flex items-center justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase text-neutral-500">Kết quả thanh toán</p>
                                <div class="mt-0.5 text-sm font-semibold text-neutral-700" data-detail-payment-result-title></div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" data-detail-download-qr class="hidden inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-xs font-bold text-neutral-700 transition hover:bg-neutral-50">Tải QR</button>
                                <button type="button" data-detail-copy-link class="hidden inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-xs font-bold text-neutral-700 transition hover:bg-neutral-50">Copy link</button>
                            </div>
                        </div>
                        <div class="flex flex-col items-center gap-3">
                            <div class="items-center justify-center rounded-xl border border-neutral-200 bg-white p-4" style="display:none" data-detail-qr-container>
                                <img data-detail-payment-qr class="max-w-[20rem] w-full rounded-lg object-contain" alt="QR thanh toán">
                            </div>
                            <div class="w-full ">
                                <input data-detail-payment-link type="text" readonly class="hidden h-11 w-full rounded-lg border border-neutral-200 bg-neutral-50 px-3 text-xs font-medium text-neutral-700">
                            </div>
                        </div>
                    </section>

                    <section class="mt-4 hidden rounded-lg border border-amber-200 bg-amber-50 p-5" data-detail-cash-proof>
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase text-amber-700">Bằng chứng thanh toán tiền mặt</p>
                                <h3 class="mt-1 text-lg font-black text-neutral-950">Ảnh khách hàng đã gửi</h3>
                                <p class="mt-1 text-sm font-medium text-amber-800">Kiểm tra ảnh chứng từ trước khi xác nhận thanh toán.</p>
                            </div>
                            <a data-detail-cash-proof-link href="#" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center rounded-lg border border-amber-300 bg-white px-3 text-xs font-bold text-amber-800 transition hover:bg-amber-100">
                                Mở ảnh gốc
                            </a>
                        </div>
                        <div class="mt-4 overflow-hidden rounded-lg border border-amber-200 bg-white">
                            <img data-detail-cash-proof-img src="" alt="Bằng chứng thanh toán tiền mặt" class="max-h-[28rem] w-full object-contain">
                        </div>
                    </section>
                    <p class="mt-4 hidden rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" data-detail-error></p>

                    <section data-detail-section="approved" class="mt-4 hidden rounded-lg border border-neutral-200 bg-white p-5">
                        <div class="mb-4">
                            <p class="text-xs font-bold uppercase text-neutral-500">Thanh toán</p>
                            <h3 class="mt-1 text-lg font-black text-neutral-950">Chọn phương thức thanh toán</h3>
                        </div>
                        <flux:radio.group variant="cards" class="grid gap-3 sm:grid-cols-2" label="Phương thức thanh toán">
                            <flux:radio value="cash" icon="banknotes" label="Thanh toán tiền mặt" description="Upload bằng chứng thanh toán để kế toán duyệt." data-detail-payment-method="cash" />
                            <flux:radio value="online" icon="qr-code" label="Thanh toán Online" description="Tạo QR hoặc link thanh toán qua cổng thanh toán." data-detail-payment-method="online" />
                        </flux:radio.group>

                        <form data-detail-cash-form class="mt-4 hidden rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <label class="block">
                                <span class="text-sm font-bold text-neutral-800">Bằng chứng thanh toán</span>
                                <input type="file" name="photo" accept="image/*" class="mt-2 block w-full rounded-lg border border-neutral-200 bg-white text-sm file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-700">
                            </label>
                        </form>

                        <div data-detail-online-panel class="mt-4 hidden space-y-4 rounded-lg border border-primary-200 bg-primary-50/50 p-4">
                            <div class="grid gap-3 sm:grid-cols-3">
                                @foreach(\App\Services\Payments\PaymentProviderManager::providerLabels() as $key => $meta)
                                    @if(($this->enabledProviders())[$key] ?? false)
                                    <label class="cursor-pointer rounded-lg border border-neutral-200 bg-white p-4 transition hover:border-primary-300" data-detail-provider-card="{{ $key }}">
                                        <input type="radio" name="detail_provider" value="{{ $key }}" class="sr-only">
                                        <span class="block text-sm font-black text-neutral-950">{{ $meta['name'] }}</span>
                                        <span class="mt-1 block text-xs font-medium leading-5 text-neutral-500">{{ $meta['description'] }}</span>
                                    </label>
                                    @endif
                                @endforeach
                            </div>
                            <div data-detail-momo-request class="hidden">
                                <label class="text-sm font-bold text-neutral-800">Phương thức thanh toán</label>
                                <input type="hidden" data-detail-momo-request-type value="captureWallet">
                                <div class="mt-2 grid gap-3 sm:grid-cols-3" data-detail-momo-request-options>
                                    <label class="cursor-pointer rounded-lg border border-primary-500 bg-white p-4 ring-2 ring-primary-100 transition hover:border-primary-300" data-detail-momo-request-card="captureWallet">
                                        <input type="radio" name="detail_momo_request_type" value="captureWallet" class="sr-only" checked>
                                        <span class="block text-sm font-black text-neutral-950">Ví MoMo</span>
                                        <span class="mt-1 block text-xs font-medium leading-5 text-neutral-500">Thanh toán bằng ví MoMo của khách hàng.</span>
                                    </label>
                                    <label class="cursor-pointer rounded-lg border border-neutral-200 bg-white p-4 transition hover:border-primary-300" data-detail-momo-request-card="payWithATM">
                                        <input type="radio" name="detail_momo_request_type" value="payWithATM" class="sr-only">
                                        <span class="block text-sm font-black text-neutral-950">Thẻ ATM Nội địa</span>
                                        <span class="mt-1 block text-xs font-medium leading-5 text-neutral-500">Thanh toán qua thẻ ATM nội địa có Internet Banking.</span>
                                    </label>
                                    <label class="cursor-pointer rounded-lg border border-neutral-200 bg-white p-4 transition hover:border-primary-300" data-detail-momo-request-card="payWithCC">
                                        <input type="radio" name="detail_momo_request_type" value="payWithCC" class="sr-only">
                                        <span class="block text-sm font-black text-neutral-950">Visa, Mastercard, JCB</span>
                                        <span class="mt-1 block text-xs font-medium leading-5 text-neutral-500">Thanh toán bằng thẻ tín dụng/ghi nợ quốc tế.</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 bg-white px-5 py-4 sm:px-7">
                    <button type="button" hidden data-detail-action="approve" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">Duyệt hóa đơn</button>
                    <button type="button" hidden data-detail-action="cash-submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">Gửi xác nhận thanh toán</button>
                    <button type="button" hidden data-detail-action="confirm-cash" class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">Xác nhận thanh toán</button>
                    <button type="button" hidden data-detail-action="online-submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">Tạo Mã thanh toán</button>
                    <button type="button" hidden data-detail-action="regenerate" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">Tạo lại mã thanh toán</button>
                    <button type="button" hidden data-detail-action="reset-payment-channel" class="inline-flex h-10 items-center justify-center rounded-lg border border-primary-200 bg-white px-4 text-sm font-bold text-primary-700 transition hover:bg-primary-50">Reset về đã duyệt</button>
                    <button type="button" hidden data-detail-action="mark-paid" class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">Xác nhận đã thanh toán</button>
                    <button type="button" hidden data-detail-action="reject" class="inline-flex h-10 items-center justify-center rounded-lg border border-orange-200 bg-white px-4 text-sm font-bold text-orange-700 transition hover:bg-orange-50">Không chấp nhận</button>
                    <button type="button" hidden data-detail-action="cancel" class="inline-flex h-10 items-center justify-center rounded-lg border border-red-200 bg-white px-4 text-sm font-bold text-red-700 transition hover:bg-red-50">Hủy hóa đơn</button>
                    <button type="button" data-detail-action="close" class="inline-flex h-10 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-bold text-neutral-700 transition hover:bg-neutral-50">Đóng</button>
                </div>
            </div>
        </div>

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

        {{-- Modal từ chối chứng từ thanh toán --}}
        <div id="invoice-reject-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <form id="invoice-reject-form" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-950">Từ chối chứng từ thanh toán</h2>
                        <p class="mt-1 text-sm text-neutral-500">
                            <span data-reject-modal-code>-</span>
                            <span class="mx-1">/</span>
                            <span data-reject-modal-amount>-</span>
                        </p>
                    </div>
                    <button type="button" id="invoice-reject-close" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-4">
                    <label class="block">
                        <span class="text-sm font-semibold text-neutral-700">Lý do từ chối <span class="text-red-500">*</span></span>
                        <textarea name="payment_rejection_reason" rows="4" class="mt-2 block w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-sm" placeholder="Nhập lý do từ chối chứng từ thanh toán" required></textarea>
                    </label>
                    <p class="mt-2 hidden text-sm text-red-600" data-reject-modal-error></p>
                </div>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" id="invoice-reject-dismiss" class="inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        Đóng
                    </button>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-orange-600 px-4 text-sm font-semibold text-white transition hover:bg-orange-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Từ chối
                    </button>
                </div>
            </form>
        </div>

        <div id="invoice-mark-paid-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <form id="invoice-mark-paid-form" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-950">Xác nhận đã thanh toán</h2>
                        <p class="mt-1 text-sm text-neutral-500">
                            <span data-mark-paid-modal-code>-</span>
                            <span class="mx-1">/</span>
                            <span data-mark-paid-modal-amount>-</span>
                        </p>
                    </div>
                    <button type="button" id="invoice-mark-paid-close" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-4">
                    <p class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        Hóa đơn sẽ được chuyển sang trạng thái Đã thanh toán và cập nhật công nợ tương ứng.
                    </p>
                    <p class="mt-2 hidden text-sm text-red-600" data-mark-paid-modal-error></p>
                </div>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" id="invoice-mark-paid-dismiss" class="inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        Đóng
                    </button>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Xác nhận thanh toán
                    </button>
                </div>
            </form>
        </div>

        <div id="invoice-reset-channel-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
            <form id="invoice-reset-channel-form" class="w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b border-neutral-100 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-neutral-950">Reset về đã duyệt</h2>
                        <p class="mt-1 text-sm text-neutral-500">
                            <span data-reset-channel-modal-code>-</span>
                            <span class="mx-1">/</span>
                            <span data-reset-channel-modal-amount>-</span>
                        </p>
                    </div>
                    <button type="button" id="invoice-reset-channel-close" class="rounded-lg p-2 text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800">
                        <span class="sr-only">Đóng</span>
                        <svg class="size-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="py-4">
                    <p class="rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm font-semibold text-primary-800">
                        Hóa đơn sẽ được reset về trạng thái Đã duyệt để chọn lại kênh thanh toán. Thông tin thanh toán cũ sẽ bị xóa.
                    </p>
                    <p class="mt-2 hidden text-sm text-red-600" data-reset-channel-modal-error></p>
                </div>

                <div class="flex justify-end gap-2 border-t border-neutral-100 pt-4">
                    <button type="button" id="invoice-reset-channel-dismiss" class="inline-flex h-9 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                        Đóng
                    </button>
                    <button type="submit" class="inline-flex h-9 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-60">
                        Xác nhận reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@assets
    <link rel="stylesheet" href="{{ asset('assets/datatables/dataTables.tailwindcss.css') }}">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/dataTables.tailwindcss.js') }}"></script>
@endassets
