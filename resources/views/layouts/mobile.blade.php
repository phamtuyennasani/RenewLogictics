<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>{{ $title ?? 'Shipper — ' . config('system.name', 'VAU TRANS') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    @livewireStyles
    @stack('styles')
</head>
<body class="bg-neutral-50 font-sans antialiased min-h-screen">
    <div class="min-h-screen flex flex-col">
        {{-- Mobile Header --}}
        <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-neutral-200 safe-top">
            <div class="flex items-center justify-between gap-3 px-4 h-16">
                <a href="{{ route('shipper.pickups') }}" wire:navigate class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-xl text-white shadow-sm"
                         style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                        @if(config('system.logo'))
                            <img src="{{ config('system.logo') }}" alt="logo" class="h-full w-full object-contain p-1.5">
                        @else
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <span class="block truncate text-lg font-bold leading-tight text-neutral-950">{{ config('system.name', 'VAU TRANS') }}</span>
                        <span class="block truncate text-[11px] font-medium leading-tight text-neutral-500">{{ config('system.slogan', 'Quan ly van chuyen') }}</span>
                    </div>
                </a>
                <div class="flex shrink-0 items-center gap-2"
                     x-data="{
                        open: false,
                        avatarUrl: @js(auth()->user()?->avatar),
                        init() {
                            window.addEventListener('avatar-updated', (e) => {
                                this.avatarUrl = e.detail?.avatar || null;
                            });
                        }
                     }"
                     @click.outside="open = false">
                    <livewire:notification-bell />

                    <div class="relative">
                        <button type="button"
                                @click="open = !open"
                                class="flex items-center gap-1.5 rounded-full border border-primary-100 bg-white p-1 shadow-md shadow-primary-900/5 transition-colors active:bg-primary-50">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-bold text-white ring-2 ring-primary-100"
                                 style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                                <img x-show="avatarUrl" :src="avatarUrl" alt="avatar" class="h-full w-full rounded-full object-cover">
                                <span x-show="!avatarUrl">{{ strtoupper(substr(auth()->user()?->username ?? 'U', 0, 1)) }}</span>
                            </div>
                            <svg class="mr-1 h-4 w-4 text-neutral-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-64 rounded-xl border border-neutral-200 bg-white shadow-lg overflow-hidden z-50">
                            <div class="px-4 py-3 bg-neutral-50 border-b border-neutral-100">
                                <p class="text-sm font-semibold text-neutral-900 truncate">{{ auth()->user()?->fullname ?? auth()->user()?->username }}</p>
                                <p class="text-xs text-neutral-500 mt-0.5">{{ \App\Enums\RoleEnum::label(auth()->user()?->roles->first()?->name) }}</p>
                            </div>
                            <a href="{{ route('profile') }}"
                               wire:navigate
                               class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-700 hover:bg-neutral-50 transition-colors">
                                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Thông tin cá nhân
                            </a>
                            <a href="{{ route('logout') }}"
                               class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-700 hover:bg-red-50 hover:text-red-600 transition-colors group">
                                <svg class="w-4 h-4 text-neutral-400 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Main Content --}}
        <main class="flex-1 overflow-y-auto">
            {{ $slot }}
        </main>
    </div>

    <flux:toast.group position="bottom center">
        <flux:toast />
    </flux:toast.group>

    @livewireScripts
    @vite(['resources/js/app.js'])
    @fluxScripts
    @stack('scripts')
</body>
</html>
