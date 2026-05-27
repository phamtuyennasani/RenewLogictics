<?php

namespace App\Services\Reports;

use App\Enums\DebtStatusEnum;
use App\Enums\InvoicePaymentStatusEnum;
use App\Enums\OrderStatusEnum;
use App\Models\CongNo;
use App\Models\CongNoDaiLy;
use App\Models\CongNoPayment;
use App\Models\News;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SystemStatisticsService
{
    public function report(User $user, array $filters = []): array
    {
        $scope = $this->scopeFor($user);
        $dateRange = $this->dateRange($filters);

        $orders = $this->ordersQuery($user, $filters, $dateRange)
            ->with([
                'sale:id,fullname,username,code',
                'customerAccount:id,fullname,username,code,options',
            ])
            ->get([
                'id',
                'id_bill',
                'id_sale',
                'id_customer',
                'bill_status',
                'service',
                'payment_cuocban',
                'payment_cuocvon',
                'payment_loinhuan',
                'customer_payment_status',
                'created_at',
                'updated_at',
            ]);

        $customerDebts = $this->customerDebtsQuery($user, $filters, $dateRange)
            ->get(['id', 'sohoadon', 'id_sale', 'id_customer', 'id_ctv', 'status', 'total_cuocban', 'paid_amount', 'hanthanhtoan', 'created_at']);

        $incomeInvoices = $this->incomeInvoicesQuery($user, $filters, $dateRange)
            ->get(['id', 'id_congno', 'id_order', 'amount', 'status', 'method', 'payment_provider', 'created_at', 'paid_at']);

        $agencyDebts = $scope['canSeeFinance']
            ? $this->agencyDebtsQuery($user, $filters, $dateRange)
                ->get(['id', 'sohoadon', 'id_daily', 'id_sale', 'status', 'total_cuocvon', 'paid_amount', 'hanthanhtoan', 'created_at'])
            : collect();

        return [
            'scope' => $scope,
            'dateRange' => [
                'from' => $dateRange['from']->toDateString(),
                'to' => $dateRange['to']->toDateString(),
            ],
            'orders' => $this->orderSummary($orders, $dateRange),
            'finance' => $this->financeSummary($orders, $scope['canSeeFinance']),
            'customerDebt' => $this->customerDebtSummary($customerDebts),
            'agencyDebt' => $this->agencyDebtSummary($agencyDebts),
            'invoices' => $this->invoiceSummary($incomeInvoices),
            'charts' => [
                'orderTimeline' => $this->orderTimeline($orders, $dateRange),
                'revenueTimeline' => $this->revenueTimeline($orders, $dateRange),
                'orderStatuses' => $this->orderStatusChart($orders),
                'invoiceStatuses' => $this->invoiceStatusChart($incomeInvoices),
            ],
            'rankings' => [
                'sales' => $this->topSales($orders),
                'customers' => $this->topCustomers($orders),
                'services' => $this->topServices($orders),
            ],
            'attention' => [
                'unpaidDeliveredOrders' => $this->unpaidDeliveredOrders($orders),
                'staleOrders' => $this->staleOrders($orders),
                'openInvoices' => $this->openInvoices($incomeInvoices),
                'overdueCustomerDebts' => $this->overdueCustomerDebts($customerDebts),
            ],
        ];
    }

    public function filterOptions(User $user): array
    {
        $scope = $this->scopeFor($user);

        $sales = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['sale', 'SALE']))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $sale) => [
                'id' => $sale->id,
                'label' => $this->userLabel($sale),
            ])
            ->values()
            ->all();

        $customers = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['ctv', 'CTV']))
            ->when($scope['type'] === 'sale', fn ($q) => $q->where('id_sale', $user->id))
            ->when($scope['type'] === 'ctv', fn ($q) => $q->whereKey($user->id))
            ->orderBy('fullname')
            ->get(['id', 'fullname', 'username', 'code'])
            ->map(fn (User $customer) => [
                'id' => $customer->id,
                'label' => $this->userLabel($customer),
            ])
            ->values()
            ->all();

        $news = News::query()
            ->whereIn('type', ['dichvuchinh', 'chinhanh', 'daily'])
            ->orderBy('numb')
            ->get(['id', 'namevi', 'type'])
            ->groupBy('type');

        return [
            'sales' => $sales,
            'customers' => $customers,
            'services' => $this->newsOptions($news->get('dichvuchinh', collect())),
            'branches' => $this->newsOptions($news->get('chinhanh', collect())),
            'agencies' => $this->newsOptions($news->get('daily', collect())),
        ];
    }

    public function scopeFor(User $user): array
    {
        $isSale = $this->hasRole($user, ['sale', 'SALE']);
        $isCtv = $this->hasRole($user, ['ctv', 'CTV']);

        return [
            'type' => $isSale ? 'sale' : ($isCtv ? 'ctv' : 'all'),
            'label' => $isSale ? 'Dữ liệu sale của tôi' : ($isCtv ? 'Dữ liệu CTV của tôi' : 'Toàn hệ thống'),
            'canUseSaleFilter' => ! $isSale && ! $isCtv,
            'canUseCustomerFilter' => ! $isCtv,
            'canSeeFinance' => ! $isSale && ! $isCtv,
        ];
    }

    protected function ordersQuery(User $user, array $filters, array $dateRange): Builder
    {
        return Order::query()
            ->whereDate('created_at', '>=', $dateRange['from'])
            ->whereDate('created_at', '<=', $dateRange['to'])
            ->tap(fn (Builder $query) => $this->applyOrderScope($query, $user, $filters))
            ->when(filled($filters['serviceId'] ?? null), fn ($q) => $q->where('service->id_dichvu', (int) $filters['serviceId']))
            ->when(filled($filters['branchId'] ?? null), fn ($q) => $q->where('service->id_chinhanh_nhanhang', (int) $filters['branchId']))
            ->when(filled($filters['agencyId'] ?? null), fn ($q) => $q->where('service->id_daily', (int) $filters['agencyId']));
    }

    protected function customerDebtsQuery(User $user, array $filters, array $dateRange): Builder
    {
        return CongNo::query()
            ->whereDate('created_at', '>=', $dateRange['from'])
            ->whereDate('created_at', '<=', $dateRange['to'])
            ->tap(fn (Builder $query) => $this->applyDebtScope($query, $user, $filters, 'customer'));
    }

    protected function agencyDebtsQuery(User $user, array $filters, array $dateRange): Builder
    {
        return CongNoDaiLy::query()
            ->whereDate('created_at', '>=', $dateRange['from'])
            ->whereDate('created_at', '<=', $dateRange['to'])
            ->tap(fn (Builder $query) => $this->applyDebtScope($query, $user, $filters, 'agency'))
            ->when(filled($filters['agencyId'] ?? null), fn ($q) => $q->where('id_daily', (int) $filters['agencyId']));
    }

    protected function incomeInvoicesQuery(User $user, array $filters, array $dateRange): Builder
    {
        return CongNoPayment::query()
            ->whereDate('created_at', '>=', $dateRange['from'])
            ->whereDate('created_at', '<=', $dateRange['to'])
            ->where(function (Builder $query): void {
                $query->whereNull('loai_hoa_don')->orWhere('loai_hoa_don', 'thu');
            })
            ->when($this->hasRole($user, ['sale', 'SALE']), function (Builder $query) use ($user): void {
                $query->where(function (Builder $sub) use ($user): void {
                    $sub->whereHas('order', fn ($order) => $order->where('id_sale', $user->id))
                        ->orWhereHas('congNo', fn ($debt) => $debt->where('id_sale', $user->id));
                });
            })
            ->when($this->hasRole($user, ['ctv', 'CTV']), function (Builder $query) use ($user): void {
                $query->where(function (Builder $sub) use ($user): void {
                    $sub->whereHas('order', fn ($order) => $order->where('id_customer', $user->id))
                        ->orWhereHas('congNo', fn ($debt) => $debt->where('id_customer', $user->id)->orWhere('id_ctv', $user->id));
                });
            })
            ->when(! $this->hasRole($user, ['sale', 'SALE', 'ctv', 'CTV']) && filled($filters['saleId'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $sub) use ($filters): void {
                    $sub->whereHas('order', fn ($order) => $order->where('id_sale', (int) $filters['saleId']))
                        ->orWhereHas('congNo', fn ($debt) => $debt->where('id_sale', (int) $filters['saleId']));
                });
            })
            ->when(! $this->hasRole($user, ['ctv', 'CTV']) && filled($filters['customerId'] ?? null), function (Builder $query) use ($filters): void {
                $query->where(function (Builder $sub) use ($filters): void {
                    $sub->whereHas('order', fn ($order) => $order->where('id_customer', (int) $filters['customerId']))
                        ->orWhereHas('congNo', fn ($debt) => $debt->where('id_customer', (int) $filters['customerId'])->orWhere('id_ctv', (int) $filters['customerId']));
                });
            });
    }

    protected function applyOrderScope(Builder $query, User $user, array $filters): void
    {
        if ($this->hasRole($user, ['sale', 'SALE'])) {
            $query->where('id_sale', $user->id);
            return;
        }

        if ($this->hasRole($user, ['ctv', 'CTV'])) {
            $query->where('id_customer', $user->id);
            return;
        }

        $query
            ->when(filled($filters['saleId'] ?? null), fn ($q) => $q->where('id_sale', (int) $filters['saleId']))
            ->when(filled($filters['customerId'] ?? null), fn ($q) => $q->where('id_customer', (int) $filters['customerId']));
    }

    protected function applyDebtScope(Builder $query, User $user, array $filters, string $type): void
    {
        if ($this->hasRole($user, ['sale', 'SALE'])) {
            $query->where('id_sale', $user->id);
            return;
        }

        if ($type === 'customer' && $this->hasRole($user, ['ctv', 'CTV'])) {
            $query->where(fn ($q) => $q->where('id_customer', $user->id)->orWhere('id_ctv', $user->id));
            return;
        }

        if (! $this->hasRole($user, ['ctv', 'CTV']) && filled($filters['customerId'] ?? null) && $type === 'customer') {
            $query->where(fn ($q) => $q->where('id_customer', (int) $filters['customerId'])->orWhere('id_ctv', (int) $filters['customerId']));
        }

        if (filled($filters['saleId'] ?? null)) {
            $query->where('id_sale', (int) $filters['saleId']);
        }
    }

    protected function orderSummary(Collection $orders, array $dateRange): array
    {
        $delivered = $orders->where('bill_status', OrderStatusEnum::DA_GIAO)->count();
        $cancelled = $orders->where('bill_status', OrderStatusEnum::HUY)->count();
        $returned = $orders->where('bill_status', OrderStatusEnum::RETURN_ORDER)->count();

        return [
            'total' => $orders->count(),
            'new' => $orders->where('bill_status', OrderStatusEnum::MOI_TAO)->count(),
            'processing' => $orders->reject(fn (Order $order) => in_array($order->bill_status, [
                OrderStatusEnum::DA_GIAO,
                OrderStatusEnum::HUY,
                OrderStatusEnum::RETURN_ORDER,
            ], true))->count(),
            'delivered' => $delivered,
            'cancelled' => $cancelled,
            'returned' => $returned,
            'caution' => $orders->where('bill_status', OrderStatusEnum::CAUTION)->count(),
            'deliveryRate' => $orders->count() > 0 ? round(($delivered * 100) / $orders->count(), 1) : 0,
            'cancelRate' => $orders->count() > 0 ? round((($cancelled + $returned) * 100) / $orders->count(), 1) : 0,
            'avgPerDay' => round($orders->count() / max(1, $dateRange['from']->diffInDays($dateRange['to']) + 1), 1),
        ];
    }

    protected function financeSummary(Collection $orders, bool $canSeeFinance): array
    {
        $saleTotal = $orders->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', data_get($order->payment_cuocban, 'tongcuoc', 0))));
        $costTotal = $orders->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocvon, 'total_tongcuoc', data_get($order->payment_cuocvon, 'tongcuoc', 0))));
        $profitTotal = $orders->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_loinhuan, 'loinhuan', data_get($order->payment_loinhuan, 'loinhuantamtinh', 0))));

        return [
            'saleTotal' => $saleTotal,
            'costTotal' => $canSeeFinance ? $costTotal : null,
            'profitTotal' => $canSeeFinance ? $profitTotal : null,
            'margin' => $canSeeFinance && $saleTotal > 0 ? round(($profitTotal * 100) / $saleTotal, 1) : null,
        ];
    }

    protected function customerDebtSummary(Collection $debts): array
    {
        $total = $debts->sum(fn (CongNo $debt) => (float) $debt->total_cuocban);
        $paid = $debts->sum(fn (CongNo $debt) => (float) $debt->paid_amount);
        $overdue = $debts->filter(fn (CongNo $debt) => $this->isOverdue($debt->hanthanhtoan, $debt->status));

        return [
            'count' => $debts->count(),
            'total' => $total,
            'paid' => $paid,
            'remaining' => max(0, $total - $paid),
            'overdueCount' => $overdue->count(),
            'overdueRemaining' => $overdue->sum(fn (CongNo $debt) => max(0, (float) $debt->total_cuocban - (float) $debt->paid_amount)),
        ];
    }

    protected function agencyDebtSummary(Collection $debts): array
    {
        $total = $debts->sum(fn (CongNoDaiLy $debt) => (float) $debt->total_cuocvon);
        $paid = $debts->sum(fn (CongNoDaiLy $debt) => (float) $debt->paid_amount);

        return [
            'count' => $debts->count(),
            'total' => $total,
            'paid' => $paid,
            'remaining' => max(0, $total - $paid),
        ];
    }

    protected function invoiceSummary(Collection $invoices): array
    {
        $paid = $invoices->where('status', InvoicePaymentStatusEnum::DA_THANH_TOAN);
        $cancelled = $invoices->where('status', InvoicePaymentStatusEnum::HUY);
        $rejected = $invoices->where('status', InvoicePaymentStatusEnum::KHONG_CHAP_NHAN);
        $pending = $invoices->filter(fn (CongNoPayment $invoice) => $invoice->status?->isPendingPayment() ?? false);

        return [
            'count' => $invoices->count(),
            'total' => $invoices->sum(fn (CongNoPayment $invoice) => (float) $invoice->amount),
            'paid' => $paid->sum(fn (CongNoPayment $invoice) => (float) $invoice->amount),
            'pending' => $pending->sum(fn (CongNoPayment $invoice) => (float) $invoice->amount),
            'cancelled' => $cancelled->sum(fn (CongNoPayment $invoice) => (float) $invoice->amount),
            'rejectedCount' => $rejected->count(),
            'directOrderCount' => $invoices->whereNotNull('id_order')->count(),
            'debtInvoiceCount' => $invoices->whereNotNull('id_congno')->count(),
        ];
    }

    protected function orderTimeline(Collection $orders, array $dateRange): array
    {
        $grouped = $orders->groupBy(fn (Order $order) => $order->created_at?->toDateString());

        return $this->dateBuckets($dateRange)->map(fn (string $date) => [
            'label' => CarbonImmutable::parse($date)->format('d/m'),
            'orders' => $grouped->get($date, collect())->count(),
            'saleTotal' => $grouped->get($date, collect())
                ->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', 0))),
            'costTotal' => $grouped->get($date, collect())
                ->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocvon, 'total_tongcuoc', 0))),
        ])->values()->all();
    }

    protected function revenueTimeline(Collection $orders, array $dateRange): array
    {
        $grouped = $orders->groupBy(fn (Order $order) => $order->created_at?->toDateString());

        return $this->dateBuckets($dateRange)->map(function (string $date) use ($grouped): array {
            $dayOrders = $grouped->get($date, collect());

            return [
                'label' => CarbonImmutable::parse($date)->format('d/m'),
                'value' => $dayOrders->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', 0))),
            ];
        })->values()->all();
    }

    protected function orderStatusChart(Collection $orders): array
    {
        return collect(OrderStatusEnum::cases())
            ->map(fn (OrderStatusEnum $status) => [
                'label' => $status->label(),
                'value' => $orders->where('bill_status', $status)->count(),
                'color' => $this->chartColor($status->value),
            ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values()
            ->all();
    }

    protected function invoiceStatusChart(Collection $invoices): array
    {
        return collect(InvoicePaymentStatusEnum::cases())
            ->map(fn (InvoicePaymentStatusEnum $status) => [
                'label' => $status->label(),
                'value' => $invoices->where('status', $status)->count(),
                'color' => $this->chartColor($status->value),
            ])
            ->filter(fn (array $item) => $item['value'] > 0)
            ->values()
            ->all();
    }

    protected function topSales(Collection $orders): array
    {
        return $orders->whereNotNull('id_sale')
            ->groupBy('id_sale')
            ->map(fn (Collection $items) => [
                'label' => $this->userLabel($items->first()->sale),
                'count' => $items->count(),
                'amount' => $items->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', 0))),
            ])
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->all();
    }

    protected function topCustomers(Collection $orders): array
    {
        return $orders->whereNotNull('id_customer')
            ->groupBy('id_customer')
            ->map(fn (Collection $items) => [
                'label' => $this->userLabel($items->first()->customerAccount),
                'count' => $items->count(),
                'amount' => $items->sum(fn (Order $order) => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', 0))),
            ])
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->all();
    }

    protected function topServices(Collection $orders): array
    {
        $groups = $orders
            ->map(fn (Order $order) => [
                'service_id' => data_get($order->service, 'id_dichvu'),
                'amount' => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', 0)),
            ])
            ->filter(fn (array $item) => filled($item['service_id']))
            ->groupBy('service_id');

        $names = News::query()
            ->whereIn('id', $groups->keys()->map(fn ($id) => (int) $id)->all())
            ->pluck('namevi', 'id');

        return $groups
            ->map(fn (Collection $items, string|int $serviceId) => [
                'label' => $names[(int) $serviceId] ?? 'Dịch vụ #'.$serviceId,
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
            ])
            ->sortByDesc('amount')
            ->take(5)
            ->values()
            ->all();
    }

    protected function unpaidDeliveredOrders(Collection $orders): array
    {
        return $orders
            ->filter(fn (Order $order) => $order->bill_status === OrderStatusEnum::DA_GIAO
                && $order->customer_payment_status !== InvoicePaymentStatusEnum::DA_THANH_TOAN->value)
            ->sortByDesc('created_at')
            ->take(6)
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'code' => $order->id_bill ?: 'DH-'.$order->id,
                'amount' => $this->moneyValue(data_get($order->payment_cuocban, 'total_tongcuoc', 0)),
                'created_at' => $order->created_at?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    protected function staleOrders(Collection $orders): array
    {
        return $orders
            ->filter(fn (Order $order) => ! in_array($order->bill_status, [
                OrderStatusEnum::DA_GIAO,
                OrderStatusEnum::HUY,
                OrderStatusEnum::RETURN_ORDER,
            ], true) && $order->updated_at?->lt(now()->subDays(7)))
            ->sortBy('updated_at')
            ->take(6)
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'code' => $order->id_bill ?: 'DH-'.$order->id,
                'status' => $order->bill_status?->label() ?? 'Chưa rõ',
                'updated_at' => $order->updated_at?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    protected function openInvoices(Collection $invoices): array
    {
        return $invoices
            ->filter(fn (CongNoPayment $invoice) => $invoice->status?->isPendingPayment() ?? false)
            ->sortByDesc('created_at')
            ->take(6)
            ->map(fn (CongNoPayment $invoice) => [
                'id' => $invoice->id,
                'code' => $invoice->ma_hoa_don,
                'status' => $invoice->status?->label() ?? 'Chưa rõ',
                'amount' => (float) $invoice->amount,
                'created_at' => $invoice->created_at?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    protected function overdueCustomerDebts(Collection $debts): array
    {
        return $debts
            ->filter(fn (CongNo $debt) => $this->isOverdue($debt->hanthanhtoan, $debt->status))
            ->sortBy('hanthanhtoan')
            ->take(6)
            ->map(fn (CongNo $debt) => [
                'id' => $debt->id,
                'code' => $debt->sohoadon,
                'remaining' => max(0, (float) $debt->total_cuocban - (float) $debt->paid_amount),
                'due_at' => $debt->hanthanhtoan?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    protected function dateRange(array $filters): array
    {
        $to = filled($filters['toDate'] ?? null)
            ? CarbonImmutable::parse($filters['toDate'])->endOfDay()
            : now()->toImmutable()->endOfDay();

        $from = filled($filters['fromDate'] ?? null)
            ? CarbonImmutable::parse($filters['fromDate'])->startOfDay()
            : $to->subDays(30)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return ['from' => $from, 'to' => $to];
    }

    protected function dateBuckets(array $dateRange): Collection
    {
        return collect(CarbonPeriod::create($dateRange['from'], $dateRange['to']))
            ->map(fn ($date) => $date->toDateString());
    }

    protected function moneyValue(mixed $value): float
    {
        return (float) preg_replace('/[^\d.-]/', '', (string) ($value ?? 0));
    }

    protected function isOverdue(mixed $dueAt, DebtStatusEnum|string|null $status): bool
    {
        $statusValue = $status instanceof DebtStatusEnum ? $status->value : $status;

        return $dueAt
            && CarbonImmutable::parse($dueAt)->isPast()
            && $statusValue !== DebtStatusEnum::DA_THANH_TOAN->value;
    }

    protected function userLabel(?User $user): string
    {
        if (! $user) {
            return 'Chưa gán';
        }

        return trim(($user->fullname ?: $user->username).' '.($user->code ? "({$user->code})" : ''));
    }

    protected function newsOptions(Collection $items): array
    {
        return $items
            ->map(fn (News $item) => ['id' => $item->id, 'label' => $item->namevi])
            ->values()
            ->all();
    }

    protected function hasRole(User $user, array $roles): bool
    {
        return $user->hasAnyRole($roles);
    }

    protected function chartColor(string $key): string
    {
        return match ($key) {
            'da_giao', 'da_thanh_toan' => '#059669',
            'huy', 'qua_han' => '#dc2626',
            'return_order', 'khong_chap_nhan' => '#ea580c',
            'dang_phat_hang', 'da_gui_hoa_don_tt' => '#d97706',
            'da_gui_yeu_cau_tt', 'duyet_xuat_hang' => '#4f46e5',
            'da_xac_nhan', 'da_duyet' => '#2563eb',
            'caution' => '#ca8a04',
            default => '#64748b',
        };
    }
}
