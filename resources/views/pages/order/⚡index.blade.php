<?php

use App\Enums\OrderStatusEnum;
use App\Models\Member;
use App\Models\News;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Danh sách đơn hàng')] class extends Component
{
    #[Computed]
    public function statusCounts(): array
    {
        return $this->scopedOrders()
            ->selectRaw('bill_status, count(*) as total')
            ->groupBy('bill_status')
            ->pluck('total', 'bill_status')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    #[Computed]
    public function sales()
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sale', 'SALE']))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code']);
    }

    #[Computed]
    public function customers()
    {
        return Member::query()
            ->orderBy('fullname')
            ->limit(1000)
            ->get(['id', 'fullname', 'company_name', 'phone', 'code']);
    }

    #[Computed]
    public function services()
    {
        return News::query()
            ->whereIn('type', ['dichvuchinh', 'chinhanh'])
            ->orderBy('type')
            ->orderBy('numb')
            ->get(['id', 'namevi', 'type'])
            ->groupBy('type');
    }

    protected function scopedOrders(): Builder
    {
        $user = auth()->user();

        return Order::query()
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->when($user->hasRole('ctv'), fn ($q) => $q->where('id_customer', $user->id))
            ->when($user->hasRole('cs'), fn ($q) => $q->where(fn ($sub) => $sub->whereNull('id_cs')->orWhere('id_cs', $user->id)));
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $statusCases = OrderStatusEnum::cases();
    $serviceOptions = $this->services->get('dichvuchinh', collect());
    $branchOptions = $this->services->get('chinhanh', collect());
    $totalOrders = array_sum($this->statusCounts);
    $primaryHex = config('theme.primary.hex', '#3b82f6');
    $accentHex = config('theme.accent.hex', '#0ea5e9');
    $gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
    $routes = [
        'datatable' => route('orders.datatable'),
        'bulkStatus' => route('orders.bulk-status'),
        'deleteCancelled' => route('orders.delete-cancelled'),
        'export' => route('orders.export'),
        'create' => route('orders.create'),
    ];
@endphp

@assets
    <link rel="stylesheet" href="{{ asset('assets/datatables/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/datatables/fixedColumns.dataTables.css') }}">
    <script src="{{ asset('assets/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/datatables/dataTables.fixedColumns.min.js') }}"></script>
    <style>
        .orders-list {
            --orders-gradient: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});
        }

        .order-stat {
            border: 1px solid rgb(229 229 229);
            background: #fff;
            border-radius: .75rem;
            padding: .9rem 1rem;
            box-shadow: 0 1px 2px rgb(0 0 0 / .04);
        }

        .order-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            height: 2.25rem;
            border-radius: .65rem;
            border: 1px solid #e5e5e5;
            background: #fff;
            padding: 0 .85rem;
            color: #525252;
            font-size: .8125rem;
            font-weight: 600;
            white-space: nowrap;
            transition: background .15s, border-color .15s, color .15s, box-shadow .15s;
        }

        .order-pill:hover {
            border-color: #d4d4d4;
            background: #fafafa;
        }

        .order-pill.is-active {
            border-color: transparent;
            color: #fff;
            background: var(--orders-gradient);
            box-shadow: 0 6px 16px rgb(59 130 246 / .18);
        }

        .order-pill__count {
            min-width: 1.35rem;
            border-radius: 999px;
            background: rgb(0 0 0 / .07);
            padding: .05rem .4rem;
            text-align: center;
            font-size: .68rem;
            font-weight: 700;
        }

        .order-pill.is-active .order-pill__count {
            background: rgb(255 255 255 / .2);
        }

        .order-field {
            height: 2.5rem;
            border-radius: .75rem;
            border: 1px solid #d4d4d4;
            background: #fff;
            padding: 0 .85rem;
            color: #171717;
            font-size: .875rem;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .order-field:focus {
            border-color: {{ $primaryHex }};
            box-shadow: 0 0 0 3px color-mix(in srgb, {{ $primaryHex }} 18%, transparent);
        }

        .order-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            min-height: 2.5rem;
            border-radius: .75rem;
            padding: 0 .95rem;
            font-size: .875rem;
            font-weight: 600;
            white-space: nowrap;
            transition: background .15s, border-color .15s, color .15s, box-shadow .15s, opacity .15s;
        }

        .order-btn:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .order-btn--primary {
            color: #fff;
            background: var(--orders-gradient);
            box-shadow: 0 6px 16px rgb(59 130 246 / .2);
        }

        .order-btn--plain {
            border: 1px solid #d4d4d4;
            background: #fff;
            color: #404040;
        }

        .order-btn--plain:hover:not(:disabled) {
            background: #fafafa;
            border-color: #a3a3a3;
        }

        .order-btn--danger {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
        }

        .order-btn--warning {
            border: 1px solid #fde68a;
            background: #fffbeb;
            color: #a16207;
        }

        .order-chip {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            border: 1px solid #dbeafe;
            background: #eff6ff;
            padding: .35rem .65rem;
            color: #1d4ed8;
            font-size: .75rem;
            font-weight: 600;
        }

        .order-chip button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 999px;
            background: #dbeafe;
        }

        #orders-table_wrapper .dt-search,
        #orders-table_wrapper .dt-length {
            display: none !important;
        }

        #orders-table_wrapper .dt-layout-row {
            margin: 0;
        }

        #orders-table_wrapper .dt-layout-table {
            overflow: visible;
        }

        #orders-table {
            width: 100% !important;
            min-width: 1720px;
            border-collapse: separate !important;
            border-spacing: 0;
        }

        #orders-table.dataTable > thead > tr > th {
            border-bottom: 1px solid #e5e5e5;
            background: #fafafa;
            color: #525252;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .04em;
            padding: .85rem 1rem;
            text-transform: uppercase;
        }

        #orders-table.dataTable > tbody > tr > td {
            border-bottom: 1px solid #f5f5f5;
            color: #262626;
            padding: 1rem;
            vertical-align: top;
        }

        #orders-table.dataTable > tbody > tr:hover > td {
            background: #fafafa;
        }

        #orders-table_wrapper .dt-scroll-body {
            border-bottom: 0;
            scrollbar-color: #cbd5e1 #f8fafc;
            scrollbar-width: thin;
        }

        #orders-table_wrapper .dt-scroll-body::-webkit-scrollbar {
            height: .7rem;
        }

        #orders-table_wrapper .dt-scroll-body::-webkit-scrollbar-track {
            background: #f8fafc;
        }

        #orders-table_wrapper .dt-scroll-body::-webkit-scrollbar-thumb {
            border: 3px solid #f8fafc;
            border-radius: 999px;
            background: #cbd5e1;
        }

        #orders-table_wrapper .dtfc-fixed-left,
        #orders-table_wrapper .dtfc-fixed-right {
            background: #fff !important;
            box-shadow: 1px 0 0 #f5f5f5;
        }

        #orders-table_wrapper .dt-info {
            color: #737373;
            font-size: .8125rem;
            padding: 0;
        }

        #orders-table_wrapper .dt-paging {
            display: flex;
            gap: .25rem;
        }

        #orders-table_wrapper .dt-paging .dt-paging-button {
            border: 1px solid #e5e5e5 !important;
            border-radius: .55rem !important;
            color: #525252 !important;
            font-size: .8125rem;
            margin: 0 !important;
            padding: .4rem .7rem !important;
        }

        #orders-table_wrapper .dt-paging .dt-paging-button.current {
            border-color: transparent !important;
            color: #fff !important;
            background: var(--orders-gradient) !important;
        }

        #orders-table_processing {
            border: 1px solid #e5e5e5;
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 14px 30px rgb(15 23 42 / .12);
            color: #404040;
            font-weight: 700;
        }
    </style>
