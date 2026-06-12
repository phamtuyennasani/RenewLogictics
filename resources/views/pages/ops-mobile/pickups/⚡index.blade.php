<?php

use App\Enums\PickupStatusEnum;
use App\Models\Pickup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.mobile')] #[Title('PickUp OPS')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $tab = 'new';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['ops', 'admin', 'manager', 'cs']), 403);
        abort_unless(\Gate::allows('pickups.index'), 403);
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'tab'], true)) {
            $this->resetPage();
        }
    }

    protected function statusesForTab(): ?array
    {
        return match ($this->tab) {
            'new' => [PickupStatusEnum::MOI_TAO_PICKUP->value],
            'accepted' => [PickupStatusEnum::DA_XAC_NHAN->value],
            'picking' => [PickupStatusEnum::PICKUP_DANG_LAY->value],
            'done' => [PickupStatusEnum::PICKUP_DA_LAY->value],
            'cancelled' => [PickupStatusEnum::DA_HUY->value],
            'all' => null,
            default => [PickupStatusEnum::MOI_TAO_PICKUP->value],
        };
    }

    protected function baseQuery()
    {
        return Pickup::query()
            ->where('id_user', auth()->id())
            ->with(['shipper:id,fullname,username', 'orders:id,id_bill,tracking_code,uuid'])
            ->withCount('orders');
    }

    public function getPickupsProperty()
    {
        $statuses = $this->statusesForTab();

        return $this->baseQuery()
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('ma_pickup', 'like', "%{$keyword}%")
                        ->orWhere('info_khachhang', 'like', "%{$keyword}%")
                        ->orWhereHas('orders', fn ($orderQuery) => $orderQuery
                            ->where('id_bill', 'like', "%{$keyword}%")
                            ->orWhere('tracking_code', 'like', "%{$keyword}%"));
                });
            })
            ->latest('ngay_tao')
            ->paginate(12);
    }

    public function getStatusCountsProperty(): array
    {
        $counts = Pickup::query()
            ->where('id_user', auth()->id())
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'all' => array_sum($counts),
            ...$counts,
        ];
    }

    public function tabs(): array
    {
        return [
            'new' => ['label' => 'Mới', 'status' => PickupStatusEnum::MOI_TAO_PICKUP],
            'accepted' => ['label' => 'Xác nhận', 'status' => PickupStatusEnum::DA_XAC_NHAN],
            'picking' => ['label' => 'Đang lấy', 'status' => PickupStatusEnum::PICKUP_DANG_LAY],
            'done' => ['label' => 'Đã lấy', 'status' => PickupStatusEnum::PICKUP_DA_LAY],
            'cancelled' => ['label' => 'Đã hủy', 'status' => PickupStatusEnum::DA_HUY],
        ];
    }
};
?>

@php
    $counts = $this->statusCounts;
@endphp

<div class="min-h-screen bg-neutral-50 pb-24">
    <section class="border-b border-neutral-200 bg-white px-4 py-3 shadow-sm">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full"
                          style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});"></span>
                    <p class="text-[11px] font-bold uppercase tracking-wide text-neutral-500">OPS mobile</p>
                </div>
                <h1 class="mt-1 truncate text-xl font-bold leading-tight text-neutral-950">Danh sách PickUp</h1>
            </div>
            <div class="inline-flex shrink-0 items-center gap-2 rounded-full border border-primary-100 bg-primary-50 px-3 py-2">
                <span class="text-[10px] font-bold uppercase tracking-wide text-primary-700">Tổng</span>
                <span class="min-w-6 text-right text-base font-bold leading-none text-primary-800">{{ number_format($counts['all'] ?? 0) }}</span>
            </div>
        </div>

        <div class="mt-3 max-w-md rounded-xl border border-neutral-200 bg-neutral-50 p-1">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search"
                       wire:model.live.debounce.400ms="keyword"
                       placeholder="Tìm mã pickup, khách, order..."
                       class="h-9 w-full rounded-lg border-0 bg-transparent pl-9 pr-3 text-sm font-semibold text-neutral-900 placeholder:text-neutral-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-100">
            </div>
        </div>
    </section>

    <section class="px-4 py-3">
        <div class="flex flex-wrap gap-2">
            @foreach($this->tabs() as $key => $item)
                @php $status = $item['status']; @endphp
                <button type="button"
                        wire:click="$set('tab', '{{ $key }}')"
                        class="inline-flex h-9 items-center gap-2 rounded-full border px-3 text-sm font-bold transition {{ $tab === $key ? 'border-primary-200 bg-primary-600 text-white shadow-sm shadow-primary-900/15' : 'border-neutral-200 bg-white text-neutral-600 active:bg-neutral-50' }}">
                    <span>{{ $item['label'] }}</span>
                    <span class="inline-flex min-w-6 items-center justify-center rounded-full {{ $tab === $key ? 'bg-white/20 text-white' : 'bg-neutral-100 text-neutral-500' }} px-1.5 py-0.5 text-[11px] font-bold">
                        {{ number_format($counts[$status->value] ?? 0) }}
                    </span>
                </button>
            @endforeach
        </div>
    </section>

    <section class="space-y-3 px-4">
        @forelse($this->pickups as $pickup)
            @php
                $customer = $pickup->info_khachhang ?? [];
                $schedule = data_get($pickup->info_pickup, 'ngayhen');
            @endphp
            <a href="{{ route('ops.mobile.pickups.show', $pickup->id) }}"
               wire:navigate
               class="block rounded-xl border border-neutral-200 bg-white p-4 shadow-sm active:bg-neutral-50">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-base font-bold text-neutral-950">{{ $pickup->ma_pickup }}</p>
                        <p class="mt-1 truncate text-xs font-medium text-neutral-500">{{ data_get($customer, 'company') ?: data_get($customer, 'fullname', '-') }}</p>
                    </div>
                    @if($pickup->status)
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $pickup->status->color() }}">{{ $pickup->status->label() }}</span>
                    @endif
                </div>

                <div class="mt-3 space-y-2 text-xs text-neutral-600">
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-neutral-500">Shipper</span>
                        <span class="truncate font-bold text-neutral-800">{{ $pickup->shipper?->fullname ?: $pickup->shipper?->username ?: 'Chưa gán' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-neutral-500">Order</span>
                        <span class="font-bold text-neutral-800">{{ (int) $pickup->orders_count }} đơn</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="font-semibold text-neutral-500">Lịch hẹn</span>
                        <span class="font-bold text-neutral-800">{{ $schedule ? \Carbon\Carbon::parse($schedule)->format('H:i d/m') : '-' }}</span>
                    </div>
                </div>

                @if($pickup->status === \App\Enums\PickupStatusEnum::DA_HUY)
                    <div class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                        Có thể chọn lại shipper cho phiếu này.
                    </div>
                @endif
            </a>
        @empty
            <div class="rounded-xl border border-neutral-200 bg-white px-4 py-12 text-center shadow-sm">
                <p class="text-sm font-bold text-neutral-700">Chưa có PickUp phù hợp</p>
                <p class="mt-1 text-xs text-neutral-400">Các phiếu PickUp của OPS sẽ hiển thị tại đây.</p>
            </div>
        @endforelse

        @if($this->pickups->hasPages())
            <div class="pt-2">
                {{ $this->pickups->links() }}
            </div>
        @endif
    </section>
</div>
