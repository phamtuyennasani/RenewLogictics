<?php

use Livewire\Component;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;
use App\Models\News;

new class extends Component
{
    #[Modelable]
    public $invoices = [];
    protected bool $syncingInvoices = false;

    public function addInvoice()
    {
        $this->invoices[] = [
            'tenhang' => null,
            'soluong' => 1,
            'xuatxu' => '',
            'loaihang' => '',
            'hscode' => '',
            'price' => 0,
            'total' => '0.00',
        ];
    }
    public function removeInvoice($index)
    {
        if (isset($this->invoices[$index])) {
            array_splice($this->invoices, $index, 1);
        }
    }

    protected function normalizeMoneyValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return 0;
        }

        $normalized = preg_replace('/[^\d.]/', '', $value);

        if (substr_count($normalized, '.') > 1) {
            $parts = explode('.', $normalized);
            $normalized = array_shift($parts) . '.' . implode('', $parts);
        }

        return $normalized === '' ? 0 : (float) $normalized;
    }

    protected function recalculateRow(int $index): void
    {
        if (! isset($this->invoices[$index])) {
            return;
        }

        $soluong = max(1, (int) ($this->invoices[$index]['soluong'] ?? 1));
        $price = $this->normalizeMoneyValue($this->invoices[$index]['price'] ?? 0);
        $total = round($soluong * $price, 2);

        if (($this->invoices[$index]['soluong'] ?? null) != $soluong) {
            $this->invoices[$index]['soluong'] = $soluong;
        }

        if (($this->invoices[$index]['price'] ?? null) != $price) {
            $this->invoices[$index]['price'] = $price;
        }

        if (($this->invoices[$index]['total'] ?? null) != $total) {
            $this->invoices[$index]['total'] = number_format($total, 2, '.', ',');
        }
    }

    public function updated($property, $value = null): void
    {
        if ($this->syncingInvoices || ! str_starts_with($property, 'invoices.')) {
            return;
        }

        [, $index, $field] = array_pad(explode('.', $property), 3, null);

        if ($index === null || $field === null || ! isset($this->invoices[(int) $index])) {
            return;
        }

        if (! in_array($field, ['soluong', 'price'], true)) {
            return;
        }

        $this->syncingInvoices = true;
        try {
            $this->recalculateRow((int) $index);
        } finally {
            $this->syncingInvoices = false;
        }
    }

    #[Computed]
    public function loaihang()
    {
        return Cache::remember('hanghoa', now()->addDay(), function () {
            return News::query()
                ->select([
                    'id',
                    'namevi',
                ])
                ->whereType('hanghoa')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                ])
                ->toArray();
        });
    }
};
?>
@php
$selectErrorClass = 'rounded-lg ring-2 ring-red-500 ring-offset-1';
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
@endphp
<div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
    <div class="px-6 py-5 border-b border-neutral-100 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Khai báo Invoice
        </h2>
        <flux:button wire:click="addInvoice" size="sm" variant="primary">
            + Thêm invoice
        </flux:button>
    </div>
    <div class=" space-y-4 {{ count($this->invoices) > 0 ? 'p-6' : '' }}">
        @foreach ($this->invoices as $index => $invoice)
            <div wire:key="invoice-row-{{ $index }}" class="grid grid-cols-12 gap-3 items-start">
                <flux:field class="col-span-5">
                    <flux:label badge="Bắt buộc">Tên hàng hóa</flux:label>
                    <flux:textarea
                        placeholder="Tên hàng hóa"
                        required
                        rows="auto"
                        wire:model="invoices.{{ $index }}.tenhang"
                    />
                </flux:field>
                <flux:field class="">
                    <flux:label>Xuất xứ</flux:label>
                    <flux:input
                        type="text"
                        wire:model="invoices.{{ $index }}.xuatxu"
                        placeholder=""
                        :class:input="$inputClass"
                    />
                </flux:field>
                <flux:field class="">
                    <flux:label>HS Code</flux:label>
                    <flux:input
                        type="text"
                        wire:model="invoices.{{ $index }}.hscode"
                        placeholder=""
                        :class:input="$inputClass"
                    />
                </flux:field>
                <flux:field class="">
                    <flux:label>Số lượng</flux:label>
                    <flux:input
                        type="text"
                        wire:model.live.debounce.300ms="invoices.{{ $index }}.soluong"
                        placeholder=""
                        :class:input="$inputClass"
                        mask:dynamic="Math.max(1, parseInt($input.replace(/\D/g, '')) || 1).toString()"
                    />
                </flux:field>
                <flux:field class="">
                    <flux:label>Loại kiện</flux:label>
                    <flux:select wire:model="invoices.{{ $index }}.loaihang" placeholder="Loại kiện" :class:input="$inputClass">
                        @foreach($this->loaihang as $item)
                            <flux:select.option value="{{ $item['id'] }}">{{ $item['name'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <flux:field class="">
                    <flux:label>Đơn giá</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix>$</flux:input.group.prefix>
                        <flux:input
                            type="text"
                            wire:model.live.debounce.300ms="invoices.{{ $index }}.price"
                            placeholder="0.00"
                            :class:input="$inputClass"
                            mask:dynamic="$money($input, '.', ',', 2)"
                        />
                    </flux:input.group>
                </flux:field>
                <flux:field class="col-span-2">
                    <flux:label>Tổng giá</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input
                            type="text"
                            readonly
                            wire:model="invoices.{{ $index }}.total"
                            placeholder=""
                            variant="filled"
                            :class:input="$inputClass"
                             mask:dynamic="$money($input, '.', ',', 2)"
                        />
                        <flux:button type="button" wire:click="removeInvoice({{ $index }})" >
                            <flux:icon.minus-circle />
                        </flux:button>
                    </div>
                </flux:field>
            </div>
        @endforeach
    </div>
</div>
