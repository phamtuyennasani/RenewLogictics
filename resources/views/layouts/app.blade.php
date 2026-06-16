<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('system.name', 'Bee Express') . ' — Quản lý vận chuyển' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    @livewireStyles
    @stack('styles')
</head>
<body class=" bg-neutral-50 font-sans antialiased h-screen overflow-hidden">
    @persist('loader')
    <x-global-loader />
    @endpersist
    @persist('confirm')
    <x-confirm-modal />
    @endpersist
    <div class="flex h-screen overflow-hidden">
        @persist('sidebar')
        <x-sidebar />
        @endpersist
        <div class="flex-1 flex flex-col ">
            @persist('header')
            <x-header :page-title="$pageTitle ?? null" />
            @endpersist
            <main class="overflow-y-auto p-6 flex-1">
                {{$slot}}
            </main>
            @persist('footer')
            @php
                $footerDieuKhoan = \Illuminate\Support\Facades\Cache::remember('static_dieu-khoan', 3600, function () {
                    return optional(\Illuminate\Support\Facades\DB::table('static')->where('type', 'dieu-khoan')->first())->contentvi ?? '';
                });
                $footerBaoMat = \Illuminate\Support\Facades\Cache::remember('static_bao-mat', 3600, function () {
                    return optional(\Illuminate\Support\Facades\DB::table('static')->where('type', 'bao-mat')->first())->contentvi ?? '';
                });
            @endphp
            <footer class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between px-6 shrink-0 z-10">
                <div class="text-sm text-neutral-500 mb-0 flex items-center">
                    <flux:modal.trigger name="footer-dieu-khoan">
                    <a href="javascript:void(0)" class="hover:text-primary-700">Điều khoản sử dụng</a>
                    </flux:modal.trigger>
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 bg-neutral-300 mx-3"></span>
                    <flux:modal.trigger name="footer-bao-mat">
                        <a href="javascript:void(0)" class="hover:text-primary-700">Chính sách bảo mật</a>
                    </flux:modal.trigger>
                </div>
                <p class="text-sm text-neutral-500 mb-0 flex items-center gap-1">
                    &copy; {{ date('Y') }} {{ config('system.name') }}. Developed by A Website
                </p>
            </footer>
            <flux:modal name="footer-dieu-khoan" class="max-w-[80rem] w-[80rem]" scroll="body">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-neutral-900">Điều khoản sử dụng</h2>
                    </div>
                    <div class="prose prose-neutral max-w-none text-neutral-700 show-content">
                        @if(!empty($footerDieuKhoan))
                            {!! $footerDieuKhoan !!}
                        @else
                            <p class="text-neutral-500">Chưa có nội dung.</p>
                        @endif
                    </div>
                    <div class="flex">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button type="button" variant="primary">Đóng</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>
            <flux:modal name="footer-bao-mat" class="max-w-[80rem] w-[80rem]" scroll="body">
                <div class="space-y-6">
                    <div>
                        <h2 class="text-xl font-bold text-neutral-900">Chính sách bảo mật</h2>
                    </div>
                    <div class="prose prose-neutral max-w-none text-neutral-700 show-content">
                        @if(!empty($footerBaoMat))
                            {!! $footerBaoMat !!}
                        @else
                            <p class="text-neutral-500">Chưa có nội dung.</p>
                        @endif
                    </div>
                    <div class="flex">
                        <flux:spacer />
                        <flux:modal.close>
                            <flux:button type="button" variant="primary">Đóng</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>
            @endpersist
        </div>
    </div>
    @persist('toast')
    <flux:toast.group position="bottom center">
        <flux:toast class="pt-24" />
    </flux:toast.group>
    @endpersist
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
