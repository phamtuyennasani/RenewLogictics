<header class="h-16 bg-white border-b border-neutral-200 flex items-center justify-between px-6 shrink-0 z-10">
    <div class="flex items-center gap-4">
        @if (isset($pageTitle))
            <h1 class="text-lg font-semibold text-neutral-900">{{ $pageTitle }}</h1>
        @endif
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
        <button class="relative p-2 rounded-lg text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
        </button>

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
            <div x-show="open"
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
                    <a href="#" class="flex items-center gap-3 px-4 py-2.5 hover:bg-neutral-50 transition-colors">
                        <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        <span class="text-sm text-neutral-700">Licenses</span>
                    </a>
                    <a href="{{ route('profile') }}"
                       wire:navigate
                       class="flex items-center gap-3 px-4 py-2.5 hover:bg-neutral-50 transition-colors">
                        <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm text-neutral-700">Account</span>
                    </a>
                </div>
                {{-- Divider + Logout --}}
                <div class="border-t border-neutral-100">
                    <a href="{{ route('logout') }}"
                       class="flex items-center gap-3 px-4 py-2.5 hover:bg-red-50 hover:text-red-600 transition-colors group">
                        <svg class="w-4 h-4 text-neutral-400 group-hover:text-red-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span class="text-sm text-neutral-700 group-hover:text-red-600 transition-colors">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>
