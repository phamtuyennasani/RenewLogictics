<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('system.name', 'VAU TRANS') . ' — Quản lý vận chuyển' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @fluxAppearance
    @stack('styles')
</head>
<body class="h-full bg-neutral-50 font-sans antialiased">
    @persist('loader')
    <x-global-loader />
    @endpersist
    <div class="flex h-screen overflow-hidden">
            @persist('sidebar')
            <x-sidebar />
            @endpersist
            <div class="flex-1 flex flex-col overflow-hidden">
                @persist('header')
                <header class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between px-6 shrink-0 z-10">
                    <div class="flex items-center gap-4">
                        <button
                            @click="$dispatch('toggle-sidebar')"
                            class="lg:hidden p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        @if (isset($pageTitle))
                            <h1 class="text-lg font-semibold text-neutral-900">{{ $pageTitle }}</h1>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <button class="relative p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </button>
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
                @endpersist
                <main class="flex-1 overflow-y-auto p-6">
                    {{$slot}}
                </main>
                @persist('footer')
                <footer class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between px-6 shrink-0 z-10">
                    <p class="text-sm text-neutral-500 mb-0 flex items-center">
                        <a href="javascript:void(0)" class="hover:text-primary-700">Chính sách điều khoản</a>
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 bg-neutral-300 mx-3"></span>
                        <a href="javascript:void(0)" class="hover:text-primary-700">Bảo mật thông tin</a>
                    </p>
                    <p class="text-sm text-neutral-500 mb-0 flex items-center gap-1">
                        &copy; {{ date('Y') }} {{ config('system.name') }}. All rights reserved
                        <span>
                            <svg class="icon-16" width="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M15.85 2.50065C16.481 2.50065 17.111 2.58965 17.71 2.79065C21.401 3.99065 22.731 8.04065 21.62 11.5806C20.99 13.3896 19.96 15.0406 18.611 16.3896C16.68 18.2596 14.561 19.9196 12.28 21.3496L12.03 21.5006L11.77 21.3396C9.48102 19.9196 7.35002 18.2596 5.40102 16.3796C4.06102 15.0306 3.03002 13.3896 2.39002 11.5806C1.26002 8.04065 2.59002 3.99065 6.32102 2.76965C6.61102 2.66965 6.91002 2.59965 7.21002 2.56065H7.33002C7.61102 2.51965 7.89002 2.50065 8.17002 2.50065H8.28002C8.91002 2.51965 9.52002 2.62965 10.111 2.83065H10.17C10.21 2.84965 10.24 2.87065 10.26 2.88965C10.481 2.96065 10.69 3.04065 10.89 3.15065L11.27 3.32065C11.3618 3.36962 11.4649 3.44445 11.554 3.50912C11.6104 3.55009 11.6612 3.58699 11.7 3.61065C11.7163 3.62028 11.7329 3.62996 11.7496 3.63972C11.8354 3.68977 11.9247 3.74191 12 3.79965C13.111 2.95065 14.46 2.49065 15.85 2.50065ZM18.51 9.70065C18.92 9.68965 19.27 9.36065 19.3 8.93965V8.82065C19.33 7.41965 18.481 6.15065 17.19 5.66065C16.78 5.51965 16.33 5.74065 16.18 6.16065C16.04 6.58065 16.26 7.04065 16.68 7.18965C17.321 7.42965 17.75 8.06065 17.75 8.75965V8.79065C17.731 9.01965 17.8 9.24065 17.94 9.41065C18.08 9.58065 18.29 9.67965 18.51 9.70065Z"
                                    fill="currentColor"></path>
                            </svg>
                        </span>
                    </p>
                </footer>
                @endpersist
            </div>
        </div>
    @livewireScripts
    @fluxScripts
    @stack('scripts')
</body>
</html>
