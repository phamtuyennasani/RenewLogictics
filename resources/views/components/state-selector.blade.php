<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public ?int $countryId = null;
    public ?string $selected = null;
    public string $search = '';

    public function mount($countryId = null, $selected = null)
    {
        $this->countryId = $countryId;
        $this->selected = $selected;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function selectState($stateName)
    {
        $this->dispatch('state-selected', state: $stateName);
        $this->dispatch('close-modal', modal: 'state-selector');
    }

    public function getStatesProperty()
    {
        if (!$this->countryId) {
            return collect([]);
        }

        return DB::table('states')
            ->where('country_id', $this->countryId)
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(10);
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<div class="bg-white rounded-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
    {{-- Header --}}
    <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-neutral-900">Chọn State/Tỉnh</h3>
        <button @click="$dispatch('close-modal', { modal: 'state-selector' })" class="p-1 rounded-lg text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- Search --}}
    <div class="px-6 py-4 border-b border-neutral-100">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="w-4 h-4 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Tìm kiếm state..."
                class="pl-9 pr-4 py-2 w-full text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder:text-neutral-400">
        </div>
    </div>

    {{-- List --}}
    <div class="flex-1 overflow-y-auto px-6 py-4">
        @if($this->states->isEmpty())
            <div class="text-center py-8">
                <p class="text-sm text-neutral-500">Không tìm thấy state nào</p>
            </div>
        @else
            <div class="space-y-1">
                @foreach($this->states as $state)
                    <button wire:click="selectState('{{ $state->name }}')"
                        class="w-full text-left px-4 py-2.5 rounded-lg text-sm hover:bg-neutral-50 transition-colors
                               {{ $selected === $state->name ? 'bg-primary-50 text-primary-700 font-medium' : 'text-neutral-700' }}">
                        {{ $state->name }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pagination --}}
    @if($this->states->hasPages())
        <div class="px-6 py-4 border-t border-neutral-100">
            {{ $this->states->links('vendor.livewire.modal-pagination') }}
        </div>
    @endif
</div>
