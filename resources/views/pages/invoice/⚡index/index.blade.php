<div
    id="invoice-index-page"
    class="space-y-4"
    data-component-cloak
    data-ready="false"
    data-routes='@json($this->routes())'
    data-enabled-providers='@json($this->enabledProviders())'
    data-provider-labels='@json($this->providerLabels())'
    data-all-providers='@json(\App\Services\Payments\PaymentProviderManager::allProviders())'
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

            <div class="overflow-hidden">
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

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/datatables/dataTables.tailwindcss.css') }}">
    <style>
        #invoice-index-page .dt-container,
        #invoice-index-page .dt-layout-table {
            position: relative;
        }

        #invoice-index-page .dt-processing {
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

        #invoice-index-page .dt-processing[style*="display: block"],
        #invoice-index-page .dt-processing[style*="display:block"] {
            display: flex !important;
        }

        #invoice-index-page .dt-processing::before {
            content: "";
            width: 1rem;
            height: 1rem;
            flex: 0 0 auto;
            border: 2px solid #bfdbfe;
            border-top-color: #2563eb;
            border-radius: 999px;
            animation: invoice-processing-spin 0.75s linear infinite;
        }

        #invoice-index-page .dt-processing::after {
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

        #invoice-index-page .dt-processing > div:first-child {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }

        #invoice-index-page .dt-processing > div:last-child {
            display: none;
        }

        #invoice-index-page td.dt-empty {
            padding: 0 !important;
            border-bottom: 0;
        }

        #invoice-index-page .invoice-empty-state {
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

        #invoice-index-page .invoice-empty-state-icon {
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

        #invoice-index-page .invoice-empty-state-title {
            margin: 0;
            color: #171717;
            font-size: 0.9375rem;
            font-weight: 750;
            line-height: 1.25rem;
        }

        #invoice-index-page .invoice-empty-state-text {
            margin: 0;
            max-width: 28rem;
            color: #737373;
            font-size: 0.875rem;
            line-height: 1.375rem;
        }

        @keyframes invoice-processing-spin {
            to {
                transform: rotate(360deg);
            }
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
                const enabledProviders = root ? JSON.parse(root.dataset.enabledProviders || '{}') : {};
                const allProviders = root ? JSON.parse(root.dataset.allProviders || '[]') : [];
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
                        url: '{{ asset('assets/datatables/vi.json') }}',
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
                    const photoUrl = invoice.payment?.photo_url || '';
                    const show = invoice.status === 'da_gui_hoa_don_tt' && !!photoUrl;

                    box?.classList.toggle('hidden', !show);
                    if (image) image.src = show ? photoUrl : '';
                    if (link) link.href = show ? photoUrl : '#';
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

                    const decodedQrUrl = qrUrl ? decodeHTMLEntities(qrUrl) : '';
                    const decodedLinkValue = linkValue ? decodeHTMLEntities(linkValue) : '';
                    box?.classList.toggle('hidden', !qrUrl && !paymentUrl);
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

            document.addEventListener('DOMContentLoaded', initInvoiceIndex);
            document.addEventListener('livewire:navigated', initInvoiceIndex);
            document.addEventListener('livewire:initialized', () => {
                Livewire.hook('morph.updated', () => setTimeout(initInvoiceIndex, 0));
            });
            setTimeout(initInvoiceIndex, 0);
        })();
    </script>
@endpush
