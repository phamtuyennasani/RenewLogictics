<aside
    x-data="SidebarData()" @@livewire:navigated.window="currentPath = window.location.pathname"
    class="w-64 bg-white border-r border-neutral-200 flex flex-col h-full overflow-hidden">
    <div class="px-4 py-3 border-b border-neutral-100">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 group">
            {{-- Logo Icon --}}
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 transition-transform group-hover:scale-105"
                 style="background: linear-gradient(135deg,
                        {{ config('theme.primary.hex', '#3b82f6') }},
                        {{ config('theme.accent.hex', '#0ea5e9') }});">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            {{-- Brand name --}}
            <div class="min-w-0">
                <h2 class="text-base font-bold text-neutral-900 truncate">
                    {{ config('system.name', 'VAU TRANS') }}
                </h2>
                <p class="text-xs text-neutral-500 truncate">
                    {{ config('system.slogan', 'Quản lý vận chuyển') }}
                </p>
            </div>
        </a>
    </div>
    {{-- =============================================
         SCROLLABLE MENU
         ============================================= --}}
    <div class="flex-1 overflow-y-auto px-2 py-4 scrollbar-thin" id="sidebar-scrollbar" wire:navigate:scroll
         x-data="{ openItem: @js($activeMenuKey ?? null) }">
        @foreach ($menuItems as $groupKey => $group)
            <div class="px-3 mb-2">
                <span class="text-xs font-semibold text-neutral-400 uppercase tracking-wider">
                    {{ $group['label'] ?? $groupKey }}
                </span>
            </div>
            <div class="space-y-0.5 mb-6">
                @foreach (($group['items'] ?? []) as $item)
                    @if (!empty($item['children']))
                        @php
                            // Tính $groupIndex giống PHP: sequential index trong $menuItems sau filter
                            $groupIndex = 0;
                            foreach ($menuItems as $gk => $g) {
                                if ($gk === $groupKey) break;
                                $groupIndex++;
                            }
                            $itemKey = $groupKey . '-' . $groupIndex . '-' . $loop->index;
                        @endphp
                        <div>
                            <a href="javascript:void(0)"
                               class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 group " :class="currentPath.startsWith('/{{ $item['startsWith'] }}') ? 'bg-primary-50 text-primary-600 font-medium' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900'"
                               data-menu-toggle
                               @click.prevent="openItem = openItem === '{{ $itemKey }}' ? null : '{{ $itemKey }}'"
                            >
                                <span class="w-5 h-5 flex items-center justify-center shrink-0">
                                    <livewire:sidebar.icon :type="$item['icon']" :key="$item['icon']" />
                                </span>
                                <span class="flex-1 text-left font-medium truncate capitalize">{{ $item['label'] }}</span>
                                <span class="shrink-0 transition-transform duration-200"
                                      :class="{ 'rotate-90': openItem === '{{ $itemKey }}' }">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </span>
                            </a>
                            <div class="menu-children ml-5 mt-1 space-y-0.5 pb-1"
                                 x-cloak
                                 :data-key="openItem === '{{ $itemKey }}' ? openItem : ''"
                                 :style="openItem === '{{ $itemKey }}' ? '' : 'display:none'"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-1">
                                @foreach ($item['children'] as $child)
                                    <a href="{{ route($child['route'], ($child['route_params'] ?? [])) }}" wire:navigate
                                       class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 group text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 data-current:bg-primary-50 data-current:text-primary-700 data-current:[&>span]:bg-primary-500">
                                        <span class="w-1.5 h-1.5 rounded-full shrink-0 bg-neutral-300"></span>
                                        {{ $child['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ route($item['route'], ($item['route_params'] ?? [])) }}" wire:navigate
                           class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-200 group text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900 data-current:bg-primary-50 data-current:text-primary-700"
                           @click="openItem = null">
                            <span class="w-5 h-5 flex items-center justify-center shrink-0">
                                <livewire:sidebar.icon :type="$item['icon']" :key="$item['icon']" />
                            </span>
                            <span class="font-medium truncate capitalize">{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- =============================================
         FOOTER: User menu
         ============================================= --}}
    <div class="px-3 py-3 border-t border-neutral-100 bg-neutral-50">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs text-neutral-600 mb-0 font-bold">
                    {{ config('system.name', 'VAU TRANS') }}
                </p>
                <span class="text-xs text-neutral-400">
                    Version {{ config('system.version', '1.0.0') }}
                </span>
            </div>
            <div class="flex items-center gap-1">
                <a href="{{ route('logout') }}">
                    <button type="button"
                            class="p-1.5 rounded-lg text-neutral-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                            title="Đăng xuất">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </a>
            </div>
        </div>
    </div>
</aside>