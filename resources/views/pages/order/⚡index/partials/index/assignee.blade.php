<div class="text-sm">
    <div class="font-medium text-neutral-800">{{ $order->sale?->fullname ?: $order->sale?->username ?: '—' }}</div>
    <div class="text-xs text-neutral-500">{{ $order->customer?->fullname ?: $order->customer?->company_name ?: '—' }}</div>
</div>
