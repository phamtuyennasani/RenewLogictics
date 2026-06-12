<?php

use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.mobile')] #[Title('Thông tin cá nhân')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'info';
    public string $fullname = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public string $username = '';
    public string $code = '';
    public mixed $avatar = null;
    public bool $isSaving = false;
    public bool $isSavingPassword = false;
    public string $current_password = '';
    public string $new_password = '';
    public string $confirm_password = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['shipper', 'ops', 'admin', 'manager', 'cs']), 403);

        $user = auth()->user();
        $this->username = $user->username ?? '';
        $this->code = $user->code ?? '';
        $this->fullname = $user->fullname ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';
        $this->address = $user->address ?? '';
    }

    protected function rulesInfo(): array
    {
        return [
            'fullname' => 'required|string|max:225',
            'email' => 'nullable|email|unique:user,email,'.auth()->id(),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'avatar' => 'nullable|image|max:2048',
        ];
    }

    public function saveInfo(): void
    {
        $this->isSaving = true;

        try {
            $this->validate($this->rulesInfo(), [
                'fullname.required' => 'Họ và tên không được để trống',
                'email.email' => 'Email không hợp lệ',
                'email.unique' => 'Email đã được sử dụng',
                'avatar.image' => 'Ảnh đại diện không hợp lệ',
                'avatar.max' => 'Ảnh đại diện tối đa 2MB',
            ]);

            $user = auth()->user();
            $data = [
                'fullname' => trim($this->fullname),
                'email' => trim($this->email) ?: null,
                'phone' => trim($this->phone) ?: null,
                'address' => trim($this->address) ?: null,
            ];

            if ($this->avatar && is_object($this->avatar)) {
                $uploadDir = public_path('uploads'.DIRECTORY_SEPARATOR.'user');
                if (! is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $oldAvatar = (string) ($user->avatar ?? '');
                if (str_starts_with($oldAvatar, '/uploads/user/')) {
                    $oldAvatarPath = public_path(ltrim($oldAvatar, '/'));
                    if (is_file($oldAvatarPath)) {
                        @unlink($oldAvatarPath);
                    }
                }

                $filename = auth()->id().'_'.time().'.'.$this->avatar->getClientOriginalExtension();
                copy($this->avatar->getRealPath(), $uploadDir.DIRECTORY_SEPARATOR.$filename);
                $data['avatar'] = '/uploads/user/'.$filename;
            }

            $user->update($data);
            $this->avatar = null;

            if (isset($data['avatar'])) {
                $this->dispatch('avatar-updated', avatar: $data['avatar']);
            }

            Flux::toast(duration: 2200, heading: 'Đã lưu', text: 'Thông tin cá nhân đã được cập nhật.', variant: 'success');
        } finally {
            $this->isSaving = false;
        }
    }

    public function savePassword(): void
    {
        $this->isSavingPassword = true;

        try {
            $this->validate([
                'current_password' => 'required|string',
                'new_password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>\/?\\|`~]).{8,}$/',
                ],
                'confirm_password' => 'required|string|same:new_password',
            ], [
                'current_password.required' => 'Vui lòng nhập mật khẩu cũ',
                'new_password.required' => 'Mật khẩu mới không được để trống',
                'new_password.min' => 'Mật khẩu tối thiểu 8 ký tự',
                'new_password.regex' => 'Mật khẩu phải có ít nhất 1 chữ hoa, 1 số, 1 ký tự đặc biệt',
                'confirm_password.required' => 'Vui lòng nhập lại mật khẩu mới',
                'confirm_password.same' => 'Mật khẩu nhập lại không khớp',
            ]);

            $user = auth()->user();

            if (! Hash::check($this->current_password, $user->password)) {
                Flux::toast(duration: 3000, heading: 'Lỗi', text: 'Mật khẩu cũ không chính xác.', variant: 'danger');
                return;
            }

            $user->update(['password' => bcrypt($this->new_password)]);
            $this->current_password = '';
            $this->new_password = '';
            $this->confirm_password = '';

            Flux::toast(duration: 2200, heading: 'Đã đổi mật khẩu', text: 'Mật khẩu mới đã được cập nhật.', variant: 'success');
        } finally {
            $this->isSavingPassword = false;
        }
    }
};
?>

@php
    $primaryHex = config('theme.primary.hex', '#3b82f6');
    $accentHex = config('theme.accent.hex', '#0ea5e9');
    $inputClass = 'w-full rounded-xl border border-neutral-200 bg-white px-4 py-3 text-base text-neutral-900 placeholder:text-neutral-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100';
    $user = auth()->user();
@endphp

