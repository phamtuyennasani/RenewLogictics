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
        <header class="sticky top-0 z-40 bg-white border-b border-neutral-200 safe-top">
            <div class="flex items-center justify-between px-4 h-14">
                <div class="flex items-center gap-2">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span class="text-base font-semibold text-neutral-900">{{ config('system.name', 'VAU TRANS') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-neutral-500">{{ auth()->user()?->fullname ?? auth()->user()?->username }}</span>
                    <a href="{{ route('logout') }}" class="p-2 text-neutral-400 hover:text-red-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
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
