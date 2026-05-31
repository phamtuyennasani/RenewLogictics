<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\News;
use App\Enums\RoleEnum;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    public bool $showModal = false;
    public ?int $editingId = null;

    public string $namevi = '';
    public string $contentvi = '';
    public array $roles = [];
    public string $status = 'active';

    public ?string $pendingAction = null;
    public mixed $pendingId = null;

    public bool $showViewModal = false;
    public ?string $viewTitle = null;
    public ?string $viewContent = null;
    public ?string $viewDate = null;
    public ?string $viewAuthor = null;

    protected function isAdmin(): bool
    {
        return auth()->user()->roles->first()?->name === 'admin';
    }

    protected function isOwner(News $item): bool
    {
        return $item->id_user === auth()->id();
    }

    protected function canCreate(): bool
    {
        $role = auth()->user()->roles->first()?->name;
        return in_array($role, RoleEnum::canCreateNotification(), true);
    }

    public function openCreate()
    {
        if (!$this->canCreate()) {
            Flux::toast(duration: 2000, heading: 'Lỗi', text: 'Bạn không có quyền tạo thông báo.', variant: 'danger');
            return;
        }

        $this->reset(['editingId', 'namevi', 'contentvi', 'roles', 'status']);
        $this->status = 'active';
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        $item = News::findOrFail($id);

        if (!$this->isAdmin() && !$this->isOwner($item)) {
            Flux::toast(duration: 2000, heading: 'Lỗi', text: 'Bạn chỉ được chỉnh sửa thông báo do mình tạo.', variant: 'danger');
            return;
        }

        $this->editingId = $item->id;
        $this->namevi = $item->namevi ?? '';
        $this->contentvi = $item->contentvi ?? '';
        $this->roles = $item->options2['roles'] ?? [];
        $this->status = $item->status ?? 'active';
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate([
            'namevi' => 'required|string|max:500',
            'contentvi' => 'required|string|max:65000',
            'roles' => 'required|array|min:1',
            'status' => 'required|in:active,inactive',
        ], [
            'namevi.required' => 'Vui lòng nhập tiêu đề thông báo.',
            'contentvi.required' => 'Vui lòng nhập nội dung thông báo.',
            'roles.required' => 'Vui lòng chọn ít nhất 1 phân quyền.',
            'roles.min' => 'Vui lòng chọn ít nhất 1 phân quyền.',
        ]);

        if ($this->editingId) {
            $item = News::findOrFail($this->editingId);
            if (!$this->isAdmin() && !$this->isOwner($item)) {
                Flux::toast(duration: 2000, heading: 'Lỗi', text: 'Bạn chỉ được chỉnh sửa thông báo do mình tạo.', variant: 'danger');
                return;
            }
            $item->update([
                'namevi' => $this->namevi,
                'contentvi' => $this->contentvi,
                'status' => $this->status,
                'options2' => ['roles' => $this->roles],
            ]);
            $message = 'Cập nhật thông báo thành công!';
        } else {
            if (!$this->canCreate()) {
                Flux::toast(duration: 2000, heading: 'Lỗi', text: 'Bạn không có quyền tạo thông báo.', variant: 'danger');
                return;
            }
            News::create([
                'namevi' => $this->namevi,
                'contentvi' => $this->contentvi,
                'type' => 'thongbao',
                'status' => $this->status,
                'options2' => ['roles' => $this->roles],
                'id_user' => auth()->id(),
                'numb' => News::where('type', 'thongbao')->count() + 1,
            ]);
            $message = 'Tạo thông báo thành công!';
        }

        $this->showModal = false;
        $this->reset(['editingId', 'namevi', 'contentvi', 'roles', 'status']);

        Flux::toast(duration: 2000, heading: 'Thành công', text: $message, variant: 'success');
    }

    public function confirmDelete($id)
    {
        if (!$this->isAdmin()) {
            Flux::toast(duration: 2000, heading: 'Lỗi', text: 'Chỉ Admin mới được phép xóa thông báo.', variant: 'danger');
            return;
        }

        $this->pendingAction = 'delete';
        $this->pendingId = $id;
        $this->dispatch('open-confirm', [
            'title' => 'Xác nhận xóa',
            'message' => 'Bạn có chắc chắn muốn xóa thông báo này? Hành động này không thể hoàn tác.',
            'variant' => 'danger',
        ]);
    }

    #[\Livewire\Attributes\On('confirm-action')]
    public function handleConfirmAction()
    {
        if ($this->pendingAction === 'delete' && $this->pendingId) {
            if (!$this->isAdmin()) return;
            News::findOrFail($this->pendingId)->delete();
            Flux::toast(duration: 2000, heading: 'Thành công', text: 'Xóa thông báo thành công!', variant: 'success');
        }
        $this->pendingAction = null;
        $this->pendingId = null;
    }

    public function toggleStatus($id)
    {
        $item = News::findOrFail($id);
        if (!$this->isAdmin() && !$this->isOwner($item)) {
            Flux::toast(duration: 2000, heading: 'Lỗi', text: 'Bạn chỉ được thay đổi trạng thái thông báo do mình tạo.', variant: 'danger');
            return;
        }
        $item->update(['status' => $item->status === 'active' ? 'inactive' : 'active']);
    }

    public function viewItem($id)
    {
        $item = News::with('user')->findOrFail($id);
        $this->viewTitle = $item->namevi;
        $this->viewContent = $item->contentvi;
        $this->viewDate = $item->created_at->format('d/m/Y H:i');
        $this->viewAuthor = $item->user?->fullname ?? 'Hệ thống';
        $this->showViewModal = true;

        \App\Models\NotificationRead::firstOrCreate([
            'user_id' => auth()->id(),
            'news_id' => $id,
        ]);
    }

    public function closeViewModal()
    {
        $this->showViewModal = false;
        $this->viewTitle = null;
        $this->viewContent = null;
    }

    #[\Livewire\Attributes\Computed]
    public function items()
    {
        return News::with(['user', 'user.roles'])
            ->where('type', 'thongbao')
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
$primaryHex = config('theme.primary.hex', '#3b82f6');
$accentHex  = config('theme.accent.hex', '#0ea5e9');
$gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
$allRoles = collect(\App\Enums\RoleEnum::cases())->filter(fn ($r) => $r->value !== 'admin');
$currentRole = auth()->user()->roles->first()?->name;
$canCreateNotification = in_array($currentRole, \App\Enums\RoleEnum::canCreateNotification(), true);
@endphp

@push('styles')
<style>
    .notification-article {
        color: #171717;
        font-size: 1rem;
        line-height: 1.8;
    }

    .notification-article :where(p) {
        margin-top: 0;
        margin-bottom: 0.85rem;
    }

    .notification-article :where(p:last-child) {
        margin-bottom: 0;
    }

    .notification-article :where(strong, b) {
        color: #111827;
        font-weight: 750;
    }

    .notification-article :where(ul, ol) {
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
        padding-left: 1.25rem;
    }

    .notification-article :where(li) {
        margin-top: 0.25rem;
        margin-bottom: 0.25rem;
    }
</style>
@endpush

<div class="space-y-6">

    {{-- PAGE HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm text-neutral-500">Cấu hình</p>
            <h1 class="text-2xl font-bold text-neutral-900">Thông báo hệ thống</h1>
        </div>
        @if ($canCreateNotification)
        <button
            wire:click="openCreate"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white
                   rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5"
            style="{{ $gradientStyle }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Thêm thông báo
        </button>
        @endif
    </div>

    {{-- TABLE --}}
    <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden shadow-xs">

        <div class="px-5 py-4 border-b border-neutral-100">
            <span class="text-sm text-neutral-500">
                @if ($this->items->total() > 0)
                    Hiển thị <span class="font-semibold text-neutral-700">{{ $this->items->firstItem() }}–{{ $this->items->lastItem() }}</span>
                    của <span class="font-semibold text-neutral-700">{{ $this->items->total() }}</span> thông báo
                @else
                    Chưa có thông báo nào
                @endif
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-neutral-50 border-b border-neutral-100">
                        <th class="px-5 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Tiêu đề</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-48">Phân quyền</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-36">Người đăng</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-28">Trạng thái</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-32">Ngày tạo</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center w-28">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->items as $item)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="text-sm font-medium text-neutral-900 line-clamp-1">{{ $item->namevi }}</p>
                                <p class="text-xs text-neutral-500 line-clamp-1 mt-0.5">{{ mb_substr(strip_tags(html_entity_decode($item->contentvi)), 0, 60) }}{{ mb_strlen(strip_tags(html_entity_decode($item->contentvi))) > 60 ? '...' : '' }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @foreach (($item->options2['roles'] ?? []) as $role)
                                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full text-white"
                                              style="background-color: {{ \App\Enums\RoleEnum::tryFrom($role)?->color() ?? '#6b7280' }}">
                                            {{ \App\Enums\RoleEnum::label($role) }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                @if ($item->user)
                                    <div class="flex items-center gap-2">
                                        <flux:avatar circle class="w-6 h-6 flex items-center justify-center text-white text-xs font-semibold shrink-0" style="{{ $gradientStyle }}" size="xs" name="{{ strtoupper(substr($item->user->fullname ?? 'U', 0, 1)) }}" initials:single />
                                        <span class="text-sm text-neutral-700 truncate">{{ $item->user->fullname }}</span>
                                    </div>
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <button wire:click="toggleStatus({{ $item->id }})" class="cursor-pointer">
                                    @if ($item->status === 'active')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-green-50 text-green-700">
                                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                                            Hiển thị
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-full bg-neutral-100 text-neutral-500">
                                            <span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>
                                            Ẩn
                                        </span>
                                    @endif
                                </button>
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-sm text-neutral-600">{{ $item->created_at->format('d/m/Y') }}</p>
                                <p class="text-xs text-neutral-400">{{ $item->created_at->format('H:i') }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        wire:click="viewItem({{ $item->id }})"
                                        class="p-2 rounded-lg text-neutral-400 hover:text-primary-600 hover:bg-primary-50 transition-all"
                                        title="Xem">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </button>
                                    @if (auth()->user()->roles->first()?->name === 'admin' || $item->id_user === auth()->id())
                                        <button
                                            wire:click="openEdit({{ $item->id }})"
                                            class="p-2 rounded-lg text-neutral-400 hover:text-primary-600 hover:bg-primary-50 transition-all"
                                            title="Chỉnh sửa">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                    @endif
                                    @if (auth()->user()->roles->first()?->name === 'admin')
                                        <button
                                            wire:click="confirmDelete({{ $item->id }})"
                                            class="p-2 rounded-lg text-neutral-400 hover:text-red-600 hover:bg-red-50 transition-all cursor-pointer"
                                            title="Xóa">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-14 h-14 rounded-2xl bg-neutral-100 flex items-center justify-center">
                                        <svg class="w-7 h-7 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-600">Chưa có thông báo nào</p>
                                        <p class="text-xs text-neutral-400 mt-0.5">{{ $canCreateNotification ? 'Hãy tạo thông báo đầu tiên' : 'Hiện chưa có thông báo nào dành cho bạn' }}</p>
                                    </div>
                                    @if ($canCreateNotification)
                                    <button wire:click="openCreate"
                                       class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium
                                              text-white rounded-xl transition-all shadow-sm hover:shadow-md"
                                       style="{{ $gradientStyle }}">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Thêm thông báo
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $this->items->links() }}

    {{-- MODAL CREATE/EDIT --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             x-data
             x-trap.noscroll="true"
             x-init="$nextTick(() => { window.initThongbaoCKEditor && window.initThongbaoCKEditor(); })">
            <div class="fixed inset-0 bg-neutral-950/45 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl border border-white/70 bg-white shadow-2xl">

                {{-- Modal Header --}}
                <div class="border-b border-neutral-200 bg-gradient-to-b from-neutral-50 to-white px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm" style="{{ $gradientStyle }}">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                    <h3 class="text-xl font-bold text-neutral-950">
                        {{ $editingId ? 'Chỉnh sửa thông báo' : 'Thêm thông báo mới' }}
                    </h3>
                    <p class="mt-1 text-sm text-neutral-500">Soạn nội dung và chọn nhóm người dùng được phép nhìn thấy.</p>
                            </div>
                        </div>
                    <button wire:click="$set('showModal', false)"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto bg-neutral-50/70 p-6">
                    <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                        <div class="mb-4 border-b border-neutral-100 pb-4">
                            <h4 class="text-sm font-bold uppercase tracking-wide text-neutral-900">Nội dung thông báo</h4>
                            <p class="mt-1 text-sm text-neutral-500">Tiêu đề ngắn gọn, nội dung rõ ràng để người dùng dễ nắm thông tin.</p>
                        </div>
                        <div class="space-y-5">
                            <flux:field>
                                <flux:label badge="Bắt buộc">Tiêu đề thông báo</flux:label>
                                <flux:input wire:model="namevi" placeholder="VD: Thông báo nghỉ lễ 30/4 - 1/5" />
                                @error('namevi') <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                                <flux:field>
                                    <flux:label badge="Bắt buộc">Nội dung</flux:label>
                                    <div wire:ignore class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs [&_.cke]:!border-0 [&_.cke_top]:!border-x-0 [&_.cke_top]:!border-t-0 [&_.cke_bottom]:!border-x-0 [&_.cke_bottom]:!border-b-0">
                                        <textarea id="ckeditor-thongbao" class="w-full">{!! $contentvi !!}</textarea>
                                    </div>
                                </flux:field>
                            </div>
                        </div>
                    </section>
                    <section class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-xs">
                        <div class="mb-4 border-b border-neutral-100 pb-4">
                            <h4 class="text-sm font-bold uppercase tracking-wide text-neutral-900">Đối tượng và xuất bản</h4>
                            <p class="mt-1 text-sm text-neutral-500">Chọn phân quyền hiển thị và trạng thái của thông báo.</p>
                        </div>
                        <div class="space-y-6">

                    <flux:checkbox.group wire:model="roles">
                        <flux:label badge="Bắt buộc">Hiển thị cho phân quyền</flux:label>
                        <div class="grid grid-cols-1 gap-2 mt-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($allRoles as $role)
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-3 transition-all
                                             {{ in_array($role->value, $roles) ? 'border-primary-300 bg-primary-50 shadow-xs' : 'border-neutral-200 bg-white hover:border-neutral-300 hover:bg-neutral-50' }}">
                                    <span class="flex min-w-0 items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white" style="background-color: {{ $role->color() }}">
                                            {{ strtoupper(substr(RoleEnum::label($role->value), 0, 1)) }}
                                        </span>
                                        <span class="min-w-0 text-sm font-semibold leading-5 text-neutral-800">{{ RoleEnum::label($role->value) }}</span>
                                    </span>
                                    <flux:checkbox value="{{ $role->value }}" />
                                </label>
                            @endforeach
                        </div>
                        @error('roles') <p class="text-sm text-red-500 mt-1">{{ $message }}</p> @enderror
                    </flux:checkbox.group>

                    <div class="border-t border-neutral-100 pt-5">
                        <flux:field variant="inline">
                            <flux:checkbox :checked="$status === 'active'" wire:click="$set('status', '{{ $status === 'active' ? 'inactive' : 'active' }}')" />
                            <flux:label>Hiển thị thông báo</flux:label>
                            <flux:error name="status" />
                        </flux:field>
                        <p class="mt-2 text-sm text-neutral-500">
                            {{ $status === 'active' ? 'Thông báo sẽ hiển thị cho các phân quyền đã chọn sau khi lưu.' : 'Thông báo được lưu nhưng chưa hiển thị cho người dùng.' }}
                        </p>
                    </div>
                        </div>
                    </section>

                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-3 border-t border-neutral-200 bg-white px-6 py-4">
                    <button wire:click="$set('showModal', false)"
                            class="px-4 py-2.5 text-sm font-medium text-neutral-700 bg-neutral-100 rounded-xl
                                   hover:bg-neutral-200 transition-colors">
                        Hủy
                    </button>
                    <button wire:click="save"
                            wire:loading.attr="disabled"
                            class="px-6 py-2.5 text-sm font-medium text-white rounded-xl
                                   transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5
                                   flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed"
                            style="{{ $gradientStyle }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        {{ $editingId ? 'Cập nhật' : 'Tạo thông báo' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- VIEW MODAL --}}
    @if ($showViewModal)
        <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-neutral-950/45 backdrop-blur-sm" wire:click="closeViewModal"></div>
            <div class="relative flex max-h-[88vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-white/70 bg-white shadow-2xl">
                <div class="border-b border-neutral-200 bg-gradient-to-b from-neutral-50 to-white px-7 py-6">
                    <div class="flex items-start justify-between gap-5">
                        <div class="flex min-w-0 items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-sm" style="{{ $gradientStyle }}">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
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
                        <article class="notification-article prose prose-neutral max-w-none">
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

@push('scripts')
<script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
<script>
    window.initThongbaoCKEditor = function () {
        const editorId = 'ckeditor-thongbao';
        const el = document.getElementById(editorId);
        if (!el || typeof CKEDITOR === 'undefined') return;

        if (CKEDITOR.instances[editorId]) {
            CKEDITOR.instances[editorId].destroy(true);
        }

        const editor = CKEDITOR.replace(editorId, {
            height: 250,
            language: 'vi',
            versionCheck: false,
            toolbar: [
                { name: 'document', items: ['Source'] },
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
                { name: 'styles', items: ['Format', 'FontSize'] },
                { name: 'colors', items: ['TextColor', 'BGColor'] },
                { name: 'tools', items: ['Maximize'] }
            ],
        });

        if (editor) {
            editor.on('change', function () {
                const component = Livewire.find(el.closest('[wire\\:id]')?.getAttribute('wire:id'));
                if (component) {
                    component.set('contentvi', editor.getData());
                }
            });
        }
    };
</script>
@endpush
