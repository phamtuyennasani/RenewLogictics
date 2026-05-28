<?php

use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\ShipmentLoadHistory;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Order $order;

    #[On('order-history-updated')]
    public function refreshHistory(): void
    {
        unset($this->allHistories, $this->latestBySection);
    }

    #[Computed]
    public function allHistories(): Collection
    {
        $orderHistories = $this->order->histories()
            ->with('user.roles:id,name')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (OrderHistory $h) => (object) [
                'type' => 'order_history',
                'tracking_source' => in_array($h->action, ['tracking_history', 'tracking_status_auto']) ? 'tracking_more' : null,
                'id' => $h->id,
                'content' => $h->content,
                'created_at' => $h->created_at,
                'user' => $h->user,
                'orderHistory' => $h,
            ]);

        $shipmentHistories = $this->order->shipmentLoadHistories()
            ->with(['user:id,fullname,username,code', 'shipmentLoad:id,code'])
            ->latest('thoigian')
            ->limit(20)
            ->get()
            ->map(fn (ShipmentLoadHistory $h) => (object) [
                'type' => 'shipment_load_history',
                'id' => $h->id,
                'shipment_load_history_id' => $h->id,
                'shipment_load_id' => $h->shipment_load_id,
                'shipment_load_code' => $h->shipmentLoad?->code,
                'thoigian' => $h->thoigian,
                'diadiem' => $h->diadiem,
                'trangthai' => $h->trangthai,
                'ghichu' => $h->ghichu,
                'created_at' => $h->created_at,
                'user' => $h->user,
                'orderHistory' => null,
            ]);

        return $orderHistories->merge($shipmentHistories)
            ->sortByDesc(fn ($item) => $item->thoigian ?? $item->created_at)
            ->values();
    }

    #[Computed]
    public function latestBySection()
    {
        return $this->allHistories
            ->unique(fn ($item) => match (true) {
                $item->type === 'shipment_load_history' => 'shipment_load_'.$item->shipment_load_id,
                $item->tracking_source === 'tracking_more' => 'tracking_more_'.$item->orderHistory?->action,
                default => $item->orderHistory ? ($this->payload($item->orderHistory)['label'] ?? 'unknown') : 'unknown',
            })
            ->values();
    }

    public function userName(?object $user): string
    {
        return $user?->fullname ?: $user?->username ?: 'Hệ thống';
    }

    public function payload(OrderHistory $history): array
    {
        $payload = json_decode((string) $history->content, true);

        if (! is_array($payload)) {
            return [
                'label' => $history->action ?: 'thao tác',
                'summary' => $history->content ?: 'cập nhật đơn hàng',
            ];
        }

        return [
            'label' => $payload['label'] ?? ($history->action ?: 'thao tác'),
            'summary' => $payload['summary'] ?? 'cập nhật đơn hàng',
            'loadCode' => $payload['shipment_load_code'] ?? null,
            'loadId' => $payload['shipment_load_id'] ?? null,
            'loadHistoryId' => $payload['shipment_load_history_id'] ?? null,
        ];
    }

    public function isFromLoad(object $item): bool
    {
        return $item->type === 'shipment_load_history';
    }

    public function isTrackingMore(object $item): bool
    {
        return $item->type === 'order_history' && ($item->tracking_source ?? null) === 'tracking_more';
    }

    public function dotColor(object $item): string
    {
        if ($this->isFromLoad($item)) {
            return 'bg-purple-500';
        }
        if ($this->isTrackingMore($item)) {
            return 'bg-sky-500';
        }
        return 'bg-primary-500';
    }

    public function isSyncedFromLoad(OrderHistory $history): bool
    {
        return $history->action === 'shipment_load_history';
    }

    public function line(object $item): string
    {
        if ($item->type === 'shipment_load_history') {
            return "Hành trình từ tải {$item->shipment_load_code}";
        }

        $history = $item->orderHistory;

        if ($this->isSyncedFromLoad($history)) {
            $loadCode = $this->payload($history)['loadCode'] ?? '?';
            return "Đồng bộ từ tải {$loadCode}";
        }

        $payload = $this->payload($history);

        if (! $history->user) {
            return 'Hệ thống chỉnh sửa thông tin '.$payload['label'];
        }

        $code = filled($history->user->code) ? ' '.$history->user->code : '';
        $roles = $history->user->roles
            ->pluck('name')
            ->filter()
            ->implode(', ');
        $roleText = $roles !== '' ? ' - '.$roles : '';

        return 'VAU Trans'.$code.$roleText.' chỉnh sửa thông tin '.$payload['label'];
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="text-sm font-semibold text-neutral-900 uppercase">Lịch sử chỉnh sửa</h2>
            <p class="text-xs text-neutral-500">Lần chỉnh sửa cuối theo từng nhóm thông tin</p>
        </div>
        @if($this->allHistories->isNotEmpty())
            <flux:modal.trigger name="order-edit-history">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Xem toàn bộ lịch sử chỉnh sửa">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </button>
            </flux:modal.trigger>
        @endif
    </div>

    <div class="p-5">
        @if($this->latestBySection->isNotEmpty())
            <div class="space-y-3">
                @foreach($this->latestBySection as $item)
                    <div class="flex gap-3">
                        <div class="mt-1.5 h-2 w-2 rounded-full {{ $this->dotColor($item) }}"></div>
                        <div class="min-w-0">
                            <p class="text-sm leading-5 text-neutral-700">
                                @if($this->isFromLoad($item))
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold text-purple-700">Hành trình từ tải</span>
                                    <span class="ml-2 font-semibold text-neutral-900">{{ $item->shipment_load_code }}</span>
                                @elseif($this->isTrackingMore($item))
                                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-700">Tracking API</span>
                                    <span class="ml-2 text-neutral-700">{{ $this->payload($item->orderHistory)['summary'] }}</span>
                                @else
                                    <span class="font-semibold text-neutral-900">{{ $this->userName($item->user) }}</span>
                                    chỉnh {{ $this->payload($item->orderHistory)['label'] }}
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ ($item->thoigian ?? $item->created_at)?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-neutral-500">Chưa có lịch sử chỉnh sửa.</p>
        @endif
    </div>

    <flux:modal name="order-edit-history" class="w-full max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Lịch sử chỉnh sửa đơn hàng</flux:heading>
                <flux:subheading>Danh sách người dùng đã chỉnh sửa các nhóm thông tin của đơn.</flux:subheading>
            </div>

            <div class="max-h-[70vh] space-y-3 overflow-y-auto pr-2">
                @foreach($this->allHistories as $item)
                    <div class="flex gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                        <div class="mt-1.5 h-2 w-2 flex-none rounded-full {{ $this->dotColor($item) }}"></div>
                        <div class="min-w-0">
                            @if($this->isFromLoad($item))
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-[11px] font-semibold text-purple-700">Hành trình từ tải</span>
                                    <span class="text-sm font-semibold text-neutral-900">{{ $item->shipment_load_code }}</span>
                                </div>
                                <p class="mt-1 text-sm font-medium text-neutral-800">{{ $item->trangthai }}</p>
                                <p class="mt-0.5 text-sm text-neutral-600">{{ $item->diadiem }}</p>
                                @if($item->ghichu)
                                    <p class="mt-1 text-sm text-neutral-500">{{ $item->ghichu }}</p>
                                @endif
                            @elseif($this->isTrackingMore($item))
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-[11px] font-semibold text-sky-700">Tracking API</span>
                                    <span class="text-sm font-medium text-neutral-800">{{ $this->payload($item->orderHistory)['summary'] }}</span>
                                </div>
                            @else
                                <p class="text-sm font-medium text-neutral-900">{{ $this->line($item) }}</p>
                            @endif
                            <p class="mt-1 text-xs text-neutral-500">{{ ($item->thoigian ?? $item->created_at)?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:modal>
</section>
