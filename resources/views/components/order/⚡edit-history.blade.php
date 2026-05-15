<?php

use App\Models\Order;
use App\Models\OrderHistory;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public Order $order;

    #[On('order-history-updated')]
    public function refreshHistory(): void
    {
        unset($this->histories, $this->latestBySection);
    }

    #[Computed]
    public function histories()
    {
        return $this->order->histories()
            ->with('user.roles:id,name')
            ->latest()
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function latestBySection()
    {
        return $this->histories
            ->unique(fn (OrderHistory $history) => $this->payload($history)['label'])
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
        ];
    }

    public function line(OrderHistory $history): string
    {
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
        @if($this->histories->isNotEmpty())
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
                @foreach($this->latestBySection as $history)
                    <div class="flex gap-3">
                        <div class="mt-1.5 h-2 w-2 rounded-full bg-primary-500"></div>
                        <div class="min-w-0">
                            <p class="text-sm leading-5 text-neutral-700">
                                <span class="font-semibold text-neutral-900">{{ $this->userName($history->user) }}</span>
                                chỉnh {{ $this->payload($history)['label'] }}
                            </p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ $history->created_at?->format('d/m/Y H:i') }}</p>
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
                @foreach($this->histories as $history)
                    <div class="flex gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                        <div class="mt-1.5 h-2 w-2 flex-none rounded-full bg-primary-500"></div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-neutral-900">{{ $this->line($history) }}</p>
                            <p class="mt-1 text-xs text-neutral-500">{{ $history->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:modal>
</section>
