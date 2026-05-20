<div class="max-w-[260px] text-sm">
    <div class="truncate font-medium text-neutral-800">{{ data_get($data, 'company') ?: data_get($data, 'fullname') ?: '—' }}</div>
    <div class="truncate text-xs text-neutral-500">
        @if(! empty($receiver))
            {{ collect([data_get($data, 'city'), data_get($data, 'state'), data_get($data, 'postcode')])->filter()->implode(', ') }}
        @else
            {{ data_get($data, 'phone') ?: data_get($data, 'address') }}
        @endif
    </div>
</div>
