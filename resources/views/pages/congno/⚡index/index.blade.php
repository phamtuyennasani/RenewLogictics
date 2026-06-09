<div
    id="congno-index-page"
    class="space-y-4"
    data-component-cloak
    data-ready="false"
    data-routes='@json($this->routes())'
    data-datatables-language-url="{{ asset('assets/datatables/vi.json') }}"
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
@assets
    <link rel="stylesheet" href="{{ asset('assets/datatables/dataTables.tailwindcss.css') }}">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/dataTables.tailwindcss.js') }}"></script>
@endassets
