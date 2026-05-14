<?php

use Livewire\Component;
use App\Models\Order;

new class extends Component
{
    public Order $order;

    public function render()
    {
        return $this->view();
    }
};

?>

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="border-b border-neutral-100 px-5 py-4">
        <h2 class="text-sm font-semibold text-neutral-900">Mốc thời gian & ghi chú</h2>
        <p class="text-xs text-neutral-500">Các ngày nghiệp vụ chính của đơn</p>
    </div>
    <div class="space-y-5 p-5">
        <div class="space-y-4">
            @foreach([
                'Tạo đơn' => $order->created_at,
                'Nhận hàng' => $order->ngaynhanhang,
                'Xuất hàng' => $order->ngayxuathang,
                'Giao dự kiến' => $order->ngaygiaodukien,
                'Giao hàng' => $order->ngaygiaohang,
            ] as $label => $date)
                <div class="flex gap-3">
                    <div class="mt-1 h-2.5 w-2.5 rounded-full {{ $date ? 'bg-primary-500' : 'bg-neutral-300' }}"></div>
                    <div>
                        <p class="text-sm font-medium text-neutral-800">{{ $label }}</p>
                        <p class="text-xs text-neutral-500">{{ $date?->format('d/m/Y H:i') ?? 'Chưa cập nhật' }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg bg-neutral-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Ghi chú</p>
            <p class="mt-2 whitespace-pre-line text-sm leading-6 text-neutral-700">{{ $order->ghichu ?: 'Chưa có ghi chú' }}</p>
        </div>
    </div>
</section>
