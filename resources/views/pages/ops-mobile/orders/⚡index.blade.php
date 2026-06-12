<?php

use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.mobile')] #[Title('Order OPS')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $status = '';
    public string $pickup = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasAnyRole(['ops', 'admin', 'manager', 'cs']), 403);
        abort_unless(\Gate::allows('orders.index'), 403);
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status', 'pickup'], true)) {
            $this->resetPage();
        }
    }

    protected function baseQuery()
    {
        return Order::query()
            ->where('id_ops', auth()->id())
            ->with(['sale:id,fullname,username', 'pickups:id,ma_pickup,status'])
            ->withCount('packages');
    }

    public function getOrdersProperty()
    {
        return $this->baseQuery()
            ->when($this->status !== '', fn ($query) => $query->where('bill_status', $this->status))
            ->when($this->pickup === 'yes', fn ($query) => $query->has('pickups'))
            ->when($this->pickup === 'no', fn ($query) => $query->doesntHave('pickups'))
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($sub) use ($keyword) {
                    $sub->where('id_bill', 'like', "%{$keyword}%")
                        ->orWhere('tracking_code', 'like', "%{$keyword}%")
                        ->orWhere('mathamchieu', 'like', "%{$keyword}%")
                        ->orWhere('sender', 'like', "%{$keyword}%")
                        ->orWhere('receiver', 'like', "%{$keyword}%");
                });
            })
            ->latest('created_at')
            ->paginate(12);
    }

    public function getSummaryProperty(): array
    {
        $base = $this->baseQuery();

        return [
            'total' => (clone $base)->count(),
            'need_pickup' => (clone $base)
                ->whereIn('bill_status', [OrderStatusEnum::MOI_TAO, OrderStatusEnum::DA_XAC_NHAN])
                ->doesntHave('pickups')
                ->count(),
            'has_pickup' => (clone $base)->has('pickups')->count(),
        ];
    }

    public function statusOptions(): array
    {
        return OrderStatusEnum::cases();
    }
};
?>

@php
    $summary = $this->summary;
@endphp

<div class="min-h-screen bg-neutral-50 pb-24">
    <section class="bg-white px-4 py-4 shadow-sm ring-1 ring-neutral-200">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">OPS mobile</p>
                <h1 class="mt-0.5 text-2xl font-bold text-neutral-950">Danh sách order</h1>
            </div>
            <a href="{{ route('mobile.scan') }}"
               wire:navigate
               class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-lg shadow-primary-900/20 active:opacity-90"
               style="background: linear-gradient(135deg, {{ config('theme.primary.hex', '#3b82f6') }}, {{ config('theme.accent.hex', '#0ea5e9') }});"
               aria-label="Scan">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4H5a1 1 0 00-1 1v2m13-3h2a1 1 0 011 1v2M4 17v2a1 1 0 001 1h2m13-3v2a1 1 0 01-1 1h-2M8 8v8m4-8v8m4-8v8"/>
                </svg>
            </a>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2">
            <div class="rounded-xl bg-neutral-50 p-3">
                <p class="text-[11px] font-semibold uppercase text-neutral-500">Tổng</p>
                <p class="mt-1 text-xl font-bold text-neutral-950">{{ number_format($summary['total']) }}</p>
            </div>
            <div class="rounded-xl bg-amber-50 p-3">
                <p class="text-[11px] font-semibold uppercase text-amber-700">Cần PickUp</p>
                <p class="mt-1 text-xl font-bold text-amber-800">{{ number_format($summary['need_pickup']) }}</p>
            </div>
            <div class="rounded-xl bg-emerald-50 p-3">
                <p class="text-[11px] font-semibold uppercase text-emerald-700">Đã có</p>
                <p class="mt-1 text-xl font-bold text-emerald-800">{{ number_format($summary['has_pickup']) }}</p>
            </div>
        </div>
    </section>

    <section class="space-y-3 px-4 py-4">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="search"
                   wire:model.live.debounce.400ms="keyword"
                   placeholder="Tìm mã đơn, tracking, khách..."
                   class="h-11 w-full rounded-xl border border-neutral-200 bg-white pl-10 pr-3 text-sm font-medium text-neutral-900 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
        </div>

        <div class="grid grid-cols-2 gap-2">
            <select wire:model.live="status" class="h-11 min-w-0 rounded-xl border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                <option value="">Tất cả trạng thái</option>
                @foreach($this->statusOptions() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="pickup" class="h-11 min-w-0 rounded-xl border border-neutral-200 bg-white px-3 text-sm font-semibold text-neutral-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100">
                <option value="">Tất cả PickUp</option>
                <option value="no">Chưa có PickUp</option>
                <option value="yes">Đã có PickUp</option>
            </select>
        </div>
    </section>

    <section class="space-y-3 px-4">
        @forelse($this->orders as $order)
            @php
                $sender = $order->sender ?? [];
                $receiver = $order->receiver ?? [];
                $pickup = $order->pickups->first();
                $weight = (float) $order->packages()->sum(DB::raw('COALESCE(c_weight, 0)'));
            @endphp
            <a href="{{ route('ops.mobile.orders.show', $order->id) }}"
               wire:navigate
               class="block rounded-xl border border-neutral-200 bg-white p-4 shadow-sm active:bg-neutral-50">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-mono text-base font-bold text-neutral-950">{{ $order->id_bill ?: $order->tracking_code ?: '#'.$order->id }}</p>
                        <p class="mt-1 truncate text-xs font-medium text-neutral-500">{{ data_get($sender, 'company') ?: data_get($sender, 'fullname', 'Người gửi') }}</p>
                    </div>
                    @if($order->bill_status)
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $order->bill_status->color() }}">{{ $order->bill_status->label() }}</span>
                    @endif
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="font-semibold text-neutral-500">Người nhận</p>
                        <p class="mt-0.5 truncate font-bold text-neutral-800">{{ data_get($receiver, 'fullname', '-') }}</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 px-3 py-2">
                        <p class="font-semibold text-neutral-500">Kiện / kg</p>
                        <p class="mt-0.5 font-bold text-neutral-800">{{ (int) $order->packages_count }} / {{ number_format($weight, 2) }}</p>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-2 text-xs">
                    <span class="truncate font-medium text-neutral-500">Sale: {{ $order->sale?->fullname ?: $order->sale?->username ?: '-' }}</span>
                    @if($pickup)
                        <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700">{{ $pickup->ma_pickup }}</span>
                    @else
                        <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 font-bold text-amber-700">Chưa PickUp</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl border border-neutral-200 bg-white px-4 py-12 text-center shadow-sm">
                <p class="text-sm font-bold text-neutral-700">Chưa có order phù hợp</p>
                <p class="mt-1 text-xs text-neutral-400">Thử đổi bộ lọc hoặc từ khóa tìm kiếm.</p>
            </div>
        @endforelse

        @if($this->orders->hasPages())
            <div class="pt-2">
                {{ $this->orders->links() }}
            </div>
        @endif
    </section>
</div>
