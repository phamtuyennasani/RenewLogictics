<?php

use App\Actions\ShipmentLoad\CreateShipmentLoadAction;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Tạo tải hàng')] class extends Component
{
    use WithPagination, WithoutUrlPagination;

    public string $pageTitle = 'Tạo tải hàng';
    public string $keyword = '';
    public array $selectedOrderIds = [];

    public function updating($property): void
    {
        if ($property === 'keyword') {
            $this->resetPage();
        }
    }

    public function toggleOrder(int $orderId): void
    {
        if (in_array($orderId, $this->selectedOrderIds, true)) {
            $this->selectedOrderIds = array_values(array_diff($this->selectedOrderIds, [$orderId]));
            return;
        }

        $this->selectedOrderIds[] = $orderId;
        $this->selectedOrderIds = array_values(array_unique($this->selectedOrderIds));
    }

    public function createLoad(): mixed
    {
        try {
            $load = CreateShipmentLoadAction::execute(
                auth()->id(),
                $this->selectedOrderIds ?: null,
            );
        } catch (\RuntimeException $exception) {
            Flux::toast(heading: 'Không thể tạo tải', text: $exception->getMessage(), variant: 'warning');
            return null;
        }

        Flux::toast(
            heading: 'Đã tạo tải',
            text: $this->selectedOrderIds !== []
                ? 'Tải '.$load->code.' đã được tạo với '.count($this->selectedOrderIds).' đơn.'
                : 'Tải '.$load->code.' đã được tạo (không có đơn).',
            variant: 'success',
        );

        return $this->redirectRoute('packages.show', ['load' => $load->id], navigate: true);
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
            ->paginate(15);
    }

    public function getSelectedSummaryProperty(): array
    {
        if ($this->selectedOrderIds === []) {
            return ['count' => 0, 'weight' => 0];
        }

        $weight = (float) DB::table('order_package')
            ->whereIn('id_order', $this->selectedOrderIds)
            ->sum('c_weight');

        return [
            'count' => count($this->selectedOrderIds),
            'weight' => $weight,
        ];
    }
};
?>

<div class="space-y-5">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-neutral-900">Tạo tải hàng</h1>
            <p class="mt-1 text-sm text-neutral-500">Chọn các đơn đang ở trạng thái Đã nhận hàng để gom vào một tải.</p>
        </div>
        <a href="{{ route('packages.index') }}" wire:navigate class="rounded-lg border border-neutral-200 px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">Quay lại</a>
    </div>

    <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
        <div class="space-y-4">
            <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                <input type="text" wire:model.live.debounce.350ms="keyword" placeholder="Tìm mã đơn, tracking, khách hàng" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none">
            </div>

            <div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-200 text-sm">
                        <thead class="bg-neutral-50 text-left text-xs font-semibold uppercase text-neutral-500">
                            <tr>
                                <th class="w-12 px-4 py-3"></th>
                                <th class="px-4 py-3">Mã đơn</th>
                                <th class="px-4 py-3">Khách hàng</th>
                                <th class="px-4 py-3">Sale</th>
                                <th class="px-4 py-3">Ngày nhận</th>
                                <th class="px-4 py-3 text-right">Cân tính phí</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse($this->eligibleOrders as $order)
                                @php($checked = in_array($order->id, $selectedOrderIds, true))
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" @checked($checked) wire:click="toggleOrder({{ $order->id }})" class="h-4 w-4 rounded border-neutral-300 text-primary-600 focus:ring-primary-500">
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-neutral-900">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</td>
                                    <td class="px-4 py-3 text-neutral-600">{{ $order->customerAccount?->fullname ?: $order->customerAccount?->username ?: '-' }}</td>
                                    <td class="px-4 py-3 text-neutral-600">{{ $order->sale?->fullname ?: $order->sale?->username ?: '-' }}</td>
                                    <td class="px-4 py-3 text-neutral-600">{{ $order->ngaynhanhang?->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right text-neutral-700">{{ number_format((float) $order->chargeable_weight, 2, ',', '.') }} kg</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-neutral-500">Không có đơn đủ điều kiện.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-100 px-4 py-3">
                    {{ $this->eligibleOrders->links() }}
                </div>
            </div>
        </div>

        <div class="h-fit rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-neutral-900">Tải sắp tạo</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Số đơn đã chọn</dt>
                    <dd class="font-semibold text-neutral-900">{{ number_format($this->selectedSummary['count']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-neutral-500">Tổng cân tính phí</dt>
                    <dd class="font-semibold text-neutral-900">{{ number_format($this->selectedSummary['weight'], 2, ',', '.') }} kg</dd>
                </div>
            </dl>
            <button type="button" wire:click="createLoad" wire:loading.attr="disabled" class="mt-5 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                Tạo tải hàng
            </button>
            <p class="mt-3 text-xs leading-5 text-neutral-500">Có thể tạo tải trống, sau đó thêm đơn trong màn hình chi tiết tải.</p>
        </div>
    </div>
</div>

