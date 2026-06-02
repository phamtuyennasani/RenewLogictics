<?php

use Livewire\Component;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public string $name = '';
    public string $short_name = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $tax_code = '';
    public string $website = '';
    public string $representative = '';
    public $city_id = null;
    public $ward_id = null;
    public $dim = 6000;
    public array $cities = [];
    public array $wards = [];
    public bool $isSaving = false;

    public function mount()
    {
        abort_unless(\Gate::allows('settings.admin'), 403);
        $setting = Setting::first();
        $options = $setting->options ?? [];

        $this->name = $options['company_name'] ?? config('system.name', '');
        $this->short_name = $options['company_short_name'] ?? config('system.short_name', '');
        $this->address = $options['company_address'] ?? '';
        $this->phone = $options['company_phone'] ?? '';
        $this->email = $options['company_email'] ?? '';
        $this->tax_code = $options['company_tax_code'] ?? '';
        $this->website = $options['company_website'] ?? '';
        $this->representative = $options['company_representative'] ?? '';
        $this->city_id = $options['company_city_id'] ?? null;
        $this->ward_id = $options['company_ward_id'] ?? null;
        $this->dim = $options['dim'] ?? 6000;
        $this->cities = DB::table('province')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        if ($this->city_id) {
            $this->loadWards($this->city_id);
        }
    }

    public function loadWards($cityId): void
    {
        if (!$cityId) {
            $this->wards = [];
            return;
        }

        $this->wards = DB::table('wards')
            ->where('parent_code', $cityId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function updatedCityId($value): void
    {
        $this->ward_id = null;
        $this->loadWards($value);
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tax_code' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'representative' => 'nullable|string|max:255',
            'city_id' => 'nullable|exists:province,id',
            'ward_id' => [
                'nullable',
                'exists:wards,id',
                function ($attribute, $value, $fail) {
                    if (!$this->city_id || !$value) {
                        return;
                    }

                    $valid = DB::table('wards')
                        ->where('id', $value)
                        ->where('parent_code', $this->city_id)
                        ->exists();

                    if (!$valid) {
                        $fail('Phường/xã không thuộc tỉnh/thành phố đã chọn');
                    }
                },
            ],
            'dim' => 'required|numeric|min:1',
        ]);

        $this->isSaving = true;

        $setting = Setting::firstOrCreate([], ['namevi' => 'Cấu hình hệ thống', 'options' => []]);
        $options = $setting->options ?? [];

        $options['company_name'] = $this->name;
        $options['company_short_name'] = $this->short_name;
        $options['company_address'] = $this->address;
        $options['company_phone'] = $this->phone;
        $options['company_email'] = $this->email;
        $options['company_tax_code'] = $this->tax_code;
        $options['company_website'] = $this->website;
        $options['company_representative'] = $this->representative;
        $options['company_city_id'] = $this->city_id;
        $options['company_ward_id'] = $this->ward_id;
        $options['dim'] = $this->dim;
        unset($options['company_slogan']);

        $setting->update(['options' => $options]);

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cập nhật thông tin công ty thành công!',
            variant: 'success'
        );
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
$primaryHex = config('theme.primary.hex', '#3b82f6');
$accentHex  = config('theme.accent.hex', '#0ea5e9');
$gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
@endphp

<div class="mx-auto space-y-6">

    {{-- PAGE HEADER --}}
    <div class="flex items-center gap-3">
        <div>
            <p class="text-sm text-neutral-500">Cấu hình</p>
            <h1 class="text-2xl font-bold text-neutral-900">Thông tin công ty</h1>
        </div>
    </div>

    {{-- FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Thông tin doanh nghiệp
            </h2>
        </div>

        <div class="p-6 space-y-5">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label badge="Bắt buộc">Tên công ty</flux:label>
                    <flux:input wire:model="name" placeholder="VD: CÔNG TY TNHH VẬN CHUYỂN ABC" />
                    @error('name') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Tên viết tắt</flux:label>
                    <flux:input wire:model="short_name" placeholder="VD: ABC TRANS" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Người đại diện</flux:label>
                <flux:input wire:model="representative" placeholder="Họ và tên người đại diện pháp luật" />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>Số điện thoại</flux:label>
                    <flux:input wire:model="phone" placeholder="VD: 0901234567" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="email" placeholder="VD: info@company.com" />
                    @error('email') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Địa chỉ chi tiết</flux:label>
                <flux:input wire:model="address" placeholder="Số nhà, đường, khu vực..." />
            </flux:field>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>Tỉnh/Thành phố</flux:label>
                    <x-select-search
                        name="city_id"
                        :options="collect($cities)->pluck('name', 'id')->toArray()"
                        :selected="$city_id"
                        placeholder="-- Chọn tỉnh/thành phố --"
                    />
                    @error('city_id') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Phường/Xã</flux:label>
                    <div wire:key="company-ward-{{ $city_id ?? 'none' }}">
                        <x-select-search
                            name="ward_id"
                            :options="collect($wards)->pluck('name', 'id')->toArray()"
                            :selected="$ward_id"
                            placeholder="-- Chọn phường/xã --"
                            :disabled="empty($city_id)"
                        />
                    </div>
                    @error('ward_id') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <flux:field>
                    <flux:label>Mã số thuế</flux:label>
                    <flux:input wire:model="tax_code" placeholder="VD: 0123456789" />
                </flux:field>

                <flux:field>
                    <flux:label>Website</flux:label>
                    <flux:input wire:model="website" placeholder="VD: https://company.com" />
                    @error('website') <flux:error>{{ $message }}</flux:error> @enderror
                </flux:field>
            </div>

            <flux:field>
                <flux:label>DIM</flux:label>
                <flux:input type="number" min="1" step="1" wire:model="dim" placeholder="VD: 6000" />
                @error('dim') <flux:error>{{ $message }}</flux:error> @enderror
            </flux:field>

        </div>

        {{-- ACTION BUTTONS --}}
        <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-end bg-neutral-50/50">
            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                class="px-6 py-2.5 text-sm font-medium text-white rounded-xl
                       transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5
                       flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed
                       disabled:hover:shadow-none disabled:hover:translate-y-0"
                style="{{ $gradientStyle }}">
                @if ($isSaving)
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Đang lưu...
                @else
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                    </svg>
                    Lưu thông tin
                @endif
            </button>
        </div>
    </div>
</div>
