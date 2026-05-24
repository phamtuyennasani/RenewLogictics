@php
    $agency = $order->daiLy?->namevi ?: $order->daiLy?->nameen;
    $airline = $order->hangBay?->namevi ?: $order->hangBay?->nameen;
    $transit = $order->doiTacChungChuyenMoi?->namevi
        ?: $order->doiTacChungChuyenMoi?->nameen
        ?: $order->doiTacChungChuyen?->namevi
        ?: $order->doiTacChungChuyen?->nameen;
    $partnerLine = collect([$airline, $transit])->filter()->implode(' - ');
@endphp

<div class="max-w-[220px] whitespace-nowrap text-sm">
    <div class="truncate font-medium text-neutral-800">{{ $agency ?: '' }}</div>
    <div class="truncate text-xs text-neutral-500">{{ $partnerLine }}</div>
</div>
