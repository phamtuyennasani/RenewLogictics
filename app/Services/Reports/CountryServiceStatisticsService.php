<?php

namespace App\Services\Reports;

use App\Enums\OrderStatusEnum;
use App\Models\Country;
use App\Models\News;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CountryServiceStatisticsService
{
    public function report(User $user, array $filters = []): array
    {
        $prefix = DB::connection()->getTablePrefix();
        $ordersTable = $prefix.'orders';
        $packageAlias = $prefix.'package_totals';
        $countryId = $this->jsonValue("{$ordersTable}.receiver", 'country_id');
        $legacyCountryId = $this->jsonValue("{$ordersTable}.receiver", 'id_country');
        $countryName = $this->jsonValue("{$ordersTable}.receiver", 'country');
        $serviceId = $this->jsonValue("{$ordersTable}.service", 'id_dichvu');
        $revenue = $this->jsonNumber("{$ordersTable}.payment_cuocban", 'total_tongcuoc');
        $profit = $this->jsonNumber("{$ordersTable}.payment_loinhuan", 'loinhuan');
        $countryKey = "COALESCE({$countryId}, {$legacyCountryId}, '')";
        $countryLabel = "COALESCE({$countryName}, '')";
        $serviceKey = "COALESCE({$serviceId}, '')";
        $delivered = OrderStatusEnum::DA_GIAO->value;

        $packageTotals = DB::table('order_package')
            ->select('id_order')
            ->selectRaw('SUM(COALESCE(NULLIF(row_g_weight, 0), g_weight, 0)) AS gross_weight')
            ->selectRaw('SUM(COALESCE(NULLIF(row_c_weight, 0), c_weight, 0)) AS charged_weight')
            ->groupBy('id_order');

        $rows = Order::query()
            ->leftJoinSub($packageTotals, 'package_totals', 'package_totals.id_order', '=', 'orders.id')
            ->whereBetween('orders.created_at', $this->dateRange($filters))
            ->tap(fn (Builder $query) => $this->applyScope($query, $user, $filters))
            ->when(filled($filters['serviceId'] ?? null), fn (Builder $query) => $query->where('orders.service->id_dichvu', (int) $filters['serviceId']))
            ->when(filled($filters['branchId'] ?? null), fn (Builder $query) => $query->where('orders.service->id_chinhanh_nhanhang', (int) $filters['branchId']))
            ->when(filled($filters['agencyId'] ?? null), fn (Builder $query) => $query->where('orders.service->id_daily', (int) $filters['agencyId']))
            ->selectRaw("{$countryKey} AS country_id")
            ->selectRaw("{$countryLabel} AS fallback_country_name")
            ->selectRaw("{$serviceKey} AS service_id")
            ->selectRaw("COUNT({$ordersTable}.id) AS order_count")
            ->selectRaw("SUM(COALESCE({$packageAlias}.gross_weight, 0)) AS gross_weight")
            ->selectRaw("SUM(COALESCE({$packageAlias}.charged_weight, 0)) AS charged_weight")
            ->selectRaw("SUM(COALESCE({$ordersTable}.re_weight, 0)) AS re_weight")
            ->selectRaw("SUM({$revenue}) AS revenue")
            ->selectRaw("SUM({$profit}) AS profit")
            ->selectRaw("SUM(CASE WHEN {$ordersTable}.bill_status = ? THEN 1 ELSE 0 END) AS delivered_count", [$delivered])
            ->groupByRaw("{$countryKey}, {$countryLabel}, {$serviceKey}")
            ->get();

        return $this->shapeReport($rows, $this->canSeeFinance($user));
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

    protected function shapeReport(Collection $rows, bool $canSeeFinance): array
    {
        $countries = Country::query()
            ->whereIn('id', $rows->pluck('country_id')->filter()->map(fn ($id) => (int) $id)->unique())
            ->get(['id', 'name', 'iso2', 'iso3'])
            ->keyBy('id');

        $services = News::query()
            ->whereIn('id', $rows->pluck('service_id')->filter()->map(fn ($id) => (int) $id)->unique())
            ->pluck('namevi', 'id');

        $grouped = $rows
            ->groupBy(fn (object $row) => filled($row->country_id) ? 'id:'.$row->country_id : 'name:'.$row->fallback_country_name)
            ->map(function (Collection $items) use ($countries, $services, $canSeeFinance): array {
                $first = $items->first();
                $country = filled($first->country_id) ? $countries->get((int) $first->country_id) : null;
                $orderCount = (int) $items->sum('order_count');
                $revenue = (float) $items->sum('revenue');
                $profit = (float) $items->sum('profit');

                return [
                    'name' => $country?->name ?: ($first->fallback_country_name ?: 'Chưa xác định'),
                    'code' => strtoupper((string) ($country?->iso2 ?: $country?->iso3)),
                    'orderCount' => $orderCount,
                    'grossWeight' => (float) $items->sum('gross_weight'),
                    'chargedWeight' => (float) $items->sum('charged_weight'),
                    'revenue' => $revenue,
                    'profitMargin' => $canSeeFinance ? $this->percentage($profit, $revenue) : null,
                    'deliveryRate' => $this->percentage((float) $items->sum('delivered_count'), $orderCount),
                    'services' => $items
                        ->map(function (object $row) use ($services, $canSeeFinance): array {
                            $revenue = (float) $row->revenue;

                            return [
                                'name' => filled($row->service_id)
                                    ? ($services[(int) $row->service_id] ?? 'Dịch vụ #'.$row->service_id)
                                    : 'Chưa xác định dịch vụ',
                                'orderCount' => (int) $row->order_count,
                                'grossWeight' => (float) $row->gross_weight,
                                'chargedWeight' => (float) $row->charged_weight,
                                'reWeight' => (float) $row->re_weight,
                                'revenue' => $revenue,
                                'profit' => $canSeeFinance ? (float) $row->profit : null,
                            ];
                        })
                        ->sortByDesc('revenue')
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $maxOrders = max(1, (int) $grouped->max('orderCount'));

        return [
            'countries' => $grouped
                ->map(fn (array $country) => $country + [
                    'orderShare' => round(($country['orderCount'] * 100) / $maxOrders, 1),
                ])
                ->all(),
            'canSeeFinance' => $canSeeFinance,
        ];
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

    protected function percentage(float $value, float $total): float
    {
        return $total > 0 ? round(($value * 100) / $total, 1) : 0;
    }

    protected function canSeeFinance(User $user): bool
    {
        return ! $user->hasAnyRole(['sale', 'SALE', 'ctv', 'CTV']);
    }
}
