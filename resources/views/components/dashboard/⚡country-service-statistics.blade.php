<?php

use App\Services\Reports\CountryServiceStatisticsService;
use Livewire\Attributes\Reactive;
use Livewire\Component;

new class extends Component {
    #[Reactive]
    public array $filters = [];

    public array $report = [];

    public function mount(CountryServiceStatisticsService $statistics): void
    {
        $this->loadReport($statistics);
    }

    public function updatedFilters(): void
    {
        $this->loadReport(app(CountryServiceStatisticsService::class));
    }

    public function placeholder(): string
    {
        return <<<'HTML'
            <section class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <div class="h-6 w-64 animate-pulse rounded bg-neutral-100"></div>
                <div class="mt-4 space-y-3">
                    <div class="h-14 animate-pulse rounded bg-neutral-100"></div>
                    <div class="h-14 animate-pulse rounded bg-neutral-100"></div>
                    <div class="h-14 animate-pulse rounded bg-neutral-100"></div>
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

    public function percent(mixed $value): string
    {
        return number_format((float) $value, 1, ',', '.').'%';
    }

    protected function loadReport(CountryServiceStatisticsService $statistics): void
    {
        $this->report = $statistics->report(auth()->user(), $this->filters);
    }
};
?>

<section class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
    <div class="border-b border-neutral-200 px-4 py-4">
        <h2 class="text-base font-bold uppercase text-primary-600">Quốc gia sử dụng dịch vụ</h2>
        <p class="mt-1 text-sm text-neutral-500">Thống kê quốc gia nhận hàng và chi tiết dịch vụ trong khoảng lọc hiện tại.</p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-[1000px] w-full text-sm">
            <thead class="bg-neutral-50 text-left text-neutral-500">
                <tr>
                    <th class="px-4 py-3 font-medium">Tên quốc gia</th>
                    <th class="px-4 py-3 font-medium">SL đơn hàng</th>
                    <th class="px-4 py-3 text-right font-medium">Cân nặng tính phí</th>
                    <th class="px-4 py-3 text-right font-medium">Doanh thu</th>
                    <th class="px-4 py-3 text-right font-medium">Tỷ suất</th>
                    <th class="px-4 py-3 text-right font-medium">Tỷ lệ giao</th>
                    <th class="w-12 px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200" x-data="{ expanded: {} }">
                @forelse (data_get($report, 'countries', []) as $index => $country)
                    <tr class="text-neutral-700">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 text-[10px] font-bold text-neutral-500">
                                    {{ $country['code'] ?: '—' }}
                                </span>
                                <span class="font-semibold text-primary-600">#{{ $index + 1 }}.</span>
                                <span class="font-medium text-neutral-900">{{ $country['name'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="min-w-44 overflow-hidden rounded bg-neutral-100">
                                <div class="bg-blue-500 px-2 py-1 text-center text-xs font-semibold text-white" style="width: {{ max(2, $country['orderShare']) }}%">
                                    {{ $this->number($country['orderCount']) }} đơn hàng
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right text-neutral-500">{{ $this->number($country['chargedWeight'], 2) }} KG</td>
                        <td class="px-4 py-3 text-right font-semibold text-primary-600">{{ $this->money($country['revenue']) }}</td>
                        <td class="px-4 py-3 text-right">{{ data_get($report, 'canSeeFinance') ? $this->percent($country['profitMargin']) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ $this->percent($country['deliveryRate']) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="rounded p-1 text-primary-600 hover:bg-primary-50" @click="expanded[{{ $index }}] = !expanded[{{ $index }}]" aria-label="Xem chi tiết dịch vụ">
                                <svg class="h-4 w-4 transition-transform" :class="{ 'rotate-180': expanded[{{ $index }}] }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    <tr x-show="expanded[{{ $index }}]" x-cloak>
                        <td colspan="7" class="bg-neutral-50 px-4 py-3">
                            <div class="grid grid-cols-7 gap-x-4 gap-y-2">
                                <div class="text-neutral-500">Dịch vụ</div>
                                <div class="text-neutral-500">Số lượng</div>
                                <div class="text-right text-neutral-500">Gross.W</div>
                                <div class="text-right text-neutral-500">Charged.W</div>
                                <div class="text-right text-neutral-500">Re-weight</div>
                                <div class="text-right text-neutral-500">Tiền cước</div>
                                <div class="text-right text-neutral-500">Lợi nhuận</div>

                                @foreach ($country['services'] as $service)
                                    <div class="font-semibold text-neutral-900">{{ $service['name'] }}</div>
                                    <div class="text-neutral-900">{{ $this->number($service['orderCount']) }} đơn</div>
                                    <div class="text-right text-neutral-700">{{ $this->number($service['grossWeight'], 2) }} KG</div>
                                    <div class="text-right font-medium text-amber-500">{{ $this->number($service['chargedWeight'], 2) }} KG</div>
                                    <div class="text-right text-red-600">{{ $this->number($service['reWeight'], 2) }} KG</div>
                                    <div class="text-right font-medium text-primary-600">{{ $this->money($service['revenue']) }}</div>
                                    <div class="text-right font-medium text-primary-700">{{ data_get($report, 'canSeeFinance') ? $this->money($service['profit']) : '—' }}</div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-neutral-500">
                            Chưa có dữ liệu quốc gia trong khoảng lọc.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>