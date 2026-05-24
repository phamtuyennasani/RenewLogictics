@php
    $receiver = $order->receiver ?? [];
    $company = data_get($receiver, 'company') ?: data_get($receiver, 'fullname') ?: '—';
    $contactName = data_get($receiver, 'fullname') ?: data_get($receiver, 'contact_name') ?: data_get($receiver, 'name');
    $phone = data_get($receiver, 'phone');
    $contactLine = collect([$contactName, $phone])->filter()->implode(' - ');
@endphp

<div class="max-w-[300px] whitespace-nowrap text-sm">
    <div class="truncate font-medium text-neutral-800 uppercase">{{ $company }}</div>
    <div class="truncate text-xs text-neutral-500">{{ $contactLine ?: '—' }}</div>
</div>
