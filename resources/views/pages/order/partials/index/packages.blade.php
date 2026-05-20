@php
    $count = $order->packages->count();
    $weight = $order->packages->sum('row_c_weight') ?: $order->packages->sum('c_weight');
@endphp
<div class="text-right text-sm">
    <div class="font-semibold text-neutral-800">{{ $count }}</div>
    <div class="text-xs text-neutral-500">{{ number_format((float) $weight, 2) }} kg</div>
</div>
