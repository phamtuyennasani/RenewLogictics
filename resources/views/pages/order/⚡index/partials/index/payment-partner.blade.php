@php
    $debt = $order->congNoDaiLyDetails
        ->sortByDesc('created_at')
        ->first(fn ($detail) => $detail->congNoDaiLy)?->congNoDaiLy;
    $paymentStatus = match ($order->agency_payment_status) {
        \App\Enums\DebtStatusEnum::DA_THANH_TOAN->value => [
            'label' => 'Đã thanh toán',
            'class' => 'bg-emerald-100 text-emerald-700',
        ],
        \App\Enums\DebtStatusEnum::DA_THANH_TOAN_MOT_PHAN->value => [
            'label' => 'Thanh toán một phần',
            'class' => 'bg-amber-100 text-amber-700',
        ],
        default => [
            'label' => 'Chưa thanh toán',
            'class' => 'bg-neutral-100 text-neutral-600',
        ],
    };
@endphp

<div class="flex flex-col items-start gap-1.5">
    <span class="{{ $paymentStatus['class'] }} inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold">
        {{ $paymentStatus['label'] }}
    </span>

    @if ($debt)
        <a
            href="{{ route('congno.daily.show', $debt->uuid) }}"
            class="inline-flex max-w-[11rem] items-center gap-1.5 whitespace-nowrap rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800"
        >
            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-70"></span>
            <span class="truncate">{{ $debt->sohoadon }}</span>
        </a>
    @else
        <span class="text-xs font-medium text-neutral-400">Chưa tạo công nợ</span>
    @endif
</div>
