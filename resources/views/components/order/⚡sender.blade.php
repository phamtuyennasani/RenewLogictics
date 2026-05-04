<?php

use Livewire\Component;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\Computed;
use App\Models\Province;
new class extends Component
{
    public $listSender;
    #[Modelable]
    public $sender;
    public $idSale;
    public function mount($listSender, $idSale){
        $this->listSender = $listSender;
        $this->idSale = $idSale;
    }
    #[Computed]
    public function citys()
    {
        return Province::all();
    }
    public function wards()
    {
        if (!$this->sender['id_city']) {
            return collect([]);
        }
        $city = Province::find($this->sender['id_city']);
        return $city ? $city->wards : collect([]);
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
            <div wire:ignore wire:key="select-sender-{{$idSale}}">
                 <flux:field >
                    <flux:label>Chọn người gủi từ danh sách có sẵn</flux:label>
                    <select data-template='custom-sender' class="tomselectEml tomselectEml-getCustomer" data-placeholder="Chọn người gửi từ danh sách" id="sender-select" autocomplete="off">
                        <option value=0>Người Gửi Mới</option>
                        @foreach($listSender as $item)
                            <option value="{{ $item['id'] }}" data-attr="{{ htmlentities(json_encode($item)) }}">AccNo. {{ $item['code'] }} - {{ $item['fullname'] }} - {{ $item['phone'] }} - {{ $item['email'] }} - {{ $item['company_name'] }} </option>
                        @endforeach
                    </select>
                </flux:field>
            </div>
            <flux:field>
                <flux:label badge="Bắt buộc">Tên công ty / Khách hàng</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.blur="sender.company"
                    placeholder="Tên công ty / Khách hàng"
                    :class:input="$inputClass"
                />
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
                </flux:field>
                <flux:field>
                    <flux:label badge="Bắt buộc">Số điện thoại</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model.blur="sender.phone"
                        placeholder="Số điện thoại"
                        :class:input="$inputClass"
                        mask:dynamic="
                            /^(03|05|07|08|09)/.test($input.replace(/\D/g, ''))
                                ? '9999 999 999'
                                : '999 9999 9999'
                        "
                    />
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
                <flux:field>
                    <flux:label>Quốc gia</flux:label>
                    <flux:input
                        type="text"
                        readonly variant="filled"
                        wire:model.blur="sender.country"
                        placeholder="Quốc gia"
                        :class:input="$inputClass"
                    />
                </flux:field>
                <flux:field class="col-span-2">
                    <flux:label badge="Bắt buộc">Địa chỉ</flux:label>
                    <flux:input
                        type="text"
                        wire:model.blur="sender.address"
                        placeholder="Địa chỉ"
                        :class:input="$inputClass"
                    />
                </flux:field>
                <flux:field>
                    <flux:label badge="Bắt buộc">Thành phố/Tỉnh</flux:label>
                    <x-select-search
                        name="sender.id_city"
                        :options="$this->citys()->pluck('name', 'id')->toArray()"
                        :selected="$sender['id_city'] ?? null"
                        placeholder="-- Chọn thành phố/tỉnh --"
                    />
                </flux:field>
                <flux:field wire:key="province-{{ $sender['id_city'] ?? 'none' }}">
                    <flux:label badge="Bắt buộc">Phường/Xã</flux:label>
                    <x-select-search
                        name="sender.id_ward"
                        :options="$this->wards()->pluck('name', 'id')->toArray()"
                        :selected="$sender['id_ward'] ?? null"
                        placeholder="-- Chọn phường/xã --"
                        :disabled="$this->wards()->isEmpty()"
                    />
                </flux:field>
            </div>
        </div>
    </div>
</div>
