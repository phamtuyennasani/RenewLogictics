@php
    $payment = $order->payment_cuocban ?? [];

    $money = static fn ($value): string => number_format(
        (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0)),
        0
    ).' đ';

    $total = data_get($payment, 'total_tongcuoc', data_get($payment, 'tongcuoc', 0));
    $totalNoVat = data_get($payment, 'total_tongcuoc_no_vat', 0);
@endphp

<div class="min-w-[150px] whitespace-nowrap text-sm">
    <div class="font-semibold text-primary-700">{{ $money($totalNoVat) }}</div>
    <div class="mt-0.5 text-xs text-neutral-500">
        Gồm VAT: <span class="font-medium text-neutral-700">{{ $money($total) }}</span>
    </div>
</div>
