<?php

use Livewire\Component;
use App\Models\News;
use App\Models\NotificationRead;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

new class extends Component {
    public bool $showViewModal = false;
    public ?string $viewTitle = null;
    public ?string $viewContent = null;
    public ?string $viewDate = null;
    public ?string $viewAuthor = null;

    #[Computed]
    public function unreadCount(): int
    {
        return $this->getUnreadQuery()->count();
    }

    #[Computed]
    public function notifications()
    {
        $role = Auth::user()->roles->first()?->name;
        $items = $this->getUnreadQuery()->with('user')->latest()->get();

        if ($role === 'admin') {
            return $items->groupBy(function ($item) {
                $roles = $item->options2['roles'] ?? [];
                return implode(', ', array_map(fn ($r) => \App\Enums\RoleEnum::label($r), $roles));
            });
        }

        return $items;
    }

    public function viewNotification($newsId)
    {
        $item = News::with('user')->findOrFail($newsId);

        $this->viewTitle = $item->namevi;
        $this->viewContent = $item->contentvi;
        $this->viewDate = $item->created_at->format('d/m/Y H:i');
        $this->viewAuthor = $item->user?->fullname ?? 'Hệ thống';
        $this->showViewModal = true;

        NotificationRead::firstOrCreate([
            'user_id' => Auth::id(),
            'news_id' => $newsId,
        ]);
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewTitle = null;
        $this->viewContent = null;
    }

    public function markAllAsRead()
    {
        $unreadIds = $this->getUnreadQuery()->pluck('id');

        $records = $unreadIds->map(fn ($id) => [
            'user_id' => Auth::id(),
            'news_id' => $id,
            'read_at' => now(),
        ])->toArray();

        if (!empty($records)) {
            NotificationRead::insert($records);
        }
    }

    protected function getUnreadQuery()
    {
        $query = News::where('type', 'thongbao')
            ->where('status', 'active')
            ->where('id_user', '!=', Auth::id())
            ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', Auth::id()));

        $role = Auth::user()->roles->first()?->name;

        if ($role !== 'admin') {
            $query->whereJsonContains('options2->roles', $role);
        }

        return $query;
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
$isAdmin = auth()->user()->roles->first()?->name === 'admin';
$primaryHex = config('theme.primary.hex', '#3b82f6');
$accentHex = config('theme.accent.hex', '#0ea5e9');
@endphp

@push('styles')
<style>
    .notification-bell-article {
        color: #171717;
        font-size: 1rem;
        line-height: 1.8;
    }

    .notification-bell-article :where(p) {
        margin-top: 0;
        margin-bottom: 0.85rem;
    }

    .notification-bell-article :where(p:last-child) {
        margin-bottom: 0;
    }

    .notification-bell-article :where(strong, b) {
        color: #111827;
        font-weight: 750;
    }

    .notification-bell-article :where(ul, ol) {
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
        padding-left: 1.25rem;
    }

    .notification-bell-article :where(li) {
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
    }
</style>
@endpush

<div x-data="{ open: false }" @click.outside="open = false" class="relative" wire:poll.60s>
    {{-- Bell Button --}}
    <button @click="open = !open" class="relative flex h-10 w-10 items-center justify-center rounded-full text-neutral-500 transition-colors hover:bg-primary-50 hover:text-primary-700">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center px-1 text-[10px] font-bold text-white rounded-full"
                  style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                {{ $this->unreadCount > 99 ? '99+' : $this->unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
         class="fixed left-4 right-4 top-[4.5rem] max-h-[calc(100vh-5.5rem)] bg-white rounded-2xl border border-neutral-200 shadow-xl overflow-hidden z-50 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-2 sm:w-96 sm:max-w-96 sm:max-h-none sm:rounded-xl">

        {{-- Header --}}
        <div class="px-4 py-3 border-b border-neutral-100 flex items-center justify-between gap-3">
            <h4 class="min-w-0 text-sm font-semibold text-neutral-900">
                Thông báo
                @if ($this->unreadCount > 0)
                    <span class="text-xs font-normal text-neutral-500">({{ $this->unreadCount }} chưa đọc)</span>
                @endif
            </h4>
            @if ($this->unreadCount > 0)
                <button wire:click="markAllAsRead" @click="open = false"
                        class="shrink-0 whitespace-nowrap text-xs text-primary-600 hover:text-primary-700 font-medium transition-colors">
                    Đọc tất cả
                </button>
            @endif
        </div>

        {{-- List --}}
        <div class="max-h-[calc(100vh-12rem)] overflow-y-auto sm:max-h-96">
            @if ($isAdmin)
                @forelse ($this->notifications as $groupName => $items)
                    <div class="border-b border-neutral-100 last:border-b-0">
                        <div class="px-4 py-2 bg-neutral-50">
                            <span class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">{{ $groupName }}</span>
                        </div>
                        @foreach ($items as $notification)
                            <div wire:click="viewNotification({{ $notification->id }})"
                                 class="px-4 py-3 hover:bg-primary-50/50 transition-colors cursor-pointer flex gap-3 border-t border-neutral-50">
                                <div class="shrink-0 mt-1">
                                    <span class="block w-2 h-2 rounded-full bg-primary-500"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-neutral-900 line-clamp-1">{{ $notification->namevi }}</p>
                                    <p class="text-xs text-neutral-500 line-clamp-1 mt-0.5">{{ mb_substr(strip_tags(html_entity_decode($notification->contentvi)), 0, 60) }}{{ mb_strlen(strip_tags(html_entity_decode($notification->contentvi))) > 60 ? '...' : '' }}</p>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs text-neutral-400">{{ $notification->created_at->diffForHumans() }}</span>
                                        @if ($notification->user)
                                            <span class="text-xs text-neutral-400">• {{ $notification->user->fullname }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <svg class="w-8 h-8 mx-auto text-neutral-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <p class="text-sm text-neutral-500">Không có thông báo mới</p>
                    </div>
                @endforelse
            @else
                @forelse ($this->notifications as $notification)
                    <div wire:click="viewNotification({{ $notification->id }})"
                         class="px-4 py-3 hover:bg-primary-50/50 transition-colors cursor-pointer flex gap-3 border-b border-neutral-50 last:border-b-0">
                        <div class="shrink-0 mt-1">
                            <span class="block w-2 h-2 rounded-full bg-primary-500"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-neutral-900 line-clamp-1">{{ $notification->namevi }}</p>
                            <p class="text-xs text-neutral-500 line-clamp-1 mt-0.5">{{ mb_substr(strip_tags(html_entity_decode($notification->contentvi)), 0, 60) }}{{ mb_strlen(strip_tags(html_entity_decode($notification->contentvi))) > 60 ? '...' : '' }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-neutral-400">{{ $notification->created_at->diffForHumans() }}</span>
                                @if ($notification->user)
                                    <span class="text-xs text-neutral-400">• {{ $notification->user->fullname }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-8 text-center">
                        <svg class="w-8 h-8 mx-auto text-neutral-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <p class="text-sm text-neutral-500">Không có thông báo mới</p>
                    </div>
                @endforelse
            @endif
        </div>

        {{-- Footer --}}
        @if ($this->unreadCount > 0)
            <div class="px-4 py-2.5 border-t border-neutral-100 bg-neutral-50">
                <a href="{{ route('settings.thongbao') }}" wire:navigate @click="open = false"
                   class="text-xs text-primary-600 hover:text-primary-700 font-medium transition-colors">
                    Xem tất cả thông báo
                </a>
            </div>
        @endif
    </div>

    {{-- View Modal --}}
    @if ($showViewModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-neutral-950/45 backdrop-blur-sm" wire:click="closeViewModal"></div>
            <div class="relative flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-white/70 bg-white shadow-2xl">
                <div class="border-b border-neutral-200 bg-gradient-to-b from-neutral-50 to-white px-7 py-6">
                    <div class="flex items-start justify-between gap-5">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm"
                                 style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex rounded-full border border-primary-100 bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700">Thông báo hệ thống</span>
                                    <span class="inline-flex rounded-full border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-neutral-500">{{ $viewDate }}</span>
                                </div>
                                <h3 class="text-2xl font-bold leading-tight text-neutral-950">{{ $viewTitle }}</h3>
                                <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-neutral-500">
                                    <span class="font-medium text-neutral-700">{{ $viewAuthor }}</span>
                                    <span class="h-1 w-1 rounded-full bg-neutral-300"></span>
                                    <span>Nội dung thông báo nội bộ</span>
                                </div>
                            </div>
                        </div>
                    <button wire:click="closeViewModal"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto bg-neutral-50/70 px-7 py-6">
                    <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-xs">
                        <article class="notification-bell-article prose prose-neutral max-w-none">
                            {!! $viewContent !!}
                        </article>
                    </div>
                </div>
                <div class="flex items-center justify-end border-t border-neutral-200 bg-white px-7 py-4">
                    <button wire:click="closeViewModal"
                            class="inline-flex items-center justify-center rounded-xl bg-neutral-100 px-4 py-2.5 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-200">
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
