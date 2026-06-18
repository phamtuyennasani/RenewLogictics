<?php

use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Nhật ký hệ thống')] class extends Component {
    use WithPagination, WithoutUrlPagination;

    public string $keyword = '';

    public string $action = '';

    public ?int $actorId = null;

    public ?string $fromDate = null;

    public ?string $toDate = null;

    public ?int $detailId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        $this->fromDate ??= now()->subDays(30)->format('Y-m-d');
        $this->toDate ??= now()->format('Y-m-d');
    }

    public function updating($property): void
    {
        if (in_array($property, ['keyword', 'action', 'actorId', 'fromDate', 'toDate'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->keyword = '';
        $this->action = '';
        $this->actorId = null;
        $this->fromDate = now()->subDays(30)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
        $this->resetPage();
        $this->dispatch('activity-log-filter-synced', filters: $this->filterState());
    }

    public function setDatePreset(string $preset): void
    {
        $endDate = now();
        $startDate = match ($preset) {
            'today' => now(),
            '7' => now()->subDays(7),
            default => now()->subDays(30),
        };

        $this->fromDate = $startDate->format('Y-m-d');
        $this->toDate = $endDate->format('Y-m-d');
        $this->resetPage();
        $this->dispatch('activity-log-filter-synced', filters: $this->filterState());
    }

    public function showDetail(int $id): void
    {
        $this->detailId = $id;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    /**
     * Nhãn hiển thị cho từng action.
     */
    public function actionOptions(): array
    {
        return [
            'order.delete' => 'Xóa đơn hàng',
            'congno.delete' => 'Xóa công nợ khách hàng',
            'congno_daily.delete' => 'Hủy công nợ đại lý',
            'invoice.delete' => 'Xóa hóa đơn thu',
        ];
    }

    public function actionLabel(?string $action): string
    {
        return $this->actionOptions()[$action] ?? ($action ?? '-');
    }

    public function actionColor(?string $action): string
    {
        return match ($action) {
            'order.delete' => 'bg-red-100 text-red-700',
            'congno.delete' => 'bg-amber-100 text-amber-700',
            'congno_daily.delete' => 'bg-orange-100 text-orange-700',
            'invoice.delete' => 'bg-rose-100 text-rose-700',
            default => 'bg-neutral-100 text-neutral-700',
        };
    }

    #[Computed]
    public function items()
    {
        return $this->baseQuery()
            ->with('actor:id,fullname,username,code')
            ->latest('id')
            ->paginate(20);
    }

    #[Computed]
    public function detail(): ?ActivityLog
    {
        if (! $this->detailId) {
            return null;
        }

        return ActivityLog::with('actor:id,fullname,username,code')->find($this->detailId);
    }

    #[Computed]
    public function actors()
    {
        $ids = ActivityLog::query()->distinct()->pluck('actor_id')->filter();

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code']);
    }

    protected function baseQuery()
    {
        return ActivityLog::query()
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($this->actorId, fn ($q) => $q->where('actor_id', $this->actorId))
            ->when($this->fromDate, fn ($q) => $q->whereDate('created_at', '>=', Carbon::parse($this->fromDate)))
            ->when($this->toDate, fn ($q) => $q->whereDate('created_at', '<=', Carbon::parse($this->toDate)))
            ->when($this->keyword !== '', function ($q) {
                $keyword = '%'.trim($this->keyword).'%';
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('title', 'like', $keyword)
                        ->orWhere('actor_name', 'like', $keyword)
                        ->orWhere('ip_address', 'like', $keyword);
                });
            });
    }

    protected function filterState(): array
    {
        return [
            'keyword' => $this->keyword,
            'action' => $this->action,
            'actorId' => $this->actorId ? (string) $this->actorId : '',
            'fromDate' => $this->fromDate ?: '',
            'toDate' => $this->toDate ?: '',
        ];
    }

    public function render()
    {
        return $this->view();
    }
};
