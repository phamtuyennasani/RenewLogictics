<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
new #[Layout('layouts::guest')] class extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $remember = false;
    public bool $showPassword = false;
    public string $errorMessage = '';
    public bool $isLoading = false;

    protected $rules = [
        'username' => 'required|string|max:255',
        'password' => 'required|string|min:1',
    ];
    protected $messages = [
        'username.required' => 'Vui lòng nhập tên đăng nhập.',
        'password.required' => 'Vui lòng nhập mật khẩu.',
    ];
    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
        $this->errorMessage = '';
    }
    public function login()
    {
        $this->validate();
        $this->isLoading = true;
        $this->errorMessage = '';

        try {
            // Tìm user theo username hoặc email
            $user = User::where('username', $this->username)
                ->orWhere('email', $this->username)
                ->first();
            if (!$user) {
                $this->errorMessage = 'Tên đăng nhập không tồn tại.';
                $this->isLoading = false;
                return;
            }

            if (!Hash::check($this->password, $user->password)) {
                $this->errorMessage = 'Mật khẩu không đúng.';
                $this->isLoading = false;
                return;
            }

            // Kiểm tra tài khoản có hoạt động không (hienthi = đang hiển thị/hoạt động)
            if (!$user->active) {
                $this->errorMessage = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
                $this->isLoading = false;
                return;
            }

            // Đăng nhập
            Auth::login($user, $this->remember);

            // Cập nhật last login (cột trong DB là lastlogin)
            $user->update(['lastlogin' => now()]);

            // Chuyển hướng về dashboard hoặc intended URL
            return redirect()->intended(route('dashboard'));
        } catch (\Exception $e) {
            $this->errorMessage = 'Đã có lỗi xảy ra. Vui lòng thử lại sau.';
            report($e);
        } finally {
            $this->isLoading = false;
        }
    }

    public function togglePassword()
    {
        $this->showPassword = !$this->showPassword;
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div class="min-h-screen flex bg-gradient-to-br from-blue-50 via-white to-sky-50">
    {{-- Left Panel: Login Form --}}
    <div class="w-full 4xl:max-w-[30rem] max-w-[500px] bg-white p-8 lg:p-14 flex flex-col justify-center relative shadow-2xl lg:shadow-none">
        {{-- Logo Header --}}
        <div class="mb-10">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-sky-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-500/30">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ config('system.name', 'LOGISTICS') }}</h1>
                    <p class="text-sm text-gray-500">{{ config('system.slogan', 'Hệ thống quản lý vận chuyển') }}</p>
                </div>
            </div>
        </div>

        {{-- Login Form --}}
        <div class="max-w-sm mx-auto w-full">
            <div class="mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-2">Chào mừng trở lại</h2>
                <p class="text-gray-500">Đăng nhập để tiếp tục quản lý đơn hàng của bạn</p>
            </div>

            <form wire:submit="login" x-data="{ errorMessage: @entangle('errorMessage') }" class="space-y-5">
                {{-- Username Field --}}
                <div>
                    <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">
                        Tên đăng nhập
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input
                            wire:model.defer="username"
                            @focus="errorMessage = ''; $el.classList.remove('border-red-300', 'bg-red-50')"
                            type="text"
                            id="username"
                            placeholder="Nhập tên đăng nhập"
                            autocomplete="username"
                            @class([
                                'w-full !outline-none pl-12 pr-4 py-3 border-2 bg-gray-50 border-gray-100 rounded-xl text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all duration-200',
                                'bg-gray-50 border-gray-100' => ! $errors->has('username'),
                                'bg-red-50 border-red-300' => $errors->has('username'),
                            ])
                        >
                    </div>
                    
                </div>

                {{-- Password Field --}}
                <div>
                    <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                        Mật khẩu
                    </label>
                    <div class="relative" x-data="{ show: @entangle('showPassword') }">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input
                            wire:model.defer="password"
                            @focus="errorMessage = '';$el.classList.remove('border-red-300', 'bg-red-50')"
                            :type="show ? 'text' : 'password'"
                            id="password"
                            placeholder="Nhập mật khẩu"
                            autocomplete="current-password"
                            @class([
                                'w-full !outline-none pl-12 pr-14 py-3 border-2 bg-gray-50 border-gray-100 rounded-xl text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-blue-500 focus:bg-white transition-all duration-200',
                                'bg-gray-50 border-gray-100' => ! $errors->has('password'),
                                'bg-red-50 border-red-300' => $errors->has('password'),
                            ])
                        >
                        <button
                            type="button"
                            @click="show = !show"
                            class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <svg x-show="!show" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                            </svg>
                            <svg x-show="show" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Error Message --}}
                <div x-show="errorMessage" x-transition class="p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3" x-cloak>
                    <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm text-red-600 font-medium" x-text="errorMessage"></p>
                </div>

                {{-- Remember & Options --}}
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="checkbox" wire:model.live="remember" class="sr-only peer">
                        <span class="w-5 h-5 rounded-md border-2 border-gray-200 peer-checked:bg-blue-500 peer-checked:border-blue-500 flex items-center justify-center transition-colors group-hover:border-blue-300">
                            <svg class="w-3 h-3 text-white opacity-0 peer-checked:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </span>
                        <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">Ghi nhớ đăng nhập</span>
                    </label>
                </div>

                {{-- Submit Button --}}
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    wire:ignore
                    x-data="{
                        isLoading: false,
                        init() {
                            Livewire.hook('message.sent', () => { this.isLoading = true; });
                            Livewire.hook('message.processed', () => { this.isLoading = false; });
                        }
                    }"
                    class="w-full py-4 bg-gradient-to-r from-blue-500 to-sky-500 hover:from-blue-600 hover:to-sky-600 disabled:from-gray-300 disabled:to-gray-400 text-white font-semibold rounded-xl transition-all duration-200 flex items-center justify-center gap-3 shadow-lg shadow-blue-500/30 hover:shadow-blue-500/50 disabled:shadow-none disabled:cursor-not-allowed"
                >
                    <template x-if="!isLoading">
                        <span class="flex items-center gap-2">
                            <span>Đăng nhập</span>
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </span>
                    </template>
                    <template x-if="isLoading">
                        <span class="flex items-center gap-2">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.657A8 8 0 0112 20v-4M12 12V4m0 0L8 8"/>
                            </svg>
                            <span>Đang xử lý...</span>
                        </span>
                    </template>
                </button>
            </form>
            {{-- Divider --}}
            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white text-gray-400">Hoặc tiếp tục với</span>
                </div>
            </div>
            {{-- Social Login --}}
            <div class="grid grid-cols-3 gap-3">
                <button class="py-3 px-4 bg-white border-2 border-gray-100 rounded-xl hover:border-gray-200 hover:bg-gray-50 transition-all flex items-center justify-center gap-2 group">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                </button>
                <button class="py-3 px-4 bg-white border-2 border-gray-100 rounded-xl hover:border-gray-200 hover:bg-gray-50 transition-all flex items-center justify-center gap-2 group">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0112 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/>
                    </svg>
                </button>
                <button class="py-3 px-4 bg-white border-2 border-gray-100 rounded-xl hover:border-gray-200 hover:bg-gray-50 transition-all flex items-center justify-center gap-2 group">
                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M22.675 0h-21.35c-.732 0-1.325.593-1.325 1.325v21.351c0 .731.593 1.324 1.325 1.324h11.35c.734 0 1.324-.593 1.325-1.324v-11.35c0-.731-.591-1.325-1.325-1.325h-.181c-3.905-.247-7.069-3.385-7.069-7.33 0-3.945 3.164-7.083 7.069-7.33h.181c.734 0 1.325-.593 1.325-1.325v-1.326c0-.732-.591-1.325-1.325-1.325z"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Footer --}}
        <div class="mt-auto pt-8 text-center text-sm text-gray-400">
            <p>© {{ date('Y') }} {{ config('system.name', 'LOGISTICS') }}. Hệ thống quản lý vận chuyển!</p>
        </div>
    </div>

    {{-- Right Panel: Banner --}}
    <div class="hidden lg:flex flex-1 relative overflow-hidden">
        {{-- Background Gradient --}}
        <div class="absolute inset-0 bg-gradient-to-br from-blue-600 via-sky-600 to-cyan-500"></div>

        {{-- Animated shapes --}}
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-white/10 rounded-full blur-3xl animate-pulse"></div>
            <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-cyan-400/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-sky-400/10 rounded-full blur-3xl"></div>
        </div>

        {{-- Grid pattern --}}
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: linear-gradient(rgba(255,255,255,.2) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.2) 1px, transparent 1px); background-size: 50px 50px;"></div>
        </div>

        {{-- Content --}}
        <div class="relative z-10 flex flex-col items-center justify-center w-full text-white p-12">
            <div class="max-w-xl text-center">
                {{-- Icon --}}
                <div class="w-24 h-24 bg-white/10 backdrop-blur-sm rounded-3xl flex items-center justify-center mx-auto mb-8 shadow-2xl">
                    <svg class="w-14 h-14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>

                <h2 class="text-5xl font-bold mb-6 leading-tight">
                    Quản lý vận chuyển<br>
                    <span class="text-cyan-200">Thông minh hơn</span>
                </h2>
                <p class="text-xl text-white/80 mb-12 leading-relaxed">
                    Hệ thống quản lý đơn hàng toàn diện, theo dõi trực tuyến và báo cáo chi tiết giúp tối ưu hoá quy trình vận chuyển.
                </p>

                {{-- Features Grid - Glassmorphism --}}
                <div class="grid grid-cols-2 gap-5">
                    {{-- Feature 1: Quản lý đơn hàng --}}
                    <div class="group relative bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 hover:bg-white/20 transition-all duration-500 hover:scale-[1.02] cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10 flex items-center gap-4">
                            <div class="w-12 h-12 bg-cyan-500/40 backdrop-blur-sm rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <h4 class="text-white font-semibold mb-1 group-hover:text-cyan-200 transition-colors leading-tight">Quản lý đơn hàng</h4>
                                <p class="text-sm text-white/60 leading-snug">Tạo, theo dõi & cập nhật trạng thái vận đơn</p>
                            </div>
                        </div>
                    </div>

                    {{-- Feature 2: Theo dõi vận chuyển --}}
                    <div class="group relative bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 hover:bg-white/20 transition-all duration-500 hover:scale-[1.02] cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10 flex items-center gap-4">
                            <div class="w-12 h-12 bg-emerald-500/40 backdrop-blur-sm rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <h4 class="text-white font-semibold mb-1 group-hover:text-cyan-200 transition-colors leading-tight">Theo dõi vận chuyển</h4>
                                <p class="text-sm text-white/60 leading-snug">Cập nhật vị trí đơn hàng theo thời gian thực</p>
                            </div>
                        </div>
                    </div>

                    {{-- Feature 3: Báo cáo & Thống kê --}}
                    <div class="group relative bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 hover:bg-white/20 transition-all duration-500 hover:scale-[1.02] cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10 flex items-center gap-4">
                            <div class="w-12 h-12 bg-amber-500/40 backdrop-blur-sm rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <h4 class="text-white font-semibold mb-1 group-hover:text-cyan-200 transition-colors leading-tight">Báo cáo thống kê</h4>
                                <p class="text-sm text-white/60 leading-snug">Biểu đồ & số liệu chi tiết theo ngày/tháng</p>
                            </div>
                        </div>
                    </div>

                    {{-- Feature 4: Quản lý tài chính --}}
                    <div class="group relative bg-white/10 backdrop-blur-md rounded-2xl p-5 border border-white/20 hover:bg-white/20 transition-all duration-500 hover:scale-[1.02] cursor-pointer">
                        <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                        <div class="relative z-10 flex items-center gap-4">
                            <div class="w-12 h-12 bg-rose-500/40 backdrop-blur-sm rounded-xl flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <h4 class="text-white font-semibold mb-1 group-hover:text-cyan-200 transition-colors leading-tight">Quản lý tài chính</h4>
                                <p class="text-sm text-white/60 leading-snug">Công nợ, thanh toán & hoa hồng</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .animate-shake {
        animation: shake 0.3s ease-in-out;
    }
</style>
@endpush