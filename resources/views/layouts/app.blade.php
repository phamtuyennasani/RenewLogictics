<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('system.name', 'VAU TRANS') . ' — Quản lý vận chuyển' }}</title>

    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    {{-- Google Fonts: Inter --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Vite / Livewire Styles --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="h-full bg-neutral-50 font-sans antialiased">
    <div class="min-h-screen flex flex-col">
        <main class="flex-1">
            @if(!\Auth::check())
                @yield('content')
            @else
                <div class="wrap-sidebar">
                    
                </div>
                <div class="wrap-main-page">
                    <div class="header-main-page"></div>
                    <div class="content-main-page"></div>
                    <div class="footer-main-page"></div>
                </div>
            @endif
        </main>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
