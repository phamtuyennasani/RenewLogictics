<?php

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component {
    use WithPagination;

    public ?int $countryId = null;
    public ?string $stateName = null;
    public ?string $selected = null;
    public string $search = '';

    public function mount($countryId = null, $stateName = null, $selected = null)
    {
        $this->countryId = $countryId;
        $this->stateName = $stateName;
        $this->selected = $selected;
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function selectCity($cityName)
    {
        $this->dispatch('city-selected', city: $cityName);
        $this->dispatch('close-modal', modal: 'city-selector');
    }

    public function getCitiesProperty()
    {
        if (!$this->countryId) {
            return collect([]);
        }

        $query = DB::table('cities')
            ->join('states', 'cities.state_id', '=', 'states.id')
            ->where('states.country_id', $this->countryId);

        // Nếu có state name thì lọc theo state
        if ($this->stateName) {
            $query->where('states.name', $this->stateName);
        }

        return $query
            ->when($this->search, fn($q) => $q->where('cities.name', 'like', '%' . $this->search . '%'))
            ->select('cities.name')
            ->orderBy('cities.name')
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
        <div>
            <h3 class="text-lg font-semibold text-neutral-900">Chọn City/Thành phố</h3>
            @if($stateName)
                <p class="text-xs text-neutral-500 mt-0.5">Lọc theo: {{ $stateName }}</p>
            @endif
        </div>
        <button @click="$dispatch('close-modal', { modal: 'city-selector' })" class="p-1 rounded-lg text-neutral-400 hover:text-neutral-600 hover:bg-neutral-100 transition-colors">
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
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Tìm kiếm city..."
                class="pl-9 pr-4 py-2 w-full text-sm border border-neutral-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 placeholder:text-neutral-400">
        </div>
    </div>

    {{-- List --}}
    <div class="flex-1 overflow-y-auto px-6 py-4">
        @if($this->cities->isEmpty())
            <div class="text-center py-8">
                <p class="text-sm text-neutral-500">Không tìm thấy city nào</p>
            </div>
        @else
            <div class="space-y-1">
                @foreach($this->cities as $city)
                    <button wire:click="selectCity('{{ $city->name }}')"
                        class="w-full text-left px-4 py-2.5 rounded-lg text-sm hover:bg-neutral-50 transition-colors
                               {{ $selected === $city->name ? 'bg-primary-50 text-primary-700 font-medium' : 'text-neutral-700' }}">
                        {{ $city->name }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Pagination --}}
    @if($this->cities->hasPages())
        <div class="px-6 py-4 border-t border-neutral-100">
            {{ $this->cities->links('vendor.livewire.modal-pagination') }}
        </div>
    @endif
</div>
