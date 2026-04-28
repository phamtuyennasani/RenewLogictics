<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Country;
use App\Models\State;
use App\Models\Cities as WorldCity;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;

new class extends Component {
    public $type;
    public $itemId = null;
    public array $config = [];
    public array $formData = [];
    public bool $isSaving = false;

    public function mount($type = null, $id = null)
    {
        $this->type = $type;
        $this->itemId = $id;
        $this->config = config("place.{$this->type}", []);

        $this->formData = match ($this->type) {
            'countries' => ['name' => null, 'iso2' => null, 'iso3' => null, 'phonecode' => null],
            'state'     => ['name' => null, 'country_id' => null, 'country_code' => null, 'iso2' => null],
            'cities'    => ['name' => null, 'state_id' => null, 'state_code' => null, 'country_id' => null, 'country_code' => null],
            default     => [],
        };

        if ($id) {
            $item = $this->modelClass()::find($id);
            if ($item) {
                foreach ($this->formData as $key => $val) {
                    $this->formData[$key] = $item->{$key} ?? null;
                }
            }
        }
    }

    private function modelClass(): ?string
    {
        return match ($this->type) {
            'countries' => Country::class,
            'state'     => State::class,
            'cities'    => WorldCity::class,
            default     => null,
        };
    }

    // ==================== CACHED QUERIES ====================

    #[Computed(persist: true, seconds: 3600)]
    public function countries()
    {
        return Cache::remember('countries:list', 3600, fn() =>
            Country::orderBy('name')->get(['id', 'name', 'iso2'])->toArray()
        );
    }

    #[Computed]
    public function states()
    {
        if (empty($this->formData['country_id'])) return [];

        $countryId = $this->formData['country_id'];
        return Cache::remember("states:country:{$countryId}", 3600, fn() =>
            State::where('country_id', $countryId)
                ->orderBy('name')
                ->get(['id', 'name', 'iso2', 'country_id'])
                ->toArray()
        );
    }

    // Clear state_id khi country thay đổi (tránh mismatch)
    public function updatedFormDataCountryId($value)
    {
        $this->formData['state_id'] = null;
    }

    // ==================== SAVE ====================

    public function save()
    {
        $this->isSaving = true;

        try {
            $rules = match ($this->type) {
                'countries' => [
                    'formData.name'      => 'required|string|max:255',
                    'formData.iso2'      => 'nullable|string|max:2',
                    'formData.iso3'      => 'nullable|string|max:3',
                    'formData.phonecode' => 'nullable|string|max:20',
                ],
                'state' => [
                    'formData.name'       => 'required|string|max:255',
                    'formData.country_id' => 'required|integer',
                    'formData.iso2'       => 'nullable|string|max:10',
                ],
                'cities' => [
                    'formData.name'     => 'required|string|max:255',
                    'formData.state_id' => 'required|integer',
                ],
                default => [],
            };

            $messages = [
                'formData.name.required'       => 'Tên không được để trống',
                'formData.country_id.required' => 'Vui lòng chọn quốc gia',
                'formData.state_id.required'   => 'Vui lòng chọn tỉnh/bang',
            ];

            $this->validate($rules, $messages);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSaving = false;
            throw $e;
        }

        $this->formData = array_map(fn($v) => is_string($v) ? trim($v) : $v, $this->formData);

        // Xử lý relationships - dùng data đã có sẵn trong computed
        if ($this->type === 'state' && !empty($this->formData['country_id'])) {
            $country = collect($this->countries)->firstWhere('id', (int) $this->formData['country_id']);
            $this->formData['country_code'] = $country['iso2'] ?? null;
        }

        if ($this->type === 'cities' && !empty($this->formData['state_id'])) {
            $state = collect($this->states)->firstWhere('id', (int) $this->formData['state_id']);
            if ($state) {
                $this->formData['state_code']   = $state['iso2'] ?? null;
                $this->formData['country_id']   = $state['country_id'];
                $country = collect($this->countries)->firstWhere('id', $state['country_id']);
                $this->formData['country_code'] = $country['iso2'] ?? null;
            }
        }

        $model = $this->modelClass();
        if ($model) {
            $model::updateOrCreate(['id' => $this->itemId], $this->formData);

            // Clear cache liên quan
            if ($this->type === 'countries') Cache::forget('countries:list');
            if ($this->type === 'state') Cache::forget("states:country:{$this->formData['country_id']}");
        }

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: $this->itemId ? 'Cập nhật thành công!' : 'Thêm mới thành công!',
            variant: 'success'
        );

        return $this->redirect(route('place.index', ['type' => $this->type]), navigate: true);
    }

    public function goBack()
    {
        return $this->redirect(route('place.index', ['type' => $this->type]), navigate: true);
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

$inputClass = [
    'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400',
    'focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500',
];
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
            <p class="text-sm text-neutral-500 capitalize">Place / {{ $this->config['group'] ?? '' }}</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $itemId ? 'Chỉnh sửa' : 'Thêm mới' }} {{ $this->config['title'] ?? '' }}
            </h1>
        </div>
    </div>

    {{-- MAIN FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200  shadow-sm">
        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide">Thông tin cơ bản</h2>
        </div>
        <div class="p-6 space-y-5">
            <flux:field>
                <flux:label badge="Bắt buộc">{{ $this->config['columns']['name'] ?? 'Tên' }}</flux:label>
                <flux:input
                    type="text"
                    required
                    wire:model.defer="formData.name"
                    :invalid="$errors->has('formData.name')"
                    placeholder="Nhập tên..."
                    :class:input="$inputClass"
                />
                @error('formData.name')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>
            @if ($type === 'countries')
                <div class="grid grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>ISO2</flux:label>
                        <flux:input type="text" maxlength="2" wire:model.defer="formData.iso2"
                            placeholder="VD: VN" :class:input="$inputClass" />
                    </flux:field>
                    <flux:field>
                        <flux:label>ISO3</flux:label>
                        <flux:input type="text" maxlength="3" wire:model.defer="formData.iso3"
                            placeholder="VD: VNM" :class:input="$inputClass" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Phone Code</flux:label>
                        <flux:input type="text" wire:model.defer="formData.phonecode"
                            placeholder="VD: 84" :class:input="$inputClass" />
                    </flux:field>
                </div>
            @endif
            @if ($type === 'state')
                <flux:field>
                    <flux:label badge="Bắt buộc">Quốc gia</flux:label>
                    <x-select-search
                        name="formData.country_id"
                        :options="collect($this->countries)->pluck('name', 'id')->toArray()"
                        :selected="$formData['country_id'] ?? null"
                        placeholder="-- Chọn quốc gia --"
                    />
                    @error('formData.country_id')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
                <flux:field>
                    <flux:label>ISO Code</flux:label>
                    <flux:input type="text" wire:model.defer="formData.iso2"
                        placeholder="VD: California" :class:input="$inputClass" />
                </flux:field>
            @endif
            @if ($type === 'cities')
                <flux:field>
                    <flux:label badge="Bắt buộc">Quốc gia</flux:label>
                    <x-select-search
                        name="formData.country_id"
                        :options="collect($this->countries)->pluck('name', 'id')->toArray()"
                        :selected="$formData['country_id'] ?? null"
                        placeholder="-- Chọn quốc gia --"
                    />
                </flux:field>
                <flux:field>
                    <flux:label badge="Bắt buộc">Tỉnh / Bang</flux:label>
                    <div wire:key="state-{{ $formData['country_id'] ?? 'none' }}">
                    <x-select-search
                        name="formData.state_id"
                        :options="collect($this->states)->pluck('name', 'id')->toArray()"
                        :selected="$formData['state_id'] ?? null"
                        placeholder="-- Chọn tỉnh/bang --"
                        :disabled="empty($formData['country_id'])"
                    />
                    </div>
                    @error('formData.state_id')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            @endif
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
                        Lưu {{ $itemId ? 'cập nhật' : 'mới' }}
                    @endif
                </button>
            </div>
        </div>
    </div>
</div>