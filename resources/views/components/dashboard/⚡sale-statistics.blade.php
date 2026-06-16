<?php

use App\Services\Reports\SaleStatisticsService;
use Livewire\Component;

new class extends Component {
    public array $report = [];
    public string $metric = 'revenue';

    public function mount(SaleStatisticsService $statistics): void
    {
        $this->loadReport($statistics);
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="h-6 w-56 animate-pulse rounded bg-neutral-100"></div>
                <div class="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="space-y-3">
                        <div class="h-16 animate-pulse rounded-xl bg-neutral-100"></div>
                        <div class="h-16 animate-pulse rounded-xl bg-neutral-100"></div>
                        <div class="h-16 animate-pulse rounded-xl bg-neutral-100"></div>
                    </div>
                    <div class="h-64 animate-pulse rounded-xl bg-neutral-100"></div>
                </div>
            </section>
        HTML;
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function number(mixed $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    public function setMetric(string $metric): void
    {
        if (in_array($metric, ['revenue', 'chargedWeight', 'orderCount'], true)) {
            $this->metric = $metric;
        }
    }

    public function ranking(): array
    {
        $ranking = collect(data_get($this->report, 'ranking', []))
            ->sortByDesc(fn (array $sale) => $this->metricValue($sale))
            ->values();
        $max = max(1, (float) $ranking->max(fn (array $sale) => $this->metricValue($sale)));

        return $ranking
            ->map(fn (array $sale, int $index) => $sale + [
                'rank' => $index + 1,
                'metricShare' => round(($this->metricValue($sale) * 100) / $max, 1),
            ])
            ->all();
    }

    public function podium(): array
    {
        return collect($this->ranking())
            ->take(3)
            ->sortBy(fn (array $sale) => match ($sale['rank']) {
                2 => 1,
                1 => 2,
                default => 3,
            })
            ->values()
            ->all();
    }

    public function metricLabel(): string
    {
        return match ($this->metric) {
            'chargedWeight' => 'cân nặng tính phí',
            'orderCount' => 'số lượng đơn',
            default => 'doanh thu',
        };
    }

    public function metricDisplay(array $sale): string
    {
        return match ($this->metric) {
            'chargedWeight' => $this->number($sale['chargedWeight'] ?? 0, 2).' KG',
            'orderCount' => $this->number($sale['orderCount'] ?? 0).' đơn',
            default => $this->money($sale['revenue'] ?? 0),
        };
    }

    protected function loadReport(SaleStatisticsService $statistics): void
    {
        // Luôn lấy dữ liệu tháng hiện tại (từ ngày 1 đến cuối tháng)
        $filters = [
            'fromDate' => now()->startOfMonth()->toDateString(),
            'toDate' => now()->endOfMonth()->toDateString(),
        ];

        $this->report = $statistics->report(auth()->user(), $filters);
    }

    public function currentMonthLabel(): string
    {
        return now()->format('m/Y');
    }

    protected function metricValue(array $sale): float
    {
        return (float) ($sale[$this->metric] ?? 0);
    }
};
?>

@php
    $ranking = $this->ranking();
    $podium = $this->podium();
    $byCustomer = data_get($report, 'dimension') === 'customer';
    $entityLabel = $byCustomer ? 'Khách hàng' : 'Sale';
    $entityNoun = $byCustomer ? 'khách hàng của bạn' : 'sale';
@endphp

<section class="overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950">
    <div class="border-b border-neutral-100 bg-gradient-to-r from-primary-50 via-white to-white px-5 py-5 dark:border-white/10 dark:from-primary-950/40 dark:via-slate-900 dark:to-slate-950 lg:px-6">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-sky-300">{{ $byCustomer ? 'Khách Hàng Performance' : 'Sales Performance' }}</p>
                <h2 class="mt-1 text-xl font-bold text-neutral-950 dark:text-white">Thống kê doanh số {{ $entityLabel }} trong tháng {{ $this->currentMonthLabel() }}</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-slate-400">Xếp hạng {{ $entityNoun }} theo {{ $this->metricLabel() }} trong tháng hiện tại.</p>
            </div>
            <div class="inline-flex w-full rounded-xl border border-neutral-200 bg-white p-1 shadow-sm dark:border-white/10 dark:bg-slate-950/80 sm:w-auto">
                @foreach ([
                    'revenue' => ['Doanh thu', 'banknotes'],
                    'chargedWeight' => ['Cân nặng', 'cube'],
                    'orderCount' => ['Số lượng đơn', 'clipboard-document-list'],
                ] as $value => [$label, $icon])
                    <button
                        type="button"
                        wire:click="setMetric('{{ $value }}')"
                        wire:loading.attr="disabled"
                        @class([
                            'flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-bold transition sm:flex-none',
                            'bg-primary-600 text-white shadow-sm dark:bg-primary-500' => $metric === $value,
                            'text-neutral-500 hover:bg-neutral-50 hover:text-neutral-900 dark:text-slate-300 dark:hover:bg-white/5 dark:hover:text-white' => $metric !== $value,
                        ])
                    >
                        <flux:icon :name="$icon" class="size-4" />
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-px border-b border-neutral-100 bg-neutral-100 dark:border-white/10 dark:bg-white/10 sm:grid-cols-3">
        <div class="bg-white px-5 py-3 dark:bg-slate-950">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">{{ $byCustomer ? 'khách hàng hoạt động' : 'Sale hoạt động' }}</p>
            <p class="mt-1 text-lg font-bold text-neutral-950 dark:text-white">{{ $this->number(data_get($report, 'summary.sales', 0)) }}</p>
        </div>
        <div class="bg-white px-5 py-3 dark:bg-slate-950">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Tổng đơn hàng</p>
            <p class="mt-1 text-lg font-bold text-neutral-950 dark:text-white">{{ $this->number(data_get($report, 'summary.orders', 0)) }}</p>
        </div>
        <div class="bg-white px-5 py-3 dark:bg-slate-950">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-neutral-400">Tổng doanh thu</p>
            <p class="mt-1 text-lg font-bold text-primary-700 dark:text-sky-300">{{ $this->money(data_get($report, 'summary.revenue', 0)) }}</p>
        </div>
    </div>

    <div class="grid xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="overflow-x-auto">
            <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-neutral-50 text-left text-xs uppercase tracking-wide text-neutral-400 dark:bg-slate-900 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ $byCustomer ? 'Khách hàng' : 'Sale' }}</th>
                        <th class="px-4 py-3 text-right font-semibold">SL đơn hàng</th>
                        <th class="px-4 py-3 text-right font-semibold">Cân nặng</th>
                        <th class="px-5 py-3 text-right font-semibold">Doanh thu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 dark:divide-white/10">
                    @forelse ($ranking as $sale)
                        <tr class="group transition hover:bg-primary-50/50 dark:hover:bg-primary-500/10">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-sky-200">
                                        {{ $sale['rank'] }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-bold text-neutral-900 dark:text-white">{{ $sale['name'] }}</p>
                                        <p class="mt-0.5 text-xs font-medium text-neutral-400">{{ $sale['code'] ?: 'Chưa có mã sale' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-neutral-700 dark:text-slate-300">{{ $this->number($sale['orderCount']) }} đơn</td>
                            <td class="px-4 py-3 text-right text-neutral-500 dark:text-slate-400">
                                {{ $this->number($sale['grossWeight'], 2) }}
                                <span class="text-neutral-300">/</span>
                                <span class="font-semibold text-primary-600 dark:text-sky-300">{{ $this->number($sale['chargedWeight'], 2) }} KG</span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <p class="font-bold text-primary-700 dark:text-sky-300">{{ $this->money($sale['revenue']) }}</p>
                                <div class="ml-auto mt-1.5 h-1.5 w-28 overflow-hidden rounded-full bg-neutral-100 dark:bg-white/10">
                                    <div class="h-full rounded-full bg-primary-500 transition-all duration-300" style="width: {{ max(3, $sale['metricShare']) }}%"></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-5 py-12 text-center text-neutral-500">Chưa có dữ liệu {{ $entityLabel }} trong khoảng lọc.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <aside class="border-t border-neutral-100 bg-neutral-50/70 p-5 dark:border-white/10 dark:bg-slate-900/70 xl:border-l xl:border-t-0">
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-600 dark:text-sky-300">Top 3 nổi bật</p>
                <h3 class="mt-1 text-lg font-bold text-neutral-950 dark:text-white">Bảng vàng {{ $this->metricLabel() }}</h3>
            </div>

            @if ($podium !== [])
                <div class="mt-6 flex h-64 items-end justify-center gap-3">
                    @foreach ($podium as $sale)
                        @php
                            $height = match ($sale['rank']) {
                                1 => 'h-44',
                                2 => 'h-36',
                                default => 'h-28',
                            };
                            $tone = match ($sale['rank']) {
                                1 => 'from-primary-600 to-primary-500',
                                2 => 'from-sky-500 to-sky-400',
                                default => 'from-indigo-500 to-indigo-400',
                            };
                            $avatarTone = match ($sale['rank']) {
                                1 => 'from-primary-700 to-primary-500',
                                2 => 'from-sky-600 to-cyan-400',
                                default => 'from-indigo-600 to-violet-400',
                            };
                        @endphp
                        <div class="flex min-w-0 flex-1 flex-col items-center">
                            <div class="relative z-10" x-data="{ imageFailed: false }">
                                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-gradient-to-br {{ $avatarTone }} text-xs font-extrabold tracking-wide text-white shadow-md dark:border-slate-900">
                                    @if ($sale['avatar'])
                                        <img
                                            x-show="!imageFailed"
                                            x-on:error="imageFailed = true"
                                            src="{{ $sale['avatar'] }}"
                                            alt="{{ $sale['name'] }}"
                                            class="h-full w-full object-cover"
                                        >
                                    @endif
                                    <span @if ($sale['avatar']) x-show="imageFailed" x-cloak @endif>{{ $sale['initials'] }}</span>
                                </div>
                                <span class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-neutral-900 text-[10px] font-bold text-white">{{ $sale['rank'] }}</span>
                            </div>
                            <div class="-mt-2 flex w-full flex-col justify-end rounded-t-xl bg-gradient-to-b {{ $tone }} px-2 pb-3 pt-5 text-center text-white shadow-lg {{ $height }}">
                                <p class="text-[11px] font-semibold opacity-80">{{ $this->number($sale['orderCount']) }} đơn</p>
                                <p class="mt-1 truncate text-xs font-bold">{{ $this->metricDisplay($sale) }}</p>
                            </div>
                            <p class="mt-2 w-full truncate text-center text-xs font-bold text-neutral-700 dark:text-slate-200">{{ $sale['shortName'] }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-xl border border-dashed border-neutral-200 bg-white px-4 py-12 text-center text-sm text-neutral-500 dark:border-white/10 dark:bg-slate-950 dark:text-slate-400">
                    Chưa đủ dữ liệu để hiển thị top 3.
                </div>
            @endif

            <div class="mt-5 rounded-xl border border-primary-100 bg-primary-50 px-3 py-2 text-center text-xs font-semibold text-primary-700 dark:border-primary-400/20 dark:bg-primary-500/10 dark:text-sky-200">
                Đang xếp hạng theo {{ $this->metricLabel() }}
            </div>
        </aside>
    </div>
</section>
