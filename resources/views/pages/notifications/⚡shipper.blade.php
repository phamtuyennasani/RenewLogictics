<?php

use App\Models\News;
use App\Models\NotificationRead;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.mobile')] #[Title('Thông báo')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $tab = 'unread';
    public bool $showViewModal = false;
    public ?string $viewTitle = null;
    public ?string $viewContent = null;
    public ?string $viewDate = null;
    public ?string $viewAuthor = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['shipper', 'ops', 'admin', 'manager', 'cs']), 403);
        abort_unless(\Gate::allows('notifications.view'), 403);
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['unread', 'all'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetPage();
    }

    protected function baseQuery()
    {
        $role = Auth::user()?->roles->first()?->name;

        return News::with(['user', 'reads' => fn ($query) => $query->where('user_id', Auth::id())])
            ->where('type', 'thongbao')
            ->where('status', 'active')
            ->whereJsonContains('options2->roles', $role);
    }

    #[Computed]
    public function unreadCount(): int
    {
        return (clone $this->baseQuery())
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', Auth::id()))
            ->count();
    }

    #[Computed]
    public function items()
    {
        return $this->baseQuery()
            ->when($this->tab === 'unread', function ($query) {
                $query->whereDoesntHave('reads', fn ($readQuery) => $readQuery->where('user_id', Auth::id()));
            })
            ->latest()
            ->paginate(10);
    }

    public function viewItem(int $id): void
    {
        $item = $this->baseQuery()->findOrFail($id);

        $this->viewTitle = $item->namevi;
        $this->viewContent = $item->contentvi;
        $this->viewDate = $item->created_at?->format('d/m/Y H:i');
        $this->viewAuthor = $item->user?->fullname ?? 'Hệ thống';
        $this->showViewModal = true;

        NotificationRead::firstOrCreate([
            'user_id' => Auth::id(),
            'news_id' => $id,
        ], [
            'read_at' => now(),
        ]);
    }

    public function markAllAsRead(): void
    {
        $unreadIds = (clone $this->baseQuery())
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', Auth::id()))
            ->pluck('id');

        $records = $unreadIds->map(fn ($id) => [
            'user_id' => Auth::id(),
            'news_id' => $id,
            'read_at' => now(),
        ])->all();

        if ($records !== []) {
            NotificationRead::insert($records);
        }

        $this->resetPage();
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->viewTitle = null;
        $this->viewContent = null;
        $this->viewDate = null;
        $this->viewAuthor = null;
    }
};
?>

@php
    $primaryHex = config('theme.primary.hex', '#3b82f6');
    $accentHex = config('theme.accent.hex', '#0ea5e9');
@endphp

@push('styles')
<style>
    .shipper-notification-article {
        color: #171717;
        font-size: 1rem;
        line-height: 1.75;
    }

    .shipper-notification-article :where(p) {
        margin-top: 0;
        margin-bottom: 0.85rem;
    }

    .shipper-notification-article :where(p:last-child) {
        margin-bottom: 0;
    }

    .shipper-notification-article :where(strong, b) {
        color: #111827;
        font-weight: 750;
    }

    .shipper-notification-article :where(ul, ol) {
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
        padding-left: 1.25rem;
    }

    .shipper-notification-article :where(li) {
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
    }

    .shipper-notification-sheet-body {
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        touch-action: pan-y;
    }
</style>
@endpush

