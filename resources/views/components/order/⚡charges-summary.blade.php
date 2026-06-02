<?php

use App\Models\Order;
use App\Enums\OrderStatusEnum;
use Livewire\Component;

new class extends Component
{
    public Order $order;

    public function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0).' đ' : '—';
    }

    public function pct(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1).' %' : '—';
    }

    public function number(mixed $value): float
    {
        return (float) ($value ?? 0);
    }

    public function canSeeInternalCharges(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'manager', 'ketoan']) ?? false;
    }

    public function chotCuocLabel(): string
    {
        $order = $this->order;
        if (filled($order->sale_price_locked_at)) {
            $locker = $order->salePriceLocker;
            $name = $locker ? "{$locker->fullname} ({$locker->username})" : '—';
            return "Chốt lúc {$order->sale_price_locked_at->format('d/m/Y H:i')} bởi {$name}";
        }
        return 'Chưa chốt cước';
    }

    public function chotCuocStatus(): bool
    {
        return filled($this->order->sale_price_locked_at);
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $isInternal = $this->canSeeInternalCharges();
    $salePay   = $order->payment_cuocban  ?? [];
    $costPay   = $order->payment_cuocvon  ?? [];
    $basePay   = $order->payment_cuocgoc  ?? [];
    $profitPay = $order->payment_loinhuan ?? [];

    $money  = fn ($v) => $this->money($v);
    $number = fn ($v) => $this->number($v);

    $defaultTab = $isInternal ? 'ban' : 'ban';
@endphp

<section
    x-data="{
        tab: '{{ $defaultTab }}',
        tabs: ['ban', 'von', 'goc'],
        labels: { ban: 'Cước bán', von: 'Cước vốn', goc: 'Cước gốc' }
    }"
    class="rounded-xl border border-neutral-200 bg-white shadow-xs"
