<?php

use Livewire\Component;
use App\Models\Order;

new class extends Component
{
    public Order $order;

    public function value(array|string|null $data, string $key, mixed $fallback = '—'): mixed
    {
        return data_get($data, $key, $fallback) ?: $fallback;
    }

    public function serviceValue(string $key, mixed $fallback = '—'): mixed
    {
        return data_get($this->order->service ?? [], $key, $fallback) ?: $fallback;
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class="grid gap-5 xl:grid-cols-3">
    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-neutral-900">Người gửi</h2>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <p class="text-base font-semibold text-neutral-900">{{ $this->value($order->sender, 'company') }}</p>
                <p class="text-sm text-neutral-500">{{ $this->value($order->sender, 'fullname') }}</p>
            </div>
            <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <div><p class="text-xs text-neutral-400">Điện thoại</p><p class="font-medium text-neutral-700">{{ $this->value($order->sender, 'phone') }}</p></div>
                <div><p class="text-xs text-neutral-400">Email</p><p class="font-medium text-neutral-700 break-words">{{ $this->value($order->sender, 'email') }}</p></div>
                <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Địa chỉ</p><p class="font-medium text-neutral-700">{{ $this->value($order->sender, 'address') }}</p></div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-neutral-900">Người nhận</h2>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <p class="text-base font-semibold text-neutral-900">{{ $this->value($order->receiver, 'company') }}</p>
                <p class="text-sm text-neutral-500">{{ $this->value($order->receiver, 'fullname', $this->value($order->receiver, 'tenlienhe')) }}</p>
            </div>
            <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <div><p class="text-xs text-neutral-400">Điện thoại</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'phone') }}</p></div>
                <div><p class="text-xs text-neutral-400">Postcode</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'postcode') }}</p></div>
                <div><p class="text-xs text-neutral-400">City/State</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'city') }} / {{ $this->value($order->receiver, 'state') }}</p></div>
                <div class="sm:col-span-2 xl:col-span-1"><p class="text-xs text-neutral-400">Địa chỉ</p><p class="font-medium text-neutral-700">{{ $this->value($order->receiver, 'address') }}</p></div>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-neutral-900">Dịch vụ & phụ trách</h2>
        </div>
        <div class="space-y-4 p-5">
            <div>
                <p class="text-base font-semibold text-neutral-900">{{ $order->dichvu?->namevi ?: '—' }}</p>
                <p class="text-sm text-neutral-500">{{ $order->chiTietDichVu?->namevi ?: 'Chưa có dịch vụ chi tiết' }}</p>
            </div>
            <div class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-1">
                <div><p class="text-xs text-neutral-400">Sản phẩm</p><p class="font-medium text-neutral-700">{{ $this->serviceValue('tensanpham') }}</p></div>
                <div><p class="text-xs text-neutral-400">Chi nhánh nhận</p><p class="font-medium text-neutral-700">{{ $order->chiNhanhNhanHang?->namevi ?: '—' }}</p></div>
                <div><p class="text-xs text-neutral-400">CS</p><p class="font-medium text-neutral-700">{{ $order->cs?->fullname ?: $order->cs?->username ?: '—' }}</p></div>
                <div><p class="text-xs text-neutral-400">OPS</p><p class="font-medium text-neutral-700">{{ $order->ops?->fullname ?: $order->ops?->username ?: '—' }}</p></div>
            </div>
        </div>
    </section>
</div>
