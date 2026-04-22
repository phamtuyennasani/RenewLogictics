<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\Member;
use Flux\Flux;

new class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public ?string $keyword = '';

    public array $xCheck = [];
    public ?string $pendingAction = null;
    public mixed $pendingId = null;

    public function updatingKeyword()
    {
        $this->resetPage();
    }

    public function updatingPage()
    {
        $this->xCheck = [];
        $this->dispatch('sync-check');
    }

    #[Computed]
    public function items()
    {
        return Member::with(['sale:id,fullname,code,username', 'ctv:id,fullname,username,code', 'sender:id,company_name,code', 'country:id,name'])
            ->receiver()
            ->when($this->keyword, fn($q) => $q->where(function ($sub) {
                $sub->where('company_name', 'like', '%' . $this->keyword . '%')
                    ->orWhere('fullname', 'like', '%' . $this->keyword . '%')
                    ->orWhere('phone', 'like', '%' . $this->keyword . '%')
                    ->orWhere('code', 'like', '%' . $this->keyword . '%');
            }))
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function currentPageIds()
    {
        return $this->items->pluck('id')->map(fn($id) => (string) $id)->toArray();
    }

    public function deleteSelected()
    {
        if (empty($this->xCheck)) {
            Flux::toast(duration: 2000, heading: 'Cảnh báo', text: 'Vui lòng chọn Receiver cần xóa!', variant: 'warning');
            return;
        }

        $count = count($this->xCheck);
        $this->pendingAction = 'deleteSelected';
        $this->pendingId = null;
        $this->dispatch('open-confirm', [
            'title'   => 'Xác nhận xóa nhiều Receiver',
            'message' => "Bạn có chắc chắn muốn xóa {$count} Receiver đã chọn? Hành động này không thể hoàn tác.",
            'variant' => 'danger',
        ]);
    }

    public function deleteItem($id)
    {
        $this->pendingAction = 'deleteItem';
        $this->pendingId = $id;
        $this->dispatch('open-confirm', [
            'title'   => 'Xác nhận xóa',
            'message' => 'Bạn có chắc chắn muốn xóa Receiver này? Hành động này không thể hoàn tác.',
            'variant' => 'danger',
        ]);
    }

    #[On('confirm-action')]
    public function handleConfirmAction()
    {
        match ($this->pendingAction) {
            'deleteItem'     => Member::whereKey($this->pendingId)->receiver()->firstOrFail()->delete(),
            'deleteSelected' => Member::whereIn('id', $this->xCheck)->receiver()->delete(),
            default          => null,
        };

        if ($this->pendingAction === 'deleteSelected') {
            $this->xCheck = [];
        }

        $this->pendingAction = null;
        $this->pendingId = null;

        Flux::toast(duration: 2000, heading: 'Thành công', text: 'Xóa Receiver thành công!', variant: 'success');
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
@endphp

<div x-data="tableCheck" class="space-y-4" style="--gradient: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm text-neutral-500 capitalize">Receiver / Người nhận</p>
            <h1 class="text-2xl font-bold text-neutral-900 capitalize mt-0.5">Danh sách Receiver</h1>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="keyword" placeholder="Tìm kiếm..."
                    class="pl-9 pr-4 py-2 w-64 text-sm border border-neutral-300 rounded-xl bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder:text-neutral-400 transition-all">
                @if ($keyword)
                    <button wire:click="$set('keyword', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-neutral-400 hover:text-neutral-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            <a href="{{ route('receiver.add') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5"
               style="{{ $gradientStyle }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Thêm mới
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden shadow-xs">
        <div class="px-5 py-4 flex items-center justify-between border-b border-neutral-100">
            <span class="text-sm text-neutral-500 leading-8">
                @if ($this->items->total() > 0)
                    Hiển thị <span class="font-semibold text-neutral-700">{{ $this->items->firstItem() }}–{{ $this->items->lastItem() }}</span>
                    của <span class="font-semibold text-neutral-700">{{ $this->items->total() }}</span> Receiver
                @else
                    Không có Receiver nào
                @endif
            </span>

            <div class="flex items-center gap-2" x-cloak x-show="localCheck.length > 0" x-transition>
                <button wire:click="deleteSelected()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Xóa <span x-text="'(' + localCheck.length + ')'"/>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-neutral-50 border-b border-neutral-100">
                        <th class="w-12 px-5 py-3.5 text-center">
                            <label class="relative flex items-center justify-center cursor-pointer select-none mx-auto w-fit">
                                <input type="checkbox" :checked="isAllSelected" @click="toggleAll()" class="peer sr-only">
                                <div class="w-4.5 h-4.5 rounded-md border flex items-center justify-center bg-white transition-all duration-200 cursor-pointer peer-hover:border-primary-400"
                                     :class="isAllSelected || isIndeterminate ? 'border-0 shadow-sm' : 'border-neutral-300'"
                                     :style="(isAllSelected || isIndeterminate) ? 'background: var(--gradient);' : ''">
                                    <svg x-show="isAllSelected" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.5l4.5 4.5 8.5-8.5"/></svg>
                                    <svg x-show="isIndeterminate" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/></svg>
                                </div>
                            </label>
                        </th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Mã</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Công ty</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Họ và tên</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Email</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">SĐT</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Sale</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">CTV</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Sender</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Quốc gia</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center">Trạng thái</th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center w-28">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->items as $v)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="px-5 py-3.5 text-center">
                                <label class="relative flex items-center justify-center cursor-pointer select-none mx-auto w-fit">
                                    <input type="checkbox" :checked="isChecked({{ $v->id }})" @click="toggle({{ $v->id }})" class="peer sr-only">
                                    <div class="w-4.5 h-4.5 rounded-md border flex items-center justify-center bg-white transition-all duration-200 cursor-pointer peer-hover:border-primary-400"
                                         :class="isChecked({{ $v->id }}) ? 'border-0 shadow-sm' : 'border-neutral-300'"
                                         :style="isChecked({{ $v->id }}) ? 'background: var(--gradient);' : ''">
                                        <svg x-show="isChecked({{ $v->id }})" class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.5l4.5 4.5 8.5-8.5"/></svg>
                                    </div>
                                </label>
                            </td>
                            <td class="px-4 py-3.5"><span class="text-sm font-mono text-neutral-600 bg-neutral-100 px-2 py-0.5 rounded">{{ $v->code ?? '—' }}</span></td>
                            <td class="px-4 py-3.5 text-sm font-medium text-neutral-700">{{ $v->company_name ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-sm font-medium text-neutral-700">{{ $v->fullname ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-sm text-neutral-600">{{ $v->email ?? '—' }}</td>
                            <td class="px-4 py-3.5 text-sm text-neutral-600">{{ $v->phone ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                @if($v->sale)
                                    <p class="text-sm text-neutral-700">{{ $v->sale->fullname ?: $v->sale->username }}</p>
                                    <p class="text-xs text-neutral-400">{{ $v->sale->code ?: '—' }}</p>
                                @else
                                    <span class="text-sm text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @if($v->ctv)
                                    <p class="text-sm text-neutral-700">{{ $v->ctv->fullname ?: $v->ctv->username }}</p>
                                    <p class="text-xs text-neutral-400">{{ $v->ctv->code ?: '—' }}</p>
                                @else
                                    <span class="text-sm text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @if($v->sender)
                                    <p class="text-sm text-neutral-700">{{ $v->sender->company_name }}</p>
                                    <p class="text-xs text-neutral-400">{{ $v->sender->code ?: '—' }}</p>
                                @else
                                    <span class="text-sm text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @if($v->country)
                                    <p class="text-sm text-neutral-700">{{ $v->country->name }}</p>
                                    @if($v->state || $v->cities)
                                        <p class="text-xs text-neutral-400">{{ $v->cities }}, {{ $v->state }}</p>
                                    @endif
                                @else
                                    <span class="text-sm text-neutral-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-center">
                                @if ($v->status == 1)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Kích hoạt</span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-neutral-100 text-neutral-500"><span class="w-1.5 h-1.5 rounded-full bg-neutral-400"></span>Chưa kích hoạt</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('receiver.edit', ['uuid' => $v->uuid]) }}" wire:navigate class="p-2 rounded-lg text-neutral-400 hover:text-primary-600 hover:bg-primary-50 transition-all" title="Chỉnh sửa">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="deleteItem({{ $v->id }})" class="p-2 rounded-lg text-neutral-400 hover:text-red-600 hover:bg-red-50 transition-all cursor-pointer" title="Xóa">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="20" class="px-5 py-16 text-center">
                                <p class="text-sm font-medium text-neutral-600">Chưa có Receiver nào</p>
                                <a href="{{ route('receiver.add') }}" wire:navigate class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white rounded-xl transition-all shadow-sm hover:shadow-md" style="{{ $gradientStyle }}">Thêm mới</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $this->items->links() }}
</div>

@script
<script>
    Alpine.data('tableCheck', () => ({
        localCheck: @json($this->xCheck).map(id => String(id)),
        init() {
            window.addEventListener('sync-check', () => {
                this.localCheck = @json($this->xCheck).map(id => String(id));
            });
        },
        getPageIds() { return @json($this->currentPageIds); },
        get isAllSelected() {
            const pageIds = this.getPageIds();
            return pageIds.length > 0 && pageIds.every(id => this.localCheck.includes(id));
        },
        get isIndeterminate() {
            const pageIds = this.getPageIds();
            return pageIds.filter(id => this.localCheck.includes(id)).length > 0
                && pageIds.filter(id => this.localCheck.includes(id)).length < pageIds.length;
        },
        isChecked(id) { return this.localCheck.includes(String(id)); },
        toggle(id) {
            const key = String(id);
            const idx = this.localCheck.indexOf(key);
            if (idx >= 0) this.localCheck.splice(idx, 1);
            else this.localCheck.push(key);
            $wire.set('xCheck', this.localCheck);
        },
        toggleAll() {
            const pageIds = this.getPageIds();
            if (this.isAllSelected) {
                this.localCheck = this.localCheck.filter(id => !pageIds.includes(id));
            } else {
                const otherIds = this.localCheck.filter(id => !pageIds.includes(id));
                this.localCheck = [...otherIds, ...pageIds];
            }
            $wire.set('xCheck', this.localCheck);
        },
    }));
</script>
@endscript
