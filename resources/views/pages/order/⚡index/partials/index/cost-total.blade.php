@php
    $canViewCost = auth()->user()?->hasAnyRole(['admin', 'manager', 'ketoan']);
    $payment = $order->payment_cuocvon ?? [];

    $money = static fn ($value): string => number_format(
        (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0)),
        0
    ).' đ';

    $total = data_get($payment, 'total_tongcuoc', data_get($payment, 'tongcuoc', 0));
    $totalNoVat = data_get($payment, 'total_tongcuoc_no_vat', 0);
@endphp

@if (! $canViewCost)
    <span class="text-neutral-400">—</span>
@else
    <div class="min-w-[150px] whitespace-nowrap text-sm">
        <div class="font-semibold text-neutral-900">{{ $money($totalNoVat) }}</div>
        <div class="mt-0.5 text-xs text-neutral-500">
            Gồm VAT: <span class="font-medium text-neutral-700">{{ $money($total) }}</span>
        </div>
    </div>
@endif
