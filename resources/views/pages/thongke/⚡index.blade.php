<?php

use App\Services\Reports\SystemStatisticsService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Thống kê tổng')] class extends Component {
    public array $filters = [
        'fromDate' => '',
        'toDate' => '',
        'saleId' => '',
        'customerId' => '',
        'serviceId' => '',
        'branchId' => '',
        'agencyId' => '',
    ];

    public array $options = [];
    public array $report = [];

    public function mount(): void
    {
        $statistics = app(SystemStatisticsService::class);

        $this->filters['fromDate'] = now()->subDays(30)->toDateString();
        $this->filters['toDate'] = now()->toDateString();
        $this->options = $statistics->filterOptions(auth()->user());
        $this->refreshReport($statistics);
    }

    public function updatedFilters(mixed $value = null, ?string $key = null): void
    {
        $this->refreshReport(app(SystemStatisticsService::class));
    }

    public function resetFilters(): void
    {
        $scope = $this->report['scope'] ?? ['canUseSaleFilter' => true, 'canUseCustomerFilter' => true];

        $this->filters = [
            'fromDate' => now()->subDays(30)->toDateString(),
            'toDate' => now()->toDateString(),
            'saleId' => '',
            'customerId' => '',
            'serviceId' => '',
            'branchId' => '',
            'agencyId' => '',
        ];

        if (! $scope['canUseSaleFilter']) {
            $this->filters['saleId'] = '';
        }

        if (! $scope['canUseCustomerFilter']) {
            $this->filters['customerId'] = '';
        }

        $this->refreshReport(app(SystemStatisticsService::class));
    }

    protected function refreshReport(SystemStatisticsService $statistics): void
    {
        $this->report = $statistics->report(auth()->user(), $this->filters);
        $this->dispatch('system-order-timeline-updated', data: data_get($this->report, 'charts.orderTimeline', []));
    }

    public function money(mixed $value): string
    {
        if ($value === null) {
            return 'Không áp dụng';
        }

        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function number(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    public function percent(mixed $value): string
    {
        return number_format((float) $value, 1, ',', '.').'%';
    }

    public function maxValue(array $items): float
    {
        return max(1, (float) max(array_column($items ?: [['value' => 0]], 'value')));
    }
};
?>

<div id="system-statistics-page" class="space-y-5">
    <section class="flex flex-col gap-4 border-b border-neutral-200 pb-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-sm font-medium text-neutral-500">Thống kê / Dashboard tổng</p>
            <h1 class="mt-1 text-2xl font-bold text-neutral-950">Thống kê tổng hệ thống</h1>
            <p class="mt-2 text-sm text-neutral-600">
                Phạm vi: <span class="font-semibold text-neutral-900">{{ data_get($report, 'scope.label') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button type="button" variant="outline" icon="arrow-path" wire:click="resetFilters">
                Làm mới bộ lọc
            </flux:button>
        </div>
    </section>

    <section class="grid gap-3 rounded-lg border border-neutral-200 bg-white p-3 shadow-sm md:grid-cols-2 xl:grid-cols-7">
        <flux:input type="date" label="Từ ngày" wire:model.live="filters.fromDate" />
        <flux:input type="date" label="Đến ngày" wire:model.live="filters.toDate" />

        @if (data_get($report, 'scope.canUseSaleFilter'))
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-neutral-700">Sale</span>
                <select wire:model.live="filters.saleId" class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-800">
                    <option value="">Tất cả sale</option>
                    @foreach ($options['sales'] ?? [] as $sale)
                        <option value="{{ $sale['id'] }}">{{ $sale['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if (data_get($report, 'scope.canUseCustomerFilter'))
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-neutral-700">CTV / Khách hàng</span>
                <select wire:model.live="filters.customerId" class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-800">
                    <option value="">Tất cả CTV</option>
                    @foreach ($options['customers'] ?? [] as $customer)
                        <option value="{{ $customer['id'] }}">{{ $customer['label'] }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-neutral-700">Dịch vụ</span>
            <select wire:model.live="filters.serviceId" class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-800">
                <option value="">Tất cả dịch vụ</option>
                @foreach ($options['services'] ?? [] as $service)
                    <option value="{{ $service['id'] }}">{{ $service['label'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-neutral-700">Chi nhánh</span>
            <select wire:model.live="filters.branchId" class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-800">
                <option value="">Tất cả chi nhánh</option>
                @foreach ($options['branches'] ?? [] as $branch)
                    <option value="{{ $branch['id'] }}">{{ $branch['label'] }}</option>
                @endforeach
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-sm font-medium text-neutral-700">Đại lý</span>
            <select wire:model.live="filters.agencyId" class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-sm text-neutral-800">
                <option value="">Tất cả đại lý</option>
                @foreach ($options['agencies'] ?? [] as $agency)
                    <option value="{{ $agency['id'] }}">{{ $agency['label'] }}</option>
                @endforeach
            </select>
        </label>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card title="Tổng đơn hàng" value="{{ $this->number(data_get($report, 'orders.total', 0)) }}" meta="{{ $this->number(data_get($report, 'orders.avgPerDay', 0)) }} đơn/ngày" tone="blue" icon="clipboard-document-list" />
        <x-stat-card title="Đã giao" value="{{ $this->number(data_get($report, 'orders.delivered', 0)) }}" meta="Tỷ lệ {{ $this->percent(data_get($report, 'orders.deliveryRate', 0)) }}" tone="emerald" icon="check-circle" />
        <x-stat-card title="Đang xử lý" value="{{ $this->number(data_get($report, 'orders.processing', 0)) }}" meta="Gồm đơn chưa hoàn tất" tone="amber" icon="truck" />
        <x-stat-card title="Hủy / hoàn" value="{{ $this->number(data_get($report, 'orders.cancelled', 0) + data_get($report, 'orders.returned', 0)) }}" meta="Tỷ lệ {{ $this->percent(data_get($report, 'orders.cancelRate', 0)) }}" tone="red" icon="x-circle" />
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card title="Doanh thu cước bán" value="{{ $this->money(data_get($report, 'finance.saleTotal', 0)) }}" meta="Theo đơn trong kỳ" tone="sky" icon="banknotes" />

        @if (data_get($report, 'scope.canSeeFinance'))
            <x-stat-card title="Chi phí cước vốn" value="{{ $this->money(data_get($report, 'finance.costTotal')) }}" meta="Chỉ quyền tổng hợp" tone="slate" icon="receipt-percent" />
            <x-stat-card title="Lợi nhuận" value="{{ $this->money(data_get($report, 'finance.profitTotal')) }}" meta="Biên {{ $this->percent(data_get($report, 'finance.margin', 0)) }}" tone="emerald" icon="chart-bar" />
            <x-stat-card title="Công nợ đại lý còn lại" value="{{ $this->money(data_get($report, 'agencyDebt.remaining', 0)) }}" meta="{{ $this->number(data_get($report, 'agencyDebt.count', 0)) }} công nợ" tone="orange" icon="building-office" />
        @else
            <x-stat-card title="Hóa đơn đã thu" value="{{ $this->money(data_get($report, 'invoices.paid', 0)) }}" meta="{{ $this->number(data_get($report, 'invoices.count', 0)) }} hóa đơn" tone="emerald" icon="document-check" />
            <x-stat-card title="Hóa đơn đang chờ" value="{{ $this->money(data_get($report, 'invoices.pending', 0)) }}" meta="Cần theo dõi thanh toán" tone="amber" icon="clock" />
            <x-stat-card title="Công nợ còn lại" value="{{ $this->money(data_get($report, 'customerDebt.remaining', 0)) }}" meta="{{ $this->number(data_get($report, 'customerDebt.count', 0)) }} công nợ" tone="red" icon="exclamation-triangle" />
        @endif
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-neutral-950">Đơn hàng theo ngày</h2>
                    <p class="mt-1 text-sm text-neutral-500">Mỗi ngày gồm số lượng đơn, tổng cước bán và tổng cước vốn</p>
                </div>
                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ data_get($report, 'dateRange.from') }} - {{ data_get($report, 'dateRange.to') }}</span>
            </div>

            @php($orderTimeline = data_get($report, 'charts.orderTimeline', []))
            <div
                id="system-order-timeline-chart"
                class="mt-4 overflow-hidden rounded-lg bg-neutral-50 p-3"
                wire:ignore
                data-chart='@json($orderTimeline)'
            >
                <div class="relative h-64 min-h-64 w-full">
                    <canvas data-order-timeline-canvas class="!h-full !w-full" aria-label="Biểu đồ đơn hàng theo ngày"></canvas>
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-neutral-500 sm:grid-cols-4">
                @foreach (collect($orderTimeline)->take(4) as $point)
                    <span>{{ $point['label'] }}: <strong class="text-neutral-800">{{ $this->number($point['orders'] ?? 0) }}</strong> đơn</span>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-bold text-neutral-950">Trạng thái đơn hàng</h2>
            <p class="mt-1 text-sm text-neutral-500">Tỷ trọng theo trạng thái hiện tại</p>

            @php($statusItems = data_get($report, 'charts.orderStatuses', []))
            @php($statusMax = $this->maxValue($statusItems))
            <div class="mt-4 space-y-3">
                @forelse ($statusItems as $item)
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="truncate font-medium text-neutral-700">{{ $item['label'] }}</span>
                            <span class="font-semibold text-neutral-950">{{ $this->number($item['value']) }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full" style="width: {{ max(4, ($item['value'] / $statusMax) * 100) }}%; background-color: {{ $item['color'] }}"></div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg bg-neutral-50 p-4 text-sm text-neutral-500">Chưa có dữ liệu đơn hàng trong khoảng lọc.</p>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm xl:col-span-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-neutral-950">Doanh thu cước bán theo ngày</h2>
                    <p class="mt-1 text-sm text-neutral-500">Dựa trên `payment_cuocban.total_tongcuoc`</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ $this->money(data_get($report, 'finance.saleTotal', 0)) }}</span>
            </div>

            @php($revenueTimeline = data_get($report, 'charts.revenueTimeline', []))
            @php($revenueMax = $this->maxValue($revenueTimeline))
            <div class="mt-4 flex h-56 items-end gap-1 rounded-lg bg-neutral-50 p-3">
                @foreach ($revenueTimeline as $point)
                    <div class="group flex min-w-0 flex-1 flex-col items-center justify-end gap-1">
                        <div class="w-full rounded-t bg-emerald-500 transition group-hover:bg-emerald-600" style="height: {{ max(3, ($point['value'] / $revenueMax) * 100) }}%"></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <h2 class="text-base font-bold text-neutral-950">Trạng thái hóa đơn thu</h2>
            <p class="mt-1 text-sm text-neutral-500">Tổng quan hóa đơn trong kỳ</p>

            @php($invoiceItems = data_get($report, 'charts.invoiceStatuses', []))
            @php($invoiceMax = $this->maxValue($invoiceItems))
            <div class="mt-4 space-y-3">
                @forelse ($invoiceItems as $item)
                    <div>
                        <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                            <span class="truncate font-medium text-neutral-700">{{ $item['label'] }}</span>
                            <span class="font-semibold text-neutral-950">{{ $this->number($item['value']) }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
                            <div class="h-full rounded-full" style="width: {{ max(4, ($item['value'] / $invoiceMax) * 100) }}%; background-color: {{ $item['color'] }}"></div>
                        </div>
                    </div>
                @empty
                    <p class="rounded-lg bg-neutral-50 p-4 text-sm text-neutral-500">Chưa có hóa đơn trong khoảng lọc.</p>
                @endforelse
            </div>
        </div>
    </section>

    <livewire:dashboard.sale-statistics :filters="$filters" lazy />

    <section class="grid gap-4 xl:grid-cols-2">
        <x-ranking-panel title="Top CTV / khách hàng" :items="data_get($report, 'rankings.customers', [])" />
        <x-ranking-panel title="Top dịch vụ" :items="data_get($report, 'rankings.services', [])" />
    </section>

    <livewire:dashboard.country-service-statistics :filters="$filters" lazy />

    <section class="grid gap-4 xl:grid-cols-2">
        <x-attention-panel title="Đơn đã giao chưa ghi nhận thanh toán" :items="data_get($report, 'attention.unpaidDeliveredOrders', [])" value-key="amount" date-key="created_at" />
        <x-attention-panel title="Đơn lâu chưa cập nhật trạng thái" :items="data_get($report, 'attention.staleOrders', [])" value-key="status" date-key="updated_at" />
        <x-attention-panel title="Hóa đơn đang chờ xử lý" :items="data_get($report, 'attention.openInvoices', [])" value-key="amount" date-key="created_at" />
        <x-attention-panel title="Công nợ khách quá hạn" :items="data_get($report, 'attention.overdueCustomerDebts', [])" value-key="remaining" date-key="due_at" />
    </section>
</div>

@once
    @push('styles')
        <style>
            #system-statistics-page select:focus {
                border-color: #2563eb;
                box-shadow: 0 0 0 3px rgb(37 99 235 / 0.12);
                outline: none;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                let chart = null;

                const chartOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = context.parsed.y.toLocaleString('vi-VN');

                                    if (context.dataset.yAxisID === 'orders') {
                                        return `${context.dataset.label}: ${value} đơn`;
                                    }

                                    return `${context.dataset.label}: ${value} đ`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: '#737373',
                                maxRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 10,
                            },
                        },
                        orders: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                color: '#737373',
                                precision: 0,
                            },
                            grid: {
                                color: '#e5e7eb',
                            },
                        },
                        money: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            ticks: {
                                color: '#737373',
                                callback: (value) => Number(value).toLocaleString('vi-VN'),
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        },
                    },
                };

                function chartData(items) {
                    const normalized = Array.isArray(items) ? items : [];

                    return {
                        labels: normalized.map((item) => item.label),
                        datasets: [
                            {
                                label: 'Số lượng đơn',
                                data: normalized.map((item) => Number(item.orders || 0)),
                                backgroundColor: '#2563eb',
                                borderRadius: 5,
                                yAxisID: 'orders',
                            },
                            {
                                label: 'Tổng cước bán',
                                data: normalized.map((item) => Number(item.saleTotal || 0)),
                                backgroundColor: '#059669',
                                borderRadius: 5,
                                yAxisID: 'money',
                            },
                            {
                                label: 'Tổng cước vốn',
                                data: normalized.map((item) => Number(item.costTotal || 0)),
                                backgroundColor: '#f59e0b',
                                borderRadius: 5,
                                yAxisID: 'money',
                            },
                        ],
                    };
                }

                function initialItems(container) {
                    try {
                        return JSON.parse(container.dataset.chart || '[]');
                    } catch (error) {
                        return [];
                    }
                }

                function render(items = null) {
                    const container = document.getElementById('system-order-timeline-chart');
                    const canvas = container?.querySelector('[data-order-timeline-canvas]');

                    if (!container || !canvas || !window.Chart) {
                        return;
                    }

                    const data = chartData(items ?? initialItems(container));

                    if (!chart) {
                        chart = new window.Chart(canvas, {
                            type: 'bar',
                            data,
                            options: chartOptions,
                        });
                        return;
                    }

                    chart.data.labels = data.labels;
                    chart.data.datasets.forEach((dataset, index) => {
                        dataset.data = data.datasets[index]?.data || [];
                    });
                    chart.update();
                }

                document.addEventListener('DOMContentLoaded', () => render());
                document.addEventListener('livewire:navigated', () => render());
                window.addEventListener('system-order-timeline-updated', (event) => render(event.detail?.data || []));
            })();
        </script>
    @endpush
@endonce
