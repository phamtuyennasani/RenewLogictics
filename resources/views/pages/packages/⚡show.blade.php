<?php

use App\Actions\ShipmentLoad\AddOrdersToShipmentLoadAction;
use App\Actions\ShipmentLoad\ApproveShipmentLoadAction;
use App\Actions\ShipmentLoad\RecordShipmentLoadHistoryAction;
use App\Actions\ShipmentLoad\SyncShipmentLoadTotalsAction;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\ShipmentLoad;
use App\Models\ShipmentLoadOrder;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Chi tiết tải hàng')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public ShipmentLoad $load;
    public string $pageTitle = 'Chi tiết tải hàng';
    public string $keyword = '';
    public array $selectedOrderIds = [];
    public array $historyForm = [];

    public function mount(ShipmentLoad $load): void
    {
        $this->load = $this->loadFresh($load->id);
        $this->pageTitle = 'Tải '.$this->load->code;
        $this->resetHistoryForm();
    }

    public function updating($property): void
    {
        if ($property === 'keyword') {
            $this->resetPage('eligiblePage');
        }
    }

    protected function loadFresh(int $id): ShipmentLoad
    {
        return ShipmentLoad::query()
            ->with(['creator:id,fullname,username,code', 'approver:id,fullname,username,code'])
            ->findOrFail($id);
    }

    protected function reloadLoad(): void
    {
        $this->load = $this->loadFresh($this->load->id);
        $this->pageTitle = 'Tải '.$this->load->code;
    }

    public function toggleOrder(int $orderId): void
    {
        if (! $this->load->canEditOrders()) {
            return;
        }

        if (in_array($orderId, $this->selectedOrderIds, true)) {
            $this->selectedOrderIds = array_values(array_diff($this->selectedOrderIds, [$orderId]));
            return;
        }

        $this->selectedOrderIds[] = $orderId;
        $this->selectedOrderIds = array_values(array_unique($this->selectedOrderIds));
    }

    public function addSelectedOrders(): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->load->canEditOrders(), 403);

        try {
            AddOrdersToShipmentLoadAction::execute($this->load, $this->selectedOrderIds, auth()->id());
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể thêm đơn', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        $this->selectedOrderIds = [];
        $this->reloadLoad();
        $this->resetPage('ordersPage');
        $this->resetPage('eligiblePage');
        Flux::toast(heading: 'Đã thêm đơn', text: 'Danh sách tải hàng đã được cập nhật.', variant: 'success');
    }

    public function removeOrder(int $orderId): void
    {
        abort_unless($this->canManage(), 403);
        abort_unless($this->load->canEditOrders(), 403);

        DB::transaction(function () use ($orderId) {
            $lockedLoad = ShipmentLoad::query()->whereKey($this->load->id)->lockForUpdate()->firstOrFail();

            if (! $lockedLoad->canEditOrders()) {
                throw new \RuntimeException('Tải đã duyệt xuất, không thể xóa đơn.');
            }

            ShipmentLoadOrder::query()
                ->where('shipment_load_id', $lockedLoad->id)
                ->where('id_order', $orderId)
                ->delete();

            SyncShipmentLoadTotalsAction::execute($lockedLoad);
        });

        $this->reloadLoad();
        Flux::toast(heading: 'Đã xóa đơn', text: 'Đơn đã được gỡ khỏi tải.', variant: 'success');
    }

    public function addHistory(): void
    {
        abort_unless($this->canManage(), 403);

        $this->validate([
            'historyForm.thoigian' => ['required', 'date_format:Y-m-d H:i'],
            'historyForm.diadiem' => ['required', 'string', 'max:255'],
            'historyForm.trangthai' => ['required', 'string', 'max:255'],
            'historyForm.ghichu' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'historyForm.thoigian' => 'thời gian',
            'historyForm.diadiem' => 'địa điểm',
            'historyForm.trangthai' => 'trạng thái',
            'historyForm.ghichu' => 'ghi chú',
        ]);

        RecordShipmentLoadHistoryAction::execute(
            load: $this->load,
            time: Carbon::createFromFormat('Y-m-d H:i', $this->historyForm['thoigian']),
            location: trim($this->historyForm['diadiem']),
            status: trim($this->historyForm['trangthai']),
            note: trim((string) ($this->historyForm['ghichu'] ?? '')) ?: null,
            userId: auth()->id(),
        );

        $this->resetHistoryForm();
        $this->reloadLoad();
        Flux::toast(heading: 'Đã thêm lịch sử', text: 'Lịch sử đã được đồng bộ xuống các đơn trong tải.', variant: 'success');
    }

    public function approveLoad(): void
    {
        abort_unless($this->canManage(), 403);

        try {
            ApproveShipmentLoadAction::execute($this->load, auth()->id());
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể duyệt xuất', text: $exception->getMessage(), variant: 'warning');
            return;
        }

        $this->reloadLoad();
        $this->resetPage('ordersPage');
        Flux::toast(heading: 'Đã duyệt xuất', text: 'Toàn bộ đơn trong tải đã chuyển sang Duyệt xuất hàng.', variant: 'success');
    }

    public function canManage(): bool
    {
        // CS, admin, manager được thao tác trên tải. Xóa tải tách riêng (packages.delete).
        return auth()->user()?->can('packages.update') ?? false;
    }

    protected function resetHistoryForm(): void
    {
        $this->historyForm = [
            'thoigian' => now()->format('Y-m-d H:i'),
            'diadiem' => '',
            'trangthai' => '',
            'ghichu' => '',
        ];
    }

    public function getLoadOrdersProperty()
    {
        return $this->load->orders()
            ->with(['customerAccount:id,fullname,username,code', 'sale:id,fullname,username,code'])
            ->withSum('packages as chargeable_weight', 'c_weight')
            ->latest('orders.created_at')
            ->paginate(10, pageName: 'ordersPage');
    }

    public function getEligibleOrdersProperty()
    {
        return Order::query()
            ->with(['customerAccount:id,fullname,username,code', 'sale:id,fullname,username,code'])
            ->withSum('packages as chargeable_weight', 'c_weight')
            ->where('bill_status', OrderStatusEnum::DA_NHAN_HANG->value)
            ->whereDoesntHave('shipmentLoads')
            ->when($this->keyword !== '', function ($query) {
                $keyword = trim($this->keyword);
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('id_bill', 'like', '%'.$keyword.'%')
                        ->orWhere('tracking_code', 'like', '%'.$keyword.'%')
                        ->orWhereHas('customerAccount', fn ($customerQuery) => $customerQuery
                            ->where('fullname', 'like', '%'.$keyword.'%')
                            ->orWhere('username', 'like', '%'.$keyword.'%')
                            ->orWhere('code', 'like', '%'.$keyword.'%'));
                });
            })
            ->latest('ngaynhanhang')
            ->paginate(8, pageName: 'eligiblePage');
    }

    public function getHistoriesProperty()
    {
        return $this->load->histories()
            ->with('user:id,fullname,username,code')
            ->latest('thoigian')
            ->get();
    }
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-xl font-semibold text-neutral-900">{{ $load->code }}</h1>
                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $load->status->color() }}">{{ $load->status->label() }}</span>
            </div>
            <p class="mt-1 text-sm text-neutral-500">Tạo bởi {{ $load->creator?->fullname ?: $load->creator?->username ?: '-' }} lúc {{ $load->created_at?->format('d/m/Y H:i') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('packages.index') }}" wire:navigate class="rounded-lg border border-neutral-200 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">Danh sách tải</a>
            @if($this->canManage() && $load->canEditOrders())
                <button type="button" wire:click="approveLoad" wire:confirm="Duyệt xuất tải này và chuyển toàn bộ đơn sang Duyệt xuất hàng?" wire:loading.attr="disabled" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">Duyệt xuất</button>
            @endif
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-neutral-500">Số lượng đơn</p>
            <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format($load->orders_count) }}</p>
        </div>
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-neutral-500">Tổng cân tính phí</p>
            <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ number_format((float) $load->total_chargeable_weight, 2, ',', '.') }} kg</p>
        </div>
        <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-neutral-500">Duyệt xuất</p>
            <p class="mt-2 text-sm font-semibold text-neutral-900">{{ $load->approved_at?->format('d/m/Y H:i') ?: 'Chưa duyệt' }}</p>
            <p class="mt-1 text-xs text-neutral-500">{{ $load->approver?->fullname ?: $load->approver?->username ?: '' }}</p>
        </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-5">
            <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="border-b border-neutral-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-neutral-900">Đơn trong tải</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="px-4 py-3">Mã đơn</th>
                                <th class="px-4 py-3">Khách hàng</th>
                                <th class="px-4 py-3">Sale</th>
                                <th class="px-4 py-3">Người nhận</th>
                                <th class="px-4 py-3">Trạng thái</th>
                                <th class="px-4 py-3 text-right">Cân tính phí</th>
                                <th class="px-4 py-3 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse($this->loadOrders as $order)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="font-semibold text-primary-700 hover:text-primary-800">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</a>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($order->customerAccount)
                                            <p class="font-medium text-neutral-900">{{ $order->customerAccount->fullname ?: $order->customerAccount->username ?: '-' }}</p>
                                        @else
                                            <p class="font-medium text-neutral-900">{{ $order->sender['company'] ?? '-' }}</p>
                                            <p class="text-xs text-neutral-500">{{ $order->sender['fullname'] ?? '' }}{{ !empty($order->sender['fullname']) && !empty($order->sender['phone']) ? ' - ' : '' }}{{ $order->sender['phone'] ?? '' }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-neutral-900">{{ $order->sale?->fullname ?: $order->sale?->username ?: '-' }}</p>
                                        @if($order->sale?->code)
                                            <p class="text-xs text-neutral-500">{{ $order->sale->code }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-neutral-900">{{ $order->receiver['company'] ?? $order->receiver['fullname'] ?? '-' }}</p>
                                        <p class="text-xs text-neutral-500">{{ $order->receiver['address'] ?? '' }}</p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $order->bill_status->color() }}">{{ $order->bill_status->label() }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-neutral-700">{{ number_format((float) $order->chargeable_weight, 2, ',', '.') }} kg</td>
                                    <td class="px-4 py-3 text-right">
                                        @if($this->canManage() && $load->canEditOrders())
                                            <button type="button" wire:click="removeOrder({{ $order->id }})" wire:confirm="Gỡ đơn này khỏi tải?" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Gỡ</button>
                                        @elseif(! $load->canEditOrders())
                                            <span class="text-xs text-neutral-400">Đã khóa</span>
                                        @else
                                            <span class="text-xs text-neutral-400">Chỉ xem</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-neutral-500">Tải chưa có đơn hàng.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-100 px-4 py-3">
                    {{ $this->loadOrders->links() }}
                </div>
            </div>

            @if($this->canManage() && $load->canEditOrders())
                <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <h2 class="text-sm font-semibold text-neutral-900">Thêm đơn vào tải</h2>
                        <button type="button" wire:click="addSelectedOrders" wire:loading.attr="disabled" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">Thêm đơn đã chọn</button>
                    </div>
                    <input type="text" wire:model.live.debounce.350ms="keyword" placeholder="Tìm mã đơn, tracking, khách hàng" class="mt-3 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">

                    <div class="mt-3 overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-200 text-sm">
                            <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                                <tr>
                                    <th class="w-12 px-4 py-3"></th>
                                    <th class="px-4 py-3">Mã đơn</th>
                                    <th class="px-4 py-3">Khách hàng</th>
                                    <th class="px-4 py-3 text-right">Cân tính phí</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                @forelse($this->eligibleOrders as $order)
                                    @php($checked = in_array($order->id, $selectedOrderIds, true))
                                    <tr>
                                        <td class="px-4 py-3">
                                            <input type="checkbox" @checked($checked) wire:click="toggleOrder({{ $order->id }})" class="h-4 w-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                                        </td>
                                        <td class="px-4 py-3 font-semibold text-neutral-900">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</td>
                                        <td class="px-4 py-3">
                                        @if($order->customerAccount)
                                            <p class="font-medium text-neutral-900">{{ $order->customerAccount->fullname ?: $order->customerAccount->username ?: '-' }}</p>
                                        @else
                                            <p class="font-medium text-neutral-900">{{ $order->sender['company'] ?? '-' }}</p>
                                            <p class="text-xs text-neutral-500">{{ $order->sender['fullname'] ?? '' }}{{ !empty($order->sender['fullname']) && !empty($order->sender['phone']) ? ' - ' : '' }}{{ $order->sender['phone'] ?? '' }}</p>
                                        @endif
                                    </td>
                                        <td class="px-4 py-3 text-right text-neutral-700">{{ number_format((float) $order->chargeable_weight, 2, ',', '.') }} kg</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-neutral-500">Không có đơn đủ điều kiện.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-neutral-100 pt-3">
                        {{ $this->eligibleOrders->links() }}
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-5">
            @if($this->canManage())
            <form wire:submit="addHistory" class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-neutral-900">Thêm hành trình</h2>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs font-medium text-neutral-600">Thời gian</label>
                        <div wire:ignore x-data x-init="
                            const picker = window.flatpickr($refs.input, {
                                enableTime: true,
                                time_24hr: true,
                                dateFormat: 'Y-m-d H:i',
                                defaultDate: $wire.get('historyForm.thoigian'),
                                onChange: (dates, value) => $wire.set('historyForm.thoigian', value),
                            });

                            Livewire.hook('morph.updated', () => {
                                const value = $wire.get('historyForm.thoigian');
                                if (picker.input.value !== value) picker.setDate(value, false);
                            });
                        ">
                            <input x-ref="input" type="text" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none" autocomplete="off">
                        </div>
                        @error('historyForm.thoigian') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-neutral-600">Trạng thái</label>
                        <input type="text" wire:model="historyForm.trangthai" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none">
                        @error('historyForm.trangthai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-neutral-600">Địa điểm</label>
                        <input type="text" wire:model="historyForm.diadiem" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none">
                        @error('historyForm.diadiem') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs font-medium text-neutral-600">Ghi chú</label>
                        <textarea wire:model="historyForm.ghichu" rows="3" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none"></textarea>
                        @error('historyForm.ghichu') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" wire:loading.attr="disabled" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700 disabled:opacity-60">Thêm hành trình</button>
                </div>
            </form>
            @endif

            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-neutral-900">Lịch sử tải</h2>
                <div class="mt-4 space-y-3">
                    @forelse($this->histories as $history)
                        <div class="rounded-lg border border-neutral-100 bg-neutral-50 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-semibold text-neutral-900">{{ $history->trangthai }}</p>
                                <span class="text-xs text-neutral-500">{{ $history->thoigian?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mt-1 text-xs font-medium text-neutral-600">{{ $history->diadiem }}</p>
                            @if($history->ghichu)
                                <p class="mt-2 text-sm text-neutral-600">{{ $history->ghichu }}</p>
                            @endif
                            <p class="mt-2 text-xs text-neutral-400">{{ $history->user?->fullname ?: $history->user?->username ?: '-' }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-neutral-500">Chưa có lịch sử tải.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

