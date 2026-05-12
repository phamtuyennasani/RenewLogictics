<?php

use Livewire\Attributes\Modelable;
use Livewire\Component;

new class extends Component
{
    #[Modelable]
    public array $service;
    public array $itemServices;

    public array $dichvuOptions = [];
    public array $dichvuChitietOptions = [];
    public array $chinhanhOptions = [];
    public array $dichvudikemOptions = [];
    public array $loaibuuguiOptions = [];
    public array $lydoguihangOptions = [];
    public array $hinhthucguihangOptions = [];
    public array $deliverytermOptions = [];
    public array $tinhtrangdonOptions = [];

    public function mount(): void
    {
        $this->loadOptions();
    }
    protected function loadOptions(): void
    {
        $itemServices = collect($this->itemServices);
        $this->dichvuOptions = $itemServices->where('type', 'dichvuchinh')->values()->all();
        $this->dichvuChitietOptions = $itemServices->where('type', 'dichvuchitiet')->values()->all();
        $this->chinhanhOptions = $itemServices->where('type', 'chinhanh')->values()->all();
        $this->dichvudikemOptions = $itemServices->where('type', 'dichvudikem')->values()->all();
        $this->loaibuuguiOptions = $itemServices->where('type', 'loaibuugui')->values()->all();
        $this->lydoguihangOptions = $itemServices->where('type', 'lydoguihang')->values()->all();
        $this->hinhthucguihangOptions = $itemServices->where('type', 'hinhthucgui')->values()->all();
        $this->deliverytermOptions = $itemServices->where('type', 'deliveryterm')->values()->all();
        $this->tinhtrangdonOptions = $itemServices->where('type', 'tinhtrangdon')->values()->all();
    }
};
?>
@php
$selectErrorClass = 'rounded-lg ring-2 ring-red-500 ring-offset-1';
@endphp
<div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
    <div class="px-6 py-5 border-b border-neutral-100">
        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            Dịch vụ
        </h2>
    </div>

    <div class="p-6 space-y-5">
        <div class="grid grid-cols-3 gap-3">
            <flux:field>
                <flux:label badge="Bắt buộc">Dịch vụ chính</flux:label>
                <div wire:ignore @class([$selectErrorClass => $errors->has('service.id_dichvu')])>
                    <select class="tomselectEml" data-placeholder="Chọn dịch vụ" wire:model.live="service.id_dichvu" required autocomplete="off">
                        <option value="">-- Chọn dịch vụ --</option>
                        @foreach($dichvuOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['namevi'] }}</option>
                        @endforeach
                    </select>
                </div>
            </flux:field>
            <flux:field>
                <flux:label>Dịch vụ chi tiết</flux:label>
                <div wire:ignore>
                    <select class="tomselectEml" data-placeholder="Chọn chi tiết dịch vụ" wire:model.defer="service.id_chitiet_dichvu" autocomplete="off">
                        <option value="">-- Chọn chi tiết dịch vụ --</option>
                        @foreach($dichvuChitietOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['namevi'] }}</option>
                        @endforeach
                    </select>
                </div>
            </flux:field>
            <flux:field>
                <flux:label>Chi nhánh nhận hàng</flux:label>
                <div wire:ignore>
                    <select class="tomselectEml" data-placeholder="Chọn chi nhánh" wire:model.defer="service.id_chinhanh_nhanhang" autocomplete="off">
                        <option value="">-- Chọn chi nhánh --</option>
                        @foreach($chinhanhOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['namevi'] }}</option>
                        @endforeach
                    </select>
                </div>
            </flux:field>
        </div>
        @if(!empty($dichvudikemOptions))
        <flux:checkbox.group label="Dịch vụ đi kèm:" variant="buttons" class="flex flex-wrap gap-4" wire:model.defer="service.dichvudikem">
            @foreach($dichvudikemOptions as $option)
            <flux:checkbox value="{{ $option['id'] }}">
                <div class="flex items-center gap-2">
                    <flux:checkbox.indicator />
                    <span class="text-sm">{{ $option['namevi'] }}</span>
                </div>
            </flux:checkbox>
            @endforeach
        </flux:checkbox.group>
        @endif
        <flux:radio.group label="Loại bưu gửi:" variant="buttons" class="flex flex-wrap gap-4" wire:model.defer="service.loaibuugui">
            @foreach($loaibuuguiOptions as $option)
            <flux:radio value="{{ $option['id'] }}">
                <div class="flex items-center gap-2">
                    <flux:radio.indicator />
                    <span class="text-sm">{{ $option['namevi'] }}</span>
                </div>
            </flux:radio>
            @endforeach
        </flux:radio.group>
        <flux:radio.group label="Lý do gửi hàng:" variant="buttons" class="flex flex-wrap gap-4" wire:model.defer="service.lydoguihang">
            @foreach($lydoguihangOptions as $option)
            <flux:radio value="{{ $option['id'] }}">
                <div class="flex items-center gap-2">
                    <flux:radio.indicator />
                    <span class="text-sm">{{ $option['namevi'] }}</span>
                </div>
            </flux:radio>
            @endforeach
        </flux:radio.group>
        <flux:radio.group label="Hình thức gửi hàng:" variant="buttons" class="flex flex-wrap gap-4" wire:model.defer="service.hinhthucguihang">
            @foreach($hinhthucguihangOptions as $option)
            <flux:radio value="{{ $option['id'] }}">
                <div class="flex items-center gap-2">
                    <flux:radio.indicator />
                    <span class="text-sm">{{ $option['namevi'] }}</span>
                </div>
            </flux:radio>
            @endforeach
        </flux:radio.group>
        <flux:radio.group label="Delivery Term:" variant="buttons" class="flex flex-wrap gap-4" wire:model.defer="service.deliveryterm">
            @foreach($deliverytermOptions as $option)
            <flux:radio value="{{ $option['id'] }}">
                <div class="flex items-center gap-2">
                    <flux:radio.indicator />
                    <span class="text-sm">{{ $option['namevi'] }}</span>
                </div>
            </flux:radio>
            @endforeach
        </flux:radio.group>
        <flux:radio.group label="Tình trạng đơn:" variant="buttons" class="flex flex-wrap gap-4" wire:model.defer="service.tinhtrangdon">
            <div class="flex flex-wrap gap-4">
                @foreach($tinhtrangdonOptions as $option)
                <flux:radio value="{{ $option['id'] }}">
                    <div class="flex items-center gap-2">
                        <flux:radio.indicator />
                        <span class="text-sm">{{ $option['namevi'] }}</span>
                    </div>
                </flux:radio>
                @endforeach
            </div>
        </flux:radio.group>
    </div>
</div>