<div class="min-h-screen bg-neutral-50 pb-24">
    <div class="border-b border-neutral-200 bg-white px-4 py-4">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">Trung tâm thông báo</p>
                <h1 class="mt-0.5 text-2xl font-bold text-neutral-950">Thông báo</h1>
            </div>
            @if($this->unreadCount > 0)
                <button type="button"
                        wire:click="markAllAsRead"
                        class="shrink-0 rounded-lg bg-primary-50 px-3 py-2 text-xs font-bold text-primary-700 active:bg-primary-100">
                    Đọc tất cả
                </button>
            @endif
        </div>
    </div>

    <div class="px-4 py-4">
        <div class="grid grid-cols-2 rounded-xl bg-neutral-200/70 p-1">
            <button type="button"
                    wire:click="setTab('unread')"
                    class="rounded-lg px-3 py-2.5 text-sm font-bold transition {{ $tab === 'unread' ? 'bg-white text-primary-700 shadow-sm' : 'text-neutral-600' }}">
                Chưa đọc
                @if($this->unreadCount > 0)
                    <span class="ml-1 rounded-full bg-primary-100 px-1.5 py-0.5 text-[10px] text-primary-700">{{ $this->unreadCount }}</span>
                @endif
            </button>
            <button type="button"
                    wire:click="setTab('all')"
                    class="rounded-lg px-3 py-2.5 text-sm font-bold transition {{ $tab === 'all' ? 'bg-white text-primary-700 shadow-sm' : 'text-neutral-600' }}">
                Tất cả
            </button>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($this->items as $item)
                @php $isUnread = $item->reads->isEmpty(); @endphp
                <button type="button"
                        wire:click="viewItem({{ $item->id }})"
                        class="w-full rounded-xl border bg-white p-4 text-left shadow-sm transition active:bg-neutral-50 {{ $isUnread ? 'border-primary-100 ring-1 ring-primary-50' : 'border-neutral-200' }}">
                    <div class="flex items-start gap-3">
                        <div class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-white shadow-sm"
                             style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-2">
                                <p class="min-w-0 flex-1 text-sm font-bold leading-snug text-neutral-950">{{ $item->namevi }}</p>
                                @if($isUnread)
                                    <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                                @endif
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs leading-5 text-neutral-500">
                                {{ \Illuminate\Support\Str::limit(strip_tags(html_entity_decode($item->contentvi)), 110) }}
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium text-neutral-400">
                                <span>{{ $item->created_at?->diffForHumans() }}</span>
                                <span class="h-1 w-1 rounded-full bg-neutral-300"></span>
                                <span>{{ $item->user?->fullname ?? 'Hệ thống' }}</span>
                            </div>
                        </div>
                    </div>
                </button>
            @empty
                <div class="rounded-xl border border-neutral-200 bg-white px-4 py-12 text-center shadow-sm">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-neutral-100 text-neutral-300">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-bold text-neutral-700">{{ $tab === 'unread' ? 'Không có thông báo chưa đọc' : 'Chưa có thông báo' }}</p>
                    <p class="mt-1 text-xs text-neutral-400">{{ $tab === 'unread' ? 'Các thông báo mới sẽ xuất hiện tại đây.' : 'Hiện chưa có thông báo dành cho bạn.' }}</p>
                </div>
            @endforelse
        </div>

        @if($this->items->hasPages())
            <div class="mt-4">
                {{ $this->items->links() }}
            </div>
        @endif
    </div>

    @if($showViewModal)
        <div class="fixed inset-0 z-[60] flex flex-col justify-end bg-neutral-950/45" wire:click="closeViewModal">
            <div class="flex max-h-[calc(100dvh-0.75rem)] min-h-0 w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl" wire:click.stop>
                <div class="shrink-0 border-b border-neutral-200 px-4 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-700">Thông báo</span>
                                <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-500">{{ $viewDate }}</span>
                            </div>
                            <h2 class="text-xl font-bold leading-tight text-neutral-950">{{ $viewTitle }}</h2>
                            <p class="mt-2 text-sm text-neutral-500">{{ $viewAuthor }}</p>
                        </div>
                        <button type="button"
                                wire:click="closeViewModal"
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-500 active:bg-neutral-200">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="shipper-notification-sheet-body min-h-0 flex-1 overflow-y-auto bg-neutral-50 px-4 py-4">
                    <article class="shipper-notification-article rounded-xl border border-neutral-200 bg-white p-4 shadow-sm">
                        {!! $viewContent !!}
                    </article>
                </div>
            </div>
        </div>
    @endif
</div>
