@php
    $pickup = $order->pickups
        ->filter(fn ($pickup) => $pickup->status && in_array($pickup->status, $visiblePickupStatuses, true))
        ->sortByDesc(fn ($pickup) => $pickup->pivot?->created_at)
        ->first();
@endphp

<div class="space-y-1">
    <span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->bill_status?->color() ?? 'bg-neutral-100 text-neutral-700' }}">
        {{ $order->bill_status?->label() ?? 'Chưa rõ' }}
    </span>

    @if($pickup)
        <div>
            <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $pickup->status->color() }}">
                PickUp: {{ $pickup->status->label() }}
            </span>
        </div>
    @endif
</div>
