@props([
    'title',
    'value',
    'meta' => '',
    'tone' => 'blue',
    'icon' => 'chart-bar',
])

@php
    $tones = [
        'blue' => ['bar' => 'bg-blue-500', 'soft' => 'bg-blue-50', 'text' => 'text-blue-700'],
        'sky' => ['bar' => 'bg-sky-500', 'soft' => 'bg-sky-50', 'text' => 'text-sky-700'],
        'emerald' => ['bar' => 'bg-emerald-500', 'soft' => 'bg-emerald-50', 'text' => 'text-emerald-700'],
        'amber' => ['bar' => 'bg-amber-500', 'soft' => 'bg-amber-50', 'text' => 'text-amber-700'],
        'red' => ['bar' => 'bg-red-500', 'soft' => 'bg-red-50', 'text' => 'text-red-700'],
        'slate' => ['bar' => 'bg-slate-500', 'soft' => 'bg-slate-50', 'text' => 'text-slate-700'],
        'orange' => ['bar' => 'bg-orange-500', 'soft' => 'bg-orange-50', 'text' => 'text-orange-700'],
    ];
    $toneClasses = $tones[$tone] ?? $tones['blue'];
@endphp

<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-lg border border-neutral-200 bg-white p-4 shadow-sm']) }}>
    <div class="absolute inset-y-4 left-0 w-1 rounded-r-full {{ $toneClasses['bar'] }}"></div>
    <div class="flex items-start justify-between gap-3 pl-2">
        <div class="min-w-0">
            <p class="truncate text-xs font-bold uppercase tracking-normal text-neutral-500">{{ $title }}</p>
            <p class="mt-3 truncate text-2xl font-black leading-none tracking-normal text-neutral-950">{{ $value }}</p>
            @if ($meta)
                <p class="mt-2 truncate text-xs font-medium text-neutral-500">{{ $meta }}</p>
            @endif
        </div>
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $toneClasses['soft'] }} {{ $toneClasses['text'] }}">
            @switch($icon)
                @case('clipboard-document-list')
                    <flux:icon.clipboard-document-list class="size-5" />
                    @break
                @case('check-circle')
                    <flux:icon.check-circle class="size-5" />
                    @break
                @case('truck')
                    <flux:icon.truck class="size-5" />
                    @break
                @case('x-circle')
                    <flux:icon.x-circle class="size-5" />
                    @break
                @case('banknotes')
                    <flux:icon.banknotes class="size-5" />
                    @break
                @case('receipt-percent')
                    <flux:icon.receipt-percent class="size-5" />
                    @break
                @case('document-check')
                    <flux:icon.document-check class="size-5" />
                    @break
                @case('clock')
                    <flux:icon.clock class="size-5" />
                    @break
                @case('exclamation-triangle')
                    <flux:icon.exclamation-triangle class="size-5" />
                    @break
                @case('building-office')
                    <flux:icon.building-office class="size-5" />
                    @break
                @default
                    <flux:icon.chart-bar class="size-5" />
            @endswitch
        </div>
    </div>
</div>
