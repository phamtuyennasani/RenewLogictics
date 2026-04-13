<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? (isset($pageTitle) ? $pageTitle : config('system.name', 'VAU TRANS')) }}</title>

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Vite / Livewire --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="h-full bg-neutral-50 font-sans antialiased">

    <div class="flex h-screen overflow-hidden">

        {{-- SIDEBAR --}}
        <x-sidebar />

        {{-- MAIN CONTENT --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- TOP HEADER BAR --}}
            <header class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between px-6 shrink-0 z-10">
                <div class="flex items-center gap-4">
                    {{-- Mobile menu toggle --}}
                    <button
                        @click="$dispatch('toggle-sidebar')"
                        class="lg:hidden p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    {{-- Page Title (if provided) --}}
                    @if (isset($pageTitle))
                        <h1 class="text-lg font-semibold text-neutral-900">{{ $pageTitle }}</h1>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    {{-- Notifications --}}
                    <button class="relative p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        {{-- Notification badge (optional) --}}
                        {{-- <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full"></span> --}}
                    </button>

                    {{-- User avatar dropdown --}}
                    <div class="flex items-center gap-3 pl-3 border-l border-neutral-200">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0"
                             style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                            {{ strtoupper(substr(Auth::user()->username ?? 'U', 0, 1)) }}
                        </div>
                        <div class="hidden sm:block">
                            <p class="text-sm font-medium text-neutral-900 leading-none">
                                {{ Auth::user()->fullname ?? Auth::user()->username }}
                            </p>
                            <p class="text-xs text-neutral-500 capitalize leading-none mt-0.5">
                                {{ Auth::user()->role }}
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            {{-- PAGE CONTENT --}}
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>

        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
