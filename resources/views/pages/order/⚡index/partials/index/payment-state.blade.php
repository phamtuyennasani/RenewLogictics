@php
    $saleTotal = (float) data_get($order->payment_cuocban, 'total_tongcuoc', data_get($order->payment_cuocban, 'tongcuoc', 0));
    $costTotal = (float) data_get($order->payment_cuocvon, 'total_tongcuoc', data_get($order->payment_cuocvon, 'tongcuoc', 0));
@endphp
<div class="space-y-1 text-xs">
    <span class="inline-flex rounded-full px-2 py-0.5 {{ $saleTotal > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500' }}">KH: {{ $saleTotal > 0 ? 'Đã nhập' : 'Chưa nhập' }}</span>
    <span class="inline-flex rounded-full px-2 py-0.5 {{ $costTotal > 0 ? 'bg-blue-50 text-blue-700' : 'bg-neutral-100 text-neutral-500' }}">NCC: {{ $costTotal > 0 ? 'Đã nhập' : 'Chưa nhập' }}</span>
</div>