@endassets

<div
    x-data="ordersPage({
        routes: @js($routes),
        csrf: @js(csrf_token()),
    })"
    class="orders-list space-y-4"
>
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm text-neutral-500">Đơn hàng / Vận chuyển</p>
            <h1 class="mt-0.5 text-2xl font-bold text-neutral-900">Danh sách đơn hàng</h1>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" x-on:click="exportCsv()" class="order-btn order-btn--plain">
                <i class="pi pi-download text-sm"></i>
                Xuất CSV
            </button>
            <a href="{{ route('orders.create') }}" wire:navigate class="order-btn order-btn--primary">
                <i class="pi pi-plus text-sm"></i>
                Tạo đơn hàng
            </a>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        <div class="order-stat">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Tổng đơn</p>
            <p class="mt-2 text-2xl font-bold text-neutral-900">{{ number_format($totalOrders) }}</p>
        </div>
        <div class="order-stat">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Mới tạo</p>
            <p class="mt-2 text-2xl font-bold text-sky-700">{{ number_format($this->statusCounts[OrderStatusEnum::MOI_TAO->value] ?? 0) }}</p>
        </div>
        <div class="order-stat">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Xuất hàng</p>
            <p class="mt-2 text-2xl font-bold text-violet-700">{{ number_format($this->statusCounts[OrderStatusEnum::DUYET_XUAT_HANG->value] ?? 0) }}</p>
        </div>
        <div class="order-stat">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Đang phát</p>
            <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($this->statusCounts[OrderStatusEnum::DANG_PHAT_HANG->value] ?? 0) }}</p>
        </div>
        <div class="order-stat">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Đã giao</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700">{{ number_format($this->statusCounts[OrderStatusEnum::DA_GIAO->value] ?? 0) }}</p>
        </div>
        <div class="order-stat">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Đã hủy</p>
            <p class="mt-2 text-2xl font-bold text-red-700">{{ number_format($this->statusCounts[OrderStatusEnum::HUY->value] ?? 0) }}</p>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-3 shadow-xs">
        <div class="flex gap-2 overflow-x-auto pb-1">
            <button type="button" x-on:click="setStatus('')" class="order-pill" :class="filters.status === '' && 'is-active'">
                Tất cả
                <span class="order-pill__count">{{ number_format($totalOrders) }}</span>
            </button>
            @foreach($statusCases as $case)
                <button type="button" x-on:click="setStatus('{{ $case->value }}')" class="order-pill" :class="filters.status === '{{ $case->value }}' && 'is-active'">
                    {{ $case->label() }}
                    <span class="order-pill__count">{{ number_format($this->statusCounts[$case->value] ?? 0) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-100 px-5 py-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                <div class="relative min-w-0 flex-1 xl:max-w-md">
                    <i class="pi pi-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-neutral-400"></i>
                    <input
                        type="search"
                        x-model.debounce.350ms="filters.search"
                        class="order-field w-full pl-9"
                        placeholder="Tìm mã đơn, tracking, người gửi, người nhận..."
                    >
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="pageSize" x-on:change="reload()" class="order-field w-32">
                        <option value="15">15 dòng</option>
                        <option value="25">25 dòng</option>
                        <option value="50">50 dòng</option>
                        <option value="100">100 dòng</option>
                    </select>

                    <flux:modal.trigger name="order-filter">
                        <button type="button" class="order-btn order-btn--plain">
                            <i class="pi pi-filter text-sm"></i>
                            Bộ lọc
                            <span x-show="activeFilterCount() > 0" x-text="activeFilterCount()" class="ml-0.5 rounded-full bg-primary-100 px-1.5 py-0.5 text-xs font-bold text-primary-700"></span>
                        </button>
                    </flux:modal.trigger>

                    <button type="button" x-on:click="reload()" class="order-btn order-btn--plain">
                        <i class="pi pi-refresh text-sm"></i>
                        Tải lại
                    </button>
                </div>
            </div>

            <div class="mt-3 flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <span x-show="selected.length > 0" x-cloak class="inline-flex items-center gap-2 rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-semibold text-neutral-700">
                        Đã chọn <span x-text="selected.length"></span>
                    </span>

                    <select x-model="bulk.status" class="order-field w-52">
                        <option value="">Chọn trạng thái</option>
                        @foreach($statusCases as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                    <button type="button" x-on:click="bulkStatus(bulk.status)" x-bind:disabled="selected.length === 0 || !bulk.status" class="order-btn order-btn--plain">
                        <i class="pi pi-check text-sm"></i>
                        Cập nhật
                    </button>
                    <button type="button" x-on:click="bulkStatus('{{ OrderStatusEnum::DUYET_XUAT_HANG->value }}')" x-bind:disabled="selected.length === 0" class="order-btn order-btn--plain">Xuất hàng</button>
                    <button type="button" x-on:click="bulkStatus('{{ OrderStatusEnum::DANG_PHAT_HANG->value }}')" x-bind:disabled="selected.length === 0" class="order-btn order-btn--plain">Departed</button>
                    <button type="button" x-on:click="bulkCancel()" x-bind:disabled="selected.length === 0" class="order-btn order-btn--warning">
                        <i class="pi pi-ban text-sm"></i>
                        Hủy
                    </button>
                    @if(auth()->user()->hasAnyRole(['admin', 'manager']))
                        <button type="button" x-on:click="deleteCancelled()" x-bind:disabled="selected.length === 0" class="order-btn order-btn--danger">
                            <i class="pi pi-trash text-sm"></i>
                            Xóa đơn hủy
                        </button>
                    @endif
                    <button type="button" x-on:click="goPayment()" x-bind:disabled="selected.length !== 1" class="order-btn order-btn--plain">
                        <i class="pi pi-credit-card text-sm"></i>
                        Thanh toán
                    </button>
                </div>

                <div x-show="activeFilterCount() > 0" x-cloak class="flex flex-wrap items-center gap-2">
                    <template x-for="(label, key) in activeFilterLabels()" :key="key">
                        <span class="order-chip">
                            <span x-text="label"></span>
                            <button type="button" x-on:click="clearFilter(key)" title="Xóa lọc">
                                <i class="pi pi-times text-[10px]"></i>
                            </button>
                        </span>
                    </template>
                    <button type="button" x-on:click="resetFilters()" class="text-sm font-semibold text-red-600 hover:text-red-700">Xóa tất cả</button>
                </div>
            </div>
        </div>

        <div class="px-4 py-4">
            <table id="orders-table" class="display nowrap text-left text-sm">
                <thead>
                    <tr>
                        <th class="w-12 text-center">
                            <input type="checkbox" x-on:change="togglePage($event.target.checked)" class="h-4 w-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                        </th>
                        <th>Mã đơn</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo / xuất</th>
                        <th>Sale / CTV</th>
                        <th>Người gửi</th>
                        <th>Người nhận</th>
                        <th>Dịch vụ</th>
                        <th class="text-right">Kiện / KG</th>
                        <th class="text-right">Cước bán</th>
                        <th class="text-right">Cước vốn</th>
                        <th class="text-right">Lợi nhuận</th>
                        <th>Thanh toán</th>
                        <th class="text-center">Thao tác</th>
                    </tr>
                </thead>
            </table>
        </div>
    </section>

    <flux:modal name="order-filter" class="w-full max-w-4xl">
        <div class="space-y-5">
            <div class="border-b border-neutral-100 pb-4">
                <h2 class="text-lg font-bold text-neutral-900">Bộ lọc đơn hàng</h2>
                <p class="mt-1 text-sm text-neutral-500">Lọc theo thời gian, trạng thái, sale, CTV, dịch vụ và chi nhánh.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">Từ ngày</span>
                    <input type="date" x-model="filters.fromDate" class="order-field w-full">
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">Tới ngày</span>
                    <input type="date" x-model="filters.toDate" class="order-field w-full">
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">Trạng thái</span>
                    <select x-model="filters.status" class="order-field w-full">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($statusCases as $case)
                            <option value="{{ $case->value }}">{{ $case->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">Sale phụ trách</span>
                    <select x-model="filters.saleId" class="order-field w-full">
                        <option value="">Tất cả sale</option>
                        @foreach($this->sales as $sale)
                            <option value="{{ $sale->id }}">{{ $sale->fullname ?: $sale->username }}{{ $sale->code ? ' - '.$sale->code : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">CTV / Khách hàng</span>
                    <select x-model="filters.customerId" class="order-field w-full">
                        <option value="">Tất cả CTV</option>
                        @foreach($this->customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->fullname ?: $customer->company_name }}{{ $customer->code ? ' - '.$customer->code : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">Dịch vụ</span>
                    <select x-model="filters.serviceId" class="order-field w-full">
                        <option value="">Tất cả dịch vụ</option>
                        @foreach($serviceOptions as $service)
                            <option value="{{ $service->id }}">{{ $service->namevi }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="space-y-1.5">
                    <span class="text-sm font-semibold text-neutral-700">Chi nhánh</span>
                    <select x-model="filters.branchId" class="order-field w-full">
                        <option value="">Tất cả chi nhánh</option>
                        @foreach($branchOptions as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->namevi }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-neutral-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <button type="button" x-on:click="resetFilters()" class="order-btn order-btn--plain">
                    <i class="pi pi-undo text-sm"></i>
                    Xóa bộ lọc
                </button>
                <div class="flex gap-2">
                    <flux:modal.close>
                        <button type="button" class="order-btn order-btn--plain">Đóng</button>
                    </flux:modal.close>
                    <flux:modal.close>
                        <button type="button" x-on:click="reload()" class="order-btn order-btn--primary">
                            <i class="pi pi-check text-sm"></i>
                            Áp dụng
                        </button>
                    </flux:modal.close>
                </div>
            </div>
        </div>
    </flux:modal>
</div>

@script
    <script>
        Alpine.data('ordersPage', ({ routes, csrf }) => ({
            table: null,
            routes,
            csrf,
            selected: [],
            paymentUrls: {},
            pageSize: 25,
            filters: {
                search: '',
                status: '',
                fromDate: '',
                toDate: '',
                saleId: '',
                customerId: '',
                serviceId: '',
                branchId: '',
            },
            bulk: { status: '' },
            filterLabelMap: {
                search: 'Tìm kiếm',
                status: 'Trạng thái',
                fromDate: 'Từ ngày',
                toDate: 'Tới ngày',
                saleId: 'Sale',
                customerId: 'CTV',
                serviceId: 'Dịch vụ',
                branchId: 'Chi nhánh',
            },
            activeFilterCount() {
                return Object.values(this.filters).filter((v) => String(v || '').length > 0).length;
            },
            activeFilterLabels() {
                const result = {};
                for (const [key, val] of Object.entries(this.filters)) {
                    if (String(val || '').length > 0) {
                        result[key] = `${this.filterLabelMap[key] || key}: ${val}`;
                    }
                }
                return result;
            },
            clearFilter(key) {
                this.filters[key] = '';
                this.reload();
            },
            init() {
                this.$nextTick(() => this.initTable());
                this.$watch('filters.search', () => this.reload());
                this.$watch('filters.status', () => this.reload());
            },
            initTable() {
                const self = this;

                if (this.table) {
                    this.table.destroy();
                }

                this.table = new DataTable('#orders-table', {
                    processing: true,
                    serverSide: true,
                    ordering: false,
                    scrollX: true,
                    scrollCollapse: true,
                    fixedColumns: {
                        left: 2,
                        right: 1,
                    },
                    pageLength: parseInt(this.pageSize, 10) || 25,
                    autoWidth: false,
                    layout: {
                        topStart: null,
                        topEnd: null,
                        bottomStart: 'info',
                        bottomEnd: 'paging',
                    },
                    language: {
                        url: '{{ asset('assets/datatables/vi.json') }}',
                    },
                    ajax: {
                        url: routes.datatable,
                        data: (data) => {
                            data.search = data.search || {};
                            data.search.value = self.filters.search || '';
                            return { ...data, ...self.filters };
                        },
                        dataSrc: (json) => {
                            self.paymentUrls = {};
                            (json.data || []).forEach((row) => {
                                const id = self.extractId(row.check);
                                const match = String(row.actions || '').match(/href="([^"]*\/payment[^"]*)"/);
                                if (id && match) {
                                    self.paymentUrls[id] = match[1];
                                }
                            });
                            self.selected = [];
                            return json.data;
                        },
                    },
                    columns: [
                        { data: 'check', orderable: false, searchable: false, className: 'text-center' },
                        { data: 'order_code', name: 'id_bill' },
                        { data: 'status_badge', name: 'bill_status' },
                        { data: 'dates', name: 'created_at' },
                        { data: 'assignee', name: 'id_sale' },
                        { data: 'sender_info', orderable: false, searchable: false },
                        { data: 'receiver_info', orderable: false, searchable: false },
                        { data: 'service_info', orderable: false, searchable: false },
                        { data: 'package_info', orderable: false, searchable: false, className: 'text-right' },
                        { data: 'sale_total', orderable: false, searchable: false, className: 'text-right' },
                        { data: 'cost_total', orderable: false, searchable: false, className: 'text-right' },
                        { data: 'profit_total', orderable: false, searchable: false, className: 'text-right' },
                        { data: 'payment_state', orderable: false, searchable: false },
                        { data: 'actions', orderable: false, searchable: false, className: 'text-center' },
                    ],
                    drawCallback: () => {
                        self.bindChecks();
                    },
                });
            },
            extractId(html) {
                const match = String(html || '').match(/value="([^"]+)"/);
                return match ? match[1] : null;
            },
            bindChecks() {
                document.querySelectorAll('.order-check').forEach((checkbox) => {
                    checkbox.checked = this.selected.includes(checkbox.value);
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked && !this.selected.includes(checkbox.value)) {
                            this.selected.push(checkbox.value);
                        }
                        if (!checkbox.checked) {
                            this.selected = this.selected.filter((id) => id !== checkbox.value);
                        }
                    });
                });
            },
            togglePage(checked) {
                document.querySelectorAll('.order-check').forEach((checkbox) => {
                    checkbox.checked = checked;
                    if (checked && !this.selected.includes(checkbox.value)) {
                        this.selected.push(checkbox.value);
                    }
                    if (!checked) {
                        this.selected = this.selected.filter((id) => id !== checkbox.value);
                    }
                });
            },
            setStatus(status) {
                this.filters.status = status;
            },
            reload() {
                if (this.table) {
                    this.table.page.len(parseInt(this.pageSize, 10) || 25);
                    this.table.ajax.reload();
                }
            },
            resetFilters() {
                this.filters = {
                    search: '',
                    status: '',
                    fromDate: '',
                    toDate: '',
                    saleId: '',
                    customerId: '',
                    serviceId: '',
                    branchId: '',
                };
                this.reload();
            },
            async post(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.json();
            },
            async bulkStatus(status) {
                if (!status || this.selected.length === 0) return;
                await this.post(routes.bulkStatus, { ids: this.selected, status, ...this.filters });
                this.reload();
            },
            bulkCancel() {
                this.bulkStatus('{{ OrderStatusEnum::HUY->value }}');
            },
            async deleteCancelled() {
                if (this.selected.length === 0) return;
                await this.post(routes.deleteCancelled, { ids: this.selected, ...this.filters });
                this.reload();
            },
            exportCsv() {
                const params = Object.fromEntries(
                    Object.entries(this.filters).filter(([_, v]) => String(v || '').length > 0)
                );
                const query = new URLSearchParams(params).toString();
                window.location.href = routes.export + (query ? '?' + query : '');
            },
            goPayment() {
                const id = this.selected[0];
                if (id && this.paymentUrls[id]) {
                    window.location.href = this.paymentUrls[id];
                }
            },
        }));
    </script>
@endscript
