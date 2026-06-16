<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>{{ $title ?? config('system.name', 'Bee Express') }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @include('partials.system-theme-script')
    @vite(['resources/css/app.css'])
    @livewireStyles
    @stack('styles')
</head>
<body class="system-shell mobile-shell min-h-screen bg-neutral-50 font-sans text-neutral-900 antialiased transition-colors dark:bg-neutral-950 dark:text-neutral-100">
    @php
        $__mobileUser = auth()->user();
        $__mobileRouteName = request()->route()?->getName();
        $__isOpsMobileRoute = $__mobileRouteName && (str_starts_with($__mobileRouteName, 'ops.mobile.') || $__mobileRouteName === 'mobile.scan');
        $__isOpsMobile = auth()->check()
            && $__mobileUser?->hasAnyRole(['ops', 'admin', 'manager', 'cs'])
            && $__isOpsMobileRoute;
        $__isShipperMobile = auth()->check()
            && $__mobileUser?->hasRole('shipper')
            && ! $__isOpsMobile
            && ($__mobileRouteName ? str_starts_with($__mobileRouteName, 'shipper.') : true);
        $__mobileHomeRoute = $__isOpsMobile ? 'ops.mobile.orders.index' : 'shipper.pickups';
        $__mobileProfileRoute = $__isOpsMobile ? 'ops.mobile.profile' : 'shipper.profile';
    @endphp
    <div class="min-h-screen flex flex-col">
        {{-- Mobile Header --}}
        <header class="sticky top-0 z-40 border-b border-neutral-200 bg-white/95 backdrop-blur safe-top dark:border-white/10 dark:bg-slate-950/95">
            <div class="flex items-center justify-between gap-3 px-4 h-16">
                <a href="{{ route($__mobileHomeRoute) }}" wire:navigate class="flex min-w-0 items-center gap-3">
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
                        <span class="block truncate text-lg font-bold leading-tight text-neutral-950 dark:text-white">{{ config('system.name', 'Bee Express') }}</span>
                        <span class="block truncate text-[11px] font-medium leading-tight text-neutral-500 dark:text-slate-400">{{ config('system.slogan', 'Quan ly van chuyen') }}</span>
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

                    @if(config('features.theme_toggle', false))
                        <x-theme-toggle />
                    @endif

                    <div class="relative">
                        <button type="button"
                                @click="open = !open"
                                class="flex items-center gap-1.5 rounded-full border border-primary-100 bg-white p-1 shadow-md shadow-primary-900/5 transition-colors active:bg-primary-50 dark:border-white/10 dark:bg-slate-900 dark:active:bg-slate-800">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-bold text-white ring-2 ring-primary-100"
                                 style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                                <img x-show="avatarUrl" :src="avatarUrl" alt="avatar" class="h-full w-full rounded-full object-cover">
                                <span x-show="!avatarUrl">{{ strtoupper(substr(auth()->user()?->username ?? 'U', 0, 1)) }}</span>
                            </div>
                            <svg class="mr-1 h-4 w-4 text-neutral-400 transition-transform dark:text-slate-500" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                             class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-white/10 dark:bg-slate-900 dark:shadow-black/30">
                            <div class="border-b border-neutral-100 bg-neutral-50 px-4 py-3 dark:border-white/10 dark:bg-slate-950">
                                <p class="truncate text-sm font-semibold text-neutral-900 dark:text-white">{{ auth()->user()?->fullname ?? auth()->user()?->username }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500 dark:text-slate-400">{{ \App\Enums\RoleEnum::label(auth()->user()?->roles->first()?->name) }}</p>
                            </div>
                            <a href="{{ route($__mobileProfileRoute) }}"
                               wire:navigate
                               class="flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-50 dark:text-slate-200 dark:hover:bg-white/5">
                                <svg class="h-4 w-4 text-neutral-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Thông tin cá nhân
                            </a>
                            <a href="{{ route('logout') }}"
                               class="group flex items-center gap-3 px-4 py-2.5 text-sm text-neutral-700 transition-colors hover:bg-red-50 hover:text-red-600 dark:text-slate-200 dark:hover:bg-red-500/10 dark:hover:text-red-300">
                                <svg class="h-4 w-4 text-neutral-400 transition-colors group-hover:text-red-500 dark:text-slate-500 dark:group-hover:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <main class="flex-1 overflow-y-auto {{ ($__isShipperMobile || $__isOpsMobile) ? 'pb-28' : '' }}">
            {{ $slot }}
        </main>
    </div>

    @if($__isShipperMobile)
        <nav data-mobile-nav class="fixed inset-x-4 bottom-4 z-[80] mx-auto max-w-md rounded-[2rem] border border-white/20 bg-gradient-to-r from-primary-600 via-blue-600 to-primary-700 p-1.5 shadow-2xl shadow-primary-950/25 backdrop-blur dark:border-white/10 dark:from-slate-900 dark:via-primary-950 dark:to-slate-950 dark:shadow-black/40">
            <div class="grid grid-cols-3 items-center gap-1">
                <a href="{{ route('shipper.pickups') }}"
                   wire:navigate
                   class="flex h-12 items-center justify-center gap-2 rounded-[1.65rem] text-sm font-bold transition
                    {{ $__mobileRouteName === 'shipper.pickups' ? 'bg-white text-primary-700 shadow-lg shadow-primary-950/10' : 'text-white/75 hover:text-white active:bg-white/10' }}">
                    <svg class="h-5 w-5 shrink-0 {{ $__mobileRouteName === 'shipper.pickups' ? '' : 'opacity-90' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    @if($__mobileRouteName === 'shipper.pickups')
                        <span>Pickup</span>
                    @endif
                </a>

                <a href="{{ route('shipper.scan') }}"
                   wire:navigate
                   class="group flex h-12 items-center justify-center gap-2 rounded-[1.65rem] text-sm font-bold transition
                    {{ $__mobileRouteName === 'shipper.scan' ? 'bg-white text-primary-700 shadow-lg shadow-primary-950/10' : 'text-white/80 hover:text-white active:bg-white/10' }}"
                   aria-label="Quét mã">
                    <span class="flex h-9 w-9 items-center justify-center rounded-2xl {{ $__mobileRouteName === 'shipper.scan' ? '' : 'bg-white/12 ring-1 ring-white/15 group-active:bg-white/20' }} transition">
                        <svg class="h-5 w-5 {{ $__mobileRouteName === 'shipper.scan' ? '' : 'opacity-95' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4H5a1 1 0 00-1 1v2m13-3h2a1 1 0 011 1v2M4 17v2a1 1 0 001 1h2m13-3v2a1 1 0 01-1 1h-2"/>
                            <path stroke-linecap="round" stroke-width="2" d="M8 8v8m3-8v8m3-8v8m3-8v8"/>
                        </svg>
                    </span>
                    @if($__mobileRouteName === 'shipper.scan')
                        <span>Scan</span>
                    @endif
                </a>

                <a href="{{ route('shipper.profile') }}"
                   wire:navigate
                   class="flex h-12 items-center justify-center gap-2 rounded-[1.65rem] text-sm font-bold transition
                    {{ $__mobileRouteName === 'shipper.profile' ? 'bg-white text-primary-700 shadow-lg shadow-primary-950/10' : 'text-white/75 hover:text-white active:bg-white/10' }}">
                    <svg class="h-5 w-5 shrink-0 {{ $__mobileRouteName === 'shipper.profile' ? '' : 'opacity-90' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0"/>
                    </svg>
                    @if($__mobileRouteName === 'shipper.profile')
                        <span>Cá nhân</span>
                    @endif
                </a>
            </div>
        </nav>
    @endif

    @if($__isOpsMobile)
        @php
            $__opsNavItems = [
                [
                    'route' => 'ops.mobile.orders.index',
                    'active' => str_starts_with((string) $__mobileRouteName, 'ops.mobile.orders.'),
                    'label' => 'Order',
                    'icon' => 'M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 012-2z',
                ],
                [
                    'route' => 'ops.mobile.pickups.index',
                    'active' => str_starts_with((string) $__mobileRouteName, 'ops.mobile.pickups.'),
                    'label' => 'PickUp',
                    'icon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
                ],
                [
                    'route' => 'mobile.scan',
                    'active' => $__mobileRouteName === 'mobile.scan',
                    'label' => 'Scan',
                    'icon' => 'M7 4H5a1 1 0 00-1 1v2m13-3h2a1 1 0 011 1v2M4 17v2a1 1 0 001 1h2m13-3v2a1 1 0 01-1 1h-2M8 8v8m4-8v8m4-8v8',
                ],
                [
                    'route' => 'ops.mobile.notifications',
                    'active' => $__mobileRouteName === 'ops.mobile.notifications',
                    'label' => 'Tin',
                    'icon' => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-4-5.7V5a2 2 0 10-4 0v.3A6 6 0 006 11v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1',
                ],
                [
                    'route' => 'ops.mobile.profile',
                    'active' => $__mobileRouteName === 'ops.mobile.profile',
                    'label' => 'Cá nhân',
                    'icon' => 'M15.75 7.5a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.25a7.5 7.5 0 0115 0',
                ],
            ];
        @endphp
        <nav data-mobile-nav class="fixed inset-x-3 bottom-4 z-[80] mx-auto max-w-lg rounded-[1.75rem] border border-white/20 p-1.5 shadow-2xl shadow-primary-950/25 backdrop-blur dark:border-white/10 dark:shadow-black/40"
             style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
            <div class="grid grid-cols-5 items-center gap-1">
                @foreach($__opsNavItems as $__item)
                    <a href="{{ route($__item['route']) }}"
                       wire:navigate
                       class="flex h-12 min-w-0 flex-col items-center justify-center gap-0.5 rounded-[1.35rem] text-[10px] font-bold transition {{ $__item['active'] ? 'bg-white text-primary-700 shadow-lg shadow-primary-950/10' : 'text-white/75 active:bg-white/10' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $__item['icon'] }}"/>
                        </svg>
                        <span class="max-w-full truncate">{{ $__item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </nav>
    @endif

    <flux:toast.group position="bottom center">
        <flux:toast />
    </flux:toast.group>

    @livewireScripts
    @php
        $__vietmapTileKey = data_get(\App\Models\Setting::first(), 'options.vietmap_tile_api_key', '');
    @endphp
    @if($__vietmapTileKey)
    <script>
        window.__VIETMAP_PUBLIC_CONFIG__ = {
            tileApiKey: @json($__vietmapTileKey),
        };
    </script>
    @endif
    @vite(['resources/js/app.js'])
    @fluxScripts
    @stack('scripts')
</body>
</html>
