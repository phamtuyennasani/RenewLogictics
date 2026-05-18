<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Models\News;
use App\Models\Order;
use App\Support\OrderAccess;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Cập nhật giá đơn hàng')] class extends Component
{
    public Order $order;
    public array $payment = [];
    public array $feeOptions = [];
    public array $chiHoOptions = [];
    public array $expenseOptions = [];

    public function mount(string $uuid): void
    {
        $this->order = Order::query()
            ->with(['customer:id,fullname,code', 'sale:id,fullname,username,code'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless(OrderAccess::canEditPayment(auth()->user(), $this->order), 403);

        $this->payment = [
            'cuocban' => $this->normalizePayment($this->order->payment_cuocban, 'dongiaban'),
            'cuocvon' => $this->normalizePayment($this->order->payment_cuocvon, 'dongiavon'),
            'cuocgoc' => $this->normalizePayment($this->order->payment_cuocgoc, 'dongiagoc'),
        ];
        $this->feeOptions = $this->loadFeeOptions();
        $this->chiHoOptions = $this->loadChiHoOptions();
        $this->expenseOptions = $this->loadExpenseOptions();
        $this->hydrateFeeSelections();

        $this->recalculateAll();
    }

    protected function loadExpenseOptions(): array
    {
        return Cache::remember('payment_page_loai_chi_hhkh', now()->addDay(), function () {
            return News::query()
                ->select(['id', 'namevi'])
                ->whereType('loai-chi-hhkh')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                ])
                ->toArray();
        });
    }

    protected function loadFeeOptions(): array
    {
        return Cache::remember('payment_page_phuphidonhang', now()->addDay(), function () {
            return News::query()
                ->select([
                    'id',
                    'namevi',
                    DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(options2, '$.price')), 0) as price"),
                ])
                ->whereType('phuphidonhang')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                    'price' => (float) $item->price,
                ])
                ->toArray();
        });
    }

    protected function loadChiHoOptions(): array
    {
        return Cache::remember('payment_page_loai_chi_ho', now()->addDay(), function () {
            return News::query()
                ->select([
                    'id',
                    'namevi',
                    DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(options2, '$.price')), 0) as price"),
                ])
                ->whereType('loai-chi-ho')
                ->orderBy('numb')
                ->get()
                ->map(fn (News $item) => [
                    'id' => $item->id,
                    'name' => $item->namevi,
                    'price' => (float) $item->price,
                ])
                ->toArray();
        });
    }

    public function feeOptionsForBucket(string $bucket): array
    {
        return $bucket === 'phichiho' ? $this->chiHoOptions : $this->feeOptions;
    }

    protected function normalizePayment(?array $payment, string $priceKey): array
    {
        $payment ??= [];

        $payment = array_merge([
            $priceKey => 0,
            'vat_percent' => 0,
            'vat_amount' => 0,
            'ppxd_percent' => 0,
            'ppxd_amount' => 0,
            'tongcuoc' => 0,
            'phuphi' => [],
            'phichiho' => [],
            'hh_khachhang' => [],
            'bonus_sale_percent' => 0,
            'bonus_sale_amount' => 0,
            'total_hh_khachhang' => 0,
            'total_vat' => 0,
            'total_vat_phuphi' => 0,
            'total_phuphi_no_vat' => 0,
            'total_phuphi' => 0,
            'total_tongcuoc_no_vat' => 0,
            'total_tongcuoc' => 0,
        ], $payment);

        foreach (['phuphi', 'phichiho', 'hh_khachhang'] as $bucket) {
            $payment[$bucket] = array_values(array_map(function ($row) {
                if (! is_array($row)) {
                    $row = [];
                }

                $row['_key'] = (string) ($row['_key'] ?? Str::uuid());

                return $row;
            }, $payment[$bucket] ?? []));
        }

        return $payment;
    }

    public function updated($property): void
    {
        if (str_starts_with($property, 'payment.') && str_ends_with($property, '.id_loaiphuphi')) {
            $this->syncSelectedFeeFromProperty($property);
            return;
        }

        if (str_starts_with($property, 'payment.') && str_ends_with($property, '.id_loaichi')) {
            $this->syncSelectedExpenseFromProperty($property);
            return;
        }

        if ($this->shouldRecalculateFor($property)) {
            $this->recalculateAll();
        }
    }

    protected function shouldRecalculateFor(string $property): bool
    {
        if (! str_starts_with($property, 'payment.')) {
            return false;
        }

        foreach ([
            '.total',
            '.total_after_vat',
            '.vat_amount',
            '.ppxd_amount',
            '.total_vat',
            '.total_vat_phuphi',
            '.total_phuphi',
            '.total_phuphi_no_vat',
            '.total_tongcuoc',
            '.total_tongcuoc_no_vat',
            '.tongcuoc',
            '.name',
        ] as $computedSuffix) {
            if (str_ends_with($property, $computedSuffix)) {
                return false;
            }
        }

        return true;
    }

    public function addFee(string $group, string $bucket): void
    {
        if (! in_array($group, ['cuocban', 'cuocvon', 'cuocgoc'], true)) {
            return;
        }

        if (! in_array($bucket, ['phuphi', 'phichiho', 'hh_khachhang'], true)) {
            return;
        }

        $this->payment[$group][$bucket][] = [
            '_key' => (string) Str::uuid(),
            'id_loaiphuphi' => null,
            'id_loaichi' => null,
            'name' => '',
            'note' => '',
            'diengiai_chi' => '',
            'soluong' => 1,
            'price' => 0,
            'so_tien' => 0,
            'vat_percent' => 0,
            'vat_amount' => 0,
            'total' => 0,
            'total_after_vat' => 0,
        ];

        $this->recalculateAll();
        $this->js("
            requestAnimationFrame(() => {
                window.TomSelectHelper?.init();
            });
        ");
    }

    public function selectFee(string $group, string $bucket, int $index): void
    {
        $row = $this->payment[$group][$bucket][$index] ?? null;

        if (! is_array($row)) {
            return;
        }

        $option = collect($this->feeOptionsForBucket($bucket))->firstWhere('id', (int) ($row['id_loaiphuphi'] ?? 0));

        if (! $option) {
            $this->payment[$group][$bucket][$index]['name'] = '';
            $this->recalculateAll();
            return;
        }

        $this->payment[$group][$bucket][$index]['name'] = $option['name'];
        $this->payment[$group][$bucket][$index]['price'] = (float) ($option['price'] ?? 0);
        $this->recalculateAll();
    }

    public function selectExpense(string $group, string $bucket, int $index): void
    {
        $row = $this->payment[$group][$bucket][$index] ?? null;

        if (! is_array($row)) {
            return;
        }

        $option = collect($this->expenseOptions)->firstWhere('id', (int) ($row['id_loaichi'] ?? 0));
        $this->payment[$group][$bucket][$index]['name'] = $option['name'] ?? '';
        $this->recalculateAll();
    }

    protected function syncSelectedFeeFromProperty(string $property): void
    {
        [, $group, $bucket, $index] = array_pad(explode('.', $property), 4, null);

        if ($group === null || $bucket === null || $index === null) {
            $this->recalculateAll();
            return;
        }

        $this->selectFee($group, $bucket, (int) $index);
    }

    protected function syncSelectedExpenseFromProperty(string $property): void
    {
        [, $group, $bucket, $index] = array_pad(explode('.', $property), 4, null);

        if ($group === null || $bucket === null || $index === null) {
            $this->recalculateAll();
            return;
        }

        $this->selectExpense($group, $bucket, (int) $index);
    }

    protected function hydrateFeeSelections(): void
    {
        foreach (['cuocban', 'cuocvon', 'cuocgoc'] as $group) {
            foreach (['phuphi', 'phichiho', 'hh_khachhang'] as $bucket) {
                foreach (($this->payment[$group][$bucket] ?? []) as $index => $row) {
                    if (! empty($row['id_loaiphuphi'])) {
                        continue;
                    }

                    $name = trim((string) ($row['name'] ?? ''));

                    if ($name === '') {
                        continue;
                    }

                    $option = collect($this->feeOptionsForBucket($bucket))->first(fn ($item) => strcasecmp($item['name'], $name) === 0);

                    if ($option) {
                        $this->payment[$group][$bucket][$index]['id_loaiphuphi'] = $option['id'];
                    }
                }
            }
        }
    }

    public function removeFee(string $group, string $bucket, int $index): void
    {
        unset($this->payment[$group][$bucket][$index]);
        $this->payment[$group][$bucket] = array_values($this->payment[$group][$bucket] ?? []);
        $this->recalculateAll();
    }

    public function save(): void
    {
        abort_unless(OrderAccess::canEditPayment(auth()->user(), $this->order), 403);

        $this->syncSelectedOptionNames();

        $this->validate([
            'payment.cuocban.dongiaban' => 'nullable|regex:/^[0-9,]+$/',
            'payment.cuocban.vat_percent' => 'nullable|numeric|min:0',
            'payment.cuocban.ppxd_percent' => 'nullable|numeric|min:0',
            'payment.cuocvon.dongiavon' => 'nullable|regex:/^[0-9,]+$/',
            'payment.cuocvon.vat_percent' => 'nullable|numeric|min:0',
            'payment.cuocvon.ppxd_percent' => 'nullable|numeric|min:0',
            'payment.cuocgoc.dongiagoc' => 'nullable|regex:/^[0-9,]+$/',
            'payment.cuocgoc.vat_percent' => 'nullable|numeric|min:0',
            'payment.cuocgoc.ppxd_percent' => 'nullable|numeric|min:0',
            'payment.*.*.*.soluong' => 'nullable|numeric|min:0',
            'payment.*.*.*.price' => 'nullable|regex:/^[0-9,]+$/',
            'payment.*.*.*.vat_percent' => 'nullable|numeric|min:0',
            'payment.*.*.*.note' => 'nullable|string|max:500',
            'payment.*.*.*.diengiai_chi' => 'nullable|string|max:500',
            'payment.*.*.*.id_loaiphuphi' => 'nullable|integer',
            'payment.*.*.*.id_loaichi' => 'nullable|integer',
            'payment.*.*.*.so_tien' => 'nullable|regex:/^[0-9,]+$/',
        ]);

        $before = $this->orderPaymentSnapshot();

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);
        $this->recalculateAll();

        $this->order->forceFill([
            'payment_cuocban' => $this->payment['cuocban'],
            'payment_cuocvon' => $this->payment['cuocvon'],
            'payment_cuocgoc' => $this->payment['cuocgoc'],
            'payment_loinhuan' => $this->profitSnapshot(),
        ])->save();

        $this->order->refresh();

        RecordOrderEditHistoryAction::execute(
            $this->order,
            'edit_payment',
            'payment',
            $before,
            $this->paymentSnapshot(),
            'cập nhật giá đơn hàng'
        );

        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật giá đơn hàng.', variant: 'success');
        //$this->redirectRoute('orders.show', ['uuid' => $this->order->uuid]);
    }

    protected function syncSelectedOptionNames(): void
    {
        foreach (['cuocban', 'cuocvon', 'cuocgoc'] as $group) {
            foreach (['phuphi', 'phichiho'] as $bucket) {
                foreach (($this->payment[$group][$bucket] ?? []) as $index => $row) {
                    $option = collect($this->feeOptionsForBucket($bucket))
                        ->firstWhere('id', (int) ($row['id_loaiphuphi'] ?? 0));

                    $this->payment[$group][$bucket][$index]['name'] = $option['name'] ?? '';
                }
            }

            foreach (($this->payment[$group]['hh_khachhang'] ?? []) as $index => $row) {
                $option = collect($this->expenseOptions)
                    ->firstWhere('id', (int) ($row['id_loaichi'] ?? 0));

                $this->payment[$group]['hh_khachhang'][$index]['name'] = $option['name'] ?? '';
            }
        }
    }

    protected function recalculateAll(): void
    {
        $this->payment['cuocban'] = $this->recalculateGroup($this->payment['cuocban'] ?? [], 'dongiaban', ['phuphi', 'hh_khachhang']);
        $this->payment['cuocvon'] = $this->recalculateGroup($this->payment['cuocvon'] ?? [], 'dongiavon', ['phuphi', 'phichiho'], ['phichiho']);
        $this->payment['cuocgoc'] = $this->recalculateGroup($this->payment['cuocgoc'] ?? [], 'dongiagoc', ['phuphi']);

        $saleBonusPercent = $this->number($this->payment['cuocvon']['bonus_sale_percent'] ?? 0);
        $this->payment['cuocvon']['bonus_sale_amount'] = round($this->number($this->payment['cuocban']['total_tongcuoc_no_vat'] ?? 0) * $saleBonusPercent / 100);
    }

    protected function recalculateGroup(array $group, string $priceKey, array $buckets, array $excludedBuckets = []): array
    {
        $price = $this->number($group[$priceKey] ?? 0);
        $vatPercent = $this->number($group['vat_percent'] ?? 0);
        $ppxdPercent = $this->number($group['ppxd_percent'] ?? 0);
        $ppxdAmount = round($price * $ppxdPercent / 100);
        $vatAmount = round(($price + $ppxdAmount) * $vatPercent / 100);
        $tongcuoc = $price + $ppxdAmount + $vatAmount;
        $feeTotalNoVat = 0;
        $feeVatTotal = 0;
        $feeTotal = 0;

        foreach ($buckets as $bucket) {
            $rows = $group[$bucket] ?? [];

            foreach ($rows as $index => $row) {
                if ($bucket === 'hh_khachhang') {
                    $amount = $this->number($row['so_tien'] ?? ($row['price'] ?? 0));
                    $rows[$index]['total'] = $amount;
                    $rows[$index]['total_after_vat'] = $amount;
                    $rows[$index]['vat_amount'] = 0;
                    continue;
                }

                $qty = max(0, $this->number($row['soluong'] ?? 0));
                $rowPrice = $this->number($row['price'] ?? ($row['so_tien'] ?? 0));
                $rowVatPercent = $this->number($row['vat_percent'] ?? ($row['vat'] ?? 0));
                $rowTotal = round(($qty ?: 1) * $rowPrice);
                $rowVatAmount = round($rowTotal * $rowVatPercent / 100);
                $rows[$index]['vat_amount'] = $rowVatAmount;
                $rows[$index]['total'] = $rowTotal;
                $rows[$index]['total_after_vat'] = $rowTotal + $rowVatAmount;

                if (in_array($bucket, $excludedBuckets, true)) {
                    continue;
                }

                $feeTotalNoVat += $rowTotal;
                $feeVatTotal += $rowVatAmount;
                $feeTotal += $rows[$index]['total_after_vat'];
            }

            $group[$bucket] = array_values($rows);
        }

        $group[$priceKey] = $price;
        $group['ppxd_amount'] = $ppxdAmount;
        $group['vat_amount'] = $vatAmount;
        $group['total_vat'] = $vatAmount + $feeVatTotal;
        $group['total_vat_phuphi'] = $feeVatTotal;
        $group['total_phuphi_no_vat'] = $feeTotalNoVat;
        $group['total_phuphi'] = $feeTotal;
        $group['total_phichiho'] = array_sum(array_map(
            fn ($row) => $this->number($row['price'] ?? ($row['so_tien'] ?? 0)),
            $group['phichiho'] ?? []
        ));
        $group['total_hh_khachhang'] = array_sum(array_map(
            fn ($row) => $this->number($row['so_tien'] ?? ($row['price'] ?? 0)),
            $group['hh_khachhang'] ?? []
        ));
        $group['total_tongcuoc_no_vat'] = $price + $ppxdAmount + $feeTotalNoVat;
        $group['tongcuoc'] = $tongcuoc;
        $group['total_tongcuoc'] = $tongcuoc + $feeVatTotal + $feeTotalNoVat;

        return $group;
    }

    protected function profitSnapshot(): array
    {
        $saleNoVat = $this->number($this->payment['cuocban']['total_tongcuoc_no_vat'] ?? 0);
        $sale = $this->number($this->payment['cuocban']['total_tongcuoc'] ?? 0);
        $costNoVat = $this->number($this->payment['cuocvon']['total_tongcuoc_no_vat'] ?? 0);
        $cost = $this->number($this->payment['cuocvon']['total_tongcuoc'] ?? 0);
        $baseNoVat = $this->number($this->payment['cuocgoc']['total_tongcuoc_no_vat'] ?? 0);
        $base = $this->number($this->payment['cuocgoc']['total_tongcuoc'] ?? 0);
        $profit = $sale - $cost;

        return [
            'cuocban_no_vat' => $saleNoVat,
            'cuocban' => $sale,
            'cuocvon_no_vat' => $costNoVat,
            'cuocvon' => $cost,
            'cuocgoc_no_vat' => $baseNoVat,
            'cuocgoc' => $base,
            'loinhuantamtinh' => $saleNoVat - $costNoVat,
            'tysuattamtinh' => $saleNoVat > 0 ? round((($saleNoVat - $costNoVat) / $saleNoVat) * 100, 2) : 0,
            'loinhuan' => $profit,
            'tysuatloinhuan' => $sale > 0 ? round(($profit / $sale) * 100, 2) : 0,
        ];
    }

    protected function paymentSnapshot(): array
    {
        return [
            'cuoc_ban' => $this->number(data_get($this->payment, 'cuocban.total_tongcuoc')),
            'cuoc_von' => $this->number(data_get($this->payment, 'cuocvon.total_tongcuoc')),
            'cuoc_goc' => $this->number(data_get($this->payment, 'cuocgoc.total_tongcuoc')),
            'loi_nhuan' => $this->profitSnapshot()['loinhuan'],
        ];
    }

    protected function orderPaymentSnapshot(): array
    {
        $sale = $this->order->payment_cuocban ?? [];
        $cost = $this->order->payment_cuocvon ?? [];
        $base = $this->order->payment_cuocgoc ?? [];
        $profit = $this->order->payment_loinhuan ?? [];

        return [
            'cuoc_ban' => $this->number(data_get($sale, 'total_tongcuoc', data_get($sale, 'tongcuoc'))),
            'cuoc_von' => $this->number(data_get($cost, 'total_tongcuoc', data_get($cost, 'tongcuoc'))),
            'cuoc_goc' => $this->number(data_get($base, 'total_tongcuoc', data_get($base, 'tongcuoc'))),
            'loi_nhuan' => $this->number(data_get($profit, 'loinhuan')),
        ];
    }

    public function money(mixed $value): string
    {
        return number_format($this->number($value), 0) . ' đ';
    }

    public function profitValue(string $key): float
    {
        return $this->number($this->profitSnapshot()[$key] ?? 0);
    }

    protected function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.,-]/', '', (string) $value);

        if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
            $normalized = str_replace(',', '', $normalized);
        }

        return $normalized === '' || $normalized === '-' ? 0 : (float) $normalized;
    }

    public function showSaleCharge(): bool
    {
        return ! auth()->user()->hasRole('ketoan') || auth()->user()->hasAnyRole(['admin', 'manager']);
    }

    public function showCostCharge(): bool
    {
        return ! auth()->user()->hasRole('sale') || auth()->user()->hasAnyRole(['admin', 'manager']);
    }

    public function showBaseCharge(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }
   
};

