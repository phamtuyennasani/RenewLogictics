<?php

use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public array $packages = [];
    public int $totalNumber = 1;
    public float $totalV_Weight = 0;
    public float $totalC_Weight = 0;
    public float $dim;

    public function mount(array $packages = [], float $dim = 6000): void
    {
        $this->packages = $packages ?: [$this->defaultPackage()];
        $this->dim = $dim;
        $this->recalculateAllPackages();
    }

    #[Computed]
    public function loaikien(): array
    {
        return Cache::remember('loaikien', now()->addDay(), function () {
            return \App\Models\News::whereType('loaikien')->pluck('namevi', 'id')->toArray();
        });
    }

    protected function defaultPackage(): array
    {
        return [
            'number_of_package' => 1,
            'package_type' => null,
            'length' => '',
            'width' => '',
            'height' => '',
            'g_weight' => '',
            'v_weight' => 0,
            'c_weight' => 0,
            'row_g_weight' => 0,
            'row_v_weight' => 0,
            'row_c_weight' => 0,
        ];
    }

    public function addPackage(): void
    {
        $this->packages[] = $this->defaultPackage();
        $this->recalculateTotals();
    }

    public function updated($propertyName, $value): void
    {
        if ($propertyName === 'dim') {
            $this->recalculateAllPackages();

            return;
        }

        if (! str_starts_with($propertyName, 'packages.')) {
            return;
        }

        [, $index, $field] = array_pad(explode('.', $propertyName), 3, null);

        if ($index === null || $field === null || ! isset($this->packages[(int) $index])) {
            return;
        }

        if ($field === 'number_of_package') {
            $this->updatePackageRowFromUnitWeights((int) $index);
            $this->recalculateTotals();

            return;
        }

        if (in_array($field, ['length', 'width', 'height', 'g_weight'], true)) {
            $this->calculatePackageRow((int) $index);
            $this->recalculateTotals();
        }
    }

    protected function calculatePackageRow(int $index): void
    {
        $pkg = $this->packages[$index] ?? $this->defaultPackage();
        $num = (int) ($pkg['number_of_package'] ?? 1);
        $calcWeight = \App\Actions\Order\CalculateChargeableWeightAction::execute(
            length: (float) ($pkg['length'] ?? 0),
            width: (float) ($pkg['width'] ?? 0),
            height: (float) ($pkg['height'] ?? 0),
            gWeight: (float) ($pkg['g_weight'] ?? 0),
            dim: $this->dim
        );

        $this->packages[$index]['v_weight'] = $calcWeight['v_weight'];
        $this->packages[$index]['c_weight'] = $calcWeight['c_weight'];
        $this->packages[$index]['row_g_weight'] = round((float) ($pkg['g_weight'] ?? 0) * $num, 2);
        $this->packages[$index]['row_v_weight'] = round($calcWeight['v_weight'] * $num, 2);
        $this->packages[$index]['row_c_weight'] = round($calcWeight['c_weight'] * $num, 2);
    }

    protected function updatePackageRowFromUnitWeights(int $index): void
    {
        $pkg = $this->packages[$index] ?? $this->defaultPackage();
        $num = (int) ($pkg['number_of_package'] ?? 1);

        $this->packages[$index]['row_g_weight'] = round((float) ($pkg['g_weight'] ?? 0) * $num, 2);
        $this->packages[$index]['row_v_weight'] = round((float) ($pkg['v_weight'] ?? 0) * $num, 2);
        $this->packages[$index]['row_c_weight'] = round((float) ($pkg['c_weight'] ?? 0) * $num, 2);
    }

    protected function recalculateTotals(): void
    {
        $this->totalNumber = 0;
        $this->totalV_Weight = 0;
        $this->totalC_Weight = 0;

        foreach ($this->packages as $package) {
            $this->totalNumber += (int) ($package['number_of_package'] ?? 1);
            $this->totalV_Weight += (float) ($package['row_v_weight'] ?? 0);
            $this->totalC_Weight += (float) ($package['row_c_weight'] ?? 0);
        }
    }

    protected function recalculateAllPackages(): void
    {
        foreach ($this->packages as $index => $_) {
            $this->calculatePackageRow($index);
        }

        $this->recalculateTotals();
    }

    public function removePackage($index): void
    {
        if (count($this->packages) <= 1) {
            return;
        }

        unset($this->packages[$index]);
        $this->packages = array_values($this->packages);
        $this->recalculateTotals();
    }
};
?>

@php
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
$packageTypes = $this->loaikien();
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
                        <flux:button wire:click="removePackage({{ $index }})" size="sm">
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
                            wire:model.live.debounce.300ms="packages.{{ $index }}.number_of_package"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="Math.max(1, parseInt($input.replace(/\D/g, '')) || 1).toString()"
                        />
                    </flux:field>

                    <flux:field class="col-span-2">
                        <flux:label badge="*">Loại kiện</flux:label>
                        <flux:select wire:model="packages.{{ $index }}.package_type" placeholder="Loại kiện">
                            @foreach($packageTypes as $id => $name)
                                <flux:select.option value="{{ $id }}">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>

                    <flux:field>
                        <flux:label badge="*">Dài (cm)</flux:label>
                        <flux:input
                            type="text"
                            required
                            wire:model.live.debounce.300ms="packages.{{ $index }}.length"
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
                            wire:model.live.debounce.300ms="packages.{{ $index }}.width"
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
                            wire:model.live.debounce.300ms="packages.{{ $index }}.height"
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
                            wire:model.live.debounce.300ms="packages.{{ $index }}.g_weight"
                            placeholder=""
                            :class:input="$inputClass"
                            mask:dynamic="$input.replace(/[^0-9.,]/g, '').replace(/([.,])[.,]+/g, '$1').replace(/^[.,]/, '0$&').replace(/([.,]\d*)[.,]/g, '$1')"
                        />
                    </flux:field>
                </div>
            </div>
        @endforeach

        <div class="grid grid-cols-2 gap-3">
            <div>
                <div class="text-sm text-gray-500 flex items-center gap-2">
                    Tổng số kiện:
                    <div class="inline-flex max-w-[100px]">
                        <flux:input type="text" readonly variant="filled" wire:model="totalNumber" />
                    </div>
                </div>
            </div>
            <div>
                <div class="text-sm text-gray-500 flex items-center gap-2 justify-end">
                    Tổng cân nặng Quy đổi / Tính phí (Kg):
                    <div class="flex items-center justify-end gap-3">
                        <div class="inline-flex max-w-[100px]">
                            <flux:input type="text" readonly variant="filled" wire:model="totalV_Weight" />
                        </div>
                        <div class="inline-flex max-w-[100px]">
                            <flux:input type="text" readonly variant="filled" wire:model="totalC_Weight" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
