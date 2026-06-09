<?php

use App\Enums\OrderStatusEnum;
use App\Enums\PickupStatusEnum;
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
        'agencyId' => '',
        'airlineId' => '',
        'transitPartnerId' => '',
        'pickupStatus' => '',
    ];

    public array $pageSizes = [10, 25, 50, 100];
    public array $statusOptions = [];
    public array $mainStatusOptions = [];
    public array $specialStatusOptions = [];
    public array $sales = [];
    public array $customers = [];
    public array $services = [];
    public array $branches = [];
    public array $agencies = [];
    public array $airlines = [];
    public array $transitPartners = [];
    public array $pickupStatusOptions = [];
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
                    'isSpecial' => $status->isSpecial(),
                ];
            })
            ->values()
            ->all();

        $this->mainStatusOptions = collect($this->statusOptions)
            ->reject(fn (array $status) => $status['isSpecial'])
            ->values()
            ->all();

        $this->specialStatusOptions = collect($this->statusOptions)
            ->filter(fn (array $status) => $status['isSpecial'])
            ->values()
            ->all();

        $this->pickupStatusOptions = collect(PickupStatusEnum::cases())
            ->map(fn (PickupStatusEnum $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])
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
            ->when($user->hasRole('sale'), fn ($q) => $q->where('id_sale', $user->id))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $customer) => [
                'id' => $customer->id,
                'label' => trim(($customer->fullname ?: $customer->username).' '.($customer->code ? "({$customer->code})" : '')),
            ])
            ->all();

        $serviceOptions = Cache::remember('order_index_service_options_v2', 3600, fn () => News::query()
            ->whereIn('type', ['dichvuchinh', 'chinhanh', 'daily', 'hangbay', 'doitacchungchuyen'])
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

        $this->agencies = collect($serviceOptions)
            ->where('type', 'daily')
            ->map(fn ($item) => ['id' => $item['id'], 'label' => $item['namevi']])
            ->values()
            ->all();

        $this->airlines = collect($serviceOptions)
            ->where('type', 'hangbay')
            ->map(fn ($item) => ['id' => $item['id'], 'label' => $item['namevi']])
            ->values()
            ->all();

        $this->transitPartners = collect($serviceOptions)
            ->where('type', 'doitacchungchuyen')
            ->map(fn ($item) => ['id' => $item['id'], 'label' => $item['namevi']])
            ->values()
            ->all();

        $this->capabilities = [
            'role' => collect(['admin', 'manager', 'ketoan', 'ops', 'cs', 'sale', 'ctv', 'shipper'])->first(fn ($role) => $user->hasRole($role)),
            'roles' => $user->roles->pluck('name')->values()->all(),
            'canCreate' => $user->can('orders.create'),
            'canDeleteCancelled' => $user->hasRole('admin'),
            'canCancel' => $user->hasAnyRole(['admin', 'manager']),
            'canReceive' => $user->hasAnyRole(['admin', 'ops', 'manager', 'cs']),
            'canApproveExport' => $user->hasAnyRole(['admin', 'manager', 'cs']),
            'canShip' => $user->hasAnyRole(['admin', 'manager', 'cs']),
            'canSeeFinance' => $user->hasAnyRole(['admin', 'manager', 'ketoan']),
            'canSeeExtraFilters' => $user->hasAnyRole(['admin', 'cs']),
            'currentSaleId' => $user->hasRole('sale') ? $user->id : null,
        ];

        if ($user->hasRole('sale')) {
            $this->filters['saleId'] = (string) $user->id;
        }
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
