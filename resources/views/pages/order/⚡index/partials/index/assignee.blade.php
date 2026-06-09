@php
    $role = collect(['admin', 'manager', 'ketoan', 'ops', 'cs', 'sale', 'ctv', 'shipper'])->first(fn ($role) => auth()->user()?->hasRole($role));

    $saleName = $order->sale?->fullname ?: $order->sale?->username;
    $saleCode = $order->sale?->code;
    $saleLabel = trim(($saleName ?: '—').' '.($saleCode ? "- {$saleCode}" : ''));
    $customer = $order->customerAccount;

    $customerCompany = data_get($customer?->options, 'company.company_short_name')
        ?: data_get($customer?->options, 'company.company_name')
        ?: $customer?->fullname
        ?: $customer?->username
        ?: $customer?->code;
@endphp

<div class="max-w-[300px] whitespace-nowrap text-sm">
    @if ($order->id_customer)
        <div class="truncate font-medium text-neutral-800">{{ $customerCompany ?: '—' }}</div>
        @if($role !== 'sale')<div class="truncate text-xs text-neutral-500">{{ $saleLabel }}</div> @endif
        @if($role === 'sale' && $customer->code)<div class="truncate text-xs text-neutral-500">Mã khách hàng: {{  $customer->code }}</div> @endif
    @else
        <div class="truncate font-medium text-neutral-800">{{ $saleLabel }}</div>
    @endif
</div>