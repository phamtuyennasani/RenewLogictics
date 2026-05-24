@php
    $receiver = $order->receiver ?? [];
    $address = collect([
        data_get($receiver, 'address'),
        data_get($receiver, 'city'),
        data_get($receiver, 'state'),
    ])->filter()->implode(', ');
    $postcode = data_get($receiver, 'postcode');
@endphp

<div class="max-w-[320px] whitespace-nowrap text-sm">
    <div class="truncate font-medium text-neutral-800 whitespace-pre-line">{{ $address ?: '—' }}</div>
    <div class="truncate text-xs text-neutral-500">Postcode: {{ $postcode ?: '—' }}</div>
</div>
