@php
    $canViewProfit = auth()->user()?->hasAnyRole(['admin', 'manager', 'ketoan']);
    $profitPayment = $order->payment_loinhuan ?? [];

    $number = static fn ($value): float => (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0));
    $money = static fn ($value): string => number_format(
        (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0)),
        0
    ).' đ';

    $profit = data_get($profitPayment, 'loinhuantamtinh', 0);
    $saleNoVat = $number(data_get($profitPayment, 'cuocban_no_vat', data_get($order->payment_cuocban, 'total_tongcuoc_no_vat', 0)));
    $ratio = data_get($profitPayment, 'tysuat', data_get($profitPayment, 'tysuatloinhuan'));

    if ($ratio === null && $saleNoVat != 0.0) {
        $ratio = round(($number($profit) * 100) / $saleNoVat, 2);
    }

    $profitClass = $number($profit) >= 0 ? 'text-emerald-700' : 'text-red-700';
    $ratioClass = $number($profit) >= 0 ? 'text-emerald-600' : 'text-red-600';
@endphp

@if (! $canViewProfit)
    <span class="text-neutral-400">—</span>
@else
    <div class="min-w-[130px] whitespace-nowrap text-sm">
        <div class="font-semibold {{ $profitClass }}">{{ $money($profit) }}</div>
        <div class="mt-0.5 text-xs text-neutral-500">
            Tỷ suất:
            <span class="font-medium {{ $ratioClass }}">
                {{ $ratio !== null ? number_format((float) $ratio, 2) : '0.00' }}%
            </span>
        </div>
    </div>
@endif