<div class="min-h-screen bg-neutral-50 pb-24">
    <div class="px-4 py-4">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-neutral-200">
            <div class="flex items-center gap-4">
                <div class="h-16 w-16 overflow-hidden rounded-2xl text-white shadow-sm"
                     style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                    @if($user?->avatar)
                        <img src="{{ $user->avatar }}" alt="avatar" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-xl font-bold">
                            {{ strtoupper(substr($username ?: 'U', 0, 1)) }}
                        </div>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="truncate text-lg font-bold text-neutral-950">{{ $fullname ?: $username }}</p>
                    <p class="mt-0.5 truncate text-sm text-neutral-500">{{ $code ?: $username }}</p>
                </div>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 rounded-xl bg-neutral-200/70 p-1">
            <button type="button"
                    wire:click="$set('tab', 'info')"
                    class="rounded-lg px-3 py-2.5 text-sm font-bold transition {{ $tab === 'info' ? 'bg-white text-primary-700 shadow-sm' : 'text-neutral-600' }}">
                Thông tin
            </button>
            <button type="button"
                    wire:click="$set('tab', 'password')"
                    class="rounded-lg px-3 py-2.5 text-sm font-bold transition {{ $tab === 'password' ? 'bg-white text-primary-700 shadow-sm' : 'text-neutral-600' }}">
                Mật khẩu
            </button>
        </div>

        @if($tab === 'info')
            <div class="mt-4 space-y-4">
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-neutral-200">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase text-neutral-500">Username</p>
                            <p class="mt-1 truncate rounded-xl bg-neutral-50 px-3 py-2 text-sm font-semibold text-neutral-700">{{ $username }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-neutral-500">Mã nhân viên</p>
                            <p class="mt-1 truncate rounded-xl bg-neutral-50 px-3 py-2 text-sm font-semibold text-neutral-700">{{ $code ?: '-' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-neutral-200">
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label badge="Bắt buộc">Họ và tên</flux:label>
                            <flux:input wire:model.defer="fullname" :invalid="$errors->has('fullname')" :class:input="$inputClass" />
                            @error('fullname') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Số điện thoại</flux:label>
                            <flux:input wire:model.defer="phone" inputmode="tel" :invalid="$errors->has('phone')" :class:input="$inputClass" />
                            @error('phone') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Email</flux:label>
                            <flux:input type="email" wire:model.defer="email" :invalid="$errors->has('email')" :class:input="$inputClass" />
                            @error('email') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Địa chỉ</flux:label>
                            <flux:textarea wire:model.defer="address" rows="3" :invalid="$errors->has('address')" class="w-full rounded-xl border border-neutral-200 bg-white px-4 py-3 text-base text-neutral-900 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100" />
                            @error('address') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label>Ảnh đại diện</flux:label>
                            <input type="file"
                                   wire:model="avatar"
                                   accept="image/*"
                                   class="block w-full rounded-xl border border-neutral-200 bg-white px-3 py-3 text-sm text-neutral-600 file:mr-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-neutral-700">
                            @error('avatar') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    </div>
                </div>

                <button type="button"
                        wire:click="saveInfo"
                        wire:loading.attr="disabled"
                        wire:target="saveInfo,avatar"
                        class="flex w-full items-center justify-center rounded-xl px-4 py-3 text-base font-bold text-white shadow-sm disabled:opacity-60"
                        style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                    <span wire:loading.remove wire:target="saveInfo">Lưu thông tin</span>
                    <span wire:loading wire:target="saveInfo">Đang lưu...</span>
                </button>
            </div>
        @endif

        @if($tab === 'password')
            <div class="mt-4 space-y-4">
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-neutral-200">
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label badge="Bắt buộc">Mật khẩu cũ</flux:label>
                            <flux:input viewable type="password" wire:model.defer="current_password" autocomplete="current-password" :invalid="$errors->has('current_password')" :class:input="$inputClass" />
                            @error('current_password') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label badge="Bắt buộc">Mật khẩu mới</flux:label>
                            <flux:input viewable type="password" wire:model.defer="new_password" autocomplete="new-password" :invalid="$errors->has('new_password')" :class:input="$inputClass" />
                            @error('new_password') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>

                        <flux:field>
                            <flux:label badge="Bắt buộc">Nhập lại mật khẩu mới</flux:label>
                            <flux:input viewable type="password" wire:model.defer="confirm_password" autocomplete="new-password" :invalid="$errors->has('confirm_password')" :class:input="$inputClass" />
                            @error('confirm_password') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    </div>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-4 text-xs text-neutral-500">
                    Mật khẩu cần tối thiểu 8 ký tự, có chữ hoa, số và ký tự đặc biệt.
                </div>

                <button type="button"
                        wire:click="savePassword"
                        wire:loading.attr="disabled"
                        wire:target="savePassword"
                        class="flex w-full items-center justify-center rounded-xl px-4 py-3 text-base font-bold text-white shadow-sm disabled:opacity-60"
                        style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                    <span wire:loading.remove wire:target="savePassword">Đổi mật khẩu</span>
                    <span wire:loading wire:target="savePassword">Đang xử lý...</span>
                </button>
            </div>
        @endif
    </div>
</div>
