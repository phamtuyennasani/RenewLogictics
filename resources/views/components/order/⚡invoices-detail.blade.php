<?php

use Livewire\Component;
use App\Models\Order;

new class extends Component
{
    public Order $order;

    public function money(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2) : '—';
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900">Invoice hàng hóa</h2>
            <p class="text-xs text-neutral-500">Danh sách khai báo hàng trong đơn</p>
        </div>
        <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-semibold text-neutral-700">{{ $order->invoices->count() }} dòng</span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-neutral-50 border-b border-neutral-100">
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Tên hàng</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Loại</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">Xuất xứ</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">HS Code</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">SL</th>
                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Đơn giá</th>
                    <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-neutral-500 text-right">Tổng</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($order->invoices as $invoice)
                    <tr class="hover:bg-neutral-50/60">
                        <td class="px-5 py-3 text-sm font-medium text-neutral-800">{{ $invoice->tenhang ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $invoice->loaihang ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $invoice->xuatxu ?: '—' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $invoice->hscode ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $invoice->soluong ?: '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm text-neutral-600">{{ $this->money($invoice->price) }}</td>
                        <td class="px-5 py-3 text-right text-sm font-semibold text-neutral-800">{{ $this->money($invoice->total) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-neutral-500">Chưa có invoice hàng hóa</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