>
    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900 uppercase">Cước & thanh toán</h2>
            <p class="text-xs text-neutral-500">Tóm tắt chi tiết các loại cước</p>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex border-b border-neutral-100 px-3">
        <button
            type="button"
            @click="tab = 'ban'"
            :class="tab === 'ban'
                ? 'border-primary-600 text-primary-700 font-semibold'
                : 'border-transparent text-neutral-400 hover:text-neutral-600'"
            class="px-3 py-2.5 text-xs uppercase tracking-wide border-b-2 transition-colors"
        >Cước bán</button>
        @if ($isInternal)
            <button
                type="button"
                @click="tab = 'von'"
                :class="tab === 'von'
                    ? 'border-amber-600 text-amber-700 font-semibold'
                    : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                class="px-3 py-2.5 text-xs uppercase tracking-wide border-b-2 transition-colors"
            >Cước vốn</button>
            <button
                type="button"
                @click="tab = 'goc'"
                :class="tab === 'goc'
                    ? 'border-violet-600 text-violet-700 font-semibold'
                    : 'border-transparent text-neutral-400 hover:text-neutral-600'"
                class="px-3 py-2.5 text-xs uppercase tracking-wide border-b-2 transition-colors"
            >Cước gốc</button>
        @endif
    </div>

    <div class="p-5">
        <div x-show="tab === 'ban'" x-transition>
            @php($price      = $number(data_get($salePay, 'dongiaban', 0)))
            @php($ppxdAmt    = $number(data_get($salePay, 'ppxd_amount', 0)))
            @php($ppxdPct    = data_get($salePay, 'ppxd_percent', 0))
            @php($phuphi     = data_get($salePay, 'phuphi', []))
            @php($totalNoVat = $number(data_get($salePay, 'total_tongcuoc_no_vat', 0)))
            @php($totalVat   = $number(data_get($salePay, 'total_vat', 0)))
            @php($totalAll   = $number(data_get($salePay, 'total_tongcuoc', 0)))
            @php($hasPhuphi  = is_array($phuphi) && count($phuphi) > 0)
            @php($tongCuoc   = $price + $ppxdAmt)
            <div class="space-y-1">
                <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-2.5">
                    <span class="text-xs text-neutral-500">Cước bán</span>
                    <span class="text-sm font-semibold text-neutral-800">{{ $money($price) }}</span>
                </div>
                @if ($ppxdAmt > 0)
                    <div class="flex items-center justify-between px-4 py-1.5">
                        <span class="text-xs text-neutral-400">
                            Phụ phí xăng dầu
                            @if ($ppxdPct > 0)&nbsp;({{ number_format((float) $ppxdPct, 1) }}%)@endif
                        </span>
                        <span class="text-xs font-medium text-neutral-600">{{ $money($ppxdAmt) }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-neutral-100 px-4 py-2">
                        <span class="text-xs font-semibold text-neutral-600">Tổng cước</span>
                        <span class="text-sm font-semibold text-neutral-800">{{ $money($tongCuoc) }}</span>
                    </div>
                @endif
                @if ($hasPhuphi)
                    <div class="px-4 pt-1 pb-0.5">
                        <p class="mb-1 text-xs font-semibold text-neutral-500">Phụ phí</p>
                        @foreach ($phuphi as $pp)
                            @php($ppName = data_get($pp, 'name') ?: (data_get($pp, 'note') ?: '—'))
                            <div class="flex items-center justify-between py-1">
                                <span class="text-xs text-neutral-400">{{ $ppName }}</span>
                                <span class="text-xs font-medium text-neutral-600">{{ $money(data_get($pp, 'total_after_vat', 0)) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @if ($number(data_get($salePay, 'total_phuphi', 0)) > 0)
                        <div class="flex items-center justify-between px-4 py-1.5">
                            <span class="text-xs text-neutral-400">Tổng phụ phí</span>
                            <span class="text-xs font-semibold text-neutral-600">{{ $money(data_get($salePay, 'total_phuphi', 0)) }}</span>
                        </div>
                    @endif
                @endif
                <div class="flex items-center justify-between rounded-lg bg-neutral-100 px-4 py-2">
                    <span class="text-xs font-semibold text-neutral-600">Tổng cước trước VAT</span>
                    <span class="text-sm font-semibold text-neutral-800">{{ $money($totalNoVat > 0 ? $totalNoVat : $tongCuoc) }}</span>
                </div>
                @if ($totalVat > 0)
                    <div class="flex items-center justify-between px-4 py-1.5">
                        <span class="text-xs text-neutral-400">VAT</span>
                        <span class="text-xs font-medium text-neutral-600">{{ $money($totalVat) }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between rounded-lg bg-primary-50 px-4 py-2.5">
                    <span class="text-sm font-semibold text-primary-700">Tổng cước sau VAT</span>
                    <span class="text-sm font-bold text-primary-800">{{ $money($totalAll > 0 ? $totalAll : $tongCuoc) }}</span>
                </div>
            </div>
            {{-- Lợi nhuận & Tỷ suất cho internal (bán tab) --}}
            @if ($isInternal)
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-xs text-emerald-600">Lợi nhuận</p>
                        <p class="mt-1 text-base font-bold text-emerald-800">{{ $money(data_get($profitPay, 'loinhuan', 0)) }}</p>
                    </div>
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-3">
                        <p class="text-xs text-sky-600">Tỷ suất</p>
                        <p class="mt-1 text-base font-bold text-sky-800">{{ $this->pct(data_get($profitPay, 'tysuat', 0)) }}</p>
                    </div>
                </div>
                @php($loinhuanTamTinh = data_get($profitPay, 'loinhuantamtinh', 0))
                @php($tysuatTamTinh = data_get($profitPay, 'tysuattamtinh', 0))
                @if ($number($loinhuanTamTinh) !== $number(data_get($profitPay, 'loinhuan', 0)))
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                            <p class="text-xs text-neutral-400">Lợi nhuận tạm tính</p>
                            <p class="mt-1 text-sm font-semibold text-neutral-600">{{ $money($loinhuanTamTinh) }}</p>
                        </div>
                        <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-3">
                            <p class="text-xs text-neutral-400">Tỷ suất tạm tính</p>
                            <p class="mt-1 text-sm font-semibold text-neutral-600">{{ $this->pct($tysuatTamTinh) }}</p>
                        </div>
                    </div>
                @endif
            @endif
            {{-- Trạng thái chốt cước --}}
            <div class="mt-4 rounded-lg border {{ $this->chotCuocStatus() ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }} p-3">
                <p class="text-sm font-semibold {{ $this->chotCuocStatus() ? 'text-emerald-800' : 'text-amber-800' }}">
                    {{ $this->chotCuocStatus() ? 'Cước bán đã được chốt' : 'Cước bán chưa được chốt' }}
                </p>
                <p class="mt-0.5 text-xs {{ $this->chotCuocStatus() ? 'text-emerald-600' : 'text-amber-600' }}">
                    {{ $this->chotCuocLabel() }}
                </p>
            </div>
        </div>

        {{-- ===================== CƯỚC VỐN ===================== --}}
        @if ($isInternal)
            <div x-show="tab === 'von'" x-transition x-cloak>
                @php($cPrice   = $number(data_get($costPay, 'dongiavon', 0)))
                @php($cPpxdAmt = $number(data_get($costPay, 'ppxd_amount', 0)))
                @php($cPpxdPct = data_get($costPay, 'ppxd_percent', 0))
                @php($cPhuphi  = data_get($costPay, 'phuphi', []))
                @php($cNoVat   = $number(data_get($costPay, 'total_tongcuoc_no_vat', 0)))
                @php($cVat     = $number(data_get($costPay, 'total_vat', 0)))
                @php($cTotal   = $number(data_get($costPay, 'total_tongcuoc', 0)))
                @php($cHasPp   = is_array($cPhuphi) && count($cPhuphi) > 0)
                <div class="space-y-1">
                    <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-2.5">
                        <span class="text-xs text-neutral-500">Cước vốn</span>
                        <span class="text-sm font-semibold text-neutral-800">{{ $money($cPrice) }}</span>
                    </div>
                    @if ($cPpxdAmt > 0)
                        <div class="flex items-center justify-between px-4 py-1.5">
                            <span class="text-xs text-neutral-400">
                                Phụ phí xăng dầu
                                @if ($cPpxdPct > 0)&nbsp;({{ number_format((float) $cPpxdPct, 1) }}%)@endif
                            </span>
                            <span class="text-xs font-medium text-neutral-600">{{ $money($cPpxdAmt) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-neutral-100 px-4 py-2">
                            <span class="text-xs font-semibold text-neutral-600">Tổng cước</span>
                            <span class="text-sm font-semibold text-neutral-800">{{ $money($cPrice + $cPpxdAmt) }}</span>
                        </div>
                    @endif
                    @if ($cHasPp)
                        <div class="px-4 pt-1 pb-0.5">
                            <p class="mb-1 text-xs font-semibold text-neutral-500">Phụ phí</p>
                            @foreach ($cPhuphi as $pp)
                                @php($ppName = data_get($pp, 'name') ?: (data_get($pp, 'note') ?: '—'))
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-xs text-neutral-400">{{ $ppName }}</span>
                                    <span class="text-xs font-medium text-neutral-600">{{ $money(data_get($pp, 'total_after_vat', 0)) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if ($number(data_get($costPay, 'total_phuphi', 0)) > 0)
                            <div class="flex items-center justify-between px-4 py-1.5">
                                <span class="text-xs text-neutral-400">Tổng phụ phí</span>
                                <span class="text-xs font-semibold text-neutral-600">{{ $money(data_get($costPay, 'total_phuphi', 0)) }}</span>
                            </div>
                        @endif
                    @endif
                    <div class="flex items-center justify-between rounded-lg bg-neutral-100 px-4 py-2">
                        <span class="text-xs font-semibold text-neutral-600">Tổng cước trước VAT</span>
                        <span class="text-sm font-semibold text-neutral-800">{{ $money($cNoVat > 0 ? $cNoVat : ($cPrice + $cPpxdAmt)) }}</span>
                    </div>
                    @if ($cVat > 0)
                        <div class="flex items-center justify-between px-4 py-1.5">
                            <span class="text-xs text-neutral-400">VAT</span>
                            <span class="text-xs font-medium text-neutral-600">{{ $money($cVat) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between rounded-lg bg-amber-50 px-4 py-2.5">
                        <span class="text-sm font-semibold text-amber-700">Tổng cước sau VAT</span>
                        <span class="text-sm font-bold text-amber-800">{{ $money($cTotal > 0 ? $cTotal : ($cPrice + $cPpxdAmt)) }}</span>
                    </div>
                </div>
            </div>

            {{-- ===================== CƯỚC GỐC ===================== --}}
            <div x-show="tab === 'goc'" x-transition x-cloak>
                @php($bPrice   = $number(data_get($basePay, 'dongiagoc', 0)))
                @php($bPpxdAmt = $number(data_get($basePay, 'ppxd_amount', 0)))
                @php($bPpxdPct = data_get($basePay, 'ppxd_percent', 0))
                @php($bPhuphi  = data_get($basePay, 'phuphi', []))
                @php($bNoVat   = $number(data_get($basePay, 'total_tongcuoc_no_vat', 0)))
                @php($bVat     = $number(data_get($basePay, 'total_vat', 0)))
                @php($bTotal   = $number(data_get($basePay, 'total_tongcuoc', 0)))
                @php($bHasPp   = is_array($bPhuphi) && count($bPhuphi) > 0)
                <div class="space-y-1">
                    <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-2.5">
                        <span class="text-xs text-neutral-500">Cước gốc</span>
                        <span class="text-sm font-semibold text-neutral-800">{{ $money($bPrice) }}</span>
                    </div>
                    @if ($bPpxdAmt > 0)
                        <div class="flex items-center justify-between px-4 py-1.5">
                            <span class="text-xs text-neutral-400">
                                Phụ phí xăng dầu
                                @if ($bPpxdPct > 0)&nbsp;({{ number_format((float) $bPpxdPct, 1) }}%)@endif
                            </span>
                            <span class="text-xs font-medium text-neutral-600">{{ $money($bPpxdAmt) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-neutral-100 px-4 py-2">
                            <span class="text-xs font-semibold text-neutral-600">Tổng cước</span>
                            <span class="text-sm font-semibold text-neutral-800">{{ $money($bPrice + $bPpxdAmt) }}</span>
                        </div>
                    @endif
                    @if ($bHasPp)
                        <div class="px-4 pt-1 pb-0.5">
                            <p class="mb-1 text-xs font-semibold text-neutral-500">Phụ phí</p>
                            @foreach ($bPhuphi as $pp)
                                @php($ppName = data_get($pp, 'name') ?: (data_get($pp, 'note') ?: '—'))
                                <div class="flex items-center justify-between py-1">
                                    <span class="text-xs text-neutral-400">{{ $ppName }}</span>
                                    <span class="text-xs font-medium text-neutral-600">{{ $money(data_get($pp, 'total_after_vat', 0)) }}</span>
                                </div>
                            @endforeach
                        </div>
                        @if ($number(data_get($basePay, 'total_phuphi', 0)) > 0)
                            <div class="flex items-center justify-between px-4 py-1.5">
                                <span class="text-xs text-neutral-400">Tổng phụ phí</span>
                                <span class="text-xs font-semibold text-neutral-600">{{ $money(data_get($basePay, 'total_phuphi', 0)) }}</span>
                            </div>
                        @endif
                    @endif
                    <div class="flex items-center justify-between rounded-lg bg-neutral-100 px-4 py-2">
                        <span class="text-xs font-semibold text-neutral-600">Tổng cước trước VAT</span>
                        <span class="text-sm font-semibold text-neutral-800">{{ $money($bNoVat > 0 ? $bNoVat : ($bPrice + $bPpxdAmt)) }}</span>
                    </div>
                    @if ($bVat > 0)
                        <div class="flex items-center justify-between px-4 py-1.5">
                            <span class="text-xs text-neutral-400">VAT</span>
                            <span class="text-xs font-medium text-neutral-600">{{ $money($bVat) }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between rounded-lg bg-violet-50 px-4 py-2.5">
                        <span class="text-sm font-semibold text-violet-700">Tổng cước sau VAT</span>
                        <span class="text-sm font-bold text-violet-800">{{ $money($bTotal > 0 ? $bTotal : ($bPrice + $bPpxdAmt)) }}</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>

<style>
    [x-cloak] { display: none !important; }
</style>
