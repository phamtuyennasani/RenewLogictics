<?php

use Livewire\Component;
use App\Models\Member;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?string $uuid = null;
    public array $formData = [];
    public bool $isSaving = false;

    public array $provinces = [];
    public array $wards = [];
    public array $sales = [];
    public array $ctvs = [];

    public function mount($uuid = null)
    {
        $this->uuid = $uuid;

        // Load provinces
        $this->provinces = DB::table('province')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        // Load sales
        $this->sales = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username'])
            ->mapWithKeys(fn ($u) => [
                $u->id => trim(($u->fullname ?: $u->username) . ' (' . $u->username . ')')
            ])
            ->toArray();

        // Load CTVs
        $this->ctvs = [];
        if (!empty($this->formData['id_sale'])) {
            $this->loadCtvs($this->formData['id_sale']);
        }

        $this->formData = [
            'company_name' => '',
            'fullname' => '',
            'email' => '',
            'phone' => '',
            'id_sale' => null,
            'id_ctv' => null,
            'id_province' => null,
            'id_ward' => null,
            'address' => '',
        ];

        if ($uuid) {
            $item = Member::where('uuid', $uuid)->sender()->firstOrFail();
            $this->formData = [
                'company_name' => $item->company_name ?? '',
                'fullname' => $item->fullname ?? '',
                'email' => $item->email ?? '',
                'phone' => $item->phone ?? '',
                'id_sale' => $item->id_sale ?? null,
                'id_ctv' => $item->id_ctv ?? null,
                'id_province' => $item->id_province ?? null,
                'id_ward' => $item->id_ward ?? null,
                'address' => $item->address ?? '',
            ];

            // Load wards if province selected
            if ($this->formData['id_province']) {
                $this->loadWards($this->formData['id_province']);
            }

            // Load CTVs if sale selected
            if (!empty($this->formData['id_sale'])) {
                $this->loadCtvs($this->formData['id_sale']);
            }
        }
    }

    public function loadCtvs($saleId)
    {
        if (!$saleId) {
            $this->ctvs = [];
            return;
        }
        $this->ctvs = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'CTV'))
            ->where('id_sale', $saleId)
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->mapWithKeys(fn ($u) => [
                $u->id => trim(($u->fullname ?: $u->username) . ($u->code ? ' (' . $u->code . ')' : ''))
            ])
            ->toArray();
    }

    public function loadWards($provinceId)
    {
        if (!$provinceId) {
            $this->wards = [];
            return;
        }
        $this->wards = DB::table('wards')
            ->where('parent_code', $provinceId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function updatedFormDataIdProvince($value)
    {
        $this->formData['id_ward'] = null;
        $this->loadWards($value);
    }

    public function updatedFormDataIdSale($value)
    {
        $this->formData['id_ctv'] = null;
        $this->loadCtvs($value);
    }

    protected function rules(): array
    {
        return [
            'formData.company_name' => 'required|string|max:255',
            'formData.fullname' => 'required|string|max:255',
            'formData.email' => 'nullable|email|max:255',
            'formData.phone' => 'required|string|max:20',
            'formData.id_sale' => [
                'required',
                'exists:user,id',
                function ($attribute, $value, $fail) {
                    $isSale = User::query()
                        ->whereKey($value)
                        ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
                        ->exists();

                    if (!$isSale) {
                        $fail('Nhân viên được chọn không thuộc nhóm SALE');
                    }
                },
            ],
            'formData.id_ctv' => 'nullable|exists:user,id',
            'formData.id_province' => 'nullable|exists:province,id',
            'formData.id_ward' => [
                'nullable',
                'exists:wards,id',
                function ($attribute, $value, $fail) {
                    $provinceId = $this->formData['id_province'] ?? null;
                    if (!$provinceId || !$value) {
                        return;
                    }

                    $valid = DB::table('wards')
                        ->where('id', $value)
                        ->where('parent_code', $provinceId)
                        ->exists();

                    if (!$valid) {
                        $fail('Phường/xã không thuộc tỉnh/thành phố đã chọn');
                    }
                },
            ],
            'formData.address' => 'nullable|string|max:500',
        ];
    }

    protected function messages(): array
    {
        return [
            'formData.company_name.required' => 'Tên công ty không được để trống',
            'formData.fullname.required' => 'Họ và tên không được để trống',
            'formData.email.email' => 'Email không hợp lệ',
            'formData.phone.required' => 'Số điện thoại không được để trống',
            'formData.id_sale.required' => 'Vui lòng chọn nhân viên SALE phụ trách',
            'formData.id_sale.exists' => 'Nhân viên SALE không hợp lệ',
            'formData.id_ctv.exists' => 'CTV không hợp lệ',
            'formData.id_province.exists' => 'Tỉnh/thành phố không hợp lệ',
            'formData.id_ward.exists' => 'Phường/xã không hợp lệ',
        ];
    }

    public function save()
    {
        $this->isSaving = true;
        try {
            $this->validate($this->rules(), $this->messages());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSaving = false;
            throw $e;
        }

        $this->formData = array_map(fn($v) => is_string($v) ? trim($v) : $v, $this->formData);

        // Auto-gen code
        $code = $this->generateUniqueCode();

        $data = [
            'company_name' => $this->formData['company_name'],
            'fullname' => $this->formData['fullname'],
            'email' => $this->formData['email'] ?: null,
            'phone' => $this->formData['phone'],
            'code' => $code,
            'id_sale' => $this->formData['id_sale'],
            'id_ctv' => $this->formData['id_ctv'] ?: null,
            'id_province' => $this->formData['id_province'] ?: null,
            'id_ward' => $this->formData['id_ward'] ?: null,
            'address' => $this->formData['address'] ?: null,
            'type' => 'sender',
        ];

        if ($this->uuid) {
            $member = Member::where('uuid', $this->uuid)->sender()->firstOrFail();
            $member->update($data);
        } else {
            $data['uuid'] = (string) Str::uuid();
            Member::create($data);
        }

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: $this->uuid ? 'Cập nhật Sender thành công!' : 'Tạo Sender thành công!',
            variant: 'success'
        );

        return $this->redirect(route('sender.index'), navigate: true);
    }

    public function goBack()
    {
        return $this->redirect(route('sender.index'), navigate: true);
    }

    private function generateUniqueCode(): string
    {
        do {
            $random = strtoupper(Str::random(4));
            $code = 'SEND' . $random;
        } while (Member::where('code', $code)->exists());

        return $code;
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
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
@endphp

<div class="mx-auto space-y-6">

    {{-- PAGE HEADER --}}
    <div class="flex items-center gap-3">
        <button wire:click="goBack"
                class="p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <div>
            <p class="text-sm text-neutral-500 capitalize">Sender / Người gửi</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $uuid ? 'Chỉnh sửa' : 'Thêm mới' }} Sender
            </h1>
        </div>
    </div>

    {{-- MAIN FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Thông tin Sender
            </h2>
        </div>

        <div class="p-6 space-y-5">

            {{-- Tên công ty --}}
            <flux:field>
                <flux:label badge="Bắt buộc">Tên công ty</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.defer="formData.company_name"
                    :invalid="$errors->has('formData.company_name')"
                    placeholder="Nhập tên công ty..."
                    :class:input="$inputClass"
                />
                @error('formData.company_name')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>

            {{-- Họ và tên --}}
            <flux:field>
                <flux:label badge="Bắt buộc">Họ và tên</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.defer="formData.fullname"
                    :invalid="$errors->has('formData.fullname')"
                    placeholder="Nhập họ và tên..."
                    :class:input="$inputClass"
                />
                @error('formData.fullname')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>

            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input
                        type="email"
                        wire:model.defer="formData.email"
                        :invalid="$errors->has('formData.email')"
                        placeholder="Nhập email..."
                        :class:input="$inputClass"
                    />
                    @error('formData.email')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
                <flux:field>
                    <flux:label badge="Bắt buộc">Số điện thoại</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model.defer="formData.phone"
                        :invalid="$errors->has('formData.phone')"
                        placeholder="Nhập số điện thoại..."
                        :class:input="$inputClass"
                    />
                    @error('formData.phone')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <div class="pt-2 border-t border-neutral-100">
                <h3 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Phân công
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label badge="Bắt buộc">Nhân viên SALE phụ trách</flux:label>
                    <x-select-search
                        name="formData.id_sale"
                        :options="$sales"
                        :selected="$formData['id_sale'] ?? null"
                        placeholder="-- Chọn nhân viên SALE --"
                        wire:change="$refresh"
                    />
                    @error('formData.id_sale')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>CTV phụ trách (Tùy chọn)</flux:label>
                    <div wire:key="sender-ctv-{{ $formData['id_sale'] ?? 'none' }}">
                        <x-select-search
                            name="formData.id_ctv"
                            :options="$ctvs"
                            :selected="$formData['id_ctv'] ?? null"
                            placeholder="-- Chọn CTV --"
                            :disabled="empty($formData['id_sale'])"
                        />
                    </div>
                    @error('formData.id_ctv')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <div class="pt-2 border-t border-neutral-100">
                <h3 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Địa chỉ
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Tỉnh/Thành phố</flux:label>
                    <x-select-search
                        name="formData.id_province"
                        :options="collect($provinces)->pluck('name', 'id')->toArray()"
                        :selected="$formData['id_province'] ?? null"
                        placeholder="-- Chọn tỉnh/thành phố --"
                    />
                    @error('formData.id_province')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Phường/Xã</flux:label>
                    <div wire:key="sender-ward-{{ $formData['id_province'] ?? 'none' }}">
                        <x-select-search
                            name="formData.id_ward"
                            :options="collect($wards)->pluck('name', 'id')->toArray()"
                            :selected="$formData['id_ward'] ?? null"
                            placeholder="-- Chọn phường/xã --"
                            :disabled="empty($formData['id_province'])"
                        />
                    </div>
                    @error('formData.id_ward')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Địa chỉ chi tiết</flux:label>
                <flux:input
                    type="text"
                    wire:model.defer="formData.address"
                    :invalid="$errors->has('formData.address')"
                    placeholder="Số nhà, đường, khu vực..."
                    :class:input="$inputClass"
                />
                @error('formData.address')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>

        </div>

        {{-- ACTION BUTTONS --}}
        <div class="px-6 py-4 border-t border-neutral-100 flex items-center justify-end bg-neutral-50/50">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="goBack"
                    class="px-5 py-2.5 text-sm font-medium text-red-600 bg-red-100 border border-red-300
                           rounded-xl hover:bg-red-50 hover:text-red-800 cursor-pointer
                           transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Hủy bỏ
                </button>
                <button
                    type="button"
                    wire:click="save"
                    wire:disabled="isSaving"
                    class="px-6 py-2.5 text-sm font-medium text-white rounded-xl
                           transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5
                           flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed
                           disabled:hover:shadow-none disabled:hover:translate-y-0"
                    style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
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
                        Lưu {{ $this->uuid ? 'cập nhật' : 'mới' }}
                    @endif
                </button>
            </div>
        </div>
    </div>
</div>
