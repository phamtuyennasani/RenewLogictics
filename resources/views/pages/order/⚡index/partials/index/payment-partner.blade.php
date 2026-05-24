@php
    $debt = $order->congNoDaiLyDetails
        ->sortByDesc('created_at')
        ->first(fn ($detail) => $detail->congNoDaiLy)?->congNoDaiLy;
@endphp

@if ($debt)
    <a
        href="{{ route('congno.daily.show', $debt->uuid) }}"
        class="inline-flex max-w-[11rem] items-center gap-1.5 whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800"
    >
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"></span>
        <span class="truncate">{{ $debt->sohoadon }}</span>
    </a>
@else
    <span class="inline-flex whitespace-nowrap rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-500">
        Chưa tạo công nợ
    </span>
@endif
