<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Order;
use App\Enums\OrderStatusEnum;

new #[Layout('layouts.app')] #[Title('Chi tiết đơn hàng')] class extends Component
{
    public Order $order;
    public array $statusSteps = [];

    public function mount(string $uuid): void
    {
        $this->order = Order::query()
            ->with([
                'customer',
                'creator:id,fullname,username,code',
                'sale:id,fullname,username,code',
                'manager:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'ops:id,fullname,username,code',
                'cs:id,fullname,username,code',
                'packages',
                'invoices',
                'dichvu:id,namevi',
                'chiTietDichVu:id,namevi',
                'chiNhanhNhanHang:id,namevi',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->statusSteps = [
            OrderStatusEnum::MOI_TAO,
            OrderStatusEnum::DA_XAC_NHAN,
            OrderStatusEnum::DA_NHAN_HANG,
            OrderStatusEnum::DUYET_XUAT_HANG,
            OrderStatusEnum::DANG_PHAT_HANG,
            OrderStatusEnum::DA_GIAO,
        ];
    }

    public function getStatusLabelProperty(): string
    {
        return $this->order->bill_status?->label() ?? 'Chưa rõ';
    }

    public function getStatusColorProperty(): string
    {
        return $this->order->bill_status?->color() ?? 'bg-neutral-100 text-neutral-700';
    }

    public function getProgressProperty(): int
    {
        $current = $this->order->bill_status;

        if (!$current instanceof OrderStatusEnum) {
            return 0;
        }

        $currentIndex = collect($this->statusSteps)->search(fn ($status) => $status === $current);

        if ($currentIndex === false) {
            return $current->isFinal() ? 100 : 0;
        }

        return (int) round((($currentIndex + 1) / count($this->statusSteps)) * 100);
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $primaryHex = config('theme.primary.hex', '#3b82f6');
    $accentHex = config('theme.accent.hex', '#0ea5e9');
    $gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
@endphp

<div class="space-y-5">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-sm text-neutral-500">Đơn hàng / Chi tiết</p>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-neutral-900">{{ $order->id_bill ?: 'Đơn hàng #' . $order->id }}</h1>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $this->statusColor }}">
                    {{ $this->statusLabel }}
                </span>
                @if($order->lock_order)
                    <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Đã khóa</span>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-500">
                <span>Tạo lúc {{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                <span>Tracking: <span class="font-medium text-neutral-700">{{ $order->tracking_code ?: '—' }}</span></span>
                <span>Sale: <span class="font-medium text-neutral-700">{{ $order->sale?->fullname ?: $order->sale?->username ?: '—' }}</span></span>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('orders.index') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-xs transition-all hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Quay lại
            </a>
            <a href="{{ route('tracking', ['idbill' => $order->id_bill ?: $order->id]) }}" target="_blank"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-xs transition-all hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Theo dõi
            </a>
            <a href="{{ route('orders.create') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:shadow-md"
                style="{{ $gradientStyle }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tạo đơn mới
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-neutral-900">Tiến trình xử lý</p>
                <p class="text-xs text-neutral-500">Từ khởi tạo đến giao hàng</p>
            </div>
            <span class="text-sm font-semibold text-neutral-700">{{ $this->progress }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
            <div class="h-full rounded-full transition-all" style="width: {{ $this->progress }}%; {{ $gradientStyle }}"></div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach($statusSteps as $step)
                @php
                    $isDone = $step->sortOrder() <= ($order->bill_status?->sortOrder() ?? 0) && !$order->bill_status?->isSpecial();
                    $isCurrent = $order->bill_status === $step;
                @endphp
                <div class="rounded-lg border px-3 py-2 {{ $isCurrent ? 'border-primary-200 bg-primary-50' : ($isDone ? 'border-emerald-100 bg-emerald-50' : 'border-neutral-100 bg-neutral-50') }}">
                    <p class="text-xs font-semibold {{ $isCurrent ? 'text-primary-700' : ($isDone ? 'text-emerald-700' : 'text-neutral-500') }}">{{ $step->label() }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <livewire:order.detail-overview :order="$order" wire:key="order-detail-overview-{{ $order->id }}" />

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            <livewire:order.shipment-metrics :order="$order" wire:key="order-shipment-metrics-{{ $order->id }}" />
            <livewire:order.invoices-detail :order="$order" wire:key="order-invoices-detail-{{ $order->id }}" />
        </div>
        <div class="space-y-5">
            <livewire:order.charges-summary :order="$order" wire:key="order-charges-summary-{{ $order->id }}" />
            <livewire:order.activity-notes :order="$order" wire:key="order-activity-notes-{{ $order->id }}" />
        </div>
    </div>
</div>
