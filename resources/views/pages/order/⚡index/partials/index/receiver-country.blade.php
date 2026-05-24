@php
    $country = $order->receiverCountry ?: $order->receiverCountryLegacy;
    $countryName = $country?->name ?: data_get($order->receiver, 'country', '—');
    $countryCode = $country?->iso2 ?: $country?->iso3;
@endphp

<div class="max-w-[140px] whitespace-nowrap text-sm">
    <div class="truncate font-medium text-neutral-800">{{ $countryName ?: '—' }}</div>
    @if ($countryCode)
        <div class="truncate text-xs text-neutral-500">{{ $countryCode }}</div>
    @endif
</div>
