<?php

use Livewire\Component;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

new class extends Component {
    public ?int $itemId = null;
    public array $formData = [];
    public bool $isSaving = false;
    public bool $isActive = true;

    // Company data
    public array $companyData = [
        'company_name' => '',
        'company_short_name' => '',
        'representative_name' => '',
        'tax_code' => '',
        'company_email' => '',
        'company_phone' => '',
        'city_id' => null,
        'ward_id' => null,
        'address_detail' => '',
    ];

    public array $cities = [];
    public array $wards = [];
    public array $sales = [];

    public function mount($id = null)
    {
        $this->itemId = $id;

        // Load cities
        $this->cities = DB::table('province')
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->toArray();

        $this->sales = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username'])
            ->mapWithKeys(fn ($u) => [
                $u->id => trim(($u->fullname ?: $u->username) . ' (' . $u->username . ')')
            ])
            ->toArray();

        $this->formData = [
            'username' => '',
            'code'     => '',
            'fullname' => '',
            'email'    => '',
            'phone'    => '',
            'id_sale'  => null,
            'password' => '',
            'password_confirmation' => '',
        ];

        if ($id) {
            $item = User::find($id);
            if ($item) {
                $this->formData = [
                    'username' => $item->username ?? '',
                    'code'     => $item->code ?? '',
                    'fullname' => $item->fullname ?? '',
                    'email'    => $item->email ?? '',
                    'phone'    => $item->phone ?? '',
                    'id_sale'  => $item->id_sale ?? null,
                    'password' => '',
                    'password_confirmation' => '',
                ];
                $this->isActive = (bool) $item->status;

                // Load company data from options
                $options = $item->options ?? [];
                if (isset($options['company'])) {
                    $this->companyData = array_merge($this->companyData, $options['company']);

                    // Load wards if city selected
                    if ($this->companyData['city_id']) {
                        $this->loadWards($this->companyData['city_id']);
                    }
                }
            }
        }
    }

    public function loadWards($cityId)
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

    public function updatedCompanyDataCityId($value)
    {
        $this->companyData['ward_id'] = null;
        $this->loadWards($value);
    }

    protected function rules(): array
    {
        $uniqueUsername = 'unique:user,username' . ($this->itemId ? ',' . $this->itemId : '');
        $uniqueCode     = 'unique:user,code' . ($this->itemId ? ',' . $this->itemId : '');
        $uniqueEmail    = 'unique:user,email' . ($this->itemId ? ',' . $this->itemId : '');

        $rules = [
            'formData.username' => 'required|string|min:3|max:50|' . $uniqueUsername,
            'formData.code'     => 'nullable|string|max:50|' . $uniqueCode,
            'formData.fullname' => 'required|string|max:225',
            'formData.email'    => 'nullable|email|' . $uniqueEmail,
            'formData.phone'    => 'nullable|string|max:20',
            'formData.id_sale'  => [
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

            // Company validation
            'companyData.company_name' => 'required|string|max:255',
            'companyData.company_short_name' => 'nullable|string|max:100',
            'companyData.representative_name' => 'nullable|string|max:255',
            'companyData.tax_code' => 'nullable|string|max:50',
            'companyData.company_email' => 'nullable|email',
            'companyData.company_phone' => 'nullable|string|max:20',
            'companyData.city_id' => 'nullable|exists:city,id',
            'companyData.ward_id' => [
                'nullable',
                'exists:wards,id',
                function ($attribute, $value, $fail) {
                    $cityId = $this->companyData['city_id'] ?? null;
                    if (!$cityId || !$value) {
                        return;
                    }

                    $valid = DB::table('wards')
                        ->where('id', $value)
                        ->where('parent_code', $cityId)
                        ->exists();

                    if (!$valid) {
                        $fail('Phường/xã không thuộc tỉnh/thành phố đã chọn');
                    }
                },
            ],
            'companyData.address_detail' => 'nullable|string|max:500',
        ];

        if (!$this->itemId) {
            $rules['formData.password'] = ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]).{8,}$/'];
            $rules['formData.password_confirmation'] = ['required', 'string', 'min:8', 'same:formData.password'];
        } else {
            $rules['formData.password'] = ['nullable', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]).{8,}$/'];
            $rules['formData.password_confirmation'] = ['nullable', 'string', 'min:8', 'same:formData.password'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'formData.username.required'  => 'Username không được để trống',
            'formData.username.unique'  => 'Username đã tồn tại',
            'formData.username.min'      => 'Username tối thiểu 3 ký tự',
            'formData.code.unique'       => 'Mã CTV đã tồn tại',
            'formData.fullname.required' => 'Họ và tên không được để trống',
            'formData.email.email'       => 'Email không hợp lệ',
            'formData.email.unique'     => 'Email đã tồn tại',
            'formData.id_sale.required' => 'Vui lòng chọn nhân viên SALE phụ trách',
            'formData.id_sale.exists'   => 'Nhân viên SALE không hợp lệ',
            'formData.password.required' => 'Mật khẩu không được để trống',
            'formData.password.min'       => 'Mật khẩu tối thiểu 8 ký tự',
            'formData.password.regex'    => 'Mật khẩu phải có ít nhất 1 chữ hoa, 1 số, 1 ký tự đặc biệt',
            'formData.password_confirmation.required' => 'Vui lòng nhập lại mật khẩu',
            'formData.password_confirmation.same'     => 'Mật khẩu nhập lại không khớp',
            'formData.password_confirmation.min'     => 'Mật khẩu tối thiểu 8 ký tự',

            // Company messages
            'companyData.company_name.required' => 'Tên công ty không được để trống',
            'companyData.company_email.email' => 'Email công ty không hợp lệ',
            'companyData.city_id.required' => 'Vui lòng chọn tỉnh/thành phố',
            'companyData.city_id.exists' => 'Tỉnh/thành phố không hợp lệ',
            'companyData.ward_id.required' => 'Vui lòng chọn phường/xã',
            'companyData.ward_id.exists' => 'Phường/xã không hợp lệ',
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
        $this->companyData = array_map(fn($v) => is_string($v) ? trim($v) : $v, $this->companyData);

        // Auto-gen code if empty
        if (empty($this->formData['code'])) {
            $this->formData['code'] = $this->generateUniqueCode();
        }

        if ($this->itemId) {
            $updateData = [
                'username' => $this->formData['username'],
                'code'     => $this->formData['code'],
                'fullname' => $this->formData['fullname'],
                'email'    => $this->formData['email'] ?: null,
                'phone'    => $this->formData['phone'] ?: null,
                'id_sale'  => $this->formData['id_sale'],
                'status'   => $this->isActive ? '1' : '0',
                'options'  => ['company' => $this->companyData],
            ];
            if (!empty($this->formData['password'])) {
                $updateData['password'] = bcrypt($this->formData['password']);
            }
            $user = User::findOrFail($this->itemId);
            $user->update($updateData);
            $user->syncRoles(['CTV']);
        } else {
            $user = User::create([
                'username' => $this->formData['username'],
                'code'     => $this->formData['code'],
                'fullname' => $this->formData['fullname'],
                'email'    => $this->formData['email'] ?: null,
                'phone'    => $this->formData['phone'] ?: null,
                'id_sale'  => $this->formData['id_sale'],
                'password' => bcrypt($this->formData['password']),
                'status'   => $this->isActive ? '1' : '0',
                'options'  => ['company' => $this->companyData],
            ]);
            $user->assignRole('CTV');
        }

        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: $this->itemId ? 'Cập nhật tài khoản CTV thành công!' : 'Tạo tài khoản CTV thành công!',
            variant: 'success'
        );

        return $this->redirect(route('ctv.index'), navigate: true);
    }

    public function goBack()
    {
        return $this->redirect(route('ctv.index'), navigate: true);
    }

    private function generateUniqueCode(): string
    {
        do {
            $random = strtoupper(Str::random(4));
            $code = 'CTV' . $random;
        } while (User::where('code', $code)->exists());

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
            <p class="text-sm text-neutral-500 capitalize">CTV / Tài khoản cộng tác viên</p>
            <h1 class="text-2xl font-bold text-neutral-900">
                {{ $itemId ? 'Chỉnh sửa' : 'Thêm mới' }} tài khoản CTV
            </h1>
        </div>
    </div>

    {{-- MAIN FORM --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm">

        <div class="px-6 py-5 border-b border-neutral-100">
            <h2 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Thông tin tài khoản CTV
            </h2>
        </div>

        <div class="p-6 space-y-5">

            {{-- Username --}}
            <flux:field>
                <flux:label badge="Bắt buộc">Username</flux:label>
                <flux:input
                    type="text"
                    required
                    :variant="$itemId ? 'filled' : 'outline'"
                    :readonly="$itemId"
                    wire:model.defer="formData.username"
                    :invalid="$errors->has('formData.username')"
                    placeholder="Nhập username..."
                    :class:input="$inputClass"
                />
                @error('formData.username')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>

            {{-- Mã CTV --}}
            <flux:field>
                <flux:label badge="Tự động">Mã CTV</flux:label>
                <flux:input
                    type="text"
                    :readonly="$itemId"
                    :variant="$itemId ? 'filled' : 'outline'"
                    wire:model.defer="formData.code"
                    :invalid="$errors->has('formData.code')"
                    placeholder="Để trống để tự động tạo..."
                    :class:input="$inputClass"
                />
                @error('formData.code')<flux:error>{{ $message }}</flux:error>@enderror
                <p class="mt-1.5 text-xs text-neutral-400">
                    Nếu không nhập, hệ thống sẽ tự tạo mã duy nhất (VD: CTVXXXX)
                </p>
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
                    <flux:label>Số điện thoại</flux:label>
                    <flux:input
                        type="text"
                        wire:model.defer="formData.phone"
                        :invalid="$errors->has('formData.phone')"
                        placeholder="Nhập số điện thoại..."
                        :class:input="$inputClass"
                    />
                    @error('formData.phone')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <flux:field>
                <flux:label badge="Bắt buộc">Nhân viên SALE phụ trách</flux:label>
                <x-select-search
                    name="formData.id_sale"
                    :options="$sales"
                    :selected="$formData['id_sale'] ?? null"
                    placeholder="-- Chọn nhân viên SALE --"
                />
                @error('formData.id_sale')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>

            <div class="pt-2 border-t border-neutral-100">
                <h3 class="text-sm font-semibold text-neutral-700 uppercase tracking-wide flex items-center gap-2">
                    <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M6 3h12a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                    </svg>
                    Thông tin công ty
                </h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label badge="Bắt buộc">Tên công ty</flux:label>
                    <flux:input
                        type="text"
                        required
                        wire:model.defer="companyData.company_name"
                        :invalid="$errors->has('companyData.company_name')"
                        placeholder="Nhập tên công ty..."
                        :class:input="$inputClass"
                    />
                    @error('companyData.company_name')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Tên công ty viết tắt</flux:label>
                    <flux:input
                        type="text"
                        wire:model.defer="companyData.company_short_name"
                        :invalid="$errors->has('companyData.company_short_name')"
                        placeholder="Nhập tên viết tắt..."
                        :class:input="$inputClass"
                    />
                    @error('companyData.company_short_name')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Người đại diện</flux:label>
                    <flux:input
                        type="text"
                        wire:model.defer="companyData.representative_name"
                        :invalid="$errors->has('companyData.representative_name')"
                        placeholder="Nhập người đại diện..."
                        :class:input="$inputClass"
                    />
                    @error('companyData.representative_name')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Mã số thuế</flux:label>
                    <flux:input
                        type="text"
                        wire:model.defer="companyData.tax_code"
                        :invalid="$errors->has('companyData.tax_code')"
                        placeholder="Nhập mã số thuế..."
                        :class:input="$inputClass"
                    />
                    @error('companyData.tax_code')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Email công ty</flux:label>
                    <flux:input
                        type="email"
                        wire:model.defer="companyData.company_email"
                        :invalid="$errors->has('companyData.company_email')"
                        placeholder="Nhập email công ty..."
                        :class:input="$inputClass"
                    />
                    @error('companyData.company_email')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field>
                    <flux:label>Số điện thoại công ty</flux:label>
                    <flux:input
                        type="text"
                        wire:model.defer="companyData.company_phone"
                        :invalid="$errors->has('companyData.company_phone')"
                        placeholder="Nhập số điện thoại công ty..."
                        :class:input="$inputClass"
                    />
                    @error('companyData.company_phone')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <flux:field>
                    <flux:label>Tỉnh/Thành phố</flux:label>
                    <x-select-search
                        name="companyData.city_id"
                        :options="collect($cities)->pluck('name', 'id')->toArray()"
                        :selected="$companyData['city_id'] ?? null"
                        placeholder="-- Chọn tỉnh/thành phố --"
                    />
                    @error('companyData.city_id')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>

                <flux:field >
                    <flux:label>Phường/Xã</flux:label>
                    <div wire:key="ctv-ward-{{ $companyData['city_id'] ?? 'none' }}">
                        <x-select-search
                            name="companyData.ward_id"
                            :options="collect($wards)->pluck('name', 'id')->toArray()"
                            :selected="$companyData['ward_id'] ?? null"
                            placeholder="-- Chọn phường/xã --"
                            :disabled="empty($companyData['city_id'])"
                        />
                    </div>
                    @error('companyData.ward_id')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Địa chỉ chi tiết</flux:label>
                <flux:input
                    type="text"
                    wire:model.defer="companyData.address_detail"
                    :invalid="$errors->has('companyData.address_detail')"
                    placeholder="Số nhà, đường, khu vực..."
                    :class:input="$inputClass"
                />
                @error('companyData.address_detail')<flux:error>{{ $message }}</flux:error>@enderror
            </flux:field>

            {{-- Kích hoạt tài khoản --}}
            <div class="flex items-start gap-3">
                <div class="mt-1.5">
                    <flux:checkbox wire:model.live="isActive" />
                </div>
                <div class="flex flex-col cursor-pointer select-none" wire:click="$toggle('isActive')">
                    <label class="text-sm font-medium text-neutral-700 cursor-pointer select-none">
                        Kích hoạt tài khoản
                    </label>
                    <span class="text-xs text-neutral-400 mt-0.5">
                        Tài khoản đã kích hoạt mới có thể đăng nhập vào hệ thống.
                    </span>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <flux:field>
                    <flux:label :badge="$itemId ? 'Không bắt buộc' : 'Bắt buộc'">
                        Mật khẩu
                    </flux:label>
                    <flux:input viewable
                        type="password"
                        wire:model.defer="formData.password"
                        :invalid="$errors->has('formData.password')"
                        placeholder="{{ $itemId ? 'Nhập mật khẩu mới để thay đổi...' : 'Nhập mật khẩu...' }}"
                        autocomplete="new-password"
                        :class:input="$inputClass"
                    />
                    @error('formData.password')<flux:error>{{ $message }}</flux:error>@enderror
                    @if($this->itemId)
                        <p class="mt-1.5 text-xs text-neutral-400">Để trống nếu không muốn thay đổi mật khẩu</p>
                    @endif
                </flux:field>
                {{-- Nhập lại mật khẩu --}}
                <flux:field>
                    <flux:label :badge="$itemId ? 'Không bắt buộc' : 'Bắt buộc'">Nhập lại mật khẩu</flux:label>
                    <flux:input viewable
                        type="password"
                        wire:model.defer="formData.password_confirmation"
                        :invalid="$errors->has('formData.password_confirmation')"
                        placeholder="{{ $itemId ? 'Nhập mật khẩu mới một lần nữa...' : 'Nhập lại mật khẩu...' }}"
                        autocomplete="new-password"
                        :class:input="$inputClass"
                    />
                    @error('formData.password_confirmation')<flux:error>{{ $message }}</flux:error>@enderror
                </flux:field>
                @if(!$itemId || ($this->formData['password'] ?? ''))
                <div class="col-span-full"
                     x-data="{
                        p: '',
                        get min8()  { return this.p.length >= 8 },
                        get hasUp()  { return /[A-Z]/.test(this.p) },
                        get hasNum() { return /\d/.test(this.p) },
                        get hasSpe() { return /[!@#$%^&*()_+\-=\[\]{};:\x27\x22,.<>\/?\\|`~]/.test(this.p) },
                    }"
                     x-init="
                        p = $wire.formData.password || '';
                        $watch('$wire.formData.password', v => { p = v || '' });
                     ">
                    <p class="text-xs font-semibold text-neutral-600 mb-2">Mật khẩu phải đáp ứng:</p>
                    <div class="flex flex-wrap gap-x-5 gap-y-1">
                        <div class="flex items-center gap-1.5">
                            <span x-show="min8" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span x-show="!min8" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                            </span>
                            <span class="text-xs" :class="min8 ? 'text-green-600 font-medium' : 'text-neutral-400'">Tối thiểu 8 ký tự</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span x-show="hasUp" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span x-show="!hasUp" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                            </span>
                            <span class="text-xs" :class="hasUp ? 'text-green-600 font-medium' : 'text-neutral-400'">Ít nhất 1 chữ hoa</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span x-show="hasNum" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span x-show="!hasNum" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                            </span>
                            <span class="text-xs" :class="hasNum ? 'text-green-600 font-medium' : 'text-neutral-400'">Ít nhất 1 số</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span x-show="hasSpe" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                            </span>
                            <span x-show="!hasSpe" class="inline-flex">
                                <svg class="w-3.5 h-3.5 shrink-0 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M20 12H4"/>
                                </svg>
                            </span>
                            <span class="text-xs" :class="hasSpe ? 'text-green-600 font-medium' : 'text-neutral-400'">Ít nhất 1 ký tự đặc biệt</span>
                        </div>
                    </div>
                </div>
                @endif
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
                        Lưu {{ $this->itemId ? 'cập nhật' : 'mới' }}
                    @endif
                </button>
            </div>
        </div>
    </div>
</div>
