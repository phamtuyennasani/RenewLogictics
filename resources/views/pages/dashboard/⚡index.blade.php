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
    public array $pendingFilters = [];

    public array $options = [];
    public array $report = [];

    public function mount(): void
    {
        $statistics = app(SystemStatisticsService::class);

        $this->filters['fromDate'] = now()->subDays(30)->toDateString();
        $this->filters['toDate'] = now()->toDateString();
        $this->pendingFilters = $this->filters;
        $this->options = $statistics->filterOptions(auth()->user());
        $this->refreshReport($statistics);
    }

    public function syncPendingFilters(): void
    {
        $this->pendingFilters = $this->filters;
        $this->dispatch('system-statistics-filters-synced', filters: $this->pendingFilters);
    }

    public function applyFilters(): void
    {
        $this->filters = $this->normalizeFilters($this->pendingFilters);
        $this->pendingFilters = $this->filters;
        $this->refreshReport(app(SystemStatisticsService::class));
    }

    public function resetPendingFilters(): void
    {
        $this->pendingFilters = $this->normalizeFilters([
            'fromDate' => now()->subDays(30)->toDateString(),
            'toDate' => now()->toDateString(),
            'saleId' => '',
            'customerId' => '',
            'serviceId' => '',
            'branchId' => '',
            'agencyId' => '',
        ]);

        $this->dispatch('system-statistics-filters-synced', filters: $this->pendingFilters);
    }
    public function resetFilters(): void
    {
        $this->filters = $this->normalizeFilters([
            'fromDate' => now()->subDays(30)->toDateString(),
            'toDate' => now()->toDateString(),
            'saleId' => '',
            'customerId' => '',
            'serviceId' => '',
            'branchId' => '',
            'agencyId' => '',
        ]);

        $this->pendingFilters = $this->filters;
        $this->refreshReport(app(SystemStatisticsService::class));
    }

    protected function normalizeFilters(array $filters): array
    {
        return [
            'fromDate' => (string) ($filters['fromDate'] ?? ''),
            'toDate' => (string) ($filters['toDate'] ?? ''),
            'saleId' => (string) ($filters['saleId'] ?? ''),
            'customerId' => (string) ($filters['customerId'] ?? ''),
            'serviceId' => (string) ($filters['serviceId'] ?? ''),
            'branchId' => (string) ($filters['branchId'] ?? ''),
            'agencyId' => (string) ($filters['agencyId'] ?? ''),
        ];
    }

    protected function refreshReport(SystemStatisticsService $statistics): void
    {
        $this->report = $statistics->report(auth()->user(), $this->filters);
        $this->dispatch('system-yearly-timeline-updated', data: data_get($this->report, 'charts.yearlyTimeline', []));
        $this->dispatch('system-statistics-filters-synced', filters: $this->filters);
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

    public function weight(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.').' KG';
    }

    public function maxValue(array $items): float
    {
        return max(1, (float) max(array_column($items ?: [['value' => 0]], 'value')));
    }

    public function loadCustomersForSale(mixed $saleId): void
    {
        $statistics = app(SystemStatisticsService::class);
        $options = $statistics->filterOptions(auth()->user(), (string) $saleId ?: null);
        $this->dispatch('system-customers-updated', customers: $options['customers']);
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
            <flux:modal.trigger name="system-statistics-filter">
                <flux:button type="button" variant="outline" icon="funnel" wire:click="syncPendingFilters">
                    Bộ lọc
                </flux:button>
            </flux:modal.trigger>

            <flux:button type="button" variant="outline" icon="arrow-path" wire:click="resetFilters">
                Làm mới bộ lọc
            </flux:button>
        </div>
    </section>

    <flux:modal name="system-statistics-filter" class="w-full max-w-5xl !overflow-visible">
        <div class="system-filter-panel">
            <div class="system-filter-header">
                <div class="system-filter-title-row">
                    <div class="system-filter-icon">
                        <flux:icon.funnel class="size-5" />
                    </div>
                    <div>
                        <flux:heading size="lg">Bộ lọc dashboard</flux:heading>
                        <flux:subheading>Lọc theo thời gian, sale, CTV, dịch vụ, chi nhánh và đại lý.</flux:subheading>
                    </div>
                </div>
            </div>

            <div class="system-filter-content">
                <section class="system-filter-section system-filter-section-time">
                    <div class="system-filter-section-heading">
                        <div>
                            <h3>Thời gian</h3>
                            <p>Khoảng ngày tạo đơn trong báo cáo</p>
                        </div>
                    </div>
                    <div class="system-filter-section-grid system-filter-section-grid-2">
                        <label class="system-filter-field system-date-picker-field" wire:ignore>
                            <span class="system-filter-label">Từ ngày</span>
                            <span class="system-date-control">
                                <input type="text" value="{{ $pendingFilters['fromDate'] ?? $filters['fromDate'] }}" data-system-date-picker data-livewire-model="pendingFilters.fromDate" data-livewire-live="false" class="system-filter-control system-date-input" autocomplete="off" />
                            </span>
                        </label>
                        <label class="system-filter-field system-date-picker-field" wire:ignore>
                            <span class="system-filter-label">Đến ngày</span>
                            <span class="system-date-control">
                                <input type="text" value="{{ $pendingFilters['toDate'] ?? $filters['toDate'] }}" data-system-date-picker data-livewire-model="pendingFilters.toDate" data-livewire-live="false" class="system-filter-control system-date-input" autocomplete="off" />
                            </span>
                        </label>
                    </div>
                </section>

                <section class="system-filter-section">
                    <div class="system-filter-section-heading">
                        <div>
                            <h3>Phụ trách</h3>
                            <p>Sale và CTV / khách hàng trong phạm vi dữ liệu</p>
                        </div>
                    </div>
                    <div class="system-filter-section-grid @if (data_get($report, 'scope.canUseSaleFilter') && data_get($report, 'scope.canUseCustomerFilter')) system-filter-section-grid-2 @endif">
                        @if (data_get($report, 'scope.canUseSaleFilter'))
                            <label class="system-filter-field" wire:ignore>
                                <span class="system-filter-label">Sale</span>
                                <select data-placeholder="Tất cả sale" data-livewire-model="pendingFilters.saleId" data-livewire-live="false" class="system-filter-control tomselectEml system-filter-tomselect">
                                    <option value="">Tất cả sale</option>
                                    @foreach ($options['sales'] ?? [] as $sale)
                                        <option value="{{ $sale['id'] }}" @selected((string) ($pendingFilters['saleId'] ?? '') === (string) $sale['id'])>{{ $sale['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        @if (data_get($report, 'scope.canUseCustomerFilter'))
                            <label class="system-filter-field" wire:ignore>
                                <span class="system-filter-label">CTV / Khách hàng</span>
                                <select data-placeholder="Tất cả CTV" data-livewire-model="pendingFilters.customerId" data-livewire-live="false" class="system-filter-control tomselectEml system-filter-tomselect">
                                    <option value="">Tất cả CTV</option>
                                    @foreach ($options['customers'] ?? [] as $customer)
                                        <option value="{{ $customer['id'] }}" @selected((string) ($pendingFilters['customerId'] ?? '') === (string) $customer['id'])>{{ $customer['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </div>
                </section>

                <section class="system-filter-section system-filter-section-wide">
                    <div class="system-filter-section-heading">
                        <div>
                            <h3>Vận hành</h3>
                            <p>Dịch vụ, chi nhánh và đại lý</p>
                        </div>
                    </div>
                    <div class="system-filter-section-grid @if (data_get($report, 'scope.canUseAgencyFilter')) system-filter-section-grid-3 @else system-filter-section-grid-2 @endif">
                        <label class="system-filter-field" wire:ignore>
                            <span class="system-filter-label">Dịch vụ</span>
                            <select data-placeholder="Tất cả dịch vụ" data-livewire-model="pendingFilters.serviceId" data-livewire-live="false" class="system-filter-control tomselectEml system-filter-tomselect">
                                <option value="">Tất cả dịch vụ</option>
                                @foreach ($options['services'] ?? [] as $service)
                                    <option value="{{ $service['id'] }}" @selected((string) ($pendingFilters['serviceId'] ?? '') === (string) $service['id'])>{{ $service['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="system-filter-field" wire:ignore>
                            <span class="system-filter-label">Chi nhánh</span>
                            <select data-placeholder="Tất cả chi nhánh" data-livewire-model="pendingFilters.branchId" data-livewire-live="false" class="system-filter-control tomselectEml system-filter-tomselect">
                                <option value="">Tất cả chi nhánh</option>
                                @foreach ($options['branches'] ?? [] as $branch)
                                    <option value="{{ $branch['id'] }}" @selected((string) ($pendingFilters['branchId'] ?? '') === (string) $branch['id'])>{{ $branch['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        @if (data_get($report, 'scope.canUseAgencyFilter'))
                            <label class="system-filter-field" wire:ignore>
                                <span class="system-filter-label">Đại lý</span>
                                <select data-placeholder="Tất cả đại lý" data-livewire-model="pendingFilters.agencyId" data-livewire-live="false" class="system-filter-control tomselectEml system-filter-tomselect">
                                    <option value="">Tất cả đại lý</option>
                                    @foreach ($options['agencies'] ?? [] as $agency)
                                        <option value="{{ $agency['id'] }}" @selected((string) ($pendingFilters['agencyId'] ?? '') === (string) $agency['id'])>{{ $agency['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif
                    </div>
                </section>
            </div>

            <div class="system-filter-actions">
                <flux:button type="button" variant="ghost" icon="arrow-path" wire:click="resetPendingFilters">Làm mới</flux:button>
                <flux:modal.close>
                    <flux:button type="button" variant="primary" wire:click="applyFilters">Áp dụng</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card title="Tổng đơn hàng" value="{{ $this->number(data_get($report, 'orders.total', 0)) }}" meta="{{ $this->number(data_get($report, 'orders.avgPerDay', 0)) }} đơn/ngày" tone="blue" icon="clipboard-document-list" />
        <x-stat-card title="Đã giao" value="{{ $this->number(data_get($report, 'orders.delivered', 0)) }}" meta="Tỷ lệ {{ $this->percent(data_get($report, 'orders.deliveryRate', 0)) }}" tone="emerald" icon="check-circle" />
        <x-stat-card title="Đang xử lý" value="{{ $this->number(data_get($report, 'orders.processing', 0)) }}" meta="Gồm đơn chưa hoàn tất" tone="amber" icon="truck" />
        <x-stat-card title="Hủy / hoàn" value="{{ $this->number(data_get($report, 'orders.cancelled', 0) + data_get($report, 'orders.returned', 0)) }}" meta="Tỷ lệ {{ $this->percent(data_get($report, 'orders.cancelRate', 0)) }}" tone="red" icon="x-circle" />
    </section>

    @if (data_get($report, 'scope.hideMoney'))
        <section class="grid gap-3 sm:grid-cols-2">
            <x-stat-card title="Cân nặng ban đầu" value="{{ $this->weight(data_get($report, 'orders.grossWeight', 0)) }}" meta="Tổng trọng lượng thực tế" tone="sky" icon="cube" />
            <x-stat-card title="Cân nặng tính phí" value="{{ $this->weight(data_get($report, 'orders.chargedWeight', 0)) }}" meta="Theo đơn trong kỳ" tone="emerald" icon="scale" />
        </section>
    @else
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
    @endif

    <section class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm xl:col-span-2">
            @php ($hideMoney = (bool) data_get($report, 'scope.hideMoney', false)) @endphp
            @php ($hideCostProfit = (bool) data_get($report, 'scope.hideCostProfit', false)) @endphp
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-bold text-neutral-950">Biểu đồ {{ $hideMoney ? 'sản lượng' : 'doanh số' }} trong năm {{ now()->year }}</h2>
                    <p class="mt-1 text-sm text-neutral-500">{{ $hideMoney ? 'Số đơn và cân nặng tính phí theo từng tháng' : ($hideCostProfit ? 'Số đơn, cân nặng tính phí và cước bán theo từng tháng' : 'Số đơn, cân nặng tính phí, cước vốn, cước bán và lợi nhuận theo từng tháng') }}</p>
                </div>

                {{-- Chú thích tách riêng khỏi vùng vẽ cho thoáng --}}
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 sm:justify-end">
                    @php
                        $legendItems = $hideMoney
                            ? [
                                ['label' => 'Số lượng đơn', 'color' => '#6366f1', 'shape' => 'bar'],
                                ['label' => 'Cân nặng tính phí', 'color' => '#0ea5e9', 'shape' => 'bar'],
                            ]
                            : ($hideCostProfit
                                ? [
                                    ['label' => 'Số lượng đơn', 'color' => '#6366f1', 'shape' => 'bar'],
                                    ['label' => 'Cân nặng tính phí', 'color' => '#0ea5e9', 'shape' => 'bar'],
                                    ['label' => 'Cước bán', 'color' => '#10b981', 'shape' => 'line'],
                                ]
                                : [
                                    ['label' => 'Số lượng đơn', 'color' => '#6366f1', 'shape' => 'bar'],
                                    ['label' => 'Cân nặng tính phí', 'color' => '#0ea5e9', 'shape' => 'bar'],
                                    ['label' => 'Cước bán', 'color' => '#10b981', 'shape' => 'line'],
                                    ['label' => 'Cước vốn', 'color' => '#f59e0b', 'shape' => 'dash'],
                                    ['label' => 'Lợi nhuận', 'color' => '#8b5cf6', 'shape' => 'line'],
                                ]);
                    @endphp
                    @foreach ($legendItems as $legend)
                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-neutral-600">
                            @if ($legend['shape'] === 'bar')
                                <span class="h-2.5 w-2.5 rounded-[3px]" style="background-color: {{ $legend['color'] }}"></span>
                            @elseif ($legend['shape'] === 'dash')
                                <span class="h-0 w-4 border-t-2 border-dashed" style="border-color: {{ $legend['color'] }}"></span>
                            @else
                                <span class="h-1 w-4 rounded-full" style="background-color: {{ $legend['color'] }}"></span>
                            @endif
                            {{ $legend['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            @php($yearlyTimeline = data_get($report, 'charts.yearlyTimeline', []))
            <div
                id="system-yearly-timeline-chart"
                class="mt-5"
                wire:ignore
                data-chart='@json($yearlyTimeline)'
                data-hide-money="{{ $hideMoney ? '1' : '0' }}"
                data-hide-cost-profit="{{ $hideCostProfit ? '1' : '0' }}"
            >
                <div class="relative h-72 min-h-72 w-full">
                    <canvas data-yearly-timeline-canvas class="!h-full !w-full" aria-label="Biểu đồ thống kê trong năm"></canvas>
                </div>
            </div>

        </div>

        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
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

    @unless (data_get($report, 'scope.hideMoney', false))
        <livewire:dashboard.sale-statistics lazy />
    @endunless

    @php($hideCustomerRanking = (bool) data_get($report, 'scope.hideMoney', false))
    <section class="grid gap-4 {{ $hideCustomerRanking ? '' : 'xl:grid-cols-2' }}">
        @unless ($hideCustomerRanking)
            <x-ranking-panel title="Top CTV / khách hàng" :items="data_get($report, 'rankings.customers', [])" />
        @endunless
        <x-ranking-panel title="Top dịch vụ" :items="data_get($report, 'rankings.services', [])" :hideMoney="$hideCustomerRanking" />
    </section>

    <livewire:dashboard.country-service-statistics :filters="$filters" lazy />
</div>

@once
    @push('styles')
        <style>
            .system-filter-panel {
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
            }

            .system-filter-header {
                border-bottom: 1px solid #e5e5e5;
                padding-bottom: 1rem;
            }

            .system-filter-title-row {
                display: flex;
                align-items: flex-start;
                gap: 0.875rem;
            }

            .system-filter-icon {
                display: flex;
                width: 2.5rem;
                height: 2.5rem;
                flex: 0 0 auto;
                align-items: center;
                justify-content: center;
                border-radius: 0.875rem;
                background: #eff6ff;
                color: #1d4ed8;
            }

            .system-filter-content {
                display: grid;
                grid-template-columns: repeat(1, minmax(0, 1fr));
                gap: 1rem;
            }

            @media (min-width: 1024px) {
                .system-filter-content {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            .system-filter-section {
                min-width: 0;
                border: 1px solid #e5e5e5;
                border-radius: 1rem;
                background: #fafafa;
                padding: 1rem;
            }

            .system-filter-section-wide {
                grid-column: 1 / -1;
            }

            .system-filter-section-heading {
                display: flex;
                min-height: 2.25rem;
                align-items: flex-start;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 1rem;
            }

            .system-filter-section-heading h3 {
                margin: 0;
                color: #171717;
                font-size: 0.9375rem;
                font-weight: 700;
                line-height: 1.25rem;
            }

            .system-filter-section-heading p {
                margin: 0.125rem 0 0;
                color: #737373;
                font-size: 0.8125rem;
                line-height: 1.125rem;
            }

            .system-filter-section-grid {
                display: grid;
                grid-template-columns: repeat(1, minmax(0, 1fr));
                gap: 1rem;
            }

            @media (min-width: 768px) {
                .system-filter-section-grid-2 {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .system-filter-section-grid-3 {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .system-filter-field {
                display: block;
                min-width: 0;
            }

            .system-filter-label {
                display: block;
                margin-bottom: 0.625rem;
                color: #262626;
                font-size: 0.875rem;
                font-weight: 650;
                line-height: 1.125rem;
            }

            .system-filter-control,
            .system-filter-panel .ts-control {
                width: 100%;
                height: 2.875rem;
                min-height: 2.875rem;
                border: 1px solid #d4d4d4;
                border-radius: 0.75rem;
                background-color: #fff;
                padding: 0.625rem 1rem;
                color: #171717;
                font-size: 0.9375rem;
                line-height: 1.375rem;
                box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
                transition: border-color 150ms ease, box-shadow 150ms ease;
            }

            .system-filter-control:focus,
            .system-filter-panel .ts-wrapper.focus .ts-control {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgb(59 130 246 / 0.14);
                outline: none;
            }

            .system-filter-select {
                appearance: auto;
            }

            .system-date-picker-field {
                position: relative;
            }

            .system-date-control,
            .system-date-picker-field .flatpickr-wrapper,
            .system-date-picker-field .flatpickr-input {
                display: block;
                width: 100%;
            }

            .system-date-picker-field .flatpickr-calendar.static {
                position: absolute;
                top: calc(100% + 0.5rem);
                left: 0;
                z-index: 60;
            }

            .system-filter-panel .ts-wrapper {
                width: 100%;
                min-width: 0;
            }

            .system-filter-panel .ts-wrapper.system-filter-control {
                height: auto;
                border: 0;
                border-radius: 0;
                background: transparent;
                padding: 0;
                box-shadow: none;
            }

            .system-filter-panel .ts-wrapper.single .ts-control {
                display: flex;
                align-items: center;
                padding-right: 2.5rem;
            }

            .system-filter-panel .ts-control > input {
                min-width: 0;
                font-size: 0.9375rem;
            }

            .system-filter-panel .ts-control .item,
            .system-filter-panel .ts-control .items-placeholder {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .system-filter-panel .ts-dropdown {
                z-index: 999999;
                border-color: #e5e5e5;
                border-radius: 0.75rem;
                overflow: hidden;
                font-size: 0.875rem;
                box-shadow: 0 16px 32px rgb(15 23 42 / 0.14);
            }

            .system-filter-actions {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                gap: 0.75rem;
                border-top: 1px solid #e5e5e5;
                padding-top: 1rem;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            (() => {
                let chart = null;
                let filterRetryCount = 0;

                const filterRoot = () => document.getElementById('system-statistics-page');
                const filterPanel = () => document.querySelector('.system-filter-panel');
                const livewireComponent = () => {
                    const componentEl = filterRoot()?.closest('[wire\\:id]');
                    const componentId = componentEl?.getAttribute('wire:id');

                    return componentId && window.Livewire?.find ? window.Livewire.find(componentId) : null;
                };

                const setLivewireFilter = (model, value) => {
                    livewireComponent()?.set(model, value || '', false);
                };

                const initSystemFilters = () => {
                    const root = filterPanel();
                    if (!root) return;

                    if (!window.flatpickr || !window.TomSelectHelper) {
                        if (filterRetryCount < 20) {
                            filterRetryCount++;
                            setTimeout(initSystemFilters, 100);
                        }
                        return;
                    }

                    root.querySelectorAll('input[data-system-date-picker]').forEach((input) => {
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
                            onChange: (_selectedDates, dateStr) => {
                                setLivewireFilter(input.dataset.livewireModel, dateStr);
                            },
                            onClose: (_selectedDates, dateStr) => {
                                setLivewireFilter(input.dataset.livewireModel, dateStr);
                            },
                        });
                    });

                    window.TomSelectHelper.init(root);

                    const saleSelect = root.querySelector('select[data-livewire-model="pendingFilters.saleId"]');
                    if (saleSelect?.tomselect && !saleSelect.dataset.saleHooked) {
                        saleSelect.dataset.saleHooked = '1';
                        saleSelect.tomselect.on('change', (value) => {
                            const ctvSelect = root.querySelector('select[data-livewire-model="pendingFilters.customerId"]');
                            if (ctvSelect?.tomselect) {
                                ctvSelect.tomselect.clear(true);
                            }
                            livewireComponent()?.call('loadCustomersForSale', value);
                        });
                    }
                };

                const updateCtvTomSelect = (customers = []) => {
                    const root = filterPanel();
                    if (!root) return;

                    const ctvSelect = root.querySelector('select[data-livewire-model="pendingFilters.customerId"]');
                    if (!ctvSelect?.tomselect) return;

                    const ts = ctvSelect.tomselect;
                    ts.clear(true);
                    ts.clearOptions();

                    // Re-add the default "Tất cả CTV" option
                    ts.addOption({ value: '', text: ctvSelect.dataset.placeholder || 'Tất cả CTV' });

                    // Add filtered customers
                    customers.forEach((c) => {
                        ts.addOption({ value: String(c.id), text: c.label });
                    });

                    ts.refreshOptions(false);
                };

                const syncSystemFilters = (filters = {}) => {
                    const root = filterPanel();
                    if (!root) return;

                    root.querySelectorAll('input[data-system-date-picker]').forEach((input) => {
                        const key = input.dataset.livewireModel?.split('.').pop();
                        const value = filters[key] || '';

                        if (input._flatpickr) {
                            input._flatpickr.setDate(value || null, false);
                        } else {
                            input.value = value;
                        }
                    });

                    root.querySelectorAll('select.system-filter-tomselect').forEach((select) => {
                        const key = select.dataset.livewireModel?.split('.').pop();
                        const value = filters[key] || '';

                        if (select.tomselect) {
                            select.tomselect.setValue(value, true);
                        } else {
                            select.value = value;
                        }
                    });
                };

                function formatMoney(value) {
                    if (value >= 1_000_000_000) return (value / 1_000_000_000).toFixed(1).replace('.', ',') + ' tỷ';
                    if (value >= 1_000_000) return (value / 1_000_000).toFixed(1).replace('.', ',') + ' tr';
                    if (value >= 1_000) return Number(value).toLocaleString('vi-VN');
                    return value + ' đ';
                }

                function formatWeight(value) {
                    return Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 2 }) + ' KG';
                }

                function buildAreaGradient(ctx, color, alpha) {
                    const gradient = ctx.createLinearGradient(0, 0, 0, 350);
                    gradient.addColorStop(0, color.replace(')', `, ${alpha})`).replace('rgb', 'rgba'));
                    gradient.addColorStop(1, color.replace(')', ', 0)').replace('rgb', 'rgba'));
                    return gradient;
                }

                function buildChartOptions(hideMoney) {
                    return {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    animation: {
                        duration: 700,
                        easing: 'easeOutCubic',
                    },
                    layout: {
                        padding: {
                            top: 4,
                            right: 8,
                            bottom: 0,
                            left: 4,
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            enabled: true,
                            backgroundColor: 'rgba(255, 255, 255, 0.98)',
                            titleColor: '#0f172a',
                            bodyColor: '#475569',
                            borderColor: 'rgba(148, 163, 184, 0.3)',
                            borderWidth: 1,
                            cornerRadius: 12,
                            padding: 14,
                            titleFont: { size: 12.5, weight: '700' },
                            bodyFont: { size: 12, weight: '500' },
                            titleMarginBottom: 8,
                            bodySpacing: 7,
                            boxWidth: 8,
                            boxHeight: 8,
                            boxPadding: 6,
                            usePointStyle: true,
                            displayColors: true,
                            caretPadding: 8,
                            caretSize: 6,
                            callbacks: {
                                title: (items) => {
                                    if (!items.length) return '';
                                    const month = items[0].dataIndex + 1;
                                    return `Tháng ${String(month).padStart(2, '0')} / ${new Date().getFullYear()}`;
                                },
                                label: (context) => {
                                    const value = context.parsed.y;
                                    const axis = context.dataset.yAxisID;
                                    let prefix;
                                    if (axis === 'left') {
                                        prefix = `${Number(value).toLocaleString('vi-VN')} đơn`;
                                    } else if (axis === 'weight') {
                                        prefix = formatWeight(value);
                                    } else {
                                        prefix = formatMoney(value);
                                    }
                                    return ` ${context.dataset.label}: ${prefix}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                            },
                            border: {
                                display: false,
                            },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 11, weight: '400', family: "'Inter', system-ui, sans-serif" },
                                padding: 8,
                            },
                        },
                        left: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10, weight: '400' },
                                padding: 8,
                                maxTicksLimit: 6,
                                precision: 0,
                                callback: (value) => value.toLocaleString('vi-VN'),
                            },
                            grid: {
                                color: 'rgba(148, 163, 184, 0.14)',
                                drawBorder: false,
                                lineWidth: 1,
                                tickBorderDash: [4, 4],
                            },
                            border: {
                                display: false,
                            },
                        },
                        right: {
                            type: 'linear',
                            position: 'right',
                            display: ! hideMoney,
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10, weight: '400' },
                                padding: 8,
                                maxTicksLimit: 6,
                                callback: (value) => formatMoney(value),
                            },
                            grid: {
                                display: false,
                            },
                            border: {
                                display: false,
                            },
                        },
                        weight: {
                            type: 'linear',
                            position: 'right',
                            display: hideMoney,
                            beginAtZero: true,
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 10, weight: '400' },
                                padding: 8,
                                maxTicksLimit: 6,
                                callback: (value) => formatWeight(value),
                            },
                            grid: {
                                display: false,
                            },
                            border: {
                                display: false,
                            },
                        },
                    },
                    };
                }

                function chartData(items, hideMoney, hideCostProfit) {
                    const normalized = Array.isArray(items) ? items : [];

                    const orderDataset = {
                        type: 'bar',
                        label: 'Số lượng đơn',
                        data: normalized.map((item) => Number(item.orders || 0)),
                        backgroundColor: function (context) {
                            const chart = context.chart;
                            const { ctx: c, chartArea } = chart;
                            if (!chartArea) return 'rgba(129, 140, 248, 0.55)';
                            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, 'rgba(99, 102, 241, 0.85)');
                            g.addColorStop(1, 'rgba(129, 140, 248, 0.35)');
                            return g;
                        },
                        hoverBackgroundColor: 'rgba(79, 70, 229, 0.95)',
                        borderRadius: 4,
                        borderSkipped: false,
                        barPercentage: 1.0,
                        categoryPercentage: 0.42,
                        maxBarThickness: 14,
                        yAxisID: 'left',
                        order: 2,
                    };

                    const weightDataset = {
                        type: 'bar',
                        label: 'Cân nặng tính phí',
                        data: normalized.map((item) => Number(item.chargedWeight || 0)),
                        backgroundColor: function (context) {
                            const chart = context.chart;
                            const { ctx: c, chartArea } = chart;
                            if (!chartArea) return 'rgba(56, 189, 248, 0.55)';
                            const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            g.addColorStop(0, 'rgba(14, 165, 233, 0.85)');
                            g.addColorStop(1, 'rgba(56, 189, 248, 0.35)');
                            return g;
                        },
                        hoverBackgroundColor: 'rgba(2, 132, 199, 0.95)',
                        borderRadius: 4,
                        borderSkipped: false,
                        barPercentage: 1.0,
                        categoryPercentage: 0.42,
                        maxBarThickness: 14,
                        yAxisID: 'weight',
                        order: 1,
                    };

                    if (hideMoney) {
                        return {
                            labels: normalized.map((item) => item.label),
                            datasets: [orderDataset, weightDataset],
                        };
                    }

                    const saleTotalDataset = {
                        type: 'line',
                        label: 'Tổng cước bán',
                        data: normalized.map((item) => Number(item.saleTotal || 0)),
                        borderColor: '#10b981',
                        backgroundColor: function (context) {
                            const chart = context.chart;
                            const { ctx: c, chartArea } = chart;
                            if (!chartArea) return 'transparent';
                            return buildAreaGradient(c, 'rgb(16, 185, 129)', 0.12);
                        },
                        borderWidth: 2.5,
                        cubicInterpolationMode: 'monotone',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#10b981',
                        pointBorderWidth: 2,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#10b981',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        yAxisID: 'right',
                        order: 1,
                    };

                    const costTotalDataset = {
                        type: 'line',
                        label: 'Tổng cước vốn',
                        data: normalized.map((item) => Number(item.costTotal || 0)),
                        borderColor: '#f59e0b',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [5, 4],
                        cubicInterpolationMode: 'monotone',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 2.5,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#f59e0b',
                        pointBorderWidth: 2,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#f59e0b',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        yAxisID: 'right',
                        order: 1,
                    };

                    const profitDataset = {
                        type: 'line',
                        label: 'Lợi nhuận',
                        data: normalized.map((item) => Number(item.profit || 0)),
                        borderColor: '#8b5cf6',
                        backgroundColor: function (context) {
                            const chart = context.chart;
                            const { ctx: c, chartArea } = chart;
                            if (!chartArea) return 'transparent';
                            return buildAreaGradient(c, 'rgb(139, 92, 246)', 0.1);
                        },
                        borderWidth: 2.5,
                        cubicInterpolationMode: 'monotone',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#8b5cf6',
                        pointBorderWidth: 2,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: '#8b5cf6',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        yAxisID: 'right',
                        order: 0,
                    };

                    const lineDatasets = hideCostProfit
                        ? [saleTotalDataset]
                        : [saleTotalDataset, costTotalDataset, profitDataset];

                    return {
                        labels: normalized.map((item) => item.label),
                        datasets: [orderDataset, weightDataset, ...lineDatasets],
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
                    const container = document.getElementById('system-yearly-timeline-chart');
                    const canvas = container?.querySelector('[data-yearly-timeline-canvas]');

                    if (!container || !canvas || !window.Chart) {
                        return;
                    }

                    const hideMoney = container.dataset.hideMoney === '1';
                    const hideCostProfit = container.dataset.hideCostProfit === '1';
                    const data = chartData(items ?? initialItems(container), hideMoney, hideCostProfit);

                    if (chart) {
                        chart.destroy();
                        chart = null;
                    }

                    chart = new window.Chart(canvas, {
                        type: 'bar',
                        data,
                        options: buildChartOptions(hideMoney),
                    });
                }

                document.addEventListener('DOMContentLoaded', () => {
                    initSystemFilters();
                    render();
                });
                document.addEventListener('livewire:navigated', () => {
                    filterRetryCount = 0;
                    initSystemFilters();
                    render();
                });
                window.addEventListener('system-statistics-filters-synced', (event) => syncSystemFilters(event.detail?.filters || {}));
                window.addEventListener('system-yearly-timeline-updated', (event) => render(event.detail?.data || []));
                window.addEventListener('system-customers-updated', (event) => updateCtvTomSelect(event.detail?.customers || []));
            })();
        </script>
    @endpush
@endonce
