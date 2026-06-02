<?php

use App\Actions\Pickup\TransitionPickupStatusAction;
use App\Enums\PickupStatusEnum;
use App\Models\News;
use App\Models\Pickup;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Quản lý Pickup')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';
    public string $status = '';
    public ?string $fromDate = null;
    public ?string $toDate = null;
    public ?int $selectedPickupId = null;

    public function mount(): void
    {
        abort_unless(\Gate::allows('pickups.index'), 403);
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

    public function getPickupsProperty()
    {
        return Pickup::query()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username'])
            ->withCount('orders')
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('ma_pickup', 'like', '%'.$keyword.'%')
                        ->orWhere('info_khachhang', 'like', '%'.$keyword.'%')
                        ->orWhereHas('orders', fn ($orderQuery) => $orderQuery
                            ->where('id_bill', 'like', '%'.$keyword.'%')
                            ->orWhere('tracking_code', 'like', '%'.$keyword.'%'));
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->fromDate, fn ($query) => $query->whereDate('ngay_tao', '>=', $this->fromDate))
            ->when($this->toDate, fn ($query) => $query->whereDate('ngay_tao', '<=', $this->toDate))
            ->latest('ngay_tao')
            ->paginate(15);
    }

    public function openDetails(int $pickupId): void
    {
        $this->selectedPickupId = $pickupId;
        Flux::modal('pickup-details')->show();
    }

    public function closeDetails(): void
    {
        $this->selectedPickupId = null;
    }

    public function updateStatus(string $status): void
    {
        $pickup = $this->selectedPickup;
        abort_unless($pickup, 404);

        try {
            TransitionPickupStatusAction::execute($pickup, PickupStatusEnum::from($status));
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể cập nhật Pickup', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        Flux::toast(heading: 'Đã cập nhật Pickup', text: 'Trạng thái phiếu Pickup đã được cập nhật.', variant: 'success');
    }

    public function getSelectedPickupProperty(): ?Pickup
    {
        if (! $this->selectedPickupId) {
            return null;
        }

        return Pickup::query()
            ->with(['user:id,fullname,username', 'shipper:id,fullname,username', 'orders'])
            ->withCount('orders')
            ->findOrFail($this->selectedPickupId);
    }

    public function getSelectedVehicleProperty(): ?News
    {
        $id = data_get($this->selectedPickup?->info_pickup, 'id_phuongtien');

        return $id ? News::query()->find($id) : null;
    }

    public function getSelectedBranchProperty(): ?News
    {
        $id = data_get($this->selectedPickup?->info_pickup, 'chinhanhnhanhang');

        return $id ? News::query()->find($id) : null;
    }
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900">Quản lý Pickup</h1>
            <p class="mt-1 text-sm text-neutral-500">Theo dõi các phiếu lấy hàng đã tạo từ chi tiết đơn hàng.</p>
        </div>
        <a href="{{ route('orders.index') }}" wire:navigate class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700">
            Chọn đơn để tạo Pickup
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-5">
            <input type="text" wire:model.live.debounce.350ms="keyword" placeholder="Mã Pickup, mã đơn, người gửi" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none md:col-span-2">
            <select wire:model.live="status" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
                <option value="">Tất cả trạng thái</option>
                @foreach(PickupStatusEnum::cases() as $option)
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
                        <th class="px-4 py-3">Mã Pickup</th>
                        <th class="px-4 py-3">Ngày tạo</th>
                        <th class="px-4 py-3">Người gửi</th>
                        <th class="px-4 py-3">Ngày hẹn</th>
                        <th class="px-4 py-3 text-right">Số đơn</th>
                        <th class="px-4 py-3 text-right">Số kiện</th>
                        <th class="px-4 py-3 text-right">Cân nặng</th>
                        <th class="px-4 py-3">Trạng thái</th>
                        <th class="px-4 py-3 text-right">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($this->pickups as $pickup)
                        <tr class="hover:bg-neutral-50">
                            <td class="px-4 py-3 font-semibold text-neutral-900">{{ $pickup->ma_pickup }}</td>
                            <td class="px-4 py-3 text-neutral-600">{{ $pickup->ngay_tao?->format('d/m/Y H:i') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-neutral-900">{{ data_get($pickup->info_khachhang, 'company') ?: data_get($pickup->info_khachhang, 'fullname', '-') }}</p>
                                <p class="text-xs text-neutral-500">{{ data_get($pickup->info_khachhang, 'phone', '') }}</p>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ data_get($pickup->info_pickup, 'ngayhen') ? \Carbon\Carbon::parse(data_get($pickup->info_pickup, 'ngayhen'))->format('d/m/Y H:i') : '-' }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format($pickup->orders_count) }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format($pickup->numb) }}</td>
                            <td class="px-4 py-3 text-right text-neutral-700">{{ number_format((float) $pickup->total_c_weight, 2, ',', '.') }} kg</td>
                            <td class="px-4 py-3">
                                @if($pickup->status)
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $pickup->status->color() }}">{{ $pickup->status->label() }}</span>
                                @else
                                    <span class="text-xs text-neutral-400">Chưa xác định</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" wire:click="openDetails({{ $pickup->id }})" class="inline-flex rounded-lg border border-primary-200 px-3 py-1.5 text-xs font-semibold text-primary-700 hover:bg-primary-50">Chi tiết</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-sm text-neutral-500">Chưa có phiếu Pickup phù hợp.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-100 px-4 py-3">
            {{ $this->pickups->links() }}
        </div>
    </div>

    <flux:modal name="pickup-details" class="w-full max-w-5xl" @close="$wire.closeDetails()">
        @if($this->selectedPickup)
            @php($selectedPickup = $this->selectedPickup)
            <div class="space-y-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <flux:heading size="lg">{{ $selectedPickup->ma_pickup }}</flux:heading>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $selectedPickup->status->color() }}">{{ $selectedPickup->status->label() }}</span>
                        </div>
                        <flux:subheading class="mt-1">Tạo bởi {{ $selectedPickup->user?->fullname ?: $selectedPickup->user?->username ?: '-' }} lúc {{ $selectedPickup->ngay_tao?->format('d/m/Y H:i') ?: '-' }}</flux:subheading>
                    </div>
                    <flux:modal.close>
                        <flux:button type="button" variant="ghost">Đóng</flux:button>
                    </flux:modal.close>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Số lượng đơn</p>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format($selectedPickup->orders_count) }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Số lượng kiện</p>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format($selectedPickup->numb) }}</p>
                    </div>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-xs font-semibold uppercase text-neutral-500">Cân nặng</p>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format((float) $selectedPickup->total_c_weight, 2, ',', '.') }} kg</p>
                    </div>
                </div>

                <div class="grid gap-5 lg:grid-cols-2">
                    <div class="rounded-lg border border-neutral-200 p-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Thông tin lấy hàng</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Ngày hẹn</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_pickup, 'ngayhen') ? \Carbon\Carbon::parse(data_get($selectedPickup->info_pickup, 'ngayhen'))->format('d/m/Y H:i') : '-' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Phương tiện</dt><dd class="mt-1 text-neutral-900">{{ $this->selectedVehicle?->namevi ?: '-' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Chi nhánh nhận hàng</dt><dd class="mt-1 text-neutral-900">{{ $this->selectedBranch?->namevi ?: '-' }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Chi phí công</dt><dd class="mt-1 text-neutral-900">{{ number_format((float) data_get($selectedPickup->info_pickup, 'chiphi_cong', 0), 0, ',', '.') }} đ</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase text-neutral-500">Ghi chú</dt><dd class="mt-1 whitespace-pre-line text-neutral-900">{{ $selectedPickup->note ?: '-' }}</dd></div>
                        </dl>
                    </div>

                    <div class="rounded-lg border border-neutral-200 p-4">
                        <h3 class="text-sm font-semibold text-neutral-900">Người gửi</h3>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Công ty</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'company', '-') }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Người liên hệ</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'fullname', '-') }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Điện thoại</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'phone', '-') }}</dd></div>
                            <div><dt class="text-xs font-medium uppercase text-neutral-500">Email</dt><dd class="mt-1 break-words text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'email', '-') }}</dd></div>
                            <div class="sm:col-span-2"><dt class="text-xs font-medium uppercase text-neutral-500">Địa chỉ</dt><dd class="mt-1 text-neutral-900">{{ data_get($selectedPickup->info_khachhang, 'address', '-') }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-neutral-200">
                    <div class="border-b border-neutral-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-neutral-900">Đơn hàng trong Pickup</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                                <tr><th class="px-4 py-3">Mã đơn</th><th class="px-4 py-3">Tracking</th><th class="px-4 py-3">Người nhận</th><th class="px-4 py-3">Trạng thái</th><th class="px-4 py-3 text-right">Thao tác</th></tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @forelse($selectedPickup->orders as $order)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-neutral-900">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</td>
                                        <td class="px-4 py-3 text-neutral-600">{{ $order->tracking_code ?: '-' }}</td>
                                        <td class="px-4 py-3 text-neutral-600">{{ data_get($order->receiver, 'company') ?: data_get($order->receiver, 'fullname', '-') }}</td>
                                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->bill_status->color() }}">{{ $order->bill_status->label() }}</span></td>
                                        <td class="px-4 py-3 text-right"><a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="text-xs font-semibold text-primary-700 hover:text-primary-800">Xem đơn</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-neutral-500">Pickup chưa có đơn hàng.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-100 pt-4">
                    @if($selectedPickup->status === PickupStatusEnum::MOI_TAO_PICKUP)
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::DA_XAC_NHAN->value }}')" wire:loading.attr="disabled" variant="primary">Đã xác nhận</flux:button>
                    @elseif($selectedPickup->status === PickupStatusEnum::DA_XAC_NHAN)
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::PICKUP_DANG_LAY->value }}')" wire:loading.attr="disabled" variant="primary">Đang lấy hàng</flux:button>
                    @elseif($selectedPickup->status === PickupStatusEnum::PICKUP_DANG_LAY)
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::PICKUP_DA_LAY->value }}')" wire:loading.attr="disabled" variant="primary">Đã lấy hàng</flux:button>
                    @endif

                    @if(! $selectedPickup->status->isFinal())
                        <flux:button type="button" wire:click="updateStatus('{{ PickupStatusEnum::DA_HUY->value }}')" wire:confirm="Hủy phiếu Pickup này?" wire:loading.attr="disabled" variant="danger">Hủy</flux:button>
                    @else
                        <p class="self-center text-sm font-medium text-neutral-500">Phiếu đã khóa thao tác.</p>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
