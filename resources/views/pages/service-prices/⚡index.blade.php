<?php

use App\Models\ServicePriceList;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Bảng giá dịch vụ')] class extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $keyword = '';

    public array $xCheck = [];

    public ?string $pendingAction = null;

    public mixed $pendingId = null;

    public function mount(): void
    {
        abort_unless(\Gate::allows('service-prices.manage'), 403);
    }

    public function updatingKeyword(): void
    {
        $this->resetPage();
    }

    public function updatingPage(): void
    {
        $this->xCheck = [];
        $this->dispatch('sync-check');
    }

    public function deleteSelected(): void
    {
        abort_unless(\Gate::allows('service-prices.delete'), 403);

        if (empty($this->xCheck)) {
            Flux::toast(duration: 2000, heading: 'Cảnh báo', text: 'Vui lòng chọn dữ liệu cần xóa!', variant: 'warning');

            return;
        }

        $count = count($this->xCheck);
        $this->pendingAction = 'deleteSelected';
        $this->pendingId = null;
        $this->dispatch('open-confirm', [
            'title' => 'Xác nhận xóa nhiều mục',
            'message' => "Bạn có chắc chắn muốn xóa {$count} bảng giá đã chọn? Hành động này không thể hoàn tác.",
            'variant' => 'danger',
        ]);
    }

    public function deleteItem(int $id): void
    {
        abort_unless(\Gate::allows('service-prices.delete'), 403);

        $this->pendingAction = 'deleteItem';
        $this->pendingId = $id;
        $this->dispatch('open-confirm', [
            'title' => 'Xác nhận xóa',
            'message' => 'Bạn có chắc chắn muốn xóa bảng giá này? Hành động này không thể hoàn tác.',
            'variant' => 'danger',
        ]);
    }

    #[On('confirm-action')]
    public function handleConfirmAction(): void
    {
        if (in_array($this->pendingAction, ['deleteItem', 'deleteSelected'], true)) {
            abort_unless(\Gate::allows('service-prices.delete'), 403);
        }

        match ($this->pendingAction) {
            'deleteItem' => ServicePriceList::query()->findOrFail($this->pendingId)->delete(),
            'deleteSelected' => ServicePriceList::query()->whereIn('id', $this->xCheck)->delete(),
            default => null,
        };

        if ($this->pendingAction === 'deleteSelected') {
            $this->xCheck = [];
            $this->dispatch('sync-check');
        }

        $this->pendingAction = null;
        $this->pendingId = null;
        unset($this->priceLists);

        Flux::toast(duration: 2000, heading: 'Thành công', text: 'Đã xóa bảng giá dịch vụ.', variant: 'success');
    }

    #[Computed]
    public function priceLists()
    {
        return ServicePriceList::query()
            ->with(['service', 'countries', 'updater'])
            ->withCount('details')
            ->when($this->keyword !== '', function ($query) {
                $keyword = '%'.$this->keyword.'%';
                $query->where(function ($scope) use ($keyword) {
                    $scope->where('name', 'like', $keyword)
                        ->orWhereHas('service', fn ($service) => $service->where('namevi', 'like', $keyword))
                        ->orWhereHas('countries', fn ($country) => $country->where('name', 'like', $keyword));
                });
            })
            ->latest('updated_at')
            ->paginate(15);
    }

    #[Computed]
    public function currentPageIds(): array
    {
        return $this->priceLists->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }
};

?>

@php
    $primaryHex = config('theme.primary.hex', '#3b82f6');
    $accentHex  = config('theme.accent.hex', '#0ea5e9');
@endphp

