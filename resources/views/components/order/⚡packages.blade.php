<?php

use Livewire\Component;

new class extends Component
{
    public array $packages = [];

    public function mount(array $packages = [])
    {
        $this->packages = $packages ?: [
            [
                'package_type' => null,
                'length' => '',
                'width' => '',
                'height' => '',
                'g_weight' => '',
            ]
        ];
    }

    public function addPackage()
    {
        $this->packages[] = [
            'package_type' => null,
            'length' => '',
            'width' => '',
            'height' => '',
            'g_weight' => '',
        ];
        $this->dispatch('packagesUpdated', packages: $this->packages);
    }

    public function removePackage($index)
    {
        if (count($this->packages) > 1) {
            unset($this->packages[$index]);
            $this->packages = array_values($this->packages);
            $this->dispatch('packagesUpdated', packages: $this->packages);
        }
    }

    public function updated($property)
    {
        // Chỉ dispatch khi thực sự cần thiết, không phải mỗi lần thay đổi
        // $this->dispatch('packagesUpdated', packages: $this->packages);
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
            <div class="border border-neutral-200 rounded-xl p-4 bg-neutral-50">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-sm font-medium text-neutral-700">Kiện {{ $index + 1 }}</span>
                    @if(count($packages) > 1)
                        <flux:button wire:click="removePackage({{ $index }})" size="sm" variant="danger">
                            Xóa
                        </flux:button>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <flux:field>
                        <flux:label badge="Bắt buộc">Dài (cm)</flux:label>
                        <flux:input
                            type="number"
                            step="0.1"
                            required
                            wire:model.blur="packages.{{ $index }}.length"
                            placeholder="0.0"
                            :class:input="$inputClass"
                        />
                        @error("packages.{$index}.length")<flux:error>{{ $message }}</flux:error>@enderror
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Bắt buộc">Rộng (cm)</flux:label>
                        <flux:input
                            type="number"
                            step="0.1"
                            required
                            wire:model.blur="packages.{{ $index }}.width"
                            placeholder="0.0"
                            :class:input="$inputClass"
                        />
                        @error("packages.{$index}.width")<flux:error>{{ $message }}</flux:error>@enderror
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Bắt buộc">Cao (cm)</flux:label>
                        <flux:input
                            type="number"
                            step="0.1"
                            required
                            wire:model.blur="packages.{{ $index }}.height"
                            placeholder="0.0"
                            :class:input="$inputClass"
                        />
                        @error("packages.{$index}.height")<flux:error>{{ $message }}</flux:error>@enderror
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Bắt buộc">Trọng lượng (kg)</flux:label>
                        <flux:input
                            type="number"
                            step="0.1"
                            required
                            wire:model.blur="packages.{{ $index }}.g_weight"
                            placeholder="0.0"
                            :class:input="$inputClass"
                        />
                        @error("packages.{$index}.g_weight")<flux:error>{{ $message }}</flux:error>@enderror
                    </flux:field>
                </div>
            </div>
        @endforeach
    </div>
</div>
