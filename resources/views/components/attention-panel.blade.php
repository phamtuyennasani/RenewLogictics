@props([
    'title',
    'items' => [],
    'valueKey' => 'amount',
    'dateKey' => 'created_at',
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-neutral-200 bg-white p-4 shadow-sm']) }}>
    <div class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-neutral-950">{{ $title }}</h2>
            <p class="mt-1 text-sm text-neutral-500">Danh sách cần kiểm tra nhanh</p>
        </div>
        <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-700">{{ count($items) }}</span>
    </div>

    <div class="mt-4 divide-y divide-neutral-100">
        @forelse ($items as $item)
            @php
                $value = $item[$valueKey] ?? null;
                $displayValue = is_numeric($value) ? number_format((float) $value, 0, ',', '.').' đ' : ($value ?: 'Chưa rõ');
            @endphp
            <div class="flex items-center justify-between gap-3 py-2.5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-neutral-900">{{ $item['code'] ?? 'Chưa có mã' }}</p>
                    <p class="mt-0.5 text-xs text-neutral-500">{{ $item[$dateKey] ?? 'Chưa có ngày' }}</p>
                </div>
                <span class="shrink-0 rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-700">{{ $displayValue }}</span>
            </div>
        @empty
            <p class="rounded-lg bg-neutral-50 p-4 text-sm text-neutral-500">Không có mục cần chú ý trong khoảng lọc.</p>
        @endforelse
    </div>
</div>
