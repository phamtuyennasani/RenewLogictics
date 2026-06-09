<?php

use Livewire\Component;

new class extends Component {
    public array $menuItems = [];

    public function mount()
    {
        abort_unless(\Gate::allows('settings.admin'), 403);
        $userRole = auth()->user()->roles->first()?->name ?? '';

        $this->menuItems = array_filter([
            [
                'route' => 'settings.he-thong',
                'icon' => 'cog',
                'title' => 'Cấu hình hệ thống',
                'description' => 'Bật/tắt cổng thanh toán, email, API keys, tài khoản ngân hàng',
                'roles' => ['admin'],
            ],
            [
                'route' => 'settings.company',
                'icon' => 'building',
                'title' => 'Thông tin công ty',
                'description' => 'Tên, địa chỉ, SĐT, email, mã số thuế',
                'roles' => ['admin'],
            ],
            [
                'route' => 'settings.logo',
                'icon' => 'photo',
                'title' => 'Logo',
                'description' => 'Upload và thay đổi logo hệ thống',
                'roles' => ['admin'],
            ],
            [
                'route' => 'settings.favicon',
                'icon' => 'star',
                'title' => 'Favicon',
                'description' => 'Icon hiển thị trên tab trình duyệt',
                'roles' => ['admin'],
            ],
            [
                'route' => 'settings.social',
                'icon' => 'share',
                'title' => 'Mạng xã hội',
                'description' => 'Facebook, Zalo, Hotline, Instagram...',
                'roles' => ['admin'],
            ],
            [
                'route' => 'settings.thongbao',
                'icon' => 'bell',
                'title' => 'Thông báo',
                'description' => 'Cấu hình template và trigger thông báo',
                'roles' => ['admin', 'manager', 'ketoan', 'cs'],
            ],
        ], fn($item) => in_array($userRole, $item['roles']));
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

<div class="space-y-6">

    {{-- PAGE HEADER --}}
    <div>
        <p class="text-sm text-neutral-500">Cấu hình</p>
        <h1 class="text-2xl font-bold text-neutral-900">Cấu hình hệ thống</h1>
    </div>

    {{-- SETTINGS GRID --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($menuItems as $item)
            <a href="{{ route($item['route']) }}" wire:navigate
               class="group bg-white rounded-2xl border border-neutral-200 p-6 shadow-sm
                      hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0 text-white"
                         style="{{ $gradientStyle }}">
                        @switch($item['icon'])
                            @case('cog')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                @break
                            @case('building')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                @break
                            @case('photo')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                @break
                            @case('star')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                                @break
                            @case('image')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                @break
                            @case('share')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                                @break
                            @case('bell')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                @break
                        @endswitch
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-neutral-900 group-hover:text-primary-600 transition-colors">
                            {{ $item['title'] }}
                        </h3>
                        <p class="text-xs text-neutral-500 mt-1 line-clamp-2">
                            {{ $item['description'] }}
                        </p>
                    </div>
                </div>
            </a>
        @endforeach
    </div>
</div>
