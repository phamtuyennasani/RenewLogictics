<div class="flex items-center justify-center gap-1">
    <a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-100" title="Xem đơn">
        <i class="pi pi-eye text-xs"></i>
    </a>
    <a href="{{ route('orders.payment', ['uuid' => $order->uuid]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-primary-700 hover:bg-primary-50" title="Giá và thanh toán">
        <i class="pi pi-credit-card text-xs"></i>
    </a>
    <a href="{{ route('orders.tracking', ['uuid' => $order->uuid]) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-md text-amber-700 hover:bg-amber-50" title="Tracking">
        <i class="pi pi-map-marker text-xs"></i>
    </a>
</div>
