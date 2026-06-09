<div class="min-w-[100px] space-y-1">
    <a wire:navigate href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" class="inline-flex items-center gap-1.5 font-bold text-primary-700 hover:text-primary-800">
        <i class="pi pi-box text-xs"></i>
        {{ $order->id_bill ?: 'ORDER-'.$order->id }}
    </a>
    <div class="max-w-[190px] truncate text-xs text-neutral-500">{{ $order->tracking_code ?: $order->mathamchieu ?: 'Chưa có tracking' }}</div>
</div>
