@props([
    'title',
    'items' => [],
    'hideMoney' => false,
])

@php
    $rankBy = $hideMoney ? 'chargedWeight' : 'amount';
    $max = max(1, collect($items)->max($rankBy) ?: collect($items)->max('count') ?: 1);
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-neutral-200 bg-white p-4 shadow-sm']) }}>
    <h2 class="text-base font-bold text-neutral-950">{{ $title }}</h2>
    <p class="mt-1 text-sm text-neutral-500">Xếp hạng theo {{ $hideMoney ? 'cân nặng tính phí' : 'doanh thu' }} trong kỳ</p>

    <div class="mt-4 space-y-3">
        @forelse ($items as $item)
            @php
                $chargedWeight = (float) ($item['chargedWeight'] ?? 0);
                $amount = (float) ($item['amount'] ?? 0);
                $rankValue = $hideMoney ? $chargedWeight : $amount;
                $widthValue = $rankValue > 0 ? $rankValue : (float) ($item['count'] ?? 0);
            @endphp
            <div>
                <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                    <span class="truncate font-medium text-neutral-700">{{ $item['label'] ?? 'Chưa rõ' }}</span>
                    <span class="shrink-0 font-semibold text-neutral-950">{{ number_format($item['count'] ?? 0, 0, ',', '.') }} đơn</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
                    <div class="h-full rounded-full bg-blue-500" style="width: {{ max(4, ($widthValue / $max) * 100) }}%"></div>
                </div>
                @if ($hideMoney)
                    <p class="mt-1 text-xs font-medium text-neutral-500">{{ number_format($chargedWeight, 2, ',', '.') }} KG</p>
                @else
                    <p class="mt-1 text-xs font-medium text-neutral-500">{{ number_format($amount, 0, ',', '.') }} đ</p>
                @endif
            </div>
        @empty
            <p class="rounded-lg bg-neutral-50 p-4 text-sm text-neutral-500">Chưa có dữ liệu xếp hạng.</p>
        @endforelse
    </div>
</div>
