@php
    $routeName = request()->route()?->getName() ?? '';
    $breadcrumbs = match (true) {
        str_starts_with($routeName, 'orders.') => [
            ['label' => 'Tác vụ', 'route' => null],
            ['label' => 'Đơn hàng', 'route' => route('orders.index')],
            ['label' => match ($routeName) {
                'orders.create' => 'Tạo đơn',
                'orders.payment' => 'Cập nhật giá',
                'orders.tracking' => 'Tracking',
                'orders.show' => 'Chi tiết',
                default => 'Danh sách',
            }, 'route' => null],
        ],
        str_starts_with($routeName, 'congno.daily.') => [
            ['label' => 'Tác vụ', 'route' => null],
            ['label' => 'Công nợ đại lý', 'route' => route('congno.daily.index')],
            ['label' => $routeName === 'congno.daily.show' ? 'Chi tiết' : 'Danh sách', 'route' => null],
        ],
        str_starts_with($routeName, 'congno.') => [
            ['label' => 'Tác vụ', 'route' => null],
            ['label' => 'Công nợ khách hàng', 'route' => route('congno.index')],
            ['label' => $routeName === 'congno.show' ? 'Chi tiết' : 'Danh sách', 'route' => null],
        ],
        str_starts_with($routeName, 'invoice.') => [
            ['label' => 'Tác vụ', 'route' => null],
            ['label' => 'Hóa đơn thu', 'route' => route('invoice.index')],
        ],
        str_starts_with($routeName, 'nhansu.') => [
            ['label' => 'Nhân sự', 'route' => null],
            ['label' => 'Danh sách', 'route' => null],
        ],
        str_starts_with($routeName, 'settings.') || $routeName === 'profile' => [
            ['label' => 'Cấu hình', 'route' => null],
            ['label' => $routeName === 'profile' ? 'Thông tin cá nhân' : 'Cấu hình chung', 'route' => null],
        ],
        $routeName === 'dashboard' => [
            ['label' => 'Dashboard', 'route' => null],
        ],
        default => isset($pageTitle) ? [['label' => $pageTitle, 'route' => null]] : [],
    };
@endphp

