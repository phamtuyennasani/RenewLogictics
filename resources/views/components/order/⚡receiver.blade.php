<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
new class extends Component
{
    #[Modelable]
    public $receiver;
    #[Computed]
    public function countries(){
        return \App\Models\Country::all();
    }
};
?>

@php
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
@endphp

<div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
    <div class="px-6 py-5 border-b border-neutral-100">
        <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
            <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Người nhận
        </h2>
    </div>

    <div class="p-6 space-y-5">
        <flux:field>
            <flux:label badge="Bắt buộc">Tên công ty nhận</flux:label>
            <flux:input
                type="text"
                required
                wire:model.blur="receiver.company"
                placeholder="Tên công ty"
                :class:input="$inputClass"
            />
        </flux:field>
        <div class="grid grid-cols-3 gap-5">
            <flux:field>
                <flux:label badge="Bắt buộc">Tên người nhận</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.blur="receiver.tenlienhe"
                    placeholder="Tên người nhận"
                    :class:input="$inputClass"
                />
            </flux:field>
            <flux:field>
                <flux:label badge="Bắt buộc">Số điện thoại</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.blur="receiver.phone"
                    placeholder="Số điện thoại"
                    :class:input="$inputClass"
                    mask:dynamic="$input.startsWith('+') ? '+' + '9'.repeat(15) : '9'.repeat(15)"
                />
            </flux:field>
            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input
                    type="email"
                    wire:model.blur="receiver.email"
                    placeholder="Email"
                    :class:input="$inputClass"
                />
            </flux:field>
            <flux:field>
                <flux:label badge="Bắt buộc">Quốc gia</flux:label>
                <x-select-search
                    name="receiver.country_id"
                    :options="$this->countries->pluck('name', 'id')->toArray()"
                    placeholder="-- Chọn quốc gia --"
                />
            </flux:field>
            <flux:field class="col-span-2">
                <flux:label badge="Bắt buộc">Địa chỉ</flux:label>
                <flux:input
                    wire:model.blur="receiver.address"
                    required
                    placeholder="Địa chỉ chi tiết"
                    :class:input="$inputClass"
                />
            </flux:field>
        </div>
        <flux:field>
            <flux:label badge="Bắt buộc">Postcode</flux:label>
            <flux:input
                type="text"
                required
                wire:model.blur="receiver.postcode"
                placeholder="Mã bưu chính"
                :class:input="$inputClass"
            />
            @error('receiver.postcode')<flux:error>{{ $message }}</flux:error>@enderror
        </flux:field>
    </div>
</div>