?>

<div class="space-y-5">
    <section class="rounded-xl border border-neutral-200 bg-white px-5 py-4 shadow-xs">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm text-neutral-500">Đơn hàng / {{ $order->id_bill ?: 'ORDER-'.$order->id }}</p>
            <h1 class="text-2xl font-bold text-neutral-900">Cập nhật giá đơn hàng</h1>
            <p class="mt-1 text-sm text-neutral-500">Cập nhật cước bán, cước vốn, cước gốc, phụ phí và lợi nhuận.</p>
        </div>
        <a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 shadow-xs hover:bg-neutral-50">
            Quay lại chi tiết
        </a>
        </div>
    </section>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-5 col-span-1">
            <div class="space-y-5">
                @if($this->showSaleCharge())
                    @include('pages.order.partials.payment-card', [
                        'group' => 'cuocban',
                        'title' => 'Cước bán',
                        'subtitle' => 'Giá bán báo khách và các khoản thu thêm',
                        'priceKey' => 'dongiaban',
                        'priceLabel' => 'Đơn giá bán',
                        'buckets' => [
                            ['key' => 'phuphi', 'label' => 'Phụ phí bán'],
                            ['key' => 'hh_khachhang', 'label' => 'Hoa hồng khách hàng'],
                        ],
                    ])
                @endif

                @if($this->showCostCharge())
                    @include('pages.order.partials.payment-card', [
                        'group' => 'cuocvon',
                        'title' => 'Cước vốn',
                        'subtitle' => 'Giá vốn nhà cung ứng và chi hộ',
                        'priceKey' => 'dongiavon',
                        'priceLabel' => 'Đơn giá vốn',
                        'buckets' => [
                            ['key' => 'phuphi', 'label' => 'Phụ phí vốn'],
                            ['key' => 'phichiho', 'label' => 'Phí chi hộ'],
                        ],
                    ])
                @endif

                @if($this->showBaseCharge())
                    @include('pages.order.partials.payment-card', [
                        'group' => 'cuocgoc',
                        'title' => 'Cước gốc / công ty',
                        'subtitle' => 'Giá gốc dùng để đối soát nội bộ',
                        'priceKey' => 'dongiagoc',
                        'priceLabel' => 'Đơn giá gốc',
                        'buckets' => [
                            ['key' => 'phuphi', 'label' => 'Phụ phí gốc'],
                        ],
                    ])
                @endif
            </div>

            <aside class="space-y-5">
                <section class="sticky top-4 rounded-xl border border-neutral-200 bg-white shadow-xs">
                    <div class="border-b border-neutral-100 px-5 py-4">
                        <h2 class="text-sm font-semibold uppercase text-neutral-800">Tổng hợp</h2>
                        <p class="text-xs text-neutral-500">Tự tính theo dữ liệu đang nhập</p>
                    </div>
                    <div class="space-y-3 p-5">
                        <div class="flex items-center justify-between rounded-lg bg-primary-50 px-4 py-3">
                            <span class="text-sm text-primary-700">Cước bán</span>
                            <span class="text-sm font-semibold text-primary-800">{{ $this->money(data_get($payment, 'cuocban.total_tongcuoc')) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
                            <span class="text-sm text-neutral-600">Cước vốn</span>
                            <span class="text-sm font-semibold text-neutral-900">{{ $this->money(data_get($payment, 'cuocvon.total_tongcuoc')) }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-neutral-50 px-4 py-3">
                            <span class="text-sm text-neutral-600">Cước gốc</span>
                            <span class="text-sm font-semibold text-neutral-900">{{ $this->money(data_get($payment, 'cuocgoc.total_tongcuoc')) }}</span>
                        </div>
                        <div class="rounded-lg bg-emerald-50 px-4 py-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-emerald-700">Lợi nhuận</span>
                                <span class="text-sm font-semibold text-emerald-800">{{ $this->money($this->profitValue('loinhuan')) }}</span>
                            </div>
                            <div class="mt-1 text-right text-xs text-emerald-700">Tỷ suất {{ number_format($this->profitValue('tysuatloinhuan'), 2) }}%</div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-neutral-100 px-5 py-4">
                        <a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                            Thoát
                        </a>
                        <flux:button type="submit" variant="primary">Lưu giá</flux:button>
                    </div>
                </section>
            </aside>
        </div>
    </form>
</div>
