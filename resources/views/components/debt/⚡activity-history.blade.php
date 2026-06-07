<?php

use App\Enums\DebtStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoDaiLy;
use App\Models\DebtActivityLog;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public CongNo|CongNoDaiLy $debt;

    #[On('debt-activity-updated')]
    public function refreshHistory(): void
    {
        unset($this->activities, $this->latestActivities);
    }

    #[Computed]
    public function activities(): Collection
    {
        return $this->debt->activityLogs()
            ->with('actor:id,fullname,username,code')
            ->latest()
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function latestActivities(): Collection
    {
        return $this->activities->take(4);
    }

    public function actorName(?object $actor): string
    {
        if (! $actor) {
            return 'Hệ thống';
        }

        $name = $actor->fullname ?: $actor->username ?: 'Người dùng';
        return filled($actor->code) ? $name.' · '.$actor->code : $name;
    }

    public function statusLabel(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        return DebtStatusEnum::tryFrom($status)?->label() ?: $status;
    }

    public function metaSummary(DebtActivityLog $activity): array
    {
        $meta = $activity->metadata ?: [];
        $items = [];

        if (isset($meta['invoice_code'])) {
            $items[] = 'HĐ: '.$meta['invoice_code'];
        }

        if (isset($meta['order_code'])) {
            $items[] = 'Order: '.$meta['order_code'];
        }

        if (isset($meta['added_count'])) {
            $items[] = 'Thêm '.$meta['added_count'].' order';
        }

        if (isset($meta['reference'])) {
            $items[] = 'Tham chiếu: '.$meta['reference'];
        } elseif (isset($meta['reference_to'])) {
            $items[] = 'Tham chiếu: '.$meta['reference_to'];
        }

        if (isset($meta['invoice_number'])) {
            $items[] = 'Số HĐĐT: '.$meta['invoice_number'];
        }

        if (isset($meta['provider'])) {
            $items[] = 'Provider: '.strtoupper((string) $meta['provider']);
        }

        if (isset($meta['amount'])) {
            $items[] = 'Số tiền: '.$this->money($meta['amount']);
        } elseif (isset($meta['total_amount'])) {
            $items[] = 'Tổng tiền: '.$this->money($meta['total_amount']);
        }

        return array_slice($items, 0, 4);
    }

    public function money(mixed $value): string
    {
        return number_format((float) $value, 0, ',', '.').' đ';
    }

    public function render()
    {
        return $this->view();
    }
};

?>

<section class="rounded-2xl border border-neutral-200 bg-white shadow-xs">
    <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
        <div>
            <h2 class="font-bold text-neutral-950">Lịch sử thao tác</h2>
            <p class="mt-1 text-sm text-neutral-500">Theo dõi người dùng đã thao tác trên công nợ.</p>
        </div>
        @if($this->activities->count() > 4)
            <flux:modal.trigger name="debt-activity-history-{{ $debt->id }}">
                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-neutral-200 text-neutral-500 transition hover:border-primary-200 hover:bg-primary-50 hover:text-primary-600" aria-label="Xem chi tiết lịch sử thao tác">
                    <flux:icon.eye class="size-4" />
                </button>
            </flux:modal.trigger>
        @endif
    </div>

    <div class="p-5">
        @if($this->latestActivities->isNotEmpty())
            <div class="space-y-4">
                @foreach($this->latestActivities as $activity)
                    <div class="flex gap-3">
                        <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-semibold text-neutral-900">{{ $activity->title }}</p>
                                @if($this->statusLabel($activity->from_status) || $this->statusLabel($activity->to_status))
                                    <span class="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-600">
                                        {{ $this->statusLabel($activity->from_status) ?: '-' }} → {{ $this->statusLabel($activity->to_status) ?: '-' }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-neutral-500">
                                {{ $this->actorName($activity->actor) }} · {{ $activity->created_at?->format('d/m/Y H:i') }}
                            </p>
                            @if($activity->note)
                                <p class="mt-1 text-xs text-neutral-600">{{ $activity->note }}</p>
                            @endif
                            @if($this->metaSummary($activity))
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($this->metaSummary($activity) as $item)
                                        <span class="rounded-lg bg-neutral-50 px-2 py-1 text-[11px] font-semibold text-neutral-600">{{ $item }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-neutral-500">Chưa có lịch sử thao tác.</p>
        @endif
    </div>

    <flux:modal name="debt-activity-history-{{ $debt->id }}" class="w-full max-w-2xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Lịch sử thao tác công nợ</flux:heading>
                <flux:subheading>Danh sách người dùng đã thao tác trên công nợ này.</flux:subheading>
            </div>

            <div class="max-h-[70vh] space-y-3 overflow-y-auto pr-2">
                @foreach($this->activities as $activity)
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-neutral-900">{{ $activity->title }}</p>
                            <p class="text-xs text-neutral-500">{{ $activity->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                        <p class="mt-1 text-sm text-neutral-600">{{ $this->actorName($activity->actor) }}</p>
                        @if($this->statusLabel($activity->from_status) || $this->statusLabel($activity->to_status))
                            <p class="mt-1 text-xs font-semibold text-neutral-500">
                                Trạng thái: {{ $this->statusLabel($activity->from_status) ?: '-' }} → {{ $this->statusLabel($activity->to_status) ?: '-' }}
                            </p>
                        @endif
                        @if($activity->note)
                            <p class="mt-2 text-sm text-neutral-700">{{ $activity->note }}</p>
                        @endif
                        @if($this->metaSummary($activity))
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach($this->metaSummary($activity) as $item)
                                    <span class="rounded-lg bg-white px-2 py-1 text-[11px] font-semibold text-neutral-600">{{ $item }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </flux:modal>
</section>
