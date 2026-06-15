<?php

namespace App\Services\Reports;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SaleStatisticsService
{
    public function report(User $user, array $filters = []): array
    {
        $prefix = DB::connection()->getTablePrefix();
        $ordersTable = $prefix.'orders';
        $packageAlias = $prefix.'package_totals';
        $revenue = $this->jsonNumber("{$ordersTable}.payment_cuocban", 'total_tongcuoc');

        // Sale đăng nhập: thống kê doanh số theo CTV (khách hàng) thuộc sale đó.
        // Các role còn lại: xếp hạng theo từng sale như cũ.
        $isSale = $user->hasAnyRole(['sale', 'SALE']);
        $groupColumn = $isSale ? 'id_customer' : 'id_sale';

        $ctvIds = $isSale
            ? User::query()
                ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['ctv', 'CTV']))
                ->where('id_sale', $user->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all()
            : [];

        $packageTotals = DB::table('order_package')
            ->select('id_order')
            ->selectRaw('SUM(COALESCE(NULLIF(row_g_weight, 0), g_weight, 0)) AS gross_weight')
            ->selectRaw('SUM(COALESCE(NULLIF(row_c_weight, 0), c_weight, 0)) AS charged_weight')
            ->groupBy('id_order');

        $rows = Order::query()
            ->leftJoinSub($packageTotals, 'package_totals', 'package_totals.id_order', '=', 'orders.id')
            ->whereNotNull("orders.{$groupColumn}")
            ->whereBetween('orders.created_at', $this->dateRange($filters))
            ->when($isSale, fn (Builder $query) => $query->whereIn('orders.id_customer', $ctvIds ?: [0]))
            ->unless($isSale, fn (Builder $query) => $this->applyScope($query, $user, $filters))
            ->when(filled($filters['serviceId'] ?? null), fn (Builder $query) => $query->where('orders.service->id_dichvu', (int) $filters['serviceId']))
            ->when(filled($filters['branchId'] ?? null), fn (Builder $query) => $query->where('orders.service->id_chinhanh_nhanhang', (int) $filters['branchId']))
            ->when(filled($filters['agencyId'] ?? null), fn (Builder $query) => $query->where('orders.service->id_daily', (int) $filters['agencyId']))
            ->selectRaw("{$ordersTable}.{$groupColumn} AS entity_id")
            ->selectRaw("COUNT({$ordersTable}.id) AS order_count")
            ->selectRaw("SUM(COALESCE({$packageAlias}.gross_weight, 0)) AS gross_weight")
            ->selectRaw("SUM(COALESCE({$packageAlias}.charged_weight, 0)) AS charged_weight")
            ->selectRaw("SUM({$revenue}) AS revenue")
            ->groupByRaw("{$ordersTable}.{$groupColumn}")
            ->orderByDesc('revenue')
            ->get();

        $entities = User::query()
            ->whereIn('id', $rows->pluck('entity_id')->map(fn ($id) => (int) $id))
            ->get(['id', 'fullname', 'username', 'code', 'avatar', 'options'])
            ->keyBy('id');

        $fallbackPrefix = $isSale ? 'CTV #' : 'Sale #';

        $ranking = $rows
            ->map(function (object $row) use ($entities, $fallbackPrefix, $isSale): array {
                $entity = $entities->get((int) $row->entity_id);

                // Khách hàng (CTV): ưu tiên tên công ty trong options.company.company_name.
                $companyName = $isSale
                    ? data_get($entity?->options, 'company.company_name')
                    : null;

                $name = $companyName
                    ?: ($entity?->fullname ?: $entity?->username ?: $fallbackPrefix.$row->entity_id);

                return [
                    'id' => (int) $row->entity_id,
                    'name' => $name,
                    'shortName' => $this->shortName($name),
                    'initials' => $this->initials($name),
                    'code' => $entity?->code,
                    'avatar' => $entity?->avatar,
                    'orderCount' => (int) $row->order_count,
                    'grossWeight' => (float) $row->gross_weight,
                    'chargedWeight' => (float) $row->charged_weight,
                    'revenue' => (float) $row->revenue,
                ];
            })
            ->values();

        $maxRevenue = max(1, (float) $ranking->max('revenue'));

        return [
            'dimension' => $isSale ? 'customer' : 'sale',
            'ranking' => $ranking
                ->map(fn (array $sale, int $index) => $sale + [
                    'rank' => $index + 1,
                    'revenueShare' => round(($sale['revenue'] * 100) / $maxRevenue, 1),
                ])
                ->all(),
            'topThree' => $ranking
                ->take(3)
                ->map(fn (array $sale, int $index) => $sale + [
                    'rank' => $index + 1,
                    'revenueShare' => round(($sale['revenue'] * 100) / $maxRevenue, 1),
                ])
                ->all(),
            'summary' => [
                'sales' => $ranking->count(),
                'orders' => (int) $ranking->sum('orderCount'),
                'revenue' => (float) $ranking->sum('revenue'),
            ],
        ];
    }

    protected function applyScope(Builder $query, User $user, array $filters): void
    {
        if ($user->hasAnyRole(['sale', 'SALE'])) {
            $query->where('orders.id_sale', $user->id);

            return;
        }

        if ($user->hasAnyRole(['ctv', 'CTV'])) {
            $query->where('orders.id_customer', $user->id);

            return;
        }

        $query
            ->when(filled($filters['saleId'] ?? null), fn (Builder $query) => $query->where('orders.id_sale', (int) $filters['saleId']))
            ->when(filled($filters['customerId'] ?? null), fn (Builder $query) => $query->where('orders.id_customer', (int) $filters['customerId']));
    }

    protected function dateRange(array $filters): array
    {
        $to = filled($filters['toDate'] ?? null)
            ? CarbonImmutable::parse($filters['toDate'])->endOfDay()
            : now()->toImmutable()->endOfDay();
        $from = filled($filters['fromDate'] ?? null)
            ? CarbonImmutable::parse($filters['fromDate'])->startOfDay()
            : $to->subDays(30)->startOfDay();

        return $from->lte($to) ? [$from, $to] : [$to->startOfDay(), $from->endOfDay()];
    }

    protected function jsonValue(string $column, string $path): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "NULLIF(json_extract({$column}, '$.{$path}'), '')"
            : "NULLIF(JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$path}')), '')";
    }

    protected function jsonNumber(string $column, string $path): string
    {
        $value = "COALESCE({$this->jsonValue($column, $path)}, 0)";

        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST({$value} AS REAL)"
            : "CAST({$value} AS DECIMAL(18, 2))";
    }

    protected function shortName(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $last = array_pop($parts);
        $first = array_shift($parts);

        return trim(implode(' ', array_filter([$first, $last])));
    }

    protected function initials(string $name): string
    {
        return collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
