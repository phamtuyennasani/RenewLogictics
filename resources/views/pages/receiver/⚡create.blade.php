<?php

use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Member;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?string $uuid = null;
    public array $formData = [];
    public bool $isSaving = false;

    public array $countries = [];
    public array $states = [];
    public array $cities = [];
    public array $sales = [];
    public array $ctvs = [];
    public array $senders = [];

    public bool $showStateModal = false;
    public bool $showCityModal = false;

    public function mount($uuid = null)
    {
        $this->uuid = $uuid;

        // Load countries
        $this->countries = DB::table('countries')
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

        // Load Senders
        $this->senders = [];
        if (!empty($this->formData['id_sale'])) {
            $this->loadSenders($this->formData['id_sale'], $this->formData['id_ctv'] ?? null);
        }

        $this->formData = [
            'company_name' => '',
            'fullname' => '',
            'email' => '',
            'phone' => '',
            'id_sale' => null,
            'id_ctv' => null,
            'id_sender' => null,
            'country_id' => null,
            'state' => '',
            'cities' => '',
            'postcode' => '',
        ];

        if ($uuid) {
            $item = Member::where('uuid', $uuid)->receiver()->firstOrFail();
            $this->formData = [
                'company_name' => $item->company_name ?? '',
                'fullname' => $item->fullname ?? '',
                'email' => $item->email ?? '',
                'phone' => $item->phone ?? '',
                'id_sale' => $item->id_sale ?? null,
                'id_ctv' => $item->id_ctv ?? null,
                'id_sender' => $item->id_sender ?? null,
                'country_id' => $item->country_id ?? null,
                'state' => $item->state ?? '',
                'cities' => $item->cities ?? '',
                'postcode' => $item->postcode ?? '',
            ];

            // Load states if country selected
            if ($this->formData['country_id']) {
                $this->loadStates($this->formData['country_id']);
            }

            // Load cities if state entered
            if ($this->formData['state']) {
                $this->loadCities($this->formData['country_id'], $this->formData['state']);
            }

            // Load CTVs if sale selected
            if (!empty($this->formData['id_sale'])) {
                $this->loadCtvs($this->formData['id_sale']);
            }

            // Load Senders if sale selected
            if (!empty($this->formData['id_sale'])) {
                $this->loadSenders($this->formData['id_sale'], $this->formData['id_ctv'] ?? null);
            }
        }
    }

    public function loadSenders($saleId, $ctvId = null)
    {
        if (!$saleId) {
            $this->senders = [];
            return;
        }

        $query = Member::where('type', 'sender')
            ->where('id_sale', $saleId);

        // Nếu có CTV thì lọc thêm theo CTV
        if ($ctvId) {
            $query->where('id_ctv', $ctvId);
        }

        $this->senders = $query->orderBy('company_name')
            ->get(['id', 'company_name', 'code'])
            ->mapWithKeys(fn ($s) => [
                $s->id => trim($s->company_name . ($s->code ? ' (' . $s->code . ')' : ''))
            ])
            ->toArray();
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

    public function loadStates($countryId)
    {
        if (!$countryId) {
            $this->states = [];
            return;
        }
        $this->states = DB::table('states')
            ->where('country_id', $countryId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function loadCities($countryId, $stateName)
    {
        if (!$countryId || !$stateName) {
            $this->cities = [];
            return;
        }

        // Find state by name
        $state = DB::table('states')
            ->where('country_id', $countryId)
            ->where('name', 'like', $stateName)
            ->first();

        if (!$state) {
            $this->cities = [];
            return;
        }

        $this->cities = DB::table('cities')
            ->where('state_id', $state->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function updatedFormDataCountryId($value)
    {
        $this->formData['state'] = '';
        $this->formData['cities'] = '';
        $this->loadStates($value);
        $this->cities = [];
    }

    public function updatedFormDataState($value)
    {
        $this->formData['cities'] = '';
        if ($this->formData['country_id'] && $value) {
            $this->loadCities($this->formData['country_id'], $value);
        } else {
            $this->cities = [];
        }
    }

    public function updatedFormDataIdSale($value)
    {
        $this->formData['id_ctv'] = null;
        $this->formData['id_sender'] = null;
        $this->loadCtvs($value);
        $this->loadSenders($value, null);
    }

    public function updatedFormDataIdCtv($value)
    {
        $this->formData['id_sender'] = null;
        $this->loadSenders($this->formData['id_sale'], $value);
    }

    public function openStateModal()
    {
        if (empty($this->formData['country_id'])) {
            Flux::toast(duration: 2000, heading: 'Cảnh báo', text: 'Vui lòng chọn quốc gia trước!', variant: 'warning');
            return;
        }
        $this->showStateModal = true;
    }

    public function openCityModal()
    {
        if (empty($this->formData['country_id'])) {
            Flux::toast(duration: 2000, heading: 'Cảnh báo', text: 'Vui lòng chọn quốc gia trước!', variant: 'warning');
            return;
        }
        $this->showCityModal = true;
    }

    #[On('state-selected')]
    public function handleStateSelected($state)
    {
        $this->formData['state'] = $state;
        $this->formData['cities'] = '';
        $this->showStateModal = false;
        $this->loadCities($this->formData['country_id'], $state);
    }

    #[On('city-selected')]
    public function handleCitySelected($city)
    {
        $this->formData['cities'] = $city;
        $this->showCityModal = false;
    }

    #[On('close-modal')]
    public function handleCloseModal($modal)
    {
        if ($modal === 'state-selector') {
            $this->showStateModal = false;
        } elseif ($modal === 'city-selector') {
            $this->showCityModal = false;
        }
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
            'formData.id_sender' => 'nullable|exists:member,id',
            'formData.country_id' => 'nullable|exists:countries,id',
            'formData.state' => 'nullable|string|max:255',
            'formData.cities' => 'nullable|string|max:255',
            'formData.postcode' => 'nullable|string|max:20',
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
            'formData.id_sender.exists' => 'Sender không hợp lệ',
            'formData.country_id.exists' => 'Quốc gia không hợp lệ',
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
            'id_sender' => $this->formData['id_sender'] ?: null,
            'country_id' => $this->formData['country_id'] ?: null,
            'state' => $this->formData['state'] ?: null,
            'cities' => $this->formData['cities'] ?: null,
            'postcode' => $this->formData['postcode'] ?: null,
            'type' => 'receiver',
        ];

        if ($this->uuid) {
            $member = Member::where('uuid', $this->uuid)->receiver()->firstOrFail();
            $member->update($data);
        } else {
            $data['uuid'] = (string) Str::uuid();
            Member::create($data);
        }

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: $this->uuid ? 'Cập nhật Receiver thành công!' : 'Tạo Receiver thành công!',
            variant: 'success'
        );

        return $this->redirect(route('receiver.index'), navigate: true);
    }

    public function goBack()
    {
        return $this->redirect(route('receiver.index'), navigate: true);
    }

    private function generateUniqueCode(): string
    {
        do {
            $random = strtoupper(Str::random(4));
            $code = 'RECV' . $random;
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
            <p class="text-sm text-neutral-500 capitalize">Receiver / Người nhận</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $uuid ? 'Chỉnh sửa' : 'Thêm mới' }} Receiver
            </h1>
        </div>
    </div>

    {{-- MAIN FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Thông tin Receiver
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
                    <div wire:key="receiver-ctv-{{ $formData['id_sale'] ?? 'none' }}">
                        <x-select-search
                            name="formData.id_ctv"
                            :options="$ctvs"
                            :selected="$formData['id_ctv'] ?? null"
                            placeholder="-- Chọn CTV --"
                            :disabled="empty($formData['id_sale'])"
                            wire:change="$refresh"
                        />
                    </div>
                    @error('formData.id_ctv')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Sender phụ trách (Tùy chọn)</flux:label>
                    <div wire:key="receiver-sender-{{ $formData['id_sale'] ?? 'none' }}-{{ $formData['id_ctv'] ?? 'none' }}">
                        <x-select-search
                            name="formData.id_sender"
                            :options="$senders"
                            :selected="$formData['id_sender'] ?? null"
                            placeholder="-- Chọn Sender --"
                            :disabled="empty($formData['id_sale'])"
                        />
                    </div>
                    @error('formData.id_sender')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <div class="pt-2 border-t border-neutral-100">
                <h3 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Địa chỉ quốc tế
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Quốc gia</flux:label>
                    <x-select-search
                        name="formData.country_id"
                        :options="collect($countries)->pluck('name', 'id')->toArray()"
                        :selected="$formData['country_id'] ?? null"
                        placeholder="-- Chọn quốc gia --"
                    />
                    @error('formData.country_id')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>State/Tỉnh</flux:label>
                    <div class="relative">
                        <flux:input
                            type="text"
                            wire:model.defer="formData.state"
                            :invalid="$errors->has('formData.state')"
                            placeholder="Nhập state..."
                            :disabled="empty($formData['country_id'])"
                            :class:input="$inputClass"
                        />
                        @if(!empty($formData['country_id']))
                            <button type="button" wire:click="openStateModal" class="absolute cursor-pointer right-2 top-1/2 -translate-y-1/2 text-xs text-primary-600 hover:text-primary-700 font-medium">
                                Chọn từ danh sách
                            </button>
                        @endif
                    </div>
                    @error('formData.state')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>City/Thành phố</flux:label>
                    <div class="relative">
                        <flux:input
                            type="text"
                            wire:model.defer="formData.cities"
                            :invalid="$errors->has('formData.cities')"
                            placeholder="Nhập city..."
                            :disabled="empty($formData['country_id'])"
                            :class:input="$inputClass"
                        />
                        @if(!empty($formData['country_id']))
                            <button type="button" wire:click="openCityModal" class="absolute cursor-pointer right-2 top-1/2 -translate-y-1/2 text-xs text-primary-600 hover:text-primary-700 font-medium">
                                Chọn từ danh sách
                            </button>
                        @endif
                    </div>
                    @error('formData.cities')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Postcode/Mã bưu điện</flux:label>
                    <flux:input
                        type="text"
                        wire:model.defer="formData.postcode"
                        :invalid="$errors->has('formData.postcode')"
                        placeholder="Nhập postcode..."
                        :class:input="$inputClass"
                    />
                    @error('formData.postcode')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

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

    {{-- State Selector Modal --}}
    @if($showStateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="handleCloseModal('state-selector')">
            <livewire:state-selector
                :countryId="$formData['country_id']"
                :selected="$formData['state']"
                :key="'state-' . ($formData['country_id'] ?? 'none')"
            />
        </div>
    @endif

    {{-- City Selector Modal --}}
    @if($showCityModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="handleCloseModal('city-selector')">
            <livewire:city-selector
                :countryId="$formData['country_id']"
                :stateName="$formData['state']"
                :selected="$formData['cities']"
                :key="'city-' . ($formData['country_id'] ?? 'none') . '-' . ($formData['state'] ?? 'none')"
            />
        </div>
    @endif
</div>
