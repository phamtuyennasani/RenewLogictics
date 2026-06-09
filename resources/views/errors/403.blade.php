<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>403 — Không có quyền truy cập</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '{{ $primaryColor }}', 600: '{{ $primaryColor }}', 700: '{{ $primaryDark }}', 800: '{{ $primaryDark }}', 900: '{{ $primaryDark }}' },
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
        }
        .float-animation { animation: float 3s ease-in-out infinite; }
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 0.6; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-out infinite; }
        @keyframes draw-in {
            from { stroke-dashoffset: 100; }
            to { stroke-dashoffset: 0; }
        }
        .draw-in { stroke-dasharray: 100; animation: draw-in 1.2s ease-out forwards; }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-neutral-50 via-blue-50 to-sky-50 flex items-center justify-center">

    <div class="text-center px-6 max-w-lg mx-auto">
        {{-- Shield Icon with animation --}}
        <div class="relative inline-flex items-center justify-center mb-8">
            {{-- Pulse rings --}}
            <div class="absolute w-40 h-40 rounded-full bg-blue-100 pulse-ring"></div>
            <div class="absolute w-32 h-32 rounded-full bg-blue-200 pulse-ring" style="animation-delay: 0.5s;"></div>
            {{-- Shield --}}
            <div class="relative w-36 h-36 rounded-3xl bg-gradient-to-br from-blue-500 to-sky-600 flex items-center justify-center float-animation shadow-xl shadow-blue-200/60">
                <svg class="w-16 h-16 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path class="draw-in" d="M12 2L4 6v5c0 5.25 3.5 10.15 8 11.5C16.5 21.15 20 16.25 20 11V6l-8-4z"
                          stroke-dashoffset="0" stroke="dasharray="100"/>
                </svg>
            </div>
            {{-- 403 badge --}}
            <div class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-10 h-10 flex items-center justify-center shadow-lg">
                403
            </div>
        </div>

        {{-- Heading --}}
        <h1 class="text-4xl font-extrabold text-neutral-900 mb-2">
            Truy cập bị từ chối
        </h1>
        <p class="text-base text-neutral-500 mb-2">
            Bạn không có quyền truy cập trang này.
        </p>
        <p class="text-sm text-neutral-400 mb-10">
            Nếu bạn nghĩ đây là lỗi, vui lòng liên hệ quản trị viên.
        </p>

        {{-- Action buttons --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
            <a href="mailto:{{ config('mail.from.address', 'admin@hethong.local') }}?subject={{ rawurlencode('Yêu cầu cấp quyền truy cập') }}"
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-white border border-neutral-300 text-neutral-700 font-medium rounded-xl hover:bg-neutral-50 hover:shadow-sm transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                </svg>
                Liên hệ admin
            </a>
            <a href="{{ route('dashboard') }}"
               class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 text-white font-medium rounded-xl shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all"
               style="background: linear-gradient(135deg, {{ $primaryColor }}, {{ $accentColor }});">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Về trang chủ
            </a>
        </div>

        {{-- Subtle footer note --}}
        <p class="mt-12 text-xs text-neutral-300">
            {{ config('system.name') }} &copy; {{ date('Y') }}
        </p>
    </div>

</body>
</html>
