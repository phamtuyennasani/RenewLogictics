@php
    $primaryColor = config('theme.primary.hex', '#3b82f6');
    $primaryDark = config('theme.primary.dark', '#2563eb');
    $accentColor = config('theme.accent.hex', '#0ea5e9');
    $homeUrl = auth()->check() ? route('dashboard') : route('login');
    $homeLabel = auth()->check() ? 'Về trang chủ' : 'Đăng nhập';
@endphp

<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>404 - Không tìm thấy trang</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 600: '{{ $primaryColor }}', 700: '{{ $primaryDark }}' },
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .soft-grid {
            background-image:
                linear-gradient(rgba(15, 23, 42, .045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, .045) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        @keyframes drift {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        @keyframes scan {
            0% { transform: translateX(-110%); opacity: 0; }
            18% { opacity: .75; }
            80% { opacity: .25; }
            100% { transform: translateX(110%); opacity: 0; }
        }
        .drift { animation: drift 4s ease-in-out infinite; }
        .scan-line { animation: scan 3.2s ease-in-out infinite; }
    </style>
</head>
<body class="min-h-full bg-neutral-50 text-neutral-950">
    <main class="relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-10 soft-grid">
        <div class="absolute inset-x-0 top-0 h-48 bg-gradient-to-b from-white to-transparent"></div>
        <div class="absolute inset-x-0 bottom-0 h-48 bg-gradient-to-t from-white to-transparent"></div>

        <section class="relative w-full max-w-5xl">
            <div class="grid items-center gap-8 lg:grid-cols-[1fr_0.92fr]">
                <div class="order-2 text-center lg:order-1 lg:text-left">
                    <div class="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-white px-3 py-1.5 text-xs font-bold uppercase tracking-normal text-neutral-500 shadow-sm">
                        <span class="h-2 w-2 rounded-full" style="background: {{ $accentColor }}"></span>
                        Không tìm thấy
                    </div>

                    <h1 class="mt-5 max-w-2xl text-4xl font-black leading-tight tracking-normal text-neutral-950 sm:text-5xl">
                        Trang này không còn khả dụng
                    </h1>
                    <p class="mx-auto mt-4 max-w-xl text-base font-medium leading-7 text-neutral-500 lg:mx-0">
                        Đường dẫn có thể đã thay đổi, trang không tồn tại hoặc tính năng này chưa được mở trong hệ thống.
                    </p>

                    <div class="mt-7 flex flex-col items-center gap-3 sm:flex-row lg:justify-start">
                        <a href="{{ $homeUrl }}"
                           class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg px-5 text-sm font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl sm:w-auto"
                           style="background: linear-gradient(135deg, {{ $primaryColor }}, {{ $accentColor }});">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 11.5 12 4l9 7.5"></path>
                                <path d="M5 10.5V20h14v-9.5"></path>
                                <path d="M9 20v-6h6v6"></path>
                            </svg>
                            {{ $homeLabel }}
                        </a>
                        <button type="button"
                                onclick="history.length > 1 ? history.back() : window.location.assign('{{ $homeUrl }}')"
                                class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-lg border border-neutral-200 bg-white px-5 text-sm font-bold text-neutral-800 shadow-sm transition hover:-translate-y-0.5 hover:bg-neutral-50 hover:shadow-md sm:w-auto">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m12 19-7-7 7-7"></path>
                                <path d="M19 12H5"></path>
                            </svg>
                            Quay lại
                        </button>
                    </div>
                </div>

                <div class="order-1 flex justify-center lg:order-2">
                    <div class="drift relative aspect-square w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-5 shadow-2xl shadow-sky-100/80">
                        <div class="absolute -right-4 -top-4 rounded-xl px-4 py-2 text-sm font-black text-white shadow-lg" style="background: {{ $primaryColor }};">
                            404
                        </div>

                        <div class="relative flex h-full flex-col overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50">
                            <div class="flex items-center gap-2 border-b border-neutral-200 bg-white px-4 py-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                <span class="ml-2 h-2 flex-1 rounded-full bg-neutral-100"></span>
                            </div>

                            <div class="relative flex flex-1 items-center justify-center p-6">
                                <div class="absolute inset-x-6 top-8 h-2 rounded-full bg-neutral-200"></div>
                                <div class="absolute inset-x-10 top-14 h-2 rounded-full bg-neutral-100"></div>
                                <div class="scan-line absolute inset-y-8 w-24 rounded-full bg-gradient-to-r from-transparent via-sky-200/80 to-transparent blur-sm"></div>

                                <svg class="relative h-40 w-40 text-neutral-300" viewBox="0 0 160 160" fill="none" aria-hidden="true">
                                    <rect x="36" y="30" width="88" height="104" rx="16" fill="white" stroke="currentColor" stroke-width="6"></rect>
                                    <path d="M56 62h48M56 82h30M56 102h42" stroke="currentColor" stroke-width="7" stroke-linecap="round"></path>
                                    <circle cx="111" cy="111" r="24" fill="white" stroke="{{ $accentColor }}" stroke-width="7"></circle>
                                    <path d="m128 128 17 17" stroke="{{ $accentColor }}" stroke-width="8" stroke-linecap="round"></path>
                                    <path d="M103 104h16M111 96v16" stroke="{{ $accentColor }}" stroke-width="6" stroke-linecap="round"></path>
                                </svg>
                            </div>

                            <div class="border-t border-neutral-200 bg-white px-4 py-3">
                                <div class="h-2 w-2/3 rounded-full bg-neutral-100"></div>
                                <div class="mt-2 h-2 w-1/2 rounded-full bg-neutral-100"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>