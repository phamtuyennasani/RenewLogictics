<?php

use App\Enums\OrderStatusEnum;
use App\Models\News;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

new class extends Component
{
    public string $pageTitle = 'Danh sách order';

    public array $filters = [
        'status' => '',
        'fromDate' => '',
        'toDate' => '',
        'saleId' => '',
        'customerId' => '',
        'serviceId' => '',
        'branchId' => '',
    ];

    public array $pageSizes = [10, 25, 50, 100];
    public array $statusOptions = [];
    public array $sales = [];
    public array $customers = [];
    public array $services = [];
    public array $branches = [];
    public array $capabilities = [];
    public array $routes = [];

    public function mount(): void
    {
        $user = auth()->user();

        $this->setDefaultDateRange();

        $this->routes = [
            'datatable' => route('orders.datatable'),
            'create' => route('orders.create'),
            'bulkStatus' => route('orders.bulk-status'),
            'deleteCancelled' => route('orders.delete-cancelled'),
            'export' => route('orders.export'),
            'customers' => route('orders.customers'),
        ];

        $this->statusOptions = collect(OrderStatusEnum::cases())
            ->map(function (OrderStatusEnum $status) {
                $classes = explode(' ', $status->color());

                return [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color(),
                    'bgClass' => collect($classes)->first(fn ($class) => str_starts_with($class, 'bg-')) ?? 'bg-neutral-100',
                    'textClass' => collect($classes)->first(fn ($class) => str_starts_with($class, 'text-')) ?? 'text-neutral-700',
                ];
            })
            ->values()
            ->all();

        $this->sales = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'SALE'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $sale) => [
                'id' => $sale->id,
                'label' => trim(($sale->fullname ?: $sale->username).' '.($sale->code ? "({$sale->code})" : '')),
            ])
            ->all();

        $this->customers = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'ctv'))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $customer) => [
                'id' => $customer->id,
                'label' => trim(($customer->fullname ?: $customer->username).' '.($customer->code ? "({$customer->code})" : '')),
            ])
            ->all();

        $serviceOptions = Cache::remember('order_index_service_options', 3600, fn () => News::query()
            ->whereIn('type', ['dichvuchinh', 'chinhanh'])
            ->orderBy('numb')
            ->get(['id', 'namevi', 'type'])
            ->toArray());

        $this->services = collect($serviceOptions)
            ->where('type', 'dichvuchinh')
            ->map(fn ($item) => ['id' => $item['id'], 'label' => $item['namevi']])
            ->values()
            ->all();

        $this->branches = collect($serviceOptions)
            ->where('type', 'chinhanh')
            ->map(fn ($item) => ['id' => $item['id'], 'label' => $item['namevi']])
            ->values()
            ->all();

        $this->capabilities = [
            'canCreate' => $user->can('orders.create') || $user->hasAnyRole(['admin', 'manager', 'sale', 'SALE', 'cs']),
            'canDeleteCancelled' => $user->hasAnyRole(['admin', 'manager']),
            'canSeeFinance' => $user->hasAnyRole(['admin', 'manager', 'ketoan']),
        ];
    }

    public function resetFilters(): void
    {
        $this->filters = array_fill_keys(array_keys($this->filters), '');
        $this->setDefaultDateRange();
    }

    protected function setDefaultDateRange(): void
    {
        $today = Carbon::today();

        $this->filters['fromDate'] = $today->copy()->subDays(30)->toDateString();
        $this->filters['toDate'] = $today->toDateString();
    }
};
