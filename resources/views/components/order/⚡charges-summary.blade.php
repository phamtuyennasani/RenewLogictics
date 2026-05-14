<?php

use Livewire\Component;
use App\Models\Order;

new class extends Component
{
    public Order $order;

    public function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0) . ' đ' : '—';
    }

    public function paymentTotal(?array $payment, string $preferredKey = 'total_tongcuoc'): float
    {
        if (!$payment) {
            return 0;
        }

        $preferred = data_get($payment, $preferredKey);

        if (is_numeric($preferred)) {
            return (float) $preferred;
        }

        return (float) data_get($payment, 'tongcuoc', 0);
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $cost = $this->paymentTotal($order->payment_cuocvon);
    $base = $this->paymentTotal($order->payment_cuocgoc);
    $sale = $this->paymentTotal($order->payment_cuocban);
    $profit = $this->paymentTotal($order->payment_loinhuan, 'loinhuan');
@endphp

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="border-b border-neutral-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-neutral-900">Cước & thanh toán</h2>
        <p class="text-xs text-neutral-500">Tóm tắt cước vốn, cước gốc, cước bán và lợi nhuận</p>
    </div>
    <div class="space-y-3 p-5">
        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
            <span class="text-sm text-neutral-500">Cước vốn</span>
            <span class="text-sm font-semibold text-neutral-800">{{ $this->money($cost) }}</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
            <span class="text-sm text-neutral-500">Cước gốc</span>
            <span class="text-sm font-semibold text-neutral-800">{{ $this->money($base) }}</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-primary-50 px-4 py-3">
            <span class="text-sm text-primary-600">Cước bán</span>
            <span class="text-sm font-semibold text-primary-700">{{ $this->money($sale) }}</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3">
            <span class="text-sm text-emerald-600">Lợi nhuận</span>
            <span class="text-sm font-semibold text-emerald-700">{{ $this->money($profit ?: ($sale - $cost)) }}</span>
        </div>
        <div class="grid grid-cols-2 gap-3 pt-2">
            <div class="rounded-lg border border-neutral-100 p-3">
                <p class="text-xs text-neutral-400">Kế toán</p>
                <p class="mt-1 text-sm font-semibold {{ $order->ketoan_success ? 'text-emerald-700' : 'text-amber-700' }}">{{ $order->ketoan_success ? 'Đã xác nhận' : 'Đang chờ' }}</p>
            </div>
            <div class="rounded-lg border border-neutral-100 p-3">
                <p class="text-xs text-neutral-400">Sale</p>
                <p class="mt-1 text-sm font-semibold {{ $order->sale_success ? 'text-emerald-700' : 'text-amber-700' }}">{{ $order->sale_success ? 'Đã xác nhận' : 'Đang chờ' }}</p>
            </div>
        </div>
    </div>
</section>
