<?php

use App\Models\News;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

new class extends Component
{
    #[Modelable]
    public array $phuphihaiquan = [];
    protected bool $syncingPhuphi = false;
    public function mount(array $phuphihaiquan = []): void
    {
        $this->phuphihaiquan = $phuphihaiquan ?: [];
    }
    protected function defaultPhuphi(): array
    {
        return [
            'id_loaiphuphi' => null,
            'soluong' => 1,
            'price' => 0,
            'vat_percent' => 0,
            'vat_amount' => 0,
            'total' => 0,
            'total_after_vat' => 0,
        ];
    }

    protected function normalizeMoneyValue(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return 0;
        }
        $normalized = preg_replace('/[^\d]/', '', $value);
        return $normalized === '' ? 0 : (float) $normalized;
    }

    protected function recalculateRow(int $index): void
    {
        if (! isset($this->phuphihaiquan[$index])) {
            return;
        }
        $soluong = max(1, (int) ($this->phuphihaiquan[$index]['soluong'] ?? 1));
        $price = $this->normalizeMoneyValue($this->phuphihaiquan[$index]['price'] ?? 0);
        $total = $soluong * $price;
        if ($this->phuphihaiquan[$index]['soluong'] != $soluong) {
            $this->phuphihaiquan[$index]['soluong'] = $soluong;
        }
        if ($this->phuphihaiquan[$index]['price'] != $price) {
            $this->phuphihaiquan[$index]['price'] = $price;
        }
        if (($this->phuphihaiquan[$index]['total'] ?? 0) != $total) {
            $this->phuphihaiquan[$index]['total'] = $total;
            $this->phuphihaiquan[$index]['total_after_vat'] = $total;
        }
    }

    public function addPhuphi(): void
    {
        $this->syncingPhuphi = true;
        try {
            $this->phuphihaiquan[] = $this->defaultPhuphi();
            $this->js("
                requestAnimationFrame(() => {
                    window.TomSelectHelper?.init();
                });
            ");
        } finally {
            $this->syncingPhuphi = false;
        }
    }
    public function removePhuphi(int $index): void
    {
        
        unset($this->phuphihaiquan[$index]);
        $this->phuphihaiquan = array_values($this->phuphihaiquan);
    }

    #[Computed]
    public function loaiphuphi(): array
    {
        return Cache::remember('phuphidonhang', now()->addDay(), function () {
            return News::query()
                ->select([
                    'id',
                    'namevi',
                    DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(options2, '$.price')), 0) as price"),
                ])
                ->whereType('phuphidonhang')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                    'price' => (float) $item->price,
                ])
                ->toArray();
        });
    }

    public function updated($property, $value): void
    {
        if ($this->syncingPhuphi) {
            return;
        }
        if (! str_starts_with($property, 'phuphihaiquan.')) {
            return;
        }
        [, $index, $field] = array_pad(explode('.', $property), 3, null);

        if ($index === null || $field === null || ! isset($this->phuphihaiquan[(int) $index])) {
            return;
        }
        $index = (int) $index;
        $this->syncingPhuphi = true;
        try {
            if ($field === 'id_loaiphuphi') {
                $selected = collect($this->loaiphuphi)->firstWhere('id', (int) $value);
                $this->phuphihaiquan[$index]['price'] = (float) ($selected['price'] ?? 0);
                $this->recalculateRow($index);
                return;
            }
            if (in_array($field, ['soluong', 'price'], true)) {
                $this->recalculateRow($index);
            }
        } finally {
            $this->syncingPhuphi = false;
        }
    }
    #[Computed]
    public function totalPhuphi(): float
    {
        return collect($this->phuphihaiquan)->sum(function (array $phuphi) {
            return ((float) ($phuphi['soluong'] ?? 0)) * $this->normalizeMoneyValue($phuphi['price'] ?? 0);
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
            Phụ phí hải quan
        </h2>
        <flux:button wire:click="addPhuphi" size="sm" variant="primary">
            + Thêm phụ phí
        </flux:button>
    </div>

    <div class=" space-y-4 {{ count($this->phuphihaiquan) > 0 ? 'p-6' : '' }}">
        @foreach ($this->phuphihaiquan as $index => $phuphi)
            <div wire:key="phuphi-row-{{ $index }}" class="grid grid-cols-12 gap-3 items-end">
                <flux:field class="col-span-6">
                    <flux:label badge="Bắt buộc">Loại phụ phí</flux:label>
                    <div wire:ignore @class([$selectErrorClass => $errors->has('phuphihaiquan.' . $index . '.id_loaiphuphi')])>
                        <select class="tomselectEml" data-placeholder="Chọn loại phụ phí" data-livewire-model="phuphihaiquan.{{ $index }}.id_loaiphuphi" required autocomplete="off">
                            <option value="">-- Chọn loại phụ phí --</option>
                            @foreach($this->loaiphuphi as $k => $v)
                                <option value="{{ $v['id'] }}">{{ $v['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </flux:field>
                <flux:field class="col-span-2">
                    <flux:label>Số lượng</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model.live.debounce.300ms="phuphihaiquan.{{ $index }}.soluong"
                        placeholder=""
                        :class:input="$errors->has('phuphihaiquan.'.$index.'.soluong') ? $inputClass.' '.$inputErrorClass : $inputClass"
                        mask:dynamic="Math.max(1, parseInt($input.replace(/\D/g, '')) || 1).toString()"
                    />
                </flux:field>
                <flux:field class="col-span-2">
                    <flux:label>Đơn giá</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model="phuphihaiquan.{{ $index }}.price"
                        placeholder=""
                        variant="filled"
                        :class:input="$inputClass"
                        mask:dynamic="$money($input)"
                    />
                </flux:field>
                
                <flux:field class="col-span-2">
                    <flux:label>Tổng giá</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input
                            type="text"
                            readonly
                            wire:model="phuphihaiquan.{{ $index }}.total"
                            placeholder=""
                            variant="filled"
                            :class:input="$inputClass"
                            mask:dynamic="$money($input)"
                        />
                        <flux:button type="button" wire:click="removePhuphi({{ $index }})" >
                            <flux:icon.minus-circle />
                        </flux:button>
                    </div>
                </flux:field>
            </div>
        @endforeach
        @if (count($this->phuphihaiquan) > 0)
        <div class="flex items-center justify-end pt-4">
            <div class="text-sm font-semibold text-neutral-70 flex items-center justify-end gap-2">
                Tổng phụ phí: 
                <div class="inline-flex max-w-[100px]">
                    <flux:input type="text" readonly variant="filled" value="{{ number_format($this->totalPhuphi) }}" />
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
