<?php

use App\Enums\ShipmentLoadStatusEnum;
use App\Models\ShipmentLoad;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Quản lý tải hàng')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $pageTitle = 'Quản lý tải hàng';
    public string $keyword = '';
    public string $status = '';
    public ?string $fromDate = null;
    public ?string $toDate = null;

    public function mount(): void
    {
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'status', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->keyword = '';
        $this->status = '';
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function getLoadsProperty()
    {
        return ShipmentLoad::query()
            ->with(['creator:id,fullname,username,code', 'approver:id,fullname,username,code'])
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('code', 'like', '%'.$keyword.'%')
                        ->orWhereHas('orders', fn ($orderQuery) => $orderQuery->where('id_bill', 'like', '%'.$keyword.'%'));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->fromDate, fn ($query) => $query->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($query) => $query->whereDate('created_at', '<=', $this->toDate))
            ->latest()
            ->paginate(15);
    }
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900">Quản lý tải hàng</h1>
            <p class="mt-1 text-sm text-neutral-500">Gom nhiều đơn vào một tải để cập nhật lịch sử và duyệt xuất đồng loạt.</p>
        </div>
        @can('packages.create')
        <a href="{{ route('packages.create') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
            Tạo tải hàng
        </a>
        @endcan
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-5">
            <input type="text" wire:model.live.debounce.350ms="keyword" placeholder="Mã tải hoặc mã đơn" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none md:col-span-2">
            <select wire:model.live="status" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                @foreach(ShipmentLoadStatusEnum::cases() as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="fromDate" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
            <input type="date" wire:model.live="toDate" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
        </div>
        <div class="mt-3 flex justify-end">
            <button type="button" wire:click="resetFilters" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50">Đặt lại</button>
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-4 py-3">Mã tải</th>
                        <th class="px-4 py-3">Ngày tạo</th>
                        <th class="px-4 py-3">Người tạo</th>
                        <th class="px-4 py-3 text-right">Số đơn</th>
                        <th class="px-4 py-3 text-right">Tổng cân tính phí</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($this->loads as $load)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-4 py-3 font-semibold text-neutral-900">{{ $load->code }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $load->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $load->creator?->fullname ?: $load->creator?->username ?: '-' }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format($load->orders_count) }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format((float) $load->total_chargeable_weight, 2, ',', '.') }} kg</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $load->status->color() }}">{{ $load->status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('packages.show', $load) }}" wire:navigate class="inline-flex rounded-lg border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-50">Chi tiết</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-neutral-500">Chưa có tải hàng phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-100 px-4 py-3">
            {{ $this->loads->links() }}
        </div>
    </div>
</div>

