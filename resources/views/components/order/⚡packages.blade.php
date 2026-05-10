<?php

use Livewire\Component;
use Livewire\Attributes\Computed;


new class extends Component
{
    public array $packages = [];
    public int $totalNumber = 1;
    public function mount(array $packages = [])
    {
        $this->packages = $packages ?: [
            [
                'number_of_package' => 1,
                'package_type' => null,
                'length' => '',
                'width' => '',
                'height' => '',
                'g_weight' => '',
                'v_weight' => '',
                'c_weight' => '',
            ]
        ];
    }
    #[Computed]
    public function loaikien()
    {
        return Cache::remember('loaikien', now()->addDay(), function () {
            return \App\Models\News::whereType('loaikien')->pluck('namevi', 'id')->toArray();
        });
    }
    public function addPackage()
    {
        $this->packages[] = [
            'number_of_package' => 1,
            'package_type' => null,
            'length' => '',
            'width' => '',
            'height' => '',
            'g_weight' => '',
            'v_weight' => '',
            'c_weight' => '',
        ];
        $this->updateTotal();
    }

    public function updated($propertyName)
    {
        if (str_starts_with($propertyName, 'packages.') && str_ends_with($propertyName, '.number_of_package')) {
            $this->updateTotal();
        }
    }

    protected function updateTotal()
    {
        $this->totalNumber = collect($this->packages)->sum('number_of_package');
        $this->dispatch('packagesUpdated', packages: $this->packages);
    }

    public function removePackage($index)
    {
        if (count($this->packages) > 1) {
            unset($this->packages[$index]);
            $this->packages = array_values($this->packages);
            $this->updateTotal();
        }
    }
};
?>

@php
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
@endphp

<div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
    <div class="px-6 py-5 border-b border-neutral-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Kiện hàng
        </h2>
        <flux:button wire:click="addPackage" size="sm" variant="primary">
            + Thêm kiện
        </flux:button>
    </div>

    <div class="p-6 space-y-4">
        @foreach($packages as $index => $package)
            <div class="border border-neutral-200 rounded-xl p-3 bg-neutral-50">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-neutral-700">Thông tin kiện</span>
                    @if(count($packages) > 1)
                        <flux:button wire:click="removePackage({{ $index }})" size="sm" >
                            <flux:icon.trash variant="mini" color="red" />
                        </flux:button>
                    @endif
                </div>
                <div class="grid grid-cols-7 gap-3">
                    <flux:field>
                        <flux:label badge="*">Số lượng</flux:label>
                        <flux:input
                            type="text"
                            required
                            wire:model.live="packages.{{ $index }}.number_of_package"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="Math.max(1, parseInt($input.replace(/\D/g, '')) || 1).toString()"
                        />
                    </flux:field>
                    <flux:field class="col-span-2">
                        <flux:label badge="*">Loại kiện</flux:label>
                        <flux:select wire:model="packages.{{ $index }}.package_type" placeholder="Loại kiện">
                            @foreach($this->loaikien() as $id => $name)
                                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <flux:field>
                        <flux:label badge="*">Dài (cm)</flux:label>
                        <flux:input
                            type="text"
                            required
                            wire:model.blur="packages.{{ $index }}.length"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="$input.replace(/[^0-9.,]/g, '').replace(/([.,])[.,]+/g, '$1').replace(/^[.,]/, '0$&').replace(/([.,]\d*)[.,]/g, '$1')"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label badge="*">Rộng (cm)</flux:label>
                        <flux:input
                            type="text"
                            required
                            wire:model.blur="packages.{{ $index }}.width"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="$input.replace(/[^0-9.,]/g, '').replace(/([.,])[.,]+/g, '$1').replace(/^[.,]/, '0$&').replace(/([.,]\d*)[.,]/g, '$1')"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label badge="*">Cao (cm)</flux:label>
                        <flux:input
                            type="text"
                            required
                            wire:model.blur="packages.{{ $index }}.height"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="$input.replace(/[^0-9.,]/g, '').replace(/([.,])[.,]+/g, '$1').replace(/^[.,]/, '0$&').replace(/([.,]\d*)[.,]/g, '$1')"
                        />
                    </flux:field>

                    <flux:field>
                        <flux:label badge="*">Cân nặng KG</flux:label>
                        <flux:input
                            type="text"
                            required
                            wire:model.blur="packages.{{ $index }}.g_weight"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="$input.replace(/[^0-9.,]/g, '').replace(/([.,])[.,]+/g, '$1').replace(/^[.,]/, '0$&').replace(/([.,]\d*)[.,]/g, '$1')"
                        />
                    </flux:field>
                    
                </div>
            </div>
        @endforeach
        <div class="grid grid-cols-2 gap-3">
            <div class="">
                <div class="text-sm text-gray-500 flex items-center gap-2">
                    Tổng số kiện: 
                    <div class="inline-flex max-w-[100px]">
                        <flux:input type="text" readonly variant="filled" wire:model="totalNumber" />
                    </div>
                </div>
            </div>
            <div class=""></div>
        </div>
    </div>
</div>
