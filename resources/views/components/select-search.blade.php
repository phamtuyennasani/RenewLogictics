@props([
    'name',
    'options' => [],
    'selected' => null,
    'placeholder' => '-- Chọn --',
    'search' => true,
    'disabled' => false,
    'required' => false,
])
@php
    $tomSelectOptions = collect($options)
        ->map(fn($label, $value) => ['value' => (string) $value, 'text' => (string) $label])
        ->values()
        ->toArray();
@endphp
<div
    x-data="selectSearch({
        propertyName: @js($name),
        initialOptions: @js($tomSelectOptions),
        placeholder: @js($placeholder),
        disabled: @js($disabled),
    })"
    data-js-required="{{ $required ? 'true' : 'false' }}"
    wire:ignore
>
    <select x-ref="select" autocomplete="off" @if($required) required @endif></select>
</div>
