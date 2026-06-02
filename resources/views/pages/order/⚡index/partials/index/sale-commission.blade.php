@php
    $payment = $order->payment_cuocvon ?? [];

    $money = static fn ($value): string => number_format(
        (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0)),
        0
    ).' đ';

    $commission = data_get($payment, 'bonus_sale_amount', 0);
    $percent = data_get($payment, 'bonus_sale_percent', 0);
@endphp

<div class="min-w-[120px] whitespace-nowrap text-sm">
    <div class="font-semibold text-emerald-700">{{ $money($commission) }}</div>
    @if ($percent)
        <div class="mt-0.5 text-xs text-neutral-500">{{ number_format((float) $percent, 1) }}%</div>
    @endif
</div>
