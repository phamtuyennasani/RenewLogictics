<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Enums\InvoicePaymentStatusEnum;
use App\Models\CongNoPayment;
use App\Models\News;
use App\Models\Order;
use App\Services\OrderInvoiceService;
use App\Services\Payments\InvoiceCodeGenerator;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\Data\PaymentRequestData;
use App\Support\OrderAccess;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Cập nhật giá đơn hàng')] class extends Component
{
    use WithFileUploads;

    public Order $order;
    public array $payment = [];
    public array $feeOptions = [];
    public array $chiHoOptions = [];
    public array $expenseOptions = [];

    public ?CongNoPayment $invoice = null;
    public ?string $selectedPaymentMethod = null;
    public string $selectedProvider = 'sepay';
    public array $enabledProviders = [];
    public $cashPhoto = null;
    public string $cancelInvoiceReason = '';
    public string $rejectPaymentReason = '';

    public function mount(string $uuid): void
    {
        $this->order = Order::query()
            ->with(['customer:id,fullname,code', 'sale:id,fullname,username,code', 'salePriceLocker:id,fullname,username,code'])
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
        $this->enforceEditableChargeScope();
        $this->loadInvoice();
        $this->loadEnabledProviders();
    }

    protected function loadEnabledProviders(): void
    {
        $this->enabledProviders = PaymentProviderManager::enabledProviders();

        if (!($this->enabledProviders[$this->selectedProvider] ?? false)) {
            $this->selectedProvider = PaymentProviderManager::defaultProvider();
        }
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
        if ($group === 'cuocban' && ! $this->canEditSaleCharge()) {
            return;
        }

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
        if ($group === 'cuocban' && ! $this->canEditSaleCharge()) {
            return;
        }

        unset($this->payment[$group][$bucket][$index]);
        $this->payment[$group][$bucket] = array_values($this->payment[$group][$bucket] ?? []);
        $this->recalculateAll();
    }

    public function save(): void
    {
        abort_unless(OrderAccess::canEditPayment(auth()->user(), $this->order), 403);

        $this->enforceEditableChargeScope();
        $this->syncSelectedOptionNames();

        if (! $this->canEditSaleCharge()) {
            $this->payment['cuocban'] = $this->normalizePayment($this->order->payment_cuocban, 'dongiaban');
        }

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

        $this->recalculateAll();

        $cuocban = $this->canEditSaleCharge()
            ? $this->payment['cuocban']
            : $this->normalizePayment($this->order->payment_cuocban, 'dongiaban');
        $cuocvon = $this->canEditAllCharges()
            ? $this->payment['cuocvon']
            : $this->normalizePayment($this->order->payment_cuocvon, 'dongiavon');
        $cuocgoc = $this->canEditAllCharges()
            ? $this->payment['cuocgoc']
            : $this->normalizePayment($this->order->payment_cuocgoc, 'dongiagoc');

        if (! $this->canEditAllCharges()) {
            $cuocvon['bonus_sale_percent'] = $this->number($this->payment['cuocvon']['bonus_sale_percent'] ?? ($cuocvon['bonus_sale_percent'] ?? 0));
            $cuocvon['bonus_sale_amount'] = $this->number($this->payment['cuocvon']['bonus_sale_amount'] ?? 0);
        }

        $this->payment['cuocban'] = $cuocban;
        $this->payment['cuocvon'] = $cuocvon;
        $this->payment['cuocgoc'] = $cuocgoc;
        $profitSnapshot = $this->profitSnapshot();

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);

        $this->order->forceFill([
            'payment_cuocban' => $cuocban,
            'payment_cuocvon' => $cuocvon,
            'payment_cuocgoc' => $cuocgoc,
            'payment_loinhuan' => $profitSnapshot,
        ])->save();

        $this->order->refresh();
        $after = $this->orderPaymentSnapshot();
        $this->enforceEditableChargeScope();

        RecordOrderEditHistoryAction::execute(
            $this->order,
            'edit_payment',
            'payment',
            $before,
            $after,
            'cập nhật giá đơn hàng'
        );

        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật giá đơn hàng.', variant: 'success');
        //$this->redirectRoute('orders.show', ['uuid' => $this->order->uuid]);
    }

    public function lockSaleCharge(): void
    {
        abort_unless(OrderAccess::canEditPayment(auth()->user(), $this->order), 403);
        abort_unless($this->canLockSaleCharge(), 403);

        $this->save();

        $this->order->forceFill([
            'sale_price_locked_at' => now(),
            'sale_price_locked_by' => auth()->id(),
            'sale_success' => true,
        ])->save();

        $this->order->refresh()->load('salePriceLocker:id,fullname,username,code');
        $this->enforceEditableChargeScope();

        Flux::toast(duration: 2500, heading: 'Đã chốt giá bán', text: 'Cước bán đã được khóa. Chỉ Admin có quyền cập nhật lại.', variant: 'success');
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

    protected function enforceEditableChargeScope(): void
    {
        if ($this->canEditAllCharges()) {
            return;
        }

        $bonusPercent = $this->number($this->payment['cuocvon']['bonus_sale_percent'] ?? data_get($this->order->payment_cuocvon, 'bonus_sale_percent', 0));
        $bonusAmount = $this->number($this->payment['cuocvon']['bonus_sale_amount'] ?? data_get($this->order->payment_cuocvon, 'bonus_sale_amount', 0));

        $this->payment['cuocvon'] = $this->normalizePayment([], 'dongiavon');
        $this->payment['cuocvon']['bonus_sale_percent'] = $bonusPercent;
        $this->payment['cuocvon']['bonus_sale_amount'] = $bonusAmount;
        $this->payment['cuocgoc'] = $this->normalizePayment([], 'dongiagoc');
    }

    protected function recalculateAll(): void
    {
        $this->payment['cuocban'] = $this->recalculateGroup($this->payment['cuocban'] ?? [], 'dongiaban', ['phuphi', 'hh_khachhang']);
        $this->payment['cuocvon'] = $this->recalculateGroup($this->payment['cuocvon'] ?? [], 'dongiavon', ['phuphi', 'phichiho'], ['phichiho']);
        $this->payment['cuocgoc'] = $this->recalculateGroup($this->payment['cuocgoc'] ?? [], 'dongiagoc', ['phuphi']);

        $saleTotalNoVat = $this->number($this->payment['cuocban']['total_tongcuoc_no_vat'] ?? 0);
        $saleBonusPercent = $this->canEditAllCharges()
            ? $this->number($this->payment['cuocvon']['bonus_sale_percent'] ?? 0)
            : $this->number(data_get($this->order->payment_cuocvon, 'bonus_sale_percent', 0));
        $this->payment['cuocvon']['bonus_sale_percent'] = $saleBonusPercent;
        $this->payment['cuocvon']['bonus_sale_amount'] = round($saleTotalNoVat * ($saleBonusPercent / 100));
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
        $customerCommission = $this->number($this->payment['cuocban']['total_hh_khachhang'] ?? 0);
        $saleBonus = $this->number($this->payment['cuocvon']['bonus_sale_amount'] ?? 0);
        $estimatedProfit = $saleNoVat - $costNoVat - $customerCommission;
        $profit = $estimatedProfit - $saleBonus;
        $estimatedProfitRate = $saleNoVat > 0 ? round(($estimatedProfit * 100) / $saleNoVat, 2) : 0;
        $profitRate = $saleNoVat > 0 ? round(($profit * 100) / $saleNoVat, 2) : 0;

        return [
            'cuocban_no_vat' => $saleNoVat,
            'cuocban' => $sale,
            'cuocvon_no_vat' => $costNoVat,
            'cuocvon' => $cost,
            'cuocgoc_no_vat' => $baseNoVat,
            'cuocgoc' => $base,
            'loinhuantamtinh' => $estimatedProfit,
            'tysuattamtinh' => $estimatedProfitRate,
            'loinhuan' => $profit,
            'tysuat' => $profitRate,
            'tysuatloinhuan' => $profitRate,
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

    public function number(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^\d.,-]/', '', (string) $value);

        if ($normalized === '' || $normalized === '-') {
            return 0;
        }

        $hasComma = str_contains($normalized, ',');
        $hasDot = str_contains($normalized, '.');

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($normalized, ',');
            $lastDot = strrpos($normalized, '.');

            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($hasComma) {
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $normalized)) {
                $normalized = str_replace(',', '', $normalized);
            } else {
                $normalized = str_replace(',', '.', $normalized);
            }
        } elseif ($hasDot && preg_match('/^-?\d{1,3}(\.\d{3})+$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return (float) $normalized;
    }

    public function showSaleCharge(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan', 'sale']);
    }

    public function canEditSaleCharge(): bool
    {
        if ($this->order->isInvoiceLocked()) {
            return false;
        }

        if (auth()->user()->hasRole('admin')) {
            return true;
        }

        return blank($this->order->sale_price_locked_at)
            && auth()->user()->hasAnyRole(['manager', 'ketoan', 'sale']);
    }

    public function canLockSaleCharge(): bool
    {
        return blank($this->order->sale_price_locked_at)
            && auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan', 'sale']);
    }

    public function showCostCharge(): bool
    {
        return $this->canEditAllCharges();
    }

    public function showBaseCharge(): bool
    {
        return $this->canEditAllCharges();
    }

    public function canEditAllCharges(): bool
    {
        return auth()->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    // =====================================================
    // INVOICE METHODS (Đơn lẻ)
    // =====================================================

    protected function loadInvoice(): void
    {
        $this->invoice = $this->order->congNoPayments()
            ->whereNotIn('status', [\App\Enums\InvoicePaymentStatusEnum::HUY->value])
            ->latest('id')
            ->first()
            ?? $this->order->congNoPayments()->latest('id')->first();
    }

    public function canCreateInvoice(): bool
    {
        return app(OrderInvoiceService::class)->canCreateInvoice($this->order, auth()->user());
    }

    public function isInvoiceLocked(): bool
    {
        return $this->order->isInvoiceLocked();
    }

    public function saleTotal(): float
    {
        return app(OrderInvoiceService::class)->getOrderSaleTotal($this->order);
    }

    public function createInvoice(): void
    {
        abort_unless($this->canCreateInvoice(), 403);

        try {
            $invoice = app(OrderInvoiceService::class)->createForOrder(
                $this->order,
                auth()->user(),
                'Tạo hóa đơn từ đơn hàng vãng lai'
            );
            $this->invoice = $invoice;
            Flux::toast(heading: 'Đã tạo hóa đơn', text: 'Hóa đơn đã được tạo và tự động duyệt.', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'danger');
        }
    }

    public function openCancelInvoice(): void
    {
        $this->cancelInvoiceReason = '';
        Flux::modal('cancel-order-invoice')->show();
    }

    public function closeCancelInvoice(): void
    {
        $this->resetErrorBag('cancelInvoiceReason');
        $this->cancelInvoiceReason = '';
        Flux::modal('cancel-order-invoice')->close();
    }

    public function submitCancelInvoice(): void
    {
        $this->validate([
            'cancelInvoiceReason' => ['required', 'string', 'max:500'],
        ]);

        try {
            app(OrderInvoiceService::class)->cancelInvoice(
                $this->invoice,
                auth()->user(),
                $this->cancelInvoiceReason
            );
            $this->loadInvoice();
            $this->closeCancelInvoice();
            Flux::toast(heading: 'Đã hủy hóa đơn', text: 'Hóa đơn đã được hủy.', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'danger');
        }
    }

    public function openPayModal(): void
    {
        if (! $this->invoice) {
            return;
        }
        abort_unless($this->invoice->canPay(auth()->user()), 403);

        $this->selectedPaymentMethod = null;
        $this->selectedProvider = $this->invoice->payment_provider ?: 'sepay';
        $this->cashPhoto = null;
        Flux::modal('pay-order-invoice')->show();
    }

    public function closePayModal(): void
    {
        $this->selectedPaymentMethod = null;
        $this->selectedProvider = PaymentProviderManager::defaultProvider();
        $this->cashPhoto = null;
        Flux::modal('pay-order-invoice')->close();
    }

    public function submitOrderCashPayment(): void
    {
        if (! $this->invoice) {
            return;
        }
        abort_unless($this->invoice->canPay(auth()->user()), 403);

        $this->validate([
            'cashPhoto' => ['required', 'image', 'max:8192'],
        ], [
            'cashPhoto.required' => 'Vui lòng upload ảnh hóa đơn đã thanh toán.',
        ]);

        $path = $this->cashPhoto->store('customer-debt-invoices', 'public');
        $fromStatus = $this->invoice->status;

        $updateData = [
            'method' => 'cash',
            'photo' => $path,
            'status' => InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT->value,
            'submitted_at' => now(),
        ];

        if ($fromStatus === InvoicePaymentStatusEnum::KHONG_CHAP_NHAN) {
            $updateData['payment_rejection_reason'] = null;
            $updateData['payment_rejected_at'] = null;
            $updateData['payment_rejected_by'] = null;
        }

        $this->invoice->forceFill($updateData)->save();
        $this->invoice->writeStatusLog('cash_submitted', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT, auth()->user()->id, null, ['photo' => $path]);

        $this->closePayModal();
        $this->loadInvoice();

        Flux::toast(heading: 'Đã gửi hóa đơn', text: 'Kế toán sẽ kiểm tra và xác nhận.', variant: 'success');
    }

    public function submitOrderOnlinePayment(): void
    {
        if (! $this->invoice) {
            return;
        }
        abort_unless($this->invoice->canPay(auth()->user()), 403);

        $providerKey = in_array($this->selectedProvider, PaymentProviderManager::allProviders(), true)
            ? $this->selectedProvider
            : PaymentProviderManager::defaultProvider();

        try {
            DB::transaction(function () use ($providerKey) {
                $locked = CongNoPayment::query()->whereKey($this->invoice->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->canPay(auth()->user()), 403);

                $code = $locked->payment_reference
                    ?: $locked->qr_payment_code
                    ?: app(InvoiceCodeGenerator::class)->generatePaymentCode($locked->ma_hoa_don);

                $intent = app(PaymentProviderManager::class)
                    ->driver($providerKey)
                    ->createPayment(new PaymentRequestData(
                        amount: (int) round((float) $locked->amount),
                        reference: $code,
                        description: $code,
                        expiresAt: now()->addMinutes(CongNoPayment::QR_THROTTLE_MINUTES),
                        metadata: ['request_id' => $code . '-' . now()->format('YmdHis')],
                    ));

                $fromStatus = $locked->status;

                $locked->forceFill([
                    'method' => $intent->channel === 'qr' ? 'bank_transfer' : 'online',
                    'status' => InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT->value,
                    'payment_provider' => $intent->provider,
                    'payment_channel' => $intent->channel,
                    'payment_reference' => $intent->reference,
                    'payment_url' => $intent->paymentUrl,
                    'provider_intent_id' => $intent->providerIntentId,
                    'provider_payload' => $intent->raw ?: null,
                    'qr_payment_code' => $intent->reference,
                    'qr_url' => $intent->qrUrl,
                    'qr_generated_at' => now(),
                    'qr_expires_at' => $intent->expiresAt,
                ])->save();

                $locked->writeStatusLog('qr_requested', $fromStatus, InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT, auth()->user()->id, null, [
                    'provider' => $intent->provider,
                ]);

                $this->invoice = $locked->fresh();
            });
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'danger');
            return;
        }

        $this->closePayModal();
        Flux::toast(heading: 'Đã tạo yêu cầu', text: 'Link thanh toán đã được tạo.', variant: 'success');
    }

    public function regenerateOrderQr(): void
    {
        if (! $this->invoice) {
            return;
        }
        abort_unless($this->invoice->canPay(auth()->user()) || $this->invoice->canManageQr(auth()->user()), 403);

        if (! $this->invoice->canRegenerateQr()) {
            $nextAt = $this->invoice->nextQrAvailableAt();
            Flux::toast(
                heading: 'Chưa thể tạo lại',
                text: $nextAt ? 'Vui lòng đợi đến ' . $nextAt->format('H:i d/m/Y') . '.' : 'Vui lòng đợi đủ 15 phút.',
                variant: 'warning'
            );
            return;
        }

        $providerKey = in_array($this->selectedProvider, PaymentProviderManager::allProviders(), true)
            ? $this->selectedProvider
            : ($this->invoice->payment_provider ?: PaymentProviderManager::defaultProvider());

        try {
            DB::transaction(function () use ($providerKey) {
                $locked = CongNoPayment::query()->whereKey($this->invoice->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->canPay(auth()->user()) || $locked->canManageQr(auth()->user()), 403);

                $code = $locked->payment_reference
                    ?: $locked->qr_payment_code
                    ?: app(InvoiceCodeGenerator::class)->generatePaymentCode($locked->ma_hoa_don);

                $intent = app(PaymentProviderManager::class)
                    ->driver($providerKey)
                    ->createPayment(new PaymentRequestData(
                        amount: (int) round((float) $locked->amount),
                        reference: $code,
                        description: $code,
                        expiresAt: now()->addMinutes(CongNoPayment::QR_THROTTLE_MINUTES),
                    ));

                $locked->forceFill([
                    'payment_provider' => $intent->provider,
                    'payment_channel' => $intent->channel,
                    'payment_reference' => $intent->reference,
                    'payment_url' => $intent->paymentUrl,
                    'provider_intent_id' => $intent->providerIntentId,
                    'provider_payload' => $intent->raw ?: null,
                    'qr_payment_code' => $intent->reference,
                    'qr_url' => $intent->qrUrl,
                    'qr_generated_at' => now(),
                    'qr_expires_at' => $intent->expiresAt,
                ])->save();

                $locked->writeStatusLog('qr_regenerated', $locked->status, $locked->status, auth()->user()->id, null, ['provider' => $intent->provider]);

                $this->invoice = $locked->fresh();
            });

            Flux::toast(heading: 'Đã tạo lại QR', text: 'Mã QR mới đã được tạo.', variant: 'success');
        } catch (\Throwable $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'danger');
        }
    }

    public function confirmOrderCash(): void
    {
        if (! $this->invoice) {
            return;
        }
        abort_unless($this->invoice->canConfirmCashPayment(auth()->user()), 403);

        try {
            app(OrderInvoiceService::class)->markPaid($this->invoice, auth()->user());
            $this->loadInvoice();
            Flux::toast(heading: 'Đã xác nhận', text: 'Thanh toán đã được ghi nhận.', variant: 'success');
        } catch (\RuntimeException $e) {
            Flux::toast(heading: 'Lỗi', text: $e->getMessage(), variant: 'danger');
        }
    }

    public function openRejectPayment(): void
    {
        $this->rejectPaymentReason = '';
        Flux::modal('reject-order-payment')->show();
    }

    public function closeRejectPayment(): void
    {
        $this->resetErrorBag('rejectPaymentReason');
        $this->rejectPaymentReason = '';
        Flux::modal('reject-order-payment')->close();
    }

    public function submitRejectPayment(): void
    {
        if (! $this->invoice) {
            return;
        }
        abort_unless($this->invoice->canRejectPayment(auth()->user()), 403);

        $this->validate([
            'rejectPaymentReason' => ['required', 'string', 'max:500'],
        ], [
            'rejectPaymentReason.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        $fromStatus = $this->invoice->status;

        $this->invoice->forceFill([
            'status' => InvoicePaymentStatusEnum::KHONG_CHAP_NHAN->value,
            'payment_rejection_reason' => $this->rejectPaymentReason,
            'payment_rejected_at' => now(),
            'payment_rejected_by' => auth()->id(),
        ])->save();

        $this->invoice->writeStatusLog('payment_rejected', $fromStatus, InvoicePaymentStatusEnum::KHONG_CHAP_NHAN, auth()->user()->id, $this->rejectPaymentReason);

        $this->closeRejectPayment();
        $this->loadInvoice();

        Flux::toast(heading: 'Đã từ chối', text: 'Chứng từ không được chấp nhận.', variant: 'warning');
    }

    public function routes(): array
    {
        return [
            'cancelInvoice' => route('invoice.cancel', ['id' => $this->invoice?->id]),
            'sales' => route('invoice.sales'),
            'customers' => route('invoice.customers'),
        ];
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
                @if($order->sale_price_locked_at)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-800">
                        <div class="font-semibold">Cước bán đã được chốt</div>
                        <div class="mt-1 text-emerald-700">
                            Chốt lúc {{ $order->sale_price_locked_at?->format('d/m/Y H:i') }}
                            @if($order->salePriceLocker)
                                bởi {{ $order->salePriceLocker->fullname ?: $order->salePriceLocker->username }}
                            @endif
                            . Chỉ Admin có quyền cập nhật cước bán sau khi chốt.
                        </div>
                    </div>
                @endif

                @if($this->showSaleCharge())
                    @include('pages.order.partials.payment-card', [
                        'group' => 'cuocban',
                        'title' => 'Cước bán',
                        'subtitle' => 'Giá bán báo khách và các khoản thu thêm',
                        'priceKey' => 'dongiaban',
                        'priceLabel' => 'Đơn giá bán',
                        'readonly' => ! $this->canEditSaleCharge(),
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

            <div class="flex items-center justify-end gap-3 rounded-xl border border-neutral-200 bg-white px-5 py-4 shadow-xs">
                <a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="inline-flex items-center justify-center rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-semibold text-neutral-700 hover:bg-neutral-50">
                    Thoát
                </a>
                @if($this->canLockSaleCharge())
                    <flux:button type="button" wire:click="lockSaleCharge" variant="outline">Chốt giá bán</flux:button>
                @endif
                @if($this->canEditSaleCharge())
                    <flux:button type="submit" variant="primary">Lưu giá</flux:button>
                @endif
            </div>
        </div>
    </form>

    {{-- ============================================================
         HÓA ĐƠN THU CHO ĐƠN LẺ
         ============================================================ --}}
    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-neutral-800">Hóa đơn thu</h2>
                <p class="mt-0.5 text-xs text-neutral-500">Hóa đơn cho đơn hàng vãng lai</p>
            </div>
            @if($this->invoice)
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $this->invoice->status->color() }}">
                    {{ $this->invoice->status->label() }}
                </span>
            @endif
        </div>

        <div class="p-5">
            @if($this->invoice)
                {{-- ĐÃ CO INVOICE --}}
                <div class="space-y-4">
                    {{-- Info row --}}
                    <div class="grid gap-4 rounded-lg border border-neutral-100 bg-neutral-50 p-4 md:grid-cols-3">
                        <div>
                            <p class="text-xs text-neutral-500">Mã hóa đơn</p>
                            <p class="mt-0.5 font-semibold text-neutral-900">{{ $this->invoice->ma_hoa_don }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-500">Số tiền</p>
                            <p class="mt-0.5 text-lg font-bold text-primary-700">{{ $this->money($this->invoice->amount) }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-neutral-500">Ngày tạo</p>
                            <p class="mt-0.5 text-sm text-neutral-700">{{ $this->invoice->created_at?->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    {{-- Trạng thái DA_DUYET --}}
                    @if($this->invoice->status === \App\Enums\InvoicePaymentStatusEnum::DA_DUYET)
                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-4">
                            <p class="text-sm font-medium text-blue-800">Hóa đơn đã duyệt — chọn phương thức thanh toán</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if($this->invoice->canPay(auth()->user()))
                                    <flux:button type="button" wire:click="openPayModal" variant="primary" size="sm">
                                        Chọn thanh toán
                                    </flux:button>
                                @endif
                                @if($this->invoice->canCancel(auth()->user()))
                                    <flux:button type="button" wire:click="openCancelInvoice" variant="danger" size="sm">
                                        Hủy hóa đơn
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                    {{-- Trạng thái DA_GUI_YEU_CAU_TT --}}
                    @elseif($this->invoice->status === \App\Enums\InvoicePaymentStatusEnum::DA_GUI_YEU_CAU_TT)
                        <div class="rounded-lg border border-purple-100 bg-purple-50 p-4 space-y-3">
                            <p class="text-sm font-medium text-purple-800">Đã gửi yêu cầu thanh toán</p>
                            @if($this->invoice->qr_url || $this->invoice->payment_url)
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                                    @if($this->invoice->qr_url && $this->invoice->payment_provider === 'sepay')
                                        {{-- SePay trả về ảnh QR thực --}}
                                        <div class="flex-shrink-0">
                                            <img src="{{ $this->invoice->qr_url }}" alt="QR" class="h-32 w-32 rounded-lg border border-purple-200 bg-white">
                                        </div>
                                    @endif
                                    <div class="flex-1 space-y-2">
                                        <p class="text-xs text-purple-700">Link thanh toán:</p>
                                        <a href="{{ $this->invoice->payment_url ?: $this->invoice->qr_url }}" target="_blank" class="inline-flex items-center gap-1 text-sm font-medium text-blue-600 hover:text-blue-800 break-all">
                                            <svg class="h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            {{ $this->invoice->payment_url ?: $this->invoice->qr_url }}
                                        </a>
                                        @if($this->invoice->payment_reference)
                                            <p class="text-xs text-neutral-500">Mã tham chiếu: {{ $this->invoice->payment_reference }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if($this->invoice->qr_generated_at)
                                <p class="text-xs text-neutral-500">
                                    @if($this->invoice->canRegenerateQr())
                                        Có thể tạo lại QR
                                    @else
                                        Tạo lại QR sau: {{ $this->invoice->nextQrAvailableAt()?->diffForHumans() }}
                                    @endif
                                </p>
                            @endif
                            <div class="flex flex-wrap gap-2 pt-1">
                                @if($this->invoice->canPay(auth()->user()) || $this->invoice->canManageQr(auth()->user()))
                                    @if($this->invoice->canRegenerateQr())
                                        <flux:button type="button" wire:click="regenerateOrderQr" variant="outline" size="sm">Tạo lại QR</flux:button>
                                    @endif
                                @endif
                                @if($this->invoice->canCancel(auth()->user()))
                                    <flux:button type="button" wire:click="openCancelInvoice" variant="danger" size="sm">Hủy</flux:button>
                                @endif
                            </div>
                        </div>

                    {{-- Trạng thái DA_GUI_HOA_DON_TT (chờ duyệt thanh toán tiền mặt) --}}
                    @elseif($this->invoice->status === \App\Enums\InvoicePaymentStatusEnum::DA_GUI_HOA_DON_TT)
                        <div class="rounded-lg border border-amber-100 bg-amber-50 p-4 space-y-3">
                            <p class="text-sm font-medium text-amber-800">Đã gửi chứng từ — chờ duyệt thanh toán</p>
                            @if($this->invoice->photo)
                                <div>
                                    <p class="text-xs text-amber-700 mb-1">Ảnh hóa đơn đã nộp:</p>
                                    <a href="{{ Storage::url($this->invoice->photo) }}" target="_blank" class="inline-block">
                                        <img src="{{ Storage::url($this->invoice->photo) }}" alt="Ảnh hóa đơn" class="h-20 w-auto rounded border border-amber-200">
                                    </a>
                                </div>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @if($this->invoice->canConfirmCashPayment(auth()->user()))
                                    <flux:button type="button" wire:click="confirmOrderCash" variant="primary" size="sm">Xác nhận thanh toán</flux:button>
                                @endif
                                @if($this->invoice->canRejectPayment(auth()->user()))
                                    <flux:button type="button" wire:click="openRejectPayment" variant="danger" size="sm">Từ chối</flux:button>
                                @endif
                                @if($this->invoice->canCancel(auth()->user()))
                                    <flux:button type="button" wire:click="openCancelInvoice" variant="outline" size="sm">Hủy</flux:button>
                                @endif
                            </div>
                        </div>

                    {{-- Trạng thái KHONG_CHAP_NHAN --}}
                    @elseif($this->invoice->status === \App\Enums\InvoicePaymentStatusEnum::KHONG_CHAP_NHAN)
                        <div class="rounded-lg border border-red-100 bg-red-50 p-4 space-y-3">
                            <p class="text-sm font-medium text-red-800">Chứng từ bị từ chối</p>
                            @if($this->invoice->payment_rejection_reason)
                                <div class="rounded border border-red-200 bg-white p-3">
                                    <p class="text-xs font-medium text-red-700">Lý do:</p>
                                    <p class="mt-0.5 text-sm text-red-900">{{ $this->invoice->payment_rejection_reason }}</p>
                                </div>
                            @endif
                            <div class="flex flex-wrap gap-2">
                                @if($this->invoice->canPay(auth()->user()))
                                    <flux:button type="button" wire:click="openPayModal" variant="primary" size="sm">Upload lại chứng từ</flux:button>
                                @endif
                                @if($this->invoice->canCancel(auth()->user()))
                                    <flux:button type="button" wire:click="openCancelInvoice" variant="outline" size="sm">Hủy hóa đơn</flux:button>
                                @endif
                            </div>
                        </div>

                    {{-- Trạng thái DA_THANH_TOAN --}}
                    @elseif($this->invoice->status === \App\Enums\InvoicePaymentStatusEnum::DA_THANH_TOAN)
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-4">
                            <div class="flex items-center gap-2">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <p class="text-sm font-semibold text-emerald-800">Đã thanh toán</p>
                            </div>
                            <p class="mt-1 text-xs text-emerald-700">
                                Thanh toán lúc {{ $this->invoice->paid_at?->format('d/m/Y H:i') }}
                            </p>
                            @if($this->invoice->method)
                                <p class="mt-0.5 text-xs text-emerald-600">
                                    Phương thức: {{ ucfirst($this->invoice->method) }}
                                </p>
                            @endif
                        </div>

                    {{-- Trạng thái HUY --}}
                    @elseif($this->invoice->status === \App\Enums\InvoicePaymentStatusEnum::HUY)
                        <div class="rounded-lg border border-neutral-200 bg-neutral-100 p-4">
                            <p class="text-sm font-medium text-neutral-600">Hóa đơn đã hủy</p>
                            @if($this->invoice->cancel_reason)
                                <p class="mt-1 text-xs text-neutral-500">Lý do: {{ $this->invoice->cancel_reason }}</p>
                            @endif
                        </div>
                        @if($this->canCreateInvoice())
                            <div class="mt-4 flex flex-col items-center gap-2 rounded-lg border border-dashed border-neutral-300 p-4">
                                <p class="text-xs text-neutral-500">Tạo hóa đơn mới với số tiền:</p>
                                <p class="text-lg font-bold text-primary-700">{{ $this->money($this->saleTotal()) }}</p>
                                <flux:button type="button" wire:click="createInvoice" variant="primary" size="sm">
                                    Tạo hóa đơn mới
                                </flux:button>
                            </div>
                        @endif
                    @endif
                </div>

            @else
                {{-- CHUA CO INVOICE --}}
                <div class="rounded-lg border border-dashed border-neutral-300 p-6 text-center">
                    @if($this->canCreateInvoice())
                        <div class="space-y-3">
                            <div class="flex items-center justify-center gap-2 text-neutral-500">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="text-sm">Chưa có hóa đơn</span>
                            </div>
                            <div class="flex flex-col items-center gap-1">
                                <p class="text-xs text-neutral-500">Số tiền hóa đơn sẽ bằng tổng cước bán:</p>
                                <p class="text-xl font-bold text-primary-700">{{ $this->money($this->saleTotal()) }}</p>
                            </div>
                            <flux:button type="button" wire:click="createInvoice" variant="primary" size="sm">
                                Tạo hóa đơn thu
                            </flux:button>
                        </div>
                    @else
                        <div class="flex items-center justify-center gap-2 text-neutral-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span class="text-sm">Chưa có hóa đơn</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- ============================================================
         MODALS
         ============================================================ --}}

    {{-- Modal: Thanh toán hóa đơn --}}
    <flux:modal name="pay-order-invoice" class="w-full max-w-2xl" @close="$wire.closePayModal()">
        <div class="space-y-5">
            <div>
                <h2 class="text-lg font-bold text-neutral-950">Thanh toán hóa đơn</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    @if ($this->invoice)
                        Mã hóa đơn <span class="font-semibold text-neutral-800">{{ $this->invoice->ma_hoa_don }}</span>
                        - Số tiền cần thanh toán
                        <span class="font-semibold text-rose-700">{{ number_format($this->invoice->amount, 0, ',', '.') }} đ</span>
                    @else
                        Chưa chọn hóa đơn.
                    @endif
                </p>
            </div>

            @if ($this->invoice)
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ $selectedPaymentMethod === 'cash' ? 'border-rose-400 bg-rose-50/70 ring-2 ring-rose-100' : 'border-neutral-200 bg-white hover:border-rose-200' }}">
                        <input type="radio" wire:model.live="selectedPaymentMethod" value="cash" class="mt-1 h-4 w-4 text-rose-600 focus:ring-rose-500">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-neutral-900">Tiền mặt</p>
                            <p class="mt-0.5 text-xs text-neutral-500">Khách thanh toán trực tiếp. Cần upload ảnh hóa đơn đã thu tiền.</p>
                        </div>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition {{ $selectedPaymentMethod === 'online' ? 'border-rose-400 bg-rose-50/70 ring-2 ring-rose-100' : 'border-neutral-200 bg-white hover:border-rose-200' }}">
                        <input type="radio" wire:model.live="selectedPaymentMethod" value="online" class="mt-1 h-4 w-4 text-rose-600 focus:ring-rose-500">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-neutral-900">Chuyển khoản online</p>
                            <p class="mt-0.5 text-xs text-neutral-500">Tạo link thanh toán qua cổng. Hệ thống tự xác nhận khi nhận tiền.</p>
                        </div>
                    </label>
                </div>

                @if ($selectedPaymentMethod === 'online')
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-4" wire:key="pay-method-online">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-600">Chọn cổng thanh toán</p>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach(\App\Services\Payments\PaymentProviderManager::providerLabels() as $key => $meta)
                                @if($enabledProviders[$key] ?? false)
                                <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition {{ $selectedProvider === $key ? 'border-'.$meta['color'].'-400 bg-'.$meta['color'].'-50/70 ring-2 ring-'.$meta['color'].'-100' : 'border-neutral-200 bg-white hover:border-'.$meta['color'].'-200' }}">
                                    <input type="radio" wire:model.live="selectedProvider" value="{{ $key }}" class="h-4 w-4 text-{{ $meta['color'] }}-600 focus:ring-{{ $meta['color'] }}-500">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-neutral-900">{{ $meta['name'] }}</p>
                                        <p class="text-xs text-neutral-500">{{ $meta['description'] }}</p>
                                    </div>
                                </label>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($selectedPaymentMethod === 'cash')
                    <form wire:submit="submitOrderCashPayment" class="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4" wire:key="pay-method-cash">
                        <div>
                            <label class="block text-sm font-semibold text-neutral-800">Ảnh hóa đơn đã thanh toán <span class="text-rose-600">*</span></label>
                            <p class="text-xs text-neutral-500">Định dạng ảnh, tối đa 8MB.</p>
                            <input type="file" wire:model="cashPhoto" accept="image/*" class="mt-2 block w-full rounded-lg border border-neutral-200 bg-white text-sm file:mr-3 file:cursor-pointer file:rounded-l-lg file:border-0 file:bg-rose-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-rose-700">
                            @error('cashPhoto')
                                <p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                            <div wire:loading wire:target="cashPhoto" class="mt-2 text-xs text-neutral-500">Đang tải ảnh lên...</div>
                            @if ($cashPhoto && method_exists($cashPhoto, 'temporaryUrl'))
                                <div class="mt-3 overflow-hidden rounded-lg border border-neutral-200 bg-white">
                                    <img src="{{ $cashPhoto->temporaryUrl() }}" alt="Preview" class="max-h-64 w-full object-contain">
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-neutral-200 pt-3">
                            <flux:button type="button" variant="outline" wire:click="closePayModal">Hủy</flux:button>
                            <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="submitOrderCashPayment,cashPhoto">
                                Gửi hóa đơn thanh toán
                            </flux:button>
                        </div>
                    </form>
                @elseif ($selectedPaymentMethod === 'online')
                    <div class="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                        <div class="flex items-start gap-2 text-sm text-neutral-600">
                            <flux:icon.information-circle class="mt-0.5 size-4 text-rose-500" />
                            <p>Khi bấm "Tạo thanh toán", hệ thống sẽ sinh yêu cầu thanh toán theo cổng đã chọn. SePay tạo QR ngân hàng, MoMo tạo link/ví MoMo, VNPAY tạo link chuyển hướng; webhook sẽ tự cập nhật trạng thái sang "Đã thanh toán".</p>
                        </div>
                        <div class="flex items-center justify-end gap-2 border-t border-neutral-200 pt-3">
                            <flux:button type="button" variant="outline" wire:click="closePayModal">Hủy</flux:button>
                            <flux:button type="button" variant="primary" icon="{{ $selectedProvider === 'sepay' ? 'qr-code' : 'link' }}" wire:click="submitOrderOnlinePayment" wire:loading.attr="disabled" wire:target="submitOrderOnlinePayment">
                                {{ $selectedProvider === 'sepay' ? 'Tạo mã QR thanh toán' : 'Tạo link thanh toán' }}
                            </flux:button>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg bg-neutral-50 px-4 py-6 text-center text-sm text-neutral-500">
                        Vui lòng chọn phương thức thanh toán phía trên.
                    </div>
                @endif
            @endif
        </div>
    </flux:modal>

    {{-- Modal: Hủy hóa đơn --}}
    <flux:modal name="cancel-order-invoice" class="w-full max-w-lg" @close="$wire.closeCancelInvoice()">
        <form wire:submit="submitCancelInvoice" class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-neutral-950">Hủy hóa đơn</h2>
                <p class="mt-1 text-sm text-neutral-500">Hủy hóa đơn <strong>{{ $this->invoice?->ma_hoa_don }}</strong>. Sau khi hủy, bạn có thể tạo hóa đơn mới.</p>
            </div>

            <flux:field>
                <flux:label>Lý do hủy <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="cancelInvoiceReason" rows="3" placeholder="Nhập lý do hủy..."/>
                <flux:error name="cancelInvoiceReason"/>
            </flux:field>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:button type="button" wire:click="closeCancelInvoice" variant="outline">Đóng</flux:button>
                <flux:button type="submit" variant="danger" icon="x-circle">Hủy hóa đơn</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal: Từ chối thanh toán --}}
    <flux:modal name="reject-order-payment" class="w-full max-w-lg" @close="$wire.closeRejectPayment()">
        <form wire:submit="submitRejectPayment" class="space-y-4">
            <div>
                <h2 class="text-lg font-bold text-neutral-950">Từ chối chứng từ</h2>
                <p class="mt-1 text-sm text-neutral-500">Chứng từ không hợp lệ. Vui lòng nhập lý do từ chối để sale biết và upload lại.</p>
            </div>

            <flux:field>
                <flux:label>Lý do từ chối <span class="text-red-500">*</span></flux:label>
                <flux:textarea wire:model="rejectPaymentReason" rows="3" placeholder="VD: Ảnh mờ, thiếu thông tin, số tiền không khớp..."/>
                <flux:error name="rejectPaymentReason"/>
            </flux:field>

            <div class="flex items-center justify-end gap-2 border-t border-neutral-100 pt-4">
                <flux:button type="button" wire:click="closeRejectPayment" variant="outline">Đóng</flux:button>
                <flux:button type="submit" variant="danger" icon="x-circle">Từ chối chứng từ</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