<div x-data="tableCheck" class="space-y-4" style="--gradient: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-neutral-500 capitalize">Phụ phí / Bảng giá dịch vụ</p>
            <h1 class="mt-0.5 text-2xl font-bold text-neutral-900 capitalize">Danh sách bảng giá dịch vụ</h1>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <svg class="h-4 w-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="keyword"
                    placeholder="Tìm kiếm..."
                    class="w-64 rounded-xl border border-neutral-300 bg-white py-2 pl-9 pr-4 text-sm transition-all placeholder:text-neutral-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                @if ($keyword)
                    <button
                        type="button"
                        wire:click="$set('keyword', '')"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-neutral-400 hover:text-neutral-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            <a href="{{ route('phuphi.service-prices.add') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                style="background: var(--gradient);">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Thêm mới
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
            <span class="text-sm leading-8 text-neutral-500">
                @if ($this->priceLists->total() > 0)
                    Hiển thị <span class="font-semibold text-neutral-700">{{ $this->priceLists->firstItem() }}–{{ $this->priceLists->lastItem() }}</span>
                    của <span class="font-semibold text-neutral-700">{{ $this->priceLists->total() }}</span> bảng giá
                @else
                    Không có bản ghi nào
                @endif
            </span>

            @can('service-prices.delete')
                <div class="flex items-center gap-2" x-cloak x-show="localCheck.length > 0" x-transition>
                    <button
                        type="button"
                        wire:click="deleteSelected()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Xóa <span x-text="'(' + localCheck.length + ')'"></span>
                    </button>
                </div>
            @endcan
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-neutral-100 bg-neutral-50">
                        @can('service-prices.delete')
                            <th class="w-12 px-5 py-3.5 text-center">
                                <label class="relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                                    <input type="checkbox" :checked="isAllSelected" @click="toggleAll()" class="peer sr-only">
                                    <div class="flex h-4.5 w-4.5 cursor-pointer items-center justify-center rounded-md border bg-white transition-all duration-200 peer-hover:border-primary-400"
                                         :class="isAllSelected || isIndeterminate ? 'border-0 shadow-sm' : 'border-neutral-300'"
                                         :style="(isAllSelected || isIndeterminate) ? 'background: var(--gradient);' : ''">
                                        <svg x-show="isAllSelected" class="h-2.5 w-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.5l4.5 4.5 8.5-8.5"/>
                                        </svg>
                                        <svg x-show="isIndeterminate" class="h-2.5 w-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        </svg>
                                    </div>
                                </label>
                            </th>
                        @endcan
                        <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">Tên bảng giá</th>
                        <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">Dịch vụ</th>
                        <th class="px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">Quốc gia</th>
                        <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-neutral-500">Dòng giá</th>
                        <th class="w-44 px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">Người cập nhật</th>
                        <th class="w-36 px-4 py-3.5 text-xs font-semibold uppercase tracking-wide text-neutral-500">Ngày cập nhật</th>
                        <th class="w-28 px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wide text-neutral-500">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->priceLists as $priceList)
                        <tr class="transition-colors hover:bg-neutral-50/60">
                            @can('service-prices.delete')
                                <td class="px-5 py-3.5 text-center">
                                    <label class="relative mx-auto flex w-fit cursor-pointer select-none items-center justify-center">
                                        <input type="checkbox" :checked="isChecked({{ $priceList->id }})" @click="toggle({{ $priceList->id }})" class="peer sr-only">
                                        <div class="flex h-4.5 w-4.5 cursor-pointer items-center justify-center rounded-md border bg-white transition-all duration-200 peer-hover:border-primary-400"
                                             :class="isChecked({{ $priceList->id }}) ? 'border-0 shadow-sm' : 'border-neutral-300'"
                                             :style="isChecked({{ $priceList->id }}) ? 'background: var(--gradient);' : ''">
                                            <svg x-show="isChecked({{ $priceList->id }})" class="h-2.5 w-2.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.5l4.5 4.5 8.5-8.5"/>
                                            </svg>
                                        </div>
                                    </label>
                                </td>
                            @endcan
                            <td class="px-4 py-3.5">
                                <a href="{{ route('phuphi.service-prices.edit', $priceList->id) }}" wire:navigate class="line-clamp-2 text-sm font-medium text-neutral-900 transition-colors hover:text-primary-600">
                                    {{ $priceList->name }}
                                </a>
                            </td>
                            <td class="px-4 py-3.5 text-sm text-neutral-700">{{ $priceList->service?->namevi ?? '—' }}</td>
                            <td class="px-4 py-3.5">
                                <div class="flex max-w-md flex-wrap gap-1.5">
                                    @foreach ($priceList->countries->take(4) as $country)
                                        <span class="rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-700">{{ $country->name }}</span>
                                    @endforeach
                                    @if ($priceList->countries->count() > 4)
                                        <span class="rounded-md border border-primary-200 bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700">+{{ $priceList->countries->count() - 4 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-center text-sm font-semibold text-neutral-800">{{ number_format($priceList->details_count) }}</td>
                            <td class="px-4 py-3.5">
                                @if ($priceList->updater)
                                    <div class="flex items-center gap-2">
                                        <flux:avatar circle class="flex h-7 w-7 shrink-0 items-center justify-center text-xs font-semibold text-white" style="background: var(--gradient);" size="sm" name="{{ strtoupper(substr($priceList->updater->fullname ?? 'U', 0, 1)) }}" initials:single />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-700">{{ $priceList->updater->fullname }}</p>
                                            <p class="truncate text-xs text-neutral-400">{{ $priceList->updater->email ?? '' }}</p>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-sm text-neutral-600">{{ $priceList->updated_at?->format('d/m/Y') }}</p>
                                <p class="text-xs text-neutral-400">{{ $priceList->updated_at?->format('H:i') }}</p>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-center gap-1">
                                    <a href="{{ route('phuphi.service-prices.edit', $priceList->id) }}" wire:navigate class="rounded-lg p-2 text-neutral-400 transition-all hover:bg-primary-50 hover:text-primary-600" title="Chỉnh sửa">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    @can('service-prices.delete')
                                        <button type="button" wire:click="deleteItem({{ $priceList->id }})" class="cursor-pointer rounded-lg p-2 text-neutral-400 transition-all hover:bg-red-50 hover:text-red-600" title="Xóa">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ \Gate::allows('service-prices.delete') ? 8 : 7 }}" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-neutral-100">
                                        <svg class="h-7 w-7 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-600">Không có bản ghi nào</p>
                                        <p class="mt-0.5 text-xs text-neutral-400">Hãy thêm bảng giá mới để bắt đầu</p>
                                    </div>
                                    <a href="{{ route('phuphi.service-prices.add') }}" wire:navigate
                                       class="mt-1 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:shadow-md"
                                       style="background: var(--gradient);">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Thêm mới
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{ $this->priceLists->links() }}
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
        getPageIds() {
            return @json($this->currentPageIds);
        },
        get isAllSelected() {
            const pageIds = this.getPageIds();
            return pageIds.length > 0 && pageIds.every(id => this.localCheck.includes(id));
        },
        get isIndeterminate() {
            const pageIds = this.getPageIds();
            const checkedOnPage = pageIds.filter(id => this.localCheck.includes(id)).length;
            return checkedOnPage > 0 && checkedOnPage < pageIds.length;
        },
        isChecked(id) {
            return this.localCheck.includes(String(id));
        },
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
