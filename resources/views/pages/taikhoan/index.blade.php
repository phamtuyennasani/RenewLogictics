<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    use WithFileUploads;

    // ─── Tab 1: Thông tin cá nhân ───
    public string $fullname = '';
    public string $email    = '';
    public string $phone    = '';
    public string $address  = '';
    public $avatar = null;
    public string $username = '';
    public string $code     = '';
    public bool $isSaving   = false;
    public string $tab      = 'info';

    // ─── Tab 2: Đổi mật khẩu ───
    public string $current_password = '';
    public string $new_password     = '';
    public string $confirm_password = '';
    public bool $isSavingPassword  = false;

    public function mount()
    {
        $user = auth()->user();
        $this->username = $user->username;
        $this->code     = $user->code ?? '';
        $this->fullname = $user->fullname ?? '';
        $this->email    = $user->email ?? '';
        $this->phone    = $user->phone ?? '';
        $this->address  = $user->address ?? '';
    }

    protected function rulesInfo(): array
    {
        return [
            'fullname' => 'required|string|max:225',
            'email'    => 'nullable|email|unique:user,email,' . auth()->id(),
            'phone'    => 'nullable|string|max:20',
            'address'  => 'nullable|string|max:255',
        ];
    }

    protected function messagesInfo(): array
    {
        return [
            'fullname.required' => 'Họ và tên không được để trống',
            'email.email'      => 'Email không hợp lệ',
            'email.unique'     => 'Email đã được sử dụng',
        ];
    }

    protected function rulesPassword(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]).{8,}$/',
            'confirm_password' => 'required|string|same:new_password',
        ];
    }

    protected function messagesPassword(): array
    {
        return [
            'current_password.required' => 'Vui lòng nhập mật khẩu cũ',
            'new_password.required'     => 'Mật khẩu mới không được để trống',
            'new_password.min'         => 'Mật khẩu tối thiểu 8 ký tự',
            'new_password.regex'       => 'Mật khẩu phải có ít nhất 1 chữ hoa, 1 số, 1 ký tự đặc biệt',
            'confirm_password.required' => 'Vui lòng nhập lại mật khẩu mới',
            'confirm_password.same'    => 'Mật khẩu nhập lại không khớp',
        ];
    }

    public function saveInfo()
    {
        $this->isSaving = true;
        try {
            $this->validate($this->rulesInfo(), $this->messagesInfo());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSaving = false;
            throw $e;
        }

        $user = auth()->user();
        $data = [
            'fullname' => trim($this->fullname),
            'email'    => trim($this->email) ?: null,
            'phone'    => trim($this->phone) ?: null,
            'address'  => trim($this->address) ?: null,
        ];

        if ($this->avatar && is_object($this->avatar)) {
            $uploadDir = public_path('uploads' . DIRECTORY_SEPARATOR . 'user');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $filename = auth()->id() . '_' . time() . '.' . $this->avatar->getClientOriginalExtension();
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            // Copy file content from Livewire temp to target
            $tempPath = $this->avatar->getRealPath();
            copy($tempPath, $targetPath);

            $data['avatar'] = '/uploads/user/' . $filename;
        }

        $user->update($data);
        $this->isSaving = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Cập nhật thông tin thành công!',
            variant: 'success',
        );
    }

    public function savePassword()
    {
        $this->isSavingPassword = true;
        try {
            $this->validate($this->rulesPassword(), $this->messagesPassword());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSavingPassword = false;
            throw $e;
        }

        $user = auth()->user();

        if (!Hash::check($this->current_password, $user->password)) {
            $this->isSavingPassword = false;
            Flux::toast(
                duration: 3000,
                heading: 'Lỗi',
                text: 'Mật khẩu cũ không chính xác!',
                variant: 'danger',
            );
            return;
        }

        $user->update(['password' => bcrypt($this->new_password)]);
        $this->current_password = '';
        $this->new_password     = '';
        $this->confirm_password = '';
        $this->isSavingPassword = false;

        Flux::toast(
            duration: 2000,
            heading: 'Thành công',
            text: 'Đổi mật khẩu thành công!',
            variant: 'success',
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
$inputClass = 'w-full px-4 py-2.5 text-sm border transition-all placeholder:text-neutral-400 focus:outline-none focus:ring-2 border-neutral-300 focus:ring-primary-500 focus:border-primary-500';
@endphp

<div class="mx-auto space-y-6">

    {{-- PAGE HEADER --}}
    <div class="flex items-center gap-3">
        <a href="{{ url()->previous() }}"
           wire:navigate
           class="p-2 rounded-xl text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-all cursor-pointer">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <p class="text-sm text-neutral-500">Tài khoản</p>
            <h1 class="text-2xl font-bold text-neutral-900">Hồ sơ cá nhân</h1>
        </div>
    </div>

    {{-- TAB NAVIGATION --}}
    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm overflow-hidden">

        {{-- Tab bar --}}
        <div class="flex border-b border-neutral-200 bg-neutral-50/50">
            <button
                wire:click="$set('tab', 'info')"
                class="relative px-6 py-3.5 text-sm font-medium transition-all cursor-pointer
                       flex items-center gap-2
                       {{ $tab === 'info' ? 'text-primary-600' : 'text-neutral-500 hover:text-neutral-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Thông tin cá nhân
                @if ($tab === 'info')
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 rounded-t-full" style="background: linear-gradient(90deg, {{ $primaryHex }}, {{ $accentHex }});"></span>
                @endif
            </button>
            <button
                wire:click="$set('tab', 'password')"
                class="relative px-6 py-3.5 text-sm font-medium transition-all cursor-pointer
                       flex items-center gap-2
                       {{ $tab === 'password' ? 'text-primary-600' : 'text-neutral-500 hover:text-neutral-700' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                Đổi mật khẩu
                @if ($tab === 'password')
                    <span class="absolute bottom-0 left-0 right-0 h-0.5 rounded-t-full" style="background: linear-gradient(90deg, {{ $primaryHex }}, {{ $accentHex }});"></span>
                @endif
            </button>
        </div>

        {{-- ═══════ TAB 1: THÔNG TIN CÁ NHÂN ═══════ --}}
        @if ($tab === 'info')
            <div class="p-6 space-y-6">

                {{-- Account info card --}}
                <div class="bg-neutral-50 rounded-xl border border-neutral-200 p-5">
                    <h3 class="text-xs font-semibold text-neutral-500 uppercase tracking-wide flex items-center gap-2 mb-4">
                        <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Thông tin tài khoản
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-neutral-500 mb-1">Username</label>
                            <div class="px-4 py-2.5 text-sm bg-neutral-100 border border-neutral-200 rounded-xl text-neutral-500">
                                {{ $username }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-neutral-500 mb-1">Mã nhân viên</label>
                            <div class="px-4 py-2.5 text-sm bg-neutral-100 border border-neutral-200 rounded-xl text-neutral-500 font-mono">
                                {{ $code ?: '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Personal info form --}}
                <div class="bg-white rounded-xl border border-neutral-200 p-5">
                    <h3 class="text-xs font-semibold text-neutral-500 uppercase tracking-wide flex items-center gap-2 mb-5">
                        <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Thông tin cá nhân
                    </h3>

                    <div class="space-y-4">
                        <flux:field>
                            <flux:label badge="Bắt buộc">Họ và tên</flux:label>
                            <flux:input
                                wire:model.defer="fullname"
                                :invalid="$errors->has('fullname')"
                                placeholder="Nhập họ và tên..."
                                :class:input="$inputClass"
                            />
                            @error('fullname')<flux:error>{{ $message }}</flux:error>@enderror
                        </flux:field>

                        <div class="grid grid-cols-2 gap-3">
                            <flux:field>
                                <flux:label>Email</flux:label>
                                <flux:input
                                    type="email"
                                    wire:model.defer="email"
                                    :invalid="$errors->has('email')"
                                    placeholder="Nhập email..."
                                    :class:input="$inputClass"
                                />
                                @error('email')<flux:error>{{ $message }}</flux:error>@enderror
                            </flux:field>
                            <flux:field>
                                <flux:label>Số điện thoại</flux:label>
                                <flux:input
                                    type="text"
                                    wire:model.defer="phone"
                                    :invalid="$errors->has('phone')"
                                    placeholder="Nhập số điện thoại..."
                                    :class:input="$inputClass"
                                />
                                @error('phone')<flux:error>{{ $message }}</flux:error>@enderror
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label>Địa chỉ</flux:label>
                            <flux:input
                                wire:model.defer="address"
                                :invalid="$errors->has('address')"
                                placeholder="Nhập địa chỉ..."
                                :class:input="$inputClass"
                            />
                            @error('address')<flux:error>{{ $message }}</flux:error>@enderror
                        </flux:field>

                        {{-- Avatar upload --}}
                        <flux:field>
                            <flux:label>Ảnh đại diện</flux:label>
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-full overflow-hidden shrink-0 ring-2 ring-neutral-200"
                                     style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                                    @if (auth()->user()->avatar)
                                        <img src="{{ auth()->user()->avatar }}"
                                             alt="avatar"
                                             class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-white text-base font-bold">
                                            {{ strtoupper(substr(auth()->user()->username ?? 'U', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <input type="file"
                                           wire:model="avatar"
                                           accept="image/*"
                                           class="block w-full text-sm text-neutral-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-neutral-100 file:text-neutral-700 hover:file:bg-neutral-200 cursor-pointer transition-colors">
                                    @error('avatar')<flux:error>{{ $message }}</flux:error>@enderror
                                    <p class="text-xs text-neutral-400 mt-1">JPG, PNG, GIF. Tối đa 2MB.</p>
                                </div>
                            </div>
                        </flux:field>
                    </div>
                </div>

                {{-- Save button --}}
                <div class="flex items-center justify-end gap-3">
                    <button
                        wire:click="saveInfo"
                        wire:disabled="isSaving"
                        class="px-6 py-2.5 text-sm font-medium text-white rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5 flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0"
                        style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                        @if ($isSaving)
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Đang lưu...
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                            </svg>
                            Lưu thay đổi
                        @endif
                    </button>
                </div>
            </div>
        @endif

        {{-- ═══════ TAB 2: ĐỔI MẬT KHẨU ═══════ --}}
        @if ($tab === 'password')
            <div class="p-6">
                <div class="max-w-xl mx-auto space-y-5">

                    {{-- Header icon + title --}}
                    <div class="flex flex-col items-center text-center">
                        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4"
                             style="background: linear-gradient(135deg, {{ $primaryHex }}20, {{ $accentHex }}20);">
                            <svg class="w-8 h-8" style="color: {{ $primaryHex }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-neutral-900">Thay đổi mật khẩu</h2>
                        <p class="text-sm text-neutral-500 mt-1">Đảm bảo tài khoản của bạn luôn được bảo mật</p>
                    </div>

                    {{-- Form card --}}
                    <div class="bg-white rounded-xl border border-neutral-200 p-6 shadow-sm">
                        <div class="space-y-4">
                            <flux:field>
                                <flux:label badge="Bắt buộc">Mật khẩu cũ</flux:label>
                                <flux:input
                                    viewable
                                    type="password"
                                    wire:model.defer="current_password"
                                    :invalid="$errors->has('current_password')"
                                    placeholder="Nhập mật khẩu hiện tại..."
                                    autocomplete="current-password"
                                    :class:input="$inputClass"
                                />
                                @error('current_password')<flux:error>{{ $message }}</flux:error>@enderror
                            </flux:field>

                            <flux:field>
                                <flux:label badge="Bắt buộc">Mật khẩu mới</flux:label>
                                <flux:input
                                    viewable
                                    type="password"
                                    wire:model.defer="new_password"
                                    :invalid="$errors->has('new_password')"
                                    placeholder="Nhập mật khẩu mới..."
                                    autocomplete="new-password"
                                    :class:input="$inputClass"
                                />
                                @error('new_password')<flux:error>{{ $message }}</flux:error>@enderror
                            </flux:field>

                            <flux:field>
                                <flux:label badge="Bắt buộc">Nhập lại mật khẩu mới</flux:label>
                                <flux:input
                                    viewable
                                    type="password"
                                    wire:model.defer="confirm_password"
                                    :invalid="$errors->has('confirm_password')"
                                    placeholder="Nhập lại mật khẩu mới..."
                                    autocomplete="new-password"
                                    :class:input="$inputClass"
                                />
                                @error('confirm_password')<flux:error>{{ $message }}</flux:error>@enderror
                            </flux:field>
                        </div>
                    </div>

                    {{-- Password rules hint --}}
                    <div class="bg-neutral-50 border border-neutral-200 rounded-xl p-4">
                        <p class="text-xs font-semibold text-neutral-600 mb-2">Mật khẩu phải đáp ứng:</p>
                        <div class="flex flex-wrap gap-x-5 gap-y-1">
                            <span class="text-xs text-neutral-400">• Tối thiểu 8 ký tự</span>
                            <span class="text-xs text-neutral-400">• Ít nhất 1 chữ hoa</span>
                            <span class="text-xs text-neutral-400">• Ít nhất 1 số</span>
                            <span class="text-xs text-neutral-400">• Ít nhất 1 ký tự đặc biệt</span>
                        </div>
                    </div>

                    {{-- Save button --}}
                    <button
                        wire:click="savePassword"
                        wire:disabled="isSavingPassword"
                        class="w-full px-6 py-3 text-sm font-medium text-white rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5 flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:shadow-none disabled:hover:translate-y-0"
                        style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                        @if ($isSavingPassword)
                            <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Đang xử lý...
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Đổi mật khẩu
                        @endif
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