<header class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between gap-4 px-6 shrink-0 z-10 dark:border-white/10 dark:bg-slate-950">
    <div class="min-w-0 flex-1">
        @if (!empty($breadcrumbs))
            <nav class="flex items-center gap-2 text-sm min-w-0" aria-label="Breadcrumb">
                @foreach ($breadcrumbs as $index => $item)
                    @if ($index > 0)
                        <svg class="w-3.5 h-3.5 text-neutral-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    @endif

                    @if (!empty($item['route']) && $index < count($breadcrumbs) - 1)
                        <a href="{{ $item['route'] }}" wire:navigate class="text-neutral-500 hover:text-primary-700 transition truncate">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="font-semibold text-neutral-900 truncate">
                            {{ $item['label'] }}
                        </span>
                    @endif
                @endforeach
            </nav>
        @elseif (isset($pageTitle))
            <h1 class="text-lg font-semibold text-neutral-900 truncate">{{ $pageTitle }}</h1>
        @endif
    </div>

    <div
        class="hidden md:block w-full max-w-xl"
        x-data="{
            query: '',
            results: [],
            loading: false,
            open: false,
            timer: null,
            endpoint: @js(route('api.global-search')),
            init() {
                window.addEventListener('keydown', (event) => {
                    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                        event.preventDefault();
                        this.$refs.searchInput?.focus();
                    }
                    if (event.key === '/' && !['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)) {
                        event.preventDefault();
                        this.$refs.searchInput?.focus();
                    }
                });
            },
            search() {
                clearTimeout(this.timer);
                this.timer = setTimeout(async () => {
                    const value = this.query.trim();
                    if (value.length < 2) {
                        this.results = [];
                        this.open = false;
                        return;
                    }

                    this.loading = true;
                    try {
                        const response = await fetch(`${this.endpoint}?q=${encodeURIComponent(value)}`, {
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();
                        this.results = data.results || [];
                        this.open = true;
                    } catch (error) {
                        this.results = [];
                        this.open = false;
                    } finally {
                        this.loading = false;
                    }
                }, 400);
            },
            go(url) {
                if (!url) return;
                window.location.href = url;
            },
            submitFirst() {
                if (this.results.length > 0) {
                    this.go(this.results[0].url);
                    return;
                }
                const value = this.query.trim();
                if (value.length > 0) {
                    this.go(`/theo-doi/${encodeURIComponent(value)}`);
                }
            }
        }"
        @click.outside="open = false"
    >
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                x-ref="searchInput"
                x-model="query"
                @input="search()"
                @focus="query.trim().length >= 2 && (open = true)"
                @keydown.enter.prevent="submitFirst()"
                @keydown.escape="open = false"
                type="text"
                placeholder="Tìm mã đơn, mã tracking..."
                class="w-full h-10 rounded-xl border border-neutral-200 bg-neutral-50 pl-9 pr-20 text-sm text-neutral-800 placeholder:text-neutral-400 outline-none transition focus:border-primary-300 focus:bg-white focus:ring-2 focus:ring-primary-100"
            >
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-2">
                <span x-show="loading" class="w-3.5 h-3.5 rounded-full border-2 border-primary-200 border-t-primary-600 animate-spin"></span>
                <span class="hidden lg:inline-flex rounded-md border border-neutral-200 bg-white px-1.5 py-0.5 text-[10px] font-semibold text-neutral-400">Ctrl K</span>
            </div>

            <div
                x-show="open"
                x-cloak
                x-transition
                class="absolute left-0 right-0 top-full mt-2 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xl z-50"
            >
                <template x-if="results.length > 0">
                    <div class="py-1.5">
                        <template x-for="item in results" :key="item.url">
                            <button type="button" @click="go(item.url)" class="w-full px-3 py-2.5 text-left hover:bg-neutral-50 transition flex items-start gap-3">
                                <span class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v14l-4-2-4 2-4-2-4 2V6a2 2 0 012-2z" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-neutral-900" x-text="item.label"></span>
                                    <span class="block truncate text-xs text-neutral-500" x-text="item.sublabel"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="results.length === 0 && query.trim().length >= 2 && !loading">
                    <button type="button" @click="submitFirst()" class="w-full px-3 py-3 text-left hover:bg-neutral-50 transition">
                        <span class="block text-sm font-semibold text-neutral-900">Theo dõi mã: <span x-text="query.trim()"></span></span>
                        <span class="block text-xs text-neutral-500 mt-0.5">Nhấn Enter để mở trang tracking với mã này.</span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3"
         x-data="{
            open: false,
            avatarUrl: @js(Auth::user()->avatar),
            init() {
                window.addEventListener('avatar-updated', (e) => {
                    this.avatarUrl = e.detail?.avatar || null;
                });
            }
         }"
         @click.outside="open = false">
        {{-- Notifications --}}
        <livewire:notification-bell />

        @if(config('features.theme_toggle', false))
            <x-theme-toggle />
        @endif

        {{-- User dropdown --}}
        <div class="relative">
            <button
                @click="open = !open"
                class="flex items-center gap-2 pl-3 border-l border-neutral-200 transition-colors py-1 pr-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0"
                    style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});">
                    <img x-show="avatarUrl" :src="avatarUrl" alt="avatar" class="w-full h-full rounded-full object-cover">
                    <span x-show="!avatarUrl">{{ strtoupper(substr(Auth::user()->username ?? 'U', 0, 1)) }}</span>
                </div>
                <div class="hidden sm:block text-left">
                    <p class="text-sm font-medium text-neutral-900 leading-none">
                        {{ Auth::user()->fullname ?? Auth::user()->username }}
                    </p>
                    <p class="text-xs text-neutral-500 capitalize leading-none mt-0.5">
                        {{ \App\Enums\RoleEnum::label(Auth::user()->roles->first()?->name) }}
                    </p>
                </div>
                <svg class="w-4 h-4 text-neutral-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Dropdown menu --}}
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-65 bg-white rounded-xl border border-neutral-200 shadow-lg overflow-hidden z-50">

                {{-- Signed in as --}}
                <div class="px-4 py-3 bg-neutral-50 border-b border-neutral-100">
                    <p class="text-xs text-neutral-400">Signed in as</p>
                    <p class="text-sm font-semibold text-neutral-900 truncate mt-0.5">{{ Auth::user()->email }}</p>
                    <p class="text-xs text-neutral-500 mt-0.5">{{ \App\Enums\RoleEnum::label(Auth::user()->roles->first()?->name) }}</p>
                </div>
                {{-- Menu items --}}
                <div class="">
                    <a href="{{ route('profile') }}"
                       wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 hover:bg-neutral-50 transition-colors">
                        <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm text-neutral-700">Thông tin tài khoản</span>
                    </a>
                </div>
                {{-- Divider + Logout --}}
                <div class="border-t border-neutral-100">
                    <a href="{{ route('logout') }}"
                       class="flex items-center gap-3 px-4 py-2.5 hover:bg-red-50 hover:text-red-600 transition-colors group">
                        <svg class="w-4 h-4 text-neutral-400 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span class="text-sm text-neutral-700 group-hover:text-red-600 transition-colors">Thoát</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
