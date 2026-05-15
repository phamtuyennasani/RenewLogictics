<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatusEnum;
use Flux\Flux;

new #[Layout('layouts.app')] #[Title('Chi tiết đơn hàng')] class extends Component
{
    public Order $order;
    public array $statusSteps = [];
    public string $trackingForm = '';
    public bool $printBillWithCvck = false;

    public function mount(string $uuid): void
    {
        $this->order = Order::query()
            ->with([
                'customer',
                'creator:id,fullname,username,code',
                'sale:id,fullname,username,code',
                'manager:id,fullname,username,code',
                'ketoan:id,fullname,username,code',
                'ops:id,fullname,username,code',
                'cs:id,fullname,username,code',
                'packages',
                'invoices',
                'photos',
                'dichvu:id,namevi',
                'chiTietDichVu:id,namevi',
                'chiNhanhNhanHang:id,namevi',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $this->trackingForm = (string) ($this->order->tracking_code ?? '');

        $this->statusSteps = [
            OrderStatusEnum::MOI_TAO,
            OrderStatusEnum::DA_XAC_NHAN,
            OrderStatusEnum::DA_NHAN_HANG,
            OrderStatusEnum::DUYET_XUAT_HANG,
            OrderStatusEnum::DANG_PHAT_HANG,
            OrderStatusEnum::DA_GIAO,
        ];
    }

    public function getStatusLabelProperty(): string
    {
        return $this->order->bill_status?->label() ?? 'Chưa rõ';
    }

    public function getStatusColorProperty(): string
    {
        return $this->order->bill_status?->color() ?? 'bg-neutral-100 text-neutral-700';
    }

    public function getProgressProperty(): int
    {
        $current = $this->progressStatus();

        if (!$current instanceof OrderStatusEnum) {
            return 0;
        }

        $currentIndex = collect($this->statusSteps)->search(fn ($status) => $status === $current);

        if ($currentIndex === false) {
            return $current->isFinal() ? 100 : 0;
        }

        return (int) round((($currentIndex + 1) / count($this->statusSteps)) * 100);
    }

    public function progressStatus(): ?OrderStatusEnum
    {
        $current = $this->order->bill_status;

        return match ($current) {
            OrderStatusEnum::RETURN_ORDER,
            OrderStatusEnum::CAUTION,
            OrderStatusEnum::CUSTOM_RELEASING,
            OrderStatusEnum::CAP_BEN => OrderStatusEnum::DANG_PHAT_HANG,
            default => $current,
        };
    }

    public function actionButtons(): array
    {
        return match ($this->order->bill_status) {
            OrderStatusEnum::MOI_TAO => [
                ['type' => 'status', 'label' => 'Xác nhận đơn', 'status' => OrderStatusEnum::DA_XAC_NHAN->value, 'variant' => 'primary'],
                ['type' => 'status', 'label' => 'Hủy đơn', 'status' => OrderStatusEnum::HUY->value, 'variant' => 'danger'],
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
            OrderStatusEnum::DA_XAC_NHAN => [
                ['type' => 'status', 'label' => 'Nhận hàng', 'status' => OrderStatusEnum::DA_NHAN_HANG->value, 'variant' => 'primary'],
                ['type' => 'price', 'label' => 'Cập nhật Giá'],
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
            OrderStatusEnum::DA_NHAN_HANG => [
                ['type' => 'status', 'label' => 'Duyệt xuất hàng', 'status' => OrderStatusEnum::DUYET_XUAT_HANG->value, 'variant' => 'primary'],
                ['type' => 'price', 'label' => 'Cập nhật Giá'],
                ['type' => 'tracking', 'label' => 'Cập nhật tracking'],
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
            OrderStatusEnum::DUYET_XUAT_HANG => [
                ['type' => 'status', 'label' => 'Xác nhận Phát hàng', 'status' => OrderStatusEnum::DANG_PHAT_HANG->value, 'variant' => 'primary'],
                ['type' => 'price', 'label' => 'Cập nhật Giá'],
                ['type' => 'tracking', 'label' => 'Cập nhật tracking'],
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
            OrderStatusEnum::DANG_PHAT_HANG => [
                ['type' => 'price', 'label' => 'Cập nhật Giá'],
                ['type' => 'tracking', 'label' => 'Cập nhật tracking'],
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
            OrderStatusEnum::HUY => [
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
            default => [
                ['type' => 'price', 'label' => 'Cập nhật Giá'],
                ['type' => 'tracking', 'label' => 'Cập nhật tracking'],
                ['type' => 'exit', 'label' => 'Thoát'],
            ],
        };
    }

    public function actionButtonClass(array $button): string
    {
        $base = 'inline-flex items-center justify-center rounded-xl border px-4 py-2 text-sm font-semibold shadow-sm transition disabled:cursor-not-allowed disabled:opacity-60';

        if (($button['variant'] ?? null) === 'danger') {
            return $base . ' border-red-600 bg-red-600 text-white hover:border-red-700 hover:bg-red-700';
        }

        return match ($button['type']) {
            'status' => $base . ' border-emerald-600 bg-emerald-600 text-white hover:border-emerald-700 hover:bg-emerald-700',
            'price' => $base . ' border-amber-200 bg-amber-50 text-amber-800 hover:border-amber-300 hover:bg-amber-100',
            'tracking' => $base . ' border-sky-200 bg-sky-50 text-sky-800 hover:border-sky-300 hover:bg-sky-100',
            'exit' => $base . ' border-neutral-200 bg-white text-neutral-700 shadow-xs hover:bg-neutral-50',
            default => $base . ' border-neutral-200 bg-white text-neutral-700 shadow-xs hover:bg-neutral-50',
        };
    }

    public function updateStatus(string $status): void
    {
        $nextStatus = OrderStatusEnum::tryFrom($status);

        if (! $nextStatus) {
            return;
        }

        $before = [
            'bill_status' => $this->order->bill_status?->value,
        ];

        $payload = [
            'bill_status' => $nextStatus,
        ];

        if ($nextStatus === OrderStatusEnum::DA_NHAN_HANG && blank($this->order->ngaynhanhang)) {
            $payload['ngaynhanhang'] = now();
        }

        if ($nextStatus === OrderStatusEnum::DUYET_XUAT_HANG && blank($this->order->ngayxuathang)) {
            $payload['ngayxuathang'] = now();
        }

        $this->order->forceFill($payload)->save();
        $this->order->refresh();

        RecordOrderEditHistoryAction::execute($this->order, 'update_status', 'trạng thái', $before, [
            'bill_status' => $this->order->bill_status?->value,
        ], 'cập nhật trạng thái đơn');

        $this->dispatch('order-history-updated');
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật trạng thái đơn.', variant: 'success');
    }

    public function saveTracking(): void
    {
        $this->validate([
            'trackingForm' => 'nullable|string|max:255',
        ]);

        $before = [
            'tracking_code' => $this->order->tracking_code,
        ];

        $this->order->forceFill([
            'tracking_code' => trim($this->trackingForm),
        ])->save();
        $this->order->refresh();
        $this->trackingForm = (string) ($this->order->tracking_code ?? '');

        RecordOrderEditHistoryAction::execute($this->order, 'edit_tracking', 'tracking', $before, [
            'tracking_code' => $this->order->tracking_code,
        ], 'cập nhật tracking');

        $this->dispatch('order-history-updated');
        Flux::modal('edit-tracking')->close();
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật tracking.', variant: 'success');
    }

    public function customerCompanyName(): ?string
    {
        if (blank($this->order->id_customer)) {
            return null;
        }

        $customer = User::query()
            ->select(['id', 'fullname', 'options'])
            ->whereKey($this->order->id_customer)
            ->first();

        if (! $customer) {
            return null;
        }

        return data_get($customer->options, 'company.company_short_name')
            ?: data_get($customer->options, 'company.company_name')
            ?: $customer->fullname;
    }

    public function code128BarcodeSvg(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $patterns = [
            '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
            '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
            '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
            '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
            '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
            '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
            '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
            '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
            '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
            '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
            '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
        ];

        $codes = [104];
        $checksum = 104;
        $weight = 1;

        foreach (str_split($value) as $char) {
            $ascii = ord($char);
            $code = ($ascii >= 32 && $ascii <= 127) ? $ascii - 32 : 0;
            $codes[] = $code;
            $checksum += $code * $weight;
            $weight++;
        }

        $codes[] = $checksum % 103;
        $codes[] = 106;

        $module = 1.45;
        $height = 42;
        $quiet = 12;
        $x = $quiet;
        $bars = '';

        foreach ($codes as $code) {
            foreach (str_split($patterns[$code]) as $index => $width) {
                $barWidth = (int) $width * $module;

                if ($index % 2 === 0) {
                    $bars .= sprintf(
                        '<rect x="%.2f" y="0" width="%.2f" height="%d" fill="#000"/>',
                        $x,
                        $barWidth,
                        $height
                    );
                }

                $x += $barWidth;
            }
        }

        $width = $x + $quiet;
        $escapedValue = e($value);

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f 56" preserveAspectRatio="none" style="display:block;width:100%%;height:18mm;"><rect width="100%%" height="100%%" fill="#fff"/>%s<text x="50%%" y="53" text-anchor="middle" font-family="Segoe UI, Tahoma, Arial, sans-serif" font-size="9" font-weight="700">%s</text></svg>',
            $width,
            $bars,
            $escapedValue
        );
    }

    public function labelText(mixed $value, string $fallback = '-'): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return $fallback;
        }

        return $text;
    }

    public function money(mixed $value, int $decimals = 0, string $fallback = '-'): string
    {
        return is_numeric($value) ? number_format((float) $value, $decimals) : $fallback;
    }

    public function paymentTotal(?array $payment, string $preferredKey = 'total_tongcuoc'): float
    {
        if (!$payment) {
            return 0;
        }

        $preferred = data_get($payment, $preferredKey);

        if (is_numeric($preferred)) {
            return (float) $preferred;
        }

        return (float) data_get($payment, 'tongcuoc', 0);
    }

    public function printBill(bool $withCvck = false): void
    {
        $this->printBillWithCvck = $withCvck;
        Flux::modal('print-bill-confirm')->close();
        $this->dispatch('print-bill-ready');
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $primaryHex = config('theme.primary.hex', '#3b82f6');
    $accentHex = config('theme.accent.hex', '#0ea5e9');
    $gradientStyle = "background: linear-gradient(135deg, {$primaryHex}, {$accentHex});";
    $customerCompanyName = $this->customerCompanyName();
    $packageTypeIds = $order->packages->pluck('package_type')->filter()->unique()->values();
    $packageTypeNames = $packageTypeIds->isNotEmpty()
        ? \App\Models\News::query()->whereIn('id', $packageTypeIds)->pluck('namevi', 'id')
        : collect();
    $labelPages = [];

    foreach ($order->packages as $packageIndex => $package) {
        $packageCount = max(1, (int) ($package->number_of_package ?? 1));

        for ($piece = 1; $piece <= $packageCount; $piece++) {
            $packageCode = trim((string) ($package->code ?? ''));

            $labelPages[] = [
                'package' => $package,
                'package_index' => $packageIndex + 1,
                'piece' => $piece,
                'pieces_in_row' => $packageCount,
                'package_type' => $packageTypeNames[$package->package_type] ?? null,
                'package_code' => $packageCode !== '' ? $packageCode : ($order->id_bill ?: 'ORDER-'.$order->id).'-K'.($packageIndex + 1),
            ];
        }
    }

    if ($labelPages === []) {
        $labelPages[] = [
            'package' => null,
            'package_index' => 1,
            'piece' => 1,
            'pieces_in_row' => 1,
            'package_type' => null,
            'package_code' => ($order->id_bill ?: 'ORDER-'.$order->id).'-K1',
        ];
    }

    $totalLabelPages = count($labelPages);
    $sender = $order->sender ?? [];
    $receiver = $order->receiver ?? [];
    $receiverCountryId = data_get($receiver, 'country_id', data_get($receiver, 'id_country'));
    $receiverCountry = $receiverCountryId ? \App\Models\Country::query()->whereKey($receiverCountryId)->first(['id', 'name', 'iso2', 'iso3', 'phonecode']) : null;
    $receiverCountryName = $receiverCountry?->name ?: data_get($receiver, 'country', '-');
    $receiverAddress = trim(implode(', ', array_filter([
        data_get($receiver, 'address'),
        data_get($receiver, 'city'),
        data_get($receiver, 'state'),
        $receiverCountryName !== '-' ? $receiverCountryName : null,
        data_get($receiver, 'postcode'),
    ])));
    $packageCount = $order->packages->count();
    $grossWeight = $order->packages->sum(fn ($package) => (float) ($package->g_weight ?? 0));
    $volumeWeight = $order->packages->sum(fn ($package) => (float) ($package->v_weight ?? 0));
    $chargeableWeight = $order->packages->sum(fn ($package) => (float) ($package->c_weight ?? 0));
    $invoiceValue = $order->invoices->sum(fn ($invoice) => (float) ($invoice->total ?? 0));
    $invoiceQty = $order->invoices->sum(fn ($invoice) => (int) ($invoice->soluong ?? 0));
    $saleTotal = $this->paymentTotal($order->payment_cuocban);
    $customerForCvck = $order->id_customer
        ? \App\Models\User::query()->whereKey($order->id_customer)->first(['id', 'fullname', 'phone', 'email', 'options'])
        : null;
    $cvckName = $customerForCvck?->fullname ?: data_get($sender, 'company', data_get($sender, 'fullname', '-'));
    $cvckId = data_get($customerForCvck?->options ?? [], 'cccd', data_get($customerForCvck?->options ?? [], 'tax_code', '-'));
    $cvckAddress = data_get($customerForCvck?->options ?? [], 'address', data_get($sender, 'address', '-'));
@endphp

<div class="space-y-5">
    <style>
        .order-label-print-area,
        .order-bill-print-area {
            display: none;
        }

        @media print {
            @page label {
                size: A6 portrait;
                margin: 0;
            }

            @page bill {
                size: A4 portrait;
                margin: 8mm;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            body * {
                visibility: hidden !important;
            }

            body[data-print-target="label"] .order-label-print-area,
            body[data-print-target="label"] .order-label-print-area *,
            body[data-print-target="bill"] .order-bill-print-area,
            body[data-print-target="bill"] .order-bill-print-area * {
                visibility: visible !important;
            }

            body[data-print-target="label"] .order-label-print-area,
            body[data-print-target="bill"] .order-bill-print-area {
                display: block !important;
                position: absolute;
                inset: 0 auto auto 0;
                background: #fff;
            }

            body[data-print-target="label"] .order-label-print-area {
                width: 105mm;
            }

            body[data-print-target="bill"] .order-bill-print-area {
                width: 194mm;
            }

            .order-label-page {
                box-sizing: border-box;
                width: 105mm;
                height: 148mm;
                page: label;
                padding: 7mm;
                overflow: hidden;
                page-break-after: always;
                break-after: page;
                color: #111827;
                font-family: "Segoe UI", Tahoma, "Arial Unicode MS", Arial, sans-serif;
            }

            .order-label-page:last-child {
                page-break-after: auto;
                break-after: auto;
            }

            .order-bill-page {
                box-sizing: border-box;
                width: 194mm;
                min-height: 281mm;
                page: bill;
                page-break-after: always;
                break-after: page;
                color: #111827;
                font-family: "Segoe UI", Tahoma, "Arial Unicode MS", Arial, sans-serif;
                font-size: 11px;
            }

            .order-bill-page:last-child {
                page-break-after: auto;
                break-after: auto;
            }
        }
    </style>

    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <p class="text-sm text-neutral-500">Đơn hàng / Chi tiết</p>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-neutral-900">{{ $order->id_bill ?: 'Đơn hàng #' . $order->id }}</h1>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $this->statusColor }}">
                    {{ $this->statusLabel }}
                </span>
                @if($order->lock_order)
                    <span class="inline-flex items-center rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Đã khóa</span>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-500">
                <span>Tạo lúc {{ $order->created_at?->format('d/m/Y H:i') ?? '—' }}</span>
                <span>-</span>
                <span>Sale: <span class="font-medium text-neutral-700">{{ $order->sale?->fullname ?: $order->sale?->username ?: '—' }}</span></span>
                @if($customerCompanyName)
                    <span>-</span>
                    <span>Thông tin Khách hàng: <span class="font-medium text-neutral-700">{{ $customerCompanyName }}</span></span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('orders.index') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-xs transition-all hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Quay lại
            </a>
            <a href="{{ route('tracking', ['idbill' => $order->id_bill ?: $order->id]) }}" target="_blank"
                class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-xs transition-all hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Theo dõi
            </a>
            <button type="button"
                onclick="document.body.dataset.printTarget = 'label'; window.print()"
                class="inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-medium text-violet-700 shadow-xs transition-all hover:border-violet-300 hover:bg-violet-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8V4h10v4M7 17H5a2 2 0 01-2-2v-4a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2h-2M7 14h10v6H7v-6z"/></svg>
                Print Label
            </button>
            <flux:modal.trigger name="print-bill-confirm">
                <button type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-700 shadow-xs transition-all hover:border-sky-300 hover:bg-sky-100">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14h6M9 18h6M7 3h7l5 5v13H7a2 2 0 01-2-2V5a2 2 0 012-2zM14 3v5h5"/></svg>
                    Print Bill
                </button>
            </flux:modal.trigger>
            <button type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700 shadow-xs transition-all hover:border-emerald-300 hover:bg-emerald-100">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v12m0 0l4-4m-4 4l-4-4M5 19h14"/></svg>
                Export Invoice
            </button>
            <a href="{{ route('orders.create') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-white shadow-sm transition-all hover:shadow-md"
                style="{{ $gradientStyle }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Tạo đơn mới
            </a>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
        <div class="mb-4 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-neutral-900 uppercase">Tiến trình xử lý</p>
                <p class="text-xs text-neutral-500">Từ khởi tạo đến giao hàng</p>
            </div>
            <span class="text-sm font-semibold text-neutral-700">{{ $this->progress }}%</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
            <div class="h-full rounded-full transition-all" style="width: {{ $this->progress }}%; {{ $gradientStyle }}"></div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
            @foreach($statusSteps as $step)
                @php
                    $progressStatus = $this->progressStatus();
                    $isDone = $progressStatus instanceof \App\Enums\OrderStatusEnum && $step->sortOrder() < $progressStatus->sortOrder();
                    $isCurrent = $progressStatus === $step;
                @endphp
                <div class="rounded-lg border px-3 py-2 {{ $isCurrent ? 'border-primary-200 bg-primary-50' : ($isDone ? 'border-emerald-100 bg-emerald-50' : 'border-neutral-100 bg-neutral-50') }}">
                    <p class="text-xs font-semibold {{ $isCurrent ? 'text-primary-700' : ($isDone ? 'text-emerald-700' : 'text-neutral-500') }}">{{ $step->label() }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <livewire:order.detail-overview :order="$order" wire:key="order-detail-overview-{{ $order->id }}" />

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            <livewire:order.shipment-metrics :order="$order" wire:key="order-shipment-metrics-{{ $order->id }}" />
            <livewire:order.invoices-detail :order="$order" wire:key="order-invoices-detail-{{ $order->id }}" />
        </div>
        <div class="space-y-5">
            <livewire:order.charges-summary :order="$order" wire:key="order-charges-summary-{{ $order->id }}" />
            <livewire:order.edit-history :order="$order" wire:key="order-edit-history-{{ $order->id }}" />
            <livewire:order.activity-notes :order="$order" wire:key="order-activity-notes-{{ $order->id }}" />
        </div>
    </div>

    <section class="rounded-xl border border-neutral-200 bg-white px-5 py-4 shadow-xs">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-neutral-900">Thao tác đơn hàng</p>
                <p class="text-xs text-neutral-500">Trạng thái hiện tại: {{ $this->statusLabel }}</p>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
                @foreach($this->actionButtons() as $button)
                    @if($button['type'] === 'status')
                        <button
                            type="button"
                            wire:click="updateStatus('{{ $button['status'] }}')"
                            wire:loading.attr="disabled"
                            class="{{ $this->actionButtonClass($button) }}"
                        >
                            {{ $button['label'] }}
                        </button>
                    @elseif($button['type'] === 'price')
                        <flux:modal.trigger name="edit-payment">
                            <button type="button" class="{{ $this->actionButtonClass($button) }}">
                                {{ $button['label'] }}
                            </button>
                        </flux:modal.trigger>
                    @elseif($button['type'] === 'tracking')
                        <flux:modal.trigger name="edit-tracking">
                            <button type="button" class="{{ $this->actionButtonClass($button) }}">
                                {{ $button['label'] }}
                            </button>
                        </flux:modal.trigger>
                    @else
                        <a href="{{ route('orders.index') }}" wire:navigate class="{{ $this->actionButtonClass($button) }}">
                            {{ $button['label'] }}
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <flux:modal name="edit-tracking" class="w-full max-w-lg">
        <form wire:submit="saveTracking" class="space-y-6">
            <div>
                <flux:heading size="lg">Cập nhật tracking</flux:heading>
                <flux:subheading>Nhập mã tracking mới cho đơn hàng hiện tại.</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Tracking code</flux:label>
                <flux:input wire:model="trackingForm" />
                @error('trackingForm') <flux:error>{{ $message }}</flux:error> @enderror
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Lưu</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="print-bill-confirm" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Print Bill</flux:heading>
                <flux:subheading>Bạn có cần in kèm công văn cam kết nội dung hàng xuất không?</flux:subheading>
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">Hủy</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="filled" wire:click="printBill(false)">Không kèm CVCK</flux:button>
                <flux:button type="button" variant="primary" wire:click="printBill(true)">In kèm CVCK</flux:button>
            </div>
        </div>
    </flux:modal>

    <div class="order-label-print-area" aria-hidden="true">
        @foreach($labelPages as $labelIndex => $label)
            @php
                $package = $label['package'];
                $sender = $order->sender ?? [];
                $receiver = $order->receiver ?? [];
                $receiverName = data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe', ''));
                $receiverLocation = trim(implode(' ', array_filter([
                    data_get($receiver, 'city'),
                    data_get($receiver, 'state'),
                    data_get($receiver, 'postcode'),
                ])));
                $senderName = $this->labelText(data_get($sender, 'company') ?: data_get($sender, 'fullname'));
                $senderAddress = $this->labelText(data_get($sender, 'address'));
                $dimensions = $package
                    ? trim(($package->length ?: '-') . ' x ' . ($package->width ?: '-') . ' x ' . ($package->height ?: '-') . ' cm')
                    : '-';
                $packageCode = (string) $label['package_code'];
            @endphp
            <section class="order-label-page">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;border-bottom:2px solid #111827;padding-bottom:8px;">
                    <div>
                        <div style="font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#4b5563;">Print Label</div>
                        <div style="font-size:24px;font-weight:800;line-height:1.1;">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</div>
                    </div>
                    <div style="min-width:28mm;border:1px solid #111827;padding:4px;text-align:center;">
                        <div style="font-size:9px;font-weight:700;color:#4b5563;">KIỆN</div>
                        <div style="font-size:20px;font-weight:800;line-height:1.1;">{{ $labelIndex + 1 }}/{{ $totalLabelPages }}</div>
                    </div>
                </div>

                <div style="margin-top:6px;border-bottom:1px solid #111827;padding-bottom:6px;">
                    <div>
                        {!! $this->code128BarcodeSvg($packageCode) !!}
                    </div>
                </div>

                <div style="margin-top:8px;border:1px solid #111827;padding:6px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;color:#4b5563;">Người nhận</div>
                    <div style="margin-top:2px;font-size:18px;font-weight:800;line-height:1.15;">{{ $receiverName ?: data_get($receiver, 'company', '-') }}</div>
                    @if(data_get($receiver, 'company') && $receiverName)
                        <div style="font-size:11px;font-weight:700;">{{ data_get($receiver, 'company') }}</div>
                    @endif
                    <div style="margin-top:4px;font-size:11px;line-height:1.35;">{{ data_get($receiver, 'address', '-') }}</div>
                    <div style="font-size:11px;line-height:1.35;">{{ $receiverLocation ?: '-' }}</div>
                    <div style="margin-top:3px;font-size:12px;font-weight:800;">Tel: {{ data_get($receiver, 'phone', '-') }}</div>
                </div>

                <div style="margin-top:8px;display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    <div style="border:1px solid #d1d5db;padding:5px;">
                        <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#6b7280;">Cân nặng</div>
                        <div style="font-size:12px;font-weight:800;">{{ $package?->c_weight ?? '-' }} kg</div>
                    </div>
                    <div style="border:1px solid #d1d5db;padding:5px;">
                        <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#6b7280;">Kích thước</div>
                        <div style="font-size:12px;font-weight:800;">{{ $dimensions }}</div>
                    </div>
                    <div style="grid-column:1 / -1;border:1px solid #d1d5db;padding:5px;">
                        <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#6b7280;">Dịch vụ</div>
                        <div style="font-size:12px;font-weight:800;">{{ $this->labelText($order->dichvu?->namevi) }}</div>
                    </div>
                </div>

                <div style="margin-top:8px;border:1px solid #d1d5db;padding:6px;">
                    <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#6b7280;">Hàng hóa</div>
                    <div style="font-size:13px;font-weight:800;line-height:1.2;">{{ data_get($order->service ?? [], 'tensanpham', '-') ?: '-' }}</div>
                </div>

                <div style="margin-top:8px;border:1px solid #d1d5db;padding:6px;">
                    <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:#6b7280;">Người gửi</div>
                    <div style="font-size:11px;font-weight:800;">{{ $senderName }}</div>
                    <div style="font-size:10px;line-height:1.35;">{{ $senderAddress }}</div>
                    <div style="font-size:10px;font-weight:700;">Tel: {{ data_get($sender, 'phone', '-') }}</div>
                </div>

                <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;border-top:1px dashed #9ca3af;padding-top:6px;font-size:10px;color:#4b5563;">
                    <span>Tạo lúc: {{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    <span>Dòng kiện {{ $label['package_index'] }}: {{ $label['piece'] }}/{{ $label['pieces_in_row'] }}</span>
                </div>
            </section>
        @endforeach
    </div>

    <div class="order-bill-print-area" aria-hidden="true">
        <section class="order-bill-page">
            <div style="border:1.5px solid #111827;">
                <div style="display:grid;grid-template-columns:34% 38% 28%;border-bottom:1px solid #111827;">
                    <div style="min-height:31mm;padding:8px;border-right:1px solid #111827;display:flex;align-items:center;">
                        <div>
                            <div style="font-size:24px;font-weight:900;letter-spacing:.03em;">VAU TRANS</div>
                            <div style="margin-top:4px;font-size:10px;color:#374151;">International Express & Logistics</div>
                        </div>
                    </div>
                    <div style="padding:8px;border-right:1px solid #111827;text-align:center;">
                        <div style="font-size:10px;text-transform:uppercase;color:#4b5563;">Order Code</div>
                        <div style="margin-top:3px;font-size:22px;font-weight:900;letter-spacing:.16em;">{{ $order->id_bill ?: 'ORDER-'.$order->id }}</div>
                        <div style="margin-top:4px;">{!! $this->code128BarcodeSvg($order->id_bill ?: 'ORDER-'.$order->id) !!}</div>
                    </div>
                    <div style="padding:8px;font-size:11px;line-height:1.55;">
                        <div><b>Date:</b> {{ $order->created_at?->format('d/m/Y') ?? '-' }}</div>
                        <div><b>Support:</b> +84 987 235514</div>
                        <div><b>Branch:</b> Ho Chi Minh</div>
                        <div><b>Website:</b> www.vaupost.vn</div>
                    </div>
                </div>

                <div style="padding:6px 8px;border-bottom:1px solid #111827;font-size:12px;">
                    <b>Service:</b> {{ $this->labelText($order->dichvu?->namevi) }}
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;border-bottom:1px solid #111827;">
                    <div style="border-right:1px solid #111827;">
                        <div style="padding:6px 8px;border-bottom:1px solid #111827;font-weight:800;">From (Shipper)</div>
                        <div style="display:grid;grid-template-columns:34% 66%;font-size:11px;">
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Company name<br><span style="color:#6b7280;">(Contact name)</span></div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;"><b>{{ $this->labelText(data_get($sender, 'company')) }}</b><br>{{ $this->labelText(data_get($sender, 'fullname')) }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Phone/Tax No.</div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;">{{ data_get($sender, 'phone', '-') }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Address</div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;">{{ $this->labelText(data_get($sender, 'address')) }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Country</div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;">VIET NAM</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;">Email</div>
                            <div style="padding:6px;">{{ data_get($sender, 'email', '-') }}</div>
                        </div>
                    </div>
                    <div>
                        <div style="padding:6px 8px;border-bottom:1px solid #111827;font-weight:800;">To (Consignee)</div>
                        <div style="display:grid;grid-template-columns:34% 66%;font-size:11px;">
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Company name<br><span style="color:#6b7280;">(Contact name)</span></div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;"><b>{{ $this->labelText(data_get($receiver, 'company')) }}</b><br>{{ $this->labelText(data_get($receiver, 'fullname', data_get($receiver, 'tenlienhe'))) }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Phone/Fax No.</div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;">{{ data_get($receiver, 'mavung') }} {{ data_get($receiver, 'phone', '-') }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Address</div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;">{{ $this->labelText($receiverAddress) }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;border-bottom:1px solid #d1d5db;">Country</div>
                            <div style="padding:6px;border-bottom:1px solid #d1d5db;">{{ $this->labelText($receiverCountryName) }}</div>
                            <div style="padding:6px;border-right:1px solid #d1d5db;">Postal code</div>
                            <div style="padding:6px;">{{ data_get($receiver, 'postcode', '-') }}</div>
                        </div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:70% 30%;border-bottom:1px solid #111827;">
                    <div>
                        <div style="padding:6px 8px;border-bottom:1px solid #d1d5db;font-weight:800;">Description of goods</div>
                        <div style="min-height:16mm;padding:7px 8px;font-size:12px;font-weight:700;">{{ $this->labelText(data_get($order->service ?? [], 'tensanpham')) }}</div>
                    </div>
                    <div style="border-left:1px solid #111827;">
                        <div style="padding:6px 8px;border-bottom:1px solid #d1d5db;font-weight:800;">Value invoice</div>
                        <div style="min-height:16mm;padding:7px 8px;text-align:center;font-size:14px;font-weight:900;">{{ $this->money($invoiceValue, 2) }} USD</div>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(4,1fr);border-bottom:1px solid #111827;text-align:center;">
                    <div style="padding:6px;border-right:1px solid #d1d5db;"><b>Total Packages</b><br><span style="font-size:16px;font-weight:900;">{{ $packageCount }}</span></div>
                    <div style="padding:6px;border-right:1px solid #d1d5db;"><b>Gross weight</b><br><span style="font-size:16px;font-weight:900;">{{ $this->money($grossWeight, 2) }}</span></div>
                    <div style="padding:6px;border-right:1px solid #d1d5db;"><b>Volume weight</b><br><span style="font-size:16px;font-weight:900;">{{ $this->money($volumeWeight, 2) }}</span></div>
                    <div style="padding:6px;"><b>Chargeable weight</b><br><span style="font-size:16px;font-weight:900;">{{ $this->money($chargeableWeight, 2) }}</span></div>
                </div>

                <table style="width:100%;border-collapse:collapse;font-size:11px;">
                    <thead>
                        <tr>
                            <th style="border-bottom:1px solid #111827;border-right:1px solid #d1d5db;padding:5px;text-align:center;">Package</th>
                            <th style="border-bottom:1px solid #111827;border-right:1px solid #d1d5db;padding:5px;text-align:center;">L (cm)</th>
                            <th style="border-bottom:1px solid #111827;border-right:1px solid #d1d5db;padding:5px;text-align:center;">W (cm)</th>
                            <th style="border-bottom:1px solid #111827;border-right:1px solid #d1d5db;padding:5px;text-align:center;">H (cm)</th>
                            <th style="border-bottom:1px solid #111827;padding:5px;text-align:center;">Code</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($order->packages as $package)
                            <tr>
                                <td style="border-bottom:1px solid #d1d5db;border-right:1px solid #d1d5db;padding:4px;text-align:center;">1</td>
                                <td style="border-bottom:1px solid #d1d5db;border-right:1px solid #d1d5db;padding:4px;text-align:center;">{{ $package->length ?: '-' }}</td>
                                <td style="border-bottom:1px solid #d1d5db;border-right:1px solid #d1d5db;padding:4px;text-align:center;">{{ $package->width ?: '-' }}</td>
                                <td style="border-bottom:1px solid #d1d5db;border-right:1px solid #d1d5db;padding:4px;text-align:center;">{{ $package->height ?: '-' }}</td>
                                <td style="border-bottom:1px solid #d1d5db;padding:4px;text-align:center;">{{ $package->code ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="padding:8px;text-align:center;">No packages</td></tr>
                        @endforelse
                    </tbody>
                </table>

                <div style="display:grid;grid-template-columns:1fr 1fr;border-top:1px solid #111827;">
                    <div style="min-height:34mm;border-right:1px solid #111827;padding:7px;">
                        <b>Chữ ký người gửi (Shipper Signature)</b>
                        <div style="margin-top:8px;">Ngày, giờ (Date/time): ...............................................</div>
                    </div>
                    <div style="min-height:34mm;padding:7px;">
                        <b>Nhân viên nhận hàng (Picked up by)</b>
                        <div style="margin-top:8px;">Ngày, giờ (Date/time): ...............................................</div>
                    </div>
                </div>
            </div>

            <div style="margin-top:10px;border:1px solid #111827;padding:8px;font-size:10px;line-height:1.45;">
                <b>Lưu ý / Notes:</b> Khách hàng xác nhận thông tin trên bill là chính xác và đồng ý với chính sách vận chuyển, khai báo hàng hóa, bồi thường và xử lý phát sinh của VAU TRANS.
            </div>
        </section>

        @if($printBillWithCvck)
            <section class="order-bill-page">
                <div style="padding:10mm 8mm;font-family:'Times New Roman',serif;font-size:14px;line-height:1.55;">
                    <div style="text-align:center;line-height:1.45;">
                        <div style="font-weight:bold;">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                        <div>Độc Lập - Tự Do - Hạnh Phúc</div>
                        <div>---***---</div>
                        <div style="margin-top:14px;font-weight:bold;font-size:16px;">CÔNG VĂN CAM KẾT NỘI DUNG HÀNG XUẤT</div>
                        <div style="font-weight:bold;">LETTER OF GUARANTEE</div>
                        <div>TP.HCM ngày {{ now()->format('d') }} tháng {{ now()->format('m') }} năm {{ now()->format('Y') }}</div>
                    </div>

                    <div style="margin-top:18px;">
                        <p style="margin:0;">Kính gửi: - Chi cục Hải Quan cửa khẩu Tân Sơn Nhất</p>
                        <p style="margin:0 0 0 54px;">- Ban soi chiếu an ninh hàng không Tân Sơn Nhất</p>
                        <p style="margin:0 0 0 54px;">- Công ty TNHH dịch vụ hàng hóa Tân Sơn Nhất (TCS/TECS)</p>
                        <p style="margin:0 0 0 54px;">- Công ty TNHH dịch vụ hàng hóa Sài Gòn (SCSC)</p>
                        <p style="margin:0 0 0 54px;">- Công Ty TNHH Xuất Nhập Khẩu VAU TRANS</p>
                    </div>

                    <div style="margin-top:18px;">
                        <p style="margin:0 0 5px;">Chúng tôi là / <i>We're</i>: <b>{{ $this->labelText($cvckName) }}</b></p>
                        <p style="margin:0 0 5px;">MST / CMND số: <b>{{ $this->labelText($cvckId) }}</b></p>
                        <p style="margin:0 0 5px;">Địa chỉ / <i>Address</i>: <b>{{ $this->labelText($cvckAddress) }}</b></p>
                        <p style="margin:0 0 5px;">Có gửi đến / <i>Shipment send to</i>: <b>{{ $this->labelText($receiverAddress) }}</b></p>
                        <p style="margin:0 0 5px;">Số bill / <i>Consignment note No.</i>: <b>{{ $order->id_bill ?: 'ORDER-'.$order->id }}</b></p>
                        <p style="margin:0 0 5px;">Nội dung hàng gửi gồm / <i>Content</i>: <b>{{ $this->labelText(data_get($order->service ?? [], 'tensanpham')) }}</b></p>
                        <p style="margin:0 0 5px 24px;">Số kiện / <i>No pcs</i>: <b>{{ $packageCount }}</b></p>
                        <p style="margin:0 0 5px 24px;">Trọng lượng thực tế / <i>Gross weight</i>: <b>{{ $this->money($grossWeight, 2) }} KG</b></p>
                    </div>

                    <table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:13px;">
                        <thead>
                            <tr>
                                <th style="border:1px solid #000;padding:4px;width:12%;">STT</th>
                                <th style="border:1px solid #000;padding:4px;">TÊN HÀNG</th>
                                <th style="border:1px solid #000;padding:4px;width:18%;">SỐ LƯỢNG</th>
                                <th style="border:1px solid #000;padding:4px;width:20%;">VALUE (USD)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($order->invoices as $invoiceIndex => $invoice)
                                <tr>
                                    <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;">{{ $invoiceIndex + 1 }}</td>
                                    <td style="border:1px solid #000;padding:4px;">{!! nl2br(e($invoice->tenhang ?: '-')) !!}</td>
                                    <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;">{{ $invoice->soluong ?: '-' }}</td>
                                    <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;">{{ $this->money($invoice->total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" style="border:1px solid #000;padding:8px;text-align:center;">Không có invoice</td></tr>
                            @endforelse
                            <tr>
                                <td colspan="2"></td>
                                <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;">{{ $invoiceQty }}</td>
                                <td style="border:1px solid #000;padding:4px;text-align:center;font-weight:bold;">{{ $this->money($invoiceValue, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <p style="margin-top:16px;">Chúng tôi xin cam kết lô hàng này không phải là hàng nguy hiểm, độc hại, không chứa xăng dầu, khí gas, khí nén, từ tính, không phải hàng dễ cháy nổ, không có tiền chất ma túy, không chứa ma túy, không phải hàng quốc cấm, hàng cấm xuất khẩu. Chúng tôi cam kết hàng đúng như khai báo và chịu trách nhiệm về nội dung lô hàng xuất nói trên.</p>
                    <p>Vậy kính mong quý cơ quan tạo điều kiện cho lô hàng được xuất đi trong thời gian sớm nhất. Tôi cam đoan toàn bộ thông tin trong công văn này là đúng sự thật.</p>
                    <p>Trân trọng. <i>Best regards</i>,</p>

                    <div style="margin-top:24px;width:320px;text-align:center;">
                        <p style="margin:0;font-weight:bold;">Giám đốc / <i style="font-weight:normal;">Director</i></p>
                        <p style="margin:0;">(Ký, đóng dấu ghi rõ họ tên)</p>
                    </div>
                </div>
            </section>
        @endif
    </div>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('print-bill-ready', () => {
                requestAnimationFrame(() => {
                    document.body.dataset.printTarget = 'bill';
                    window.print();
                });
            });
        });
    </script>
</div>
