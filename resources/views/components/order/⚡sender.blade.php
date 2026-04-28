<?php

use Livewire\Component;

new class extends Component
{
    public $listSale;
    public array $sender = [
        'company' => '',
        'fullname' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
    ];

    public function mount(array $listSale = []){
        $this->listSale = $listSale;
    }

    public function updated($property){
    }
};
?>

@php
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
@endphp
<div>
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">
        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Người gửi
            </h2>
        </div>
        <div class="p-6 space-y-5">
            <flux:field>
                <flux:label badge="Bắt buộc">Tên công ty / Khách hàng</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.blur="sender.company"
                    placeholder="Tên công ty / Khách hàng"
                    :class:input="$inputClass"
                />
                @error('sender.company')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>
            <div class="grid grid-cols-3 gap-5">
                <flux:field>
                    <flux:label badge="Bắt buộc">Tên người gửi</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model.blur="sender.fullname"
                        placeholder="Tên người gửi"
                        :class:input="$inputClass"
                    />
                    @error('sender.fullname')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
                <flux:field>
                    <flux:label badge="Bắt buộc">Số điện thoại</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model.blur="sender.phone"
                        placeholder="Số điện thoại"
                        :class:input="$inputClass"
                    />
                    @error('sender.phone')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input
                        type="email"
                        wire:model.blur="sender.email"
                        placeholder="Email"
                        :class:input="$inputClass"
                    />
                </flux:field>
            </div>
            <flux:field>
                <flux:label badge="Bắt buộc">Địa chỉ</flux:label>
                <flux:textarea
                    wire:model.blur="sender.address"
                    rows="3"
                    placeholder="Địa chỉ chi tiết"
                    :class:input="$inputClass"
                />
                @error('sender.address')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>
        </div>
    </div>
</div>
