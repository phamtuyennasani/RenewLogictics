@php
    use App\Enums\DebtStatusEnum;

    $debt = $order->congNoDetails
        ->sortByDesc('created_at')
        ->first(fn ($detail) => $detail->congNo)?->congNo;

    $isPaid = $order->customer_payment_status === DebtStatusEnum::DA_THANH_TOAN->value
        && (! $debt || $debt->status === DebtStatusEnum::DA_THANH_TOAN);

    $paymentStatus = $isPaid
        ? [
            'label' => 'Đã thanh toán',
            'class' => 'bg-emerald-100 text-emerald-700',
        ]
        : [
            'label' => 'Chưa thanh toán',
            'class' => 'bg-neutral-100 text-neutral-600',
        ];
@endphp

<div class="flex flex-col items-start gap-1.5">
    <span class="{{ $paymentStatus['class'] }} inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold">
        {{ $paymentStatus['label'] }}
    </span>

    @if ($debt)
        <a
            href="{{ route('congno.show', $debt->uuid) }}"
            class="inline-flex max-w-[11rem] items-center gap-1.5 whitespace-nowrap rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 hover:text-primary-800"
        >
            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"></span>
            <span class="truncate">{{ $debt->sohoadon }}</span>
        </a>
    @endif
</div>