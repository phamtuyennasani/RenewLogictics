<?php

use Illuminate\Support\Js;
use Livewire\Component;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use App\Models\News;

new class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public ?string $keyword = '';
    public $type;
    public array $xCheck = [];
    public bool $xCheckAll = false;
    public array $config;

    public function updatingKeyword()
    {
        $this->resetPage();
    }

    #[Computed]
    public function items()
    {
        return News::when($this->keyword, function ($query) {
            $query->where('namevi', 'like', '%' . $this->keyword . '%');
        })->where('type', $this->type)->orderByDesc('numb')->paginate(15);
    }

    public function deleteSelected()
    {
        if (empty($this->xCheck)) {
            $this->dispatch('toast', ['type' => 'warning', 'message' => 'Vui lòng chọn dữ liệu cần xóa!']);
            return;
        }
        News::whereIn('id', $this->xCheck)->delete();
        $this->xCheck = [];
        $this->xCheckAll = false;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Xóa dữ liệu thành công!']);
    }

    public function deleteItem($id)
    {
        News::findOrFail($id)->delete();
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Xóa dữ liệu thành công!']);
    }

    public function changeNumb($id, $numb)
    {
        News::findOrFail($id)->update(['numb' => $numb]);
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Cập nhật số thứ tự thành công!']);
    }

    public function render()
    {   
        $this->config = config('dulieu.' . $this->type, []);
        return $this->view();
    }
};

?>

@php
$primaryHex = config('theme.primary.hex', '#3b82f6');
$accentHex  = config('theme.accent.hex', '#0ea5e9');
@endphp

<div
    x-data="{
        xCheck: @entangle('xCheck'),
        xCheckAll: @entangle('xCheckAll'),
        get isAllSelected() {
            return this.xCheck.length > 0 && this.xCheck.length === {{ $this->items()->count() }}
        },
        get isIndeterminate() {
            return this.xCheck.length > 0 && this.xCheck.length < {{ $this->items()->count() }}
        }
    }"
    class="space-y-4"
>
    {{-- ======================= PAGE HEADER ======================= --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <p class="text-sm text-neutral-500 capitalize">
                Dữ liệu / {{ $this->config['title'] ?? 'Danh sách' }}
            </p>
            <h1 class="text-2xl font-bold text-neutral-900 capitalize mt-0.5">
                Danh sách {{ $this->config['title'] ?? '' }}
            </h1>
        </div>
        <div class="flex items-center gap-3 shrink-0">
            {{-- Search --}}
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="keyword"
                    placeholder="Tìm kiếm..."
                    class="pl-9 pr-4 py-2 w-64 text-sm border border-neutral-300 rounded-xl bg-white
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500
                           placeholder:text-neutral-400 transition-all"
                >
                @if ($keyword)
                    <button
                        wire:click="$set('keyword', '')"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-neutral-400 hover:text-neutral-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Add button --}}
            <a href=""
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white
                      rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5"
               style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Thêm mới
            </a>
        </div>
    </div>

    {{-- ======================= TABLE CARD ======================= --}}
    <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden shadow-xs">

        {{-- Table header bar --}}
        <div class="px-5 py-4 flex items-center justify-between border-b border-neutral-100">
            <div class="flex items-center gap-3">
                <span class="text-sm text-neutral-500 leading-8">
                    @if ($this->items->total() > 0)
                        Hiển thị <span class="font-semibold text-neutral-700">{{ $this->items->firstItem() }}–{{ $this->items->lastItem() }}</span>
                        của <span class="font-semibold text-neutral-700">{{ $this->items->total() }}</span> bản ghi
                    @else
                        Không có bản ghi nào
                    @endif
                </span>
            </div>

            <div class="flex items-center gap-2" x-cloak x-show="xCheck.length > 0" x-transition>
                <button
                    wire:click="deleteSelected()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium
                           text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Xóa (<span x-text="xCheck.length"></span>)
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-neutral-50 border-b border-neutral-100">

                        {{-- ======================= HEADER CHECKBOX ======================= --}}
                        <th class="w-12 px-5 py-3.5 text-center">
                            <div class="flex items-center justify-center">
                                <label class="relative flex items-center justify-center cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        x-model="xCheckAll"
                                        @change="
                                            if(!xCheckAll){
                                                xCheck = {{ Js::from($this->items->pluck('id')->map(fn($id) => (string) $id)->toArray()) }};
                                            }else{
                                                xCheck = [];
                                            }
                                            $wire.set('xCheck', xCheck);
                                        "
                                        class="peer sr-only"
                                    >
                                    <div class="w-4.5 h-4.5 rounded-md border flex items-center justify-center
                                                bg-white transition-all duration-200 cursor-pointer
                                                peer-hover:border-primary-400
                                                peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/40
                                                peer-disabled:cursor-not-allowed peer-disabled:opacity-50
                                                peer-checked:border-0 peer-checked:shadow-sm"
                                         :class="isAllSelected ? 'border-0 shadow-sm' : isIndeterminate ? 'border-primary-500' : 'border-neutral-300'"
                                         :style="isAllSelected
                                             ? 'background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});'
                                             : isIndeterminate
                                                 ? 'background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});'
                                                 : 'background-color: white;'">
                                        <svg
                                            x-show="isAllSelected"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-50"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-50"
                                            class="w-2.5 h-2.5 text-white"
                                            fill="none" stroke="currentColor" stroke-width="3"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.5l4.5 4.5 8.5-8.5"/>
                                        </svg>
                                        <svg
                                            x-show="isIndeterminate"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-50"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-50"
                                            class="w-2.5 h-2.5 text-white"
                                            fill="none" stroke="currentColor" stroke-width="3"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14"/>
                                        </svg>
                                    </div>
                                </label>
                            </div>
                        </th>

                        <th class="w-24 px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center">
                            STT
                        </th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide">
                            Tiêu đề
                        </th>
                        @foreach (@$this->config['formOptions']??[] as $field => $label)
                            <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-60 {{ $label['class'] ?? '' }}">
                                {{ $label['label'] }}
                            </th>
                        @endforeach
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-44">
                            Người cập nhật
                        </th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide w-36">
                            Ngày cập nhật
                        </th>
                        <th class="px-4 py-3.5 text-xs font-semibold text-neutral-500 uppercase tracking-wide text-center w-28">
                            Thao tác
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($this->items as $v)
                        <tr class="hover:bg-neutral-50/60 transition-colors">
                            <td class="px-5 py-3.5 text-center">
                                <label class="relative flex items-center justify-center cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        value="{{ $v->id }}"
                                        x-model="xCheck"
                                        class="peer sr-only"
                                    >
                                    <div class="w-4.5 h-4.5 rounded-md border flex items-center justify-center
                                                bg-white transition-all duration-200 cursor-pointer
                                                peer-hover:border-primary-400
                                                peer-focus-visible:ring-2 peer-focus-visible:ring-primary-500/40"
                                        :class="xCheck.includes('{{ (string) $v->id }}') ? 'border-0 shadow-sm' : 'border-neutral-300'"
                                        :style="xCheck.includes('{{ (string) $v->id }}')
                                             ? 'background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});'
                                             : 'background-color: white;'">
                                        <svg
                                            x-show="xCheck.includes('{{ (string) $v->id }}')"
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 scale-50"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-100"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-50"
                                            class="w-2.5 h-2.5 text-white"
                                            fill="none" stroke="currentColor" stroke-width="3"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 11.5l4.5 4.5 8.5-8.5"/>
                                        </svg>
                                    </div>
                                </label>
                            </td>

                            <td class="px-4 py-3.5 text-center">
                                <input
                                    type="number"
                                    value="{{ $v->numb }}"
                                    min="0"
                                    wire:change="changeNumb({{ $v->id }}, $event.target.value)"
                                    class="w-16 mx-auto text-center text-sm border border-neutral-200 rounded-lg
                                           px-2 py-1 bg-transparent hover:border-primary-400
                                           focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all cursor-pointer"
                                >
                            </td>
                            <td class="px-4 py-3.5">
                                <a href=""
                                   class="text-sm font-medium text-neutral-900 hover:text-primary-600 transition-colors line-clamp-2">
                                    {{ $v->namevi }}
                                </a>
                            </td>
                            @foreach (@$this->config['formOptions']??[] as $field => $label)
                            <td class="px-4 py-3.5 {{ $label['class'] ?? '' }}">
                                {{ ($v->options2[$field]) ?? '' }} 
                            </td>
                         @endforeach
                            <td class="px-4 py-3.5">
                                @if ($v->user)
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs font-semibold shrink-0"
                                             style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                                            {{ strtoupper(substr($v->user->fullname ?? 'U', 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-neutral-700 truncate">{{ $v->user->fullname }}</p>
                                            <p class="text-xs text-neutral-400 truncate capitalize">{{ \App\Enums\RoleEnum::label($v->user->roles->first()?->name ?? '') }}</p>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <p class="text-sm text-neutral-600">
                                    {{ \Carbon\Carbon::parse($v->updated_at)->format('d/m/Y') }}
                                </p>
                                <p class="text-xs text-neutral-400">
                                    {{ \Carbon\Carbon::parse($v->updated_at)->format('H:i') }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="flex items-center justify-center gap-1">
                                    <a
                                        href=""
                                        class="p-2 rounded-lg text-neutral-400 hover:text-primary-600 hover:bg-primary-50
                                               transition-all"
                                        title="Chỉnh sửa">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>
                                    <button
                                        wire:click="deleteItem({{ $v->id }})"
                                        wire:confirm="Bạn có chắc chắn muốn xóa bản ghi này?"
                                        class="p-2 rounded-lg text-neutral-400 hover:text-red-600 hover:bg-red-50
                                               transition-all"
                                        title="Xóa">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="20" class="px-5 py-16 text-center">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="w-14 h-14 rounded-2xl bg-neutral-100 flex items-center justify-center">
                                        <svg class="w-7 h-7 text-neutral-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-neutral-600">Không có bản ghi nào</p>
                                        <p class="text-xs text-neutral-400 mt-0.5">Hãy thêm dữ liệu mới để bắt đầu</p>
                                    </div>
                                    <a href=""
                                       class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium
                                              text-white rounded-xl transition-all shadow-sm hover:shadow-md"
                                       style="background: linear-gradient(135deg, {{ $primaryHex }}, {{ $accentHex }});">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
    {{ $this->items->links() }}
</div>
