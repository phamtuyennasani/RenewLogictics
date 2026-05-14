<?php

use Livewire\Component;
use App\Models\Order;

new class extends Component
{
    public Order $order;

    public function number(mixed $value, int $decimals = 2): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals) : '—';
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $packages = $order->packages;
    $totalGross = $packages->sum('row_g_weight');
    $totalVolume = $packages->sum('row_v_weight');
    $totalCharge = $packages->sum('row_c_weight');
    $totalQty = $packages->sum('number_of_package');
@endphp

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900">Cân nặng & kiện hàng</h2>
            <p class="text-xs text-neutral-500">Tổng hợp theo cân nặng gross, volume và chargeable</p>
        </div>
        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $packages->count() }} dòng kiện</span>
    </div>

    <div class="grid gap-3 border-b border-neutral-100 p-5 sm:grid-cols-4">
        <div class="rounded-lg bg-neutral-50 p-4"><p class="text-xs text-neutral-500">Số kiện</p><p class="mt-1 text-lg font-bold text-neutral-900">{{ $this->number($totalQty, 0) }}</p></div>
        <div class="rounded-lg bg-neutral-50 p-4"><p class="text-xs text-neutral-500">Gross weight</p><p class="mt-1 text-lg font-bold text-neutral-900">{{ $this->number($totalGross) }} kg</p></div>
        <div class="rounded-lg bg-neutral-50 p-4"><p class="text-xs text-neutral-500">Volume weight</p><p class="mt-1 text-lg font-bold text-neutral-900">{{ $this->number($totalVolume) }} kg</p></div>
        <div class="rounded-lg bg-primary-50 p-4"><p class="text-xs text-primary-600">Chargeable</p><p class="mt-1 text-lg font-bold text-primary-700">{{ $this->number($totalCharge) }} kg</p></div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-neutral-50 border-b border-neutral-100">
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Mã kiện</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Kích thước</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Số kiện</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">GW</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">VW</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">CW</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($packages as $package)
                    <tr class="hover:bg-neutral-50/60">
                        <td class="px-5 py-3 text-sm font-medium text-neutral-800">{{ $package->code ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $this->number($package->length, 0) }} × {{ $this->number($package->width, 0) }} × {{ $this->number($package->height, 0) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->number($package->number_of_package, 0) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->number($package->row_g_weight) }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->number($package->row_v_weight) }}</td>
                        <td class="px-5 py-3 text-right text-sm font-semibold text-neutral-800">{{ $this->number($package->row_c_weight) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-neutral-500">Chưa có dữ liệu kiện hàng</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
