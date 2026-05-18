<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Actions\Order\RecordTrackingHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderPackage;
use App\Services\TrackingMore\TrackingMore;
use App\Services\TrackingMore\TrackingMoreException;
use App\Support\OrderAccess;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Tracking đơn hàng')] class extends Component
{
    public Order $order;
    public array $historyForm = [];
    public array $packageTracking = [];
    public string $billStatus = '';
    public string $trackingCode = '';
    public string $commonCourierCode = '';
    public string $trackingMode = 'common';
    public array $courierOptions = [];

    public function mount(string $uuid): void
    {
        $this->order = Order::query()
            ->with(['packages', 'customer:id,fullname,code', 'sale:id,fullname,username,code'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        abort_unless(OrderAccess::canView(auth()->user(), $this->order), 403);

        $this->billStatus = $this->order->bill_status?->value ?? '';
        $this->trackingCode = (string) ($this->order->tracking_code ?? '');
        $this->commonCourierCode = (string) ($this->order->id_thamchieu ?? '');
        $this->historyForm = [
            'thoigian' => now()->format('Y-m-d H:i'),
            'diadiem' => '',
            'trangthai' => '',
            'ghichu' => '',
        ];

        $this->packageTracking = $this->order->packages
            ->mapWithKeys(fn (OrderPackage $package) => [
                $package->id => [
                    'id_thamchieu' => (string) ($package->id_thamchieu ?? $this->order->id_thamchieu ?? ''),
                    'mathamchieu' => (string) ($package->mathamchieu ?? $this->order->mathamchieu ?? ''),
                ],
            ])
            ->toArray();

        $this->trackingMode = $this->detectTrackingMode();
        $this->courierOptions = $this->loadCourierOptions();
    }

    protected function detectTrackingMode(): string
    {
        $packageTrackings = $this->order->packages
            ->map(fn (OrderPackage $package) => [
                'id_thamchieu' => trim((string) ($package->id_thamchieu ?? '')),
                'mathamchieu' => trim((string) ($package->mathamchieu ?? '')),
            ])
            ->filter(fn (array $row) => $row['id_thamchieu'] !== '' || $row['mathamchieu'] !== '');

        if (filled($this->trackingCode) || filled($this->commonCourierCode)) {
            return 'common';
        }

        return $packageTrackings->isNotEmpty() ? 'packages' : 'common';
    }

    public function detectCommonCourier(): void
    {
        abort_unless($this->canUpdate, 403);

        if (! $this->trackingMoreEnabled()) {
            return;
        }

        $trackingNumber = trim($this->trackingCode);

        if ($trackingNumber === '') {
            Flux::toast(duration: 2500, heading: 'Thiếu mã tracking', text: 'Vui lòng nhập mã tracking chung trước khi detect.', variant: 'danger');
            return;
        }

        $courierCode = $this->detectCourierCode($trackingNumber);

        if ($courierCode === null) {
            return;
        }

        $this->commonCourierCode = $courierCode;
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã nhận diện hãng vận chuyển.', variant: 'success');
    }

    public function detectPackageCourier(int $packageId): void
    {
        abort_unless($this->canUpdate, 403);

        if (! $this->trackingMoreEnabled()) {
            return;
        }

        $trackingNumber = trim((string) data_get($this->packageTracking, "{$packageId}.mathamchieu"));

        if ($trackingNumber === '') {
            Flux::toast(duration: 2500, heading: 'Thiếu mã tham chiếu', text: 'Vui lòng nhập mã tham chiếu của kiện trước khi detect.', variant: 'danger');
            return;
        }

        $courierCode = $this->detectCourierCode($trackingNumber);

        if ($courierCode === null) {
            return;
        }

        $this->packageTracking[$packageId]['id_thamchieu'] = $courierCode;
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã nhận diện hãng vận chuyển cho kiện.', variant: 'success');
    }

    protected function detectCourierCode(string $trackingNumber): ?string
    {
        try {
            $response = app(TrackingMore::class)->courier()->detect([
                'tracking_number' => $trackingNumber,
            ]);
        } catch (TrackingMoreException $e) {
            Flux::toast(duration: 3500, heading: 'Không detect được', text: $e->getMessage(), variant: 'danger');
            return null;
        }

        $courierCode = data_get($response, 'data.0.courier_code')
            ?: data_get($response, 'data.0.code')
            ?: data_get($response, 'data.0.courierCode')
            ?: data_get($response, 'data.courier_code')
            ?: data_get($response, 'data.code');

        if (blank($courierCode)) {
            Flux::toast(duration: 3500, heading: 'Không detect được', text: 'TrackingMore không trả về mã hãng vận chuyển phù hợp.', variant: 'danger');
            return null;
        }

        return (string) $courierCode;
    }

    public function getCanUpdateProperty(): bool
    {
        return OrderAccess::canEditOrder(auth()->user(), $this->order);
    }

    public function getTrackingHistoriesProperty()
    {
        return $this->order->histories()
            ->where(function ($query) {
                $query->whereNotNull('thoigian')
                    ->orWhereNotNull('trangthai')
                    ->orWhere('action', 'tracking_history');
            })
            ->orderByRaw('COALESCE(thoigian, created_at) desc')
            ->get();
    }

    public function getPackageTrackingHistoriesProperty(): array
    {
        if (! $this->trackingMoreEnabled()) {
            return [];
        }

        $packages = $this->order->packages;

        if ($this->trackingMode === 'common') {
            return [array_merge([
                'id' => 'common',
                'label' => 'Tracking chung',
                'code' => $this->order->id_bill ?: 'Don hang #'.$this->order->id,
                'courier_code' => trim((string) ($this->commonCourierCode ?: $this->order->id_thamchieu)),
                'tracking_number' => trim((string) ($this->trackingCode ?: $this->order->mathamchieu)),
                'latest_status' => null,
                'latest_time' => null,
                'events' => [],
                'error' => null,
            ], $this->trackingResultFor(
                trim((string) ($this->trackingCode ?: $this->order->mathamchieu)),
                trim((string) ($this->commonCourierCode ?: $this->order->id_thamchieu))
            ))];
        }

        return $packages
            ->map(function (OrderPackage $package) {
                $courierCode = trim((string) data_get($this->packageTracking, "{$package->id}.id_thamchieu", $package->id_thamchieu));
                $trackingNumber = trim((string) data_get($this->packageTracking, "{$package->id}.mathamchieu", $package->mathamchieu));

                return array_merge([
                    'id' => $package->id,
                    'label' => $package->code ?: 'Kien #'.$package->id,
                    'code' => $package->code ?: 'Kien #'.$package->id,
                    'courier_code' => $courierCode,
                    'tracking_number' => $trackingNumber,
                    'latest_status' => null,
                    'latest_time' => null,
                    'events' => [],
                    'error' => null,
                ], $this->trackingResultFor($trackingNumber, $courierCode));
            })
            ->values()
            ->all();
    }

    protected function trackingResultFor(string $trackingNumber, string $courierCode): array
    {
        if ($trackingNumber === '' || $courierCode === '') {
            return [
                'latest_status' => null,
                'latest_time' => null,
                'events' => [],
                'error' => null,
            ];
        }

        try {
            $response = app(TrackingMore::class)->tracking()->getTrackingResults([
                'tracking_numbers' => $trackingNumber,
                'courier_code' => $courierCode,
            ]);
        } catch (TrackingMoreException $e) {
            return [
                'latest_status' => null,
                'latest_time' => null,
                'events' => [],
                'error' => $e->getMessage(),
            ];
        }

        if ((int) data_get($response, 'meta.code') !== 200) {
            return [
                'latest_status' => null,
                'latest_time' => null,
                'events' => [],
                'error' => data_get($response, 'meta.message') ?: 'TrackingMore khong tra ve du lieu hop le.',
            ];
        }

        $events = collect(data_get($response, 'data.0.origin_info.trackinfo', []))
            ->map(function (array $event) {
                $time = null;

                try {
                    $time = filled(data_get($event, 'checkpoint_date'))
                        ? Carbon::parse(data_get($event, 'checkpoint_date'))
                        : null;
                } catch (\Throwable) {
                    $time = null;
                }

                return [
                    'time' => $time,
                    'status' => (string) (data_get($event, 'checkpoint_delivery_status') ?: data_get($event, 'delivery_status') ?: '-'),
                    'location' => (string) (data_get($event, 'location') ?: '-'),
                    'detail' => (string) (data_get($event, 'tracking_detail') ?: data_get($event, 'checkpoint_delivery_substatus') ?: '-'),
                ];
            })
            ->sortByDesc(fn (array $event) => $event['time']?->timestamp ?? 0)
            ->values();

        return [
            'latest_status' => data_get($response, 'data.0.delivery_status')
                ?: data_get($response, 'data.0.origin_info.trackinfo.0.checkpoint_delivery_status')
                ?: data_get($events->first(), 'status'),
            'latest_time' => data_get($events->first(), 'time'),
            'events' => $events->all(),
            'error' => null,
        ];
    }

    public function statusOptions(): array
    {
        $current = $this->order->bill_status;

        if (! $current instanceof OrderStatusEnum) {
            return [];
        }

        return collect([$current])
            ->merge($current->allowedTransitions(auth()->user()->hasRole('admin')))
            ->unique(fn (OrderStatusEnum $status) => $status->value)
            ->values()
            ->all();
    }

    public function statusOptionValues(): array
    {
        return collect($this->statusOptions())
            ->map(fn (OrderStatusEnum $status) => $status->value)
            ->all();
    }

    public function canEditStatus(): bool
    {
        return $this->canUpdate
            && $this->order->bill_status instanceof OrderStatusEnum
            && $this->order->bill_status !== OrderStatusEnum::DA_GIAO
            && count($this->statusOptions()) > 1;
    }

    public function trackingMoreEnabled(): bool
    {
        return filled(config('services.trackingmore.key'));
    }

    protected function loadCourierOptions(): array
    {
        if (! $this->trackingMoreEnabled()) {
            return [];
        }

        try {
            $response = app(TrackingMore::class)->courier()->getAllCouriers();
        } catch (TrackingMoreException) {
            return [];
        }

        return collect(data_get($response, 'data', []))
            ->map(function (array $courier) {
                $code = data_get($courier, 'courier_code')
                    ?: data_get($courier, 'code')
                    ?: data_get($courier, 'courierCode');

                if (blank($code)) {
                    return null;
                }

                return [
                    'code' => (string) $code,
                    'name' => (string) (data_get($courier, 'courier_name') ?: data_get($courier, 'name') ?: $code),
                ];
            })
            ->filter()
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function saveTracking(): void
    {
        abort_unless($this->canUpdate, 403);

        $rules = [
            'trackingMode' => 'required|in:common,packages',
            'trackingCode' => 'nullable|string|max:255',
            'commonCourierCode' => 'nullable|string|max:100',
            'billStatus' => 'nullable|string|in:'.implode(',', $this->statusOptionValues()),
        ];

        if ($this->trackingMode === 'common') {
            $rules['trackingCode'] = 'required|string|max:255';
            $rules['commonCourierCode'] = 'nullable|string|max:100';
            $rules['packageTracking.*.id_thamchieu'] = 'nullable|string|max:100';
            $rules['packageTracking.*.mathamchieu'] = 'nullable|string|max:255';
        } else {
            foreach ($this->order->packages as $package) {
                $rules["packageTracking.{$package->id}.id_thamchieu"] = 'nullable|string|max:100';
                $rules["packageTracking.{$package->id}.mathamchieu"] = 'required|string|max:255';
            }
        }

        $this->validate($rules, [], [
            'trackingCode' => 'mã tracking chung',
            'commonCourierCode' => 'mã hãng vận chuyển chung',
            'trackingMode' => 'chế độ tracking',
            'packageTracking.*.mathamchieu' => 'mã tham chiếu',
        ]);

        $before = [
            'tracking_code' => $this->order->tracking_code,
            'bill_status' => $this->order->bill_status?->value,
            'packages' => $this->order->packages->mapWithKeys(fn (OrderPackage $package) => [
                $package->id => [
                    'id_thamchieu' => $package->id_thamchieu,
                    'mathamchieu' => $package->mathamchieu,
                ],
            ])->toArray(),
        ];

        $oldStatus = $this->order->bill_status;
        $nextStatus = OrderStatusEnum::tryFrom($this->billStatus);
        $payload = [];

        if ($this->trackingMode === 'common') {
            $commonTrackingCode = trim($this->trackingCode);
            $commonCourierCode = trim($this->commonCourierCode);

            $payload['tracking_code'] = $commonTrackingCode;
            $payload['id_thamchieu'] = $commonCourierCode ?: null;
            $payload['mathamchieu'] = $commonTrackingCode ?: null;
            $payload['trackingmore_id'] = $this->ensureTrackingRegistered($commonTrackingCode, $commonCourierCode, $this->order->trackingmore_id);
        } else {
            $payload['tracking_code'] = null;
            $payload['id_thamchieu'] = null;
            $payload['mathamchieu'] = null;
            $payload['trackingmore_id'] = null;
        }

        if ($nextStatus) {
            $payload['bill_status'] = $nextStatus;
            if ($nextStatus === OrderStatusEnum::DA_NHAN_HANG && blank($this->order->ngaynhanhang)) {
                $payload['ngaynhanhang'] = now();
            }
            if ($nextStatus === OrderStatusEnum::DANG_PHAT_HANG && blank($this->order->ngayxuathang)) {
                $payload['ngayxuathang'] = now();
            }
            if ($nextStatus === OrderStatusEnum::DA_GIAO && blank($this->order->ngaygiaohang)) {
                $payload['ngaygiaohang'] = now();
            }
        }

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);
        $this->order->forceFill($payload)->save();

        if ($nextStatus && $oldStatus !== $nextStatus) {
            RecordTrackingHistoryAction::execute($this->order, $nextStatus);
        }

        $this->savePackageTrackings();

        $this->order->refresh()->load('packages');
        $this->trackingCode = (string) ($this->order->tracking_code ?? '');
        $this->commonCourierCode = (string) ($this->order->id_thamchieu ?? '');
        $this->billStatus = $this->order->bill_status?->value ?? '';
        $this->trackingMode = $this->detectTrackingMode();

        RecordOrderEditHistoryAction::execute($this->order, 'edit_tracking', 'tracking', $before, [
            'tracking_code' => $this->order->tracking_code,
            'bill_status' => $this->order->bill_status?->value,
                'mode' => $this->trackingMode,
                'common_courier_code' => $this->commonCourierCode,
                'packages' => $this->packageTracking,
        ], 'cập nhật tracking đơn hàng');

        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật tracking.', variant: 'success');
    }

    protected function savePackageTrackings(): void
    {
        foreach ($this->order->packages as $package) {
            $tracking = $this->packageTracking[$package->id] ?? [];

            if ($this->trackingMode === 'common') {
                $package->forceFill([
                    'id_thamchieu' => trim($this->commonCourierCode) ?: null,
                    'mathamchieu' => trim($this->trackingCode) ?: null,
                    'tracking_id' => $this->order->trackingmore_id,
                ])->save();

                continue;
            }

            $courierCode = trim((string) ($tracking['id_thamchieu'] ?? ''));
            $trackingNumber = trim((string) ($tracking['mathamchieu'] ?? ''));

            $package->forceFill([
                'id_thamchieu' => $courierCode ?: null,
                'mathamchieu' => $trackingNumber ?: null,
                'tracking_id' => $this->ensureTrackingRegistered($trackingNumber, $courierCode, $package->tracking_id),
            ])->save();
        }
    }

    protected function ensureTrackingRegistered(string $trackingNumber, string $courierCode, ?string $currentId = null): ?string
    {
        if (! $this->trackingMoreEnabled()) {
            return $currentId;
        }

        if ($trackingNumber === '' || $courierCode === '') {
            return null;
        }

        try {
            $response = app(TrackingMore::class)->tracking()->createTracking([
                'tracking_number' => $trackingNumber,
                'courier_code' => $courierCode,
                'tracking_postal_code' => data_get($this->order->receiver, 'postcode'),
            ]);
        } catch (TrackingMoreException) {
            return $currentId;
        }

        $trackingId = data_get($response, 'data.id') ?: $currentId;

        if ((int) data_get($response, 'meta.code') === 4101 && filled($trackingId)) {
            try {
                app(TrackingMore::class)->tracking()->updateTrackingByID((string) $trackingId, [
                    'courier_code' => $courierCode,
                ]);
            } catch (TrackingMoreException) {
                return (string) $trackingId;
            }
        }

        return filled($trackingId) ? (string) $trackingId : $currentId;
    }

    public function addHistory(): void
    {
        abort_unless($this->canUpdate, 403);

        $this->validate([
            'historyForm.thoigian' => 'required|date_format:Y-m-d H:i',
            'historyForm.diadiem' => 'required|string|max:255',
            'historyForm.trangthai' => 'required|string|max:255',
            'historyForm.ghichu' => 'nullable|string|max:1000',
        ], [], [
            'historyForm.thoigian' => 'thời gian',
            'historyForm.diadiem' => 'địa điểm',
            'historyForm.trangthai' => 'trạng thái',
        ]);

        $this->order->histories()->create([
            'id_user' => auth()->id(),
            'action' => 'tracking_history',
            'content' => json_encode(['label' => 'hành trình', 'summary' => 'thêm hành trình vận chuyển'], JSON_UNESCAPED_UNICODE),
            'thoigian' => Carbon::createFromFormat('Y-m-d H:i', $this->historyForm['thoigian']),
            'diadiem' => trim($this->historyForm['diadiem']),
            'trangthai' => trim($this->historyForm['trangthai']),
            'ghichu' => trim((string) ($this->historyForm['ghichu'] ?? '')),
            'main' => false,
        ]);

        OrderAccess::assignCsOnEdit(auth()->user(), $this->order);
        $this->order->save();
        $this->order->refresh();

        $this->historyForm = [
            'thoigian' => now()->format('Y-m-d H:i'),
            'diadiem' => '',
            'trangthai' => '',
            'ghichu' => '',
        ];
        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã thêm hành trình vận chuyển.', variant: 'success');
    }

    public function deleteHistory(int $historyId): void
    {
        abort_unless($this->canUpdate, 403);

        OrderHistory::query()
            ->whereKey($historyId)
            ->where('id_order', $this->order->id)
            ->where(function ($query) {
                $query->whereNotNull('thoigian')
                    ->orWhereNotNull('trangthai')
                    ->orWhereIn('action', ['tracking_history', 'tracking_status_auto']);
            })
            ->delete();

        Flux::toast(duration: 2500, heading: 'Đã xóa', text: 'Hành trình đã được xóa.', variant: 'success');
    }

    public function deleteAllTrackingHistories(): void
    {
        abort_unless($this->canUpdate, 403);

        $this->order->histories()
            ->where(function ($query) {
                $query->whereNotNull('thoigian')
                    ->orWhereNotNull('trangthai')
                    ->orWhereIn('action', ['tracking_history', 'tracking_status_auto']);
            })
            ->delete();

        Flux::toast(duration: 2500, heading: 'Đã xóa', text: 'Đã xóa toàn bộ lịch sử vận chuyển.', variant: 'success');
    }

    public function progressStep(): int
    {
        return match ($this->order->bill_status) {
            OrderStatusEnum::DA_GIAO => 3,
            OrderStatusEnum::DUYET_XUAT_HANG,
            OrderStatusEnum::DANG_PHAT_HANG,
            OrderStatusEnum::CAP_BEN,
            OrderStatusEnum::CAUTION,
            OrderStatusEnum::CUSTOM_RELEASING,
            OrderStatusEnum::RETURN_ORDER => 2,
            OrderStatusEnum::DA_NHAN_HANG => 1,
            default => 0,
        };
    }

    public function render()
    {
        return $this->view();
    }
};

?>

@php
    $progressStep = $this->progressStep();
    $historyRows = $this->trackingHistories;
    $packageTrackingHistories = $this->packageTrackingHistories;
@endphp

<div class="space-y-5">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <p class="text-sm text-neutral-500">Đơn hàng / Tracking</p>
            <div class="mt-1 flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-bold text-neutral-900">{{ $order->id_bill ?: 'Đơn hàng #'.$order->id }}</h1>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $order->bill_status?->color() ?? 'bg-neutral-100 text-neutral-700' }}">
                    {{ $order->bill_status?->label() ?? 'Chưa rõ' }}
                </span>
                @if($order->lock_order)
                    <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Đã khóa</span>
                @endif
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-neutral-500">
                <span>Tạo lúc {{ $order->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                <span>Sale: <span class="font-medium text-neutral-700">{{ $order->sale?->fullname ?: $order->sale?->username ?: '-' }}</span></span>
                @if($order->ngaygiaodukien)
                    <span>Dự kiến hoàn thành: <span class="font-semibold text-neutral-900">{{ $order->ngaygiaodukien->format('d/m/Y H:i') }}</span></span>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('orders.show', ['uuid' => $order->uuid]) }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-white px-4 py-2 text-sm font-medium text-neutral-700 shadow-xs transition hover:bg-neutral-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Thoát
            </a>
            @if($this->canUpdate)
                <button type="button" wire:click="saveTracking" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-xl border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-emerald-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Cập nhật
                </button>
            @endif
        </div>
    </div>

    <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
        <div class="mb-5 flex items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold uppercase text-neutral-900">Trạng thái vận chuyển</h2>
                <p class="text-xs text-neutral-500">Theo dõi luồng nhận hàng, vận chuyển và giao hàng</p>
            </div>
            <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-700">{{ $trackingCode ?: 'Chưa có mã tracking' }}</span>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            @foreach([
                1 => ['label' => 'Nhận hàng', 'icon' => 'M20 7L10 17l-5-5'],
                2 => ['label' => 'Đang vận chuyển', 'icon' => 'M3 12h13m0 0l-4-4m4 4l-4 4'],
                3 => ['label' => 'Đã giao hàng', 'icon' => 'M5 13l4 4L19 7'],
            ] as $step => $meta)
                @php
                    $active = $progressStep >= $step;
                    $current = $progressStep === $step;
                @endphp
                <div class="flex items-center gap-3 rounded-xl border p-4 {{ $active ? 'border-primary-200 bg-primary-50' : 'border-neutral-200 bg-neutral-50' }}">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $active ? 'bg-primary-600 text-white' : 'bg-white text-neutral-400' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $meta['icon'] }}"/></svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold {{ $active ? 'text-primary-800' : 'text-neutral-500' }}">{{ $meta['label'] }}</p>
                        <p class="text-xs {{ $current ? 'text-primary-600' : 'text-neutral-400' }}">{{ $current ? 'Đang ở bước này' : ($active ? 'Đã hoàn tất' : 'Chưa đến bước') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-3">
        <section class="rounded-xl border border-neutral-200 bg-white shadow-xs xl:col-span-2">
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 px-5 py-4">
                <div>
                    <h2 class="text-sm font-semibold uppercase text-neutral-900">Lịch sử vận chuyển</h2>
                    <p class="text-xs text-neutral-500">Các mốc hành trình của đơn hàng</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-neutral-500">{{ $historyRows->count() }} mốc</span>
                    @if($this->canUpdate && $historyRows->isNotEmpty())
                        <button type="button" wire:click="deleteAllTrackingHistories" wire:confirm="Xóa toàn bộ lịch sử vận chuyển?" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:border-red-300 hover:bg-red-100">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Xóa lịch sử
                        </button>
                    @endif
                </div>
            </div>

            <div class="divide-y divide-neutral-100">
                @forelse($historyRows as $history)
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-12 md:items-start">
                        <div class="md:col-span-2">
                            <p class="text-sm font-semibold text-neutral-900">{{ ($history->thoigian ?: $history->created_at)?->format('d/m/Y') }}</p>
                            <p class="mt-0.5 text-xs text-neutral-500">{{ ($history->thoigian ?: $history->created_at)?->format('H:i') }}</p>
                        </div>
                        <div class="md:col-span-3">
                            <p class="text-sm text-neutral-700">{{ $history->diadiem ?: '-' }}</p>
                        </div>
                        <div class="md:col-span-3">
                            <p class="text-sm font-semibold uppercase text-neutral-900">{{ $history->trangthai ?: '-' }}</p>
                        </div>
                        <div class="flex gap-3 md:col-span-4">
                            <p class="min-w-0 flex-1 text-sm text-neutral-600">{{ $history->ghichu ?: '-' }}</p>
                            @if($this->canUpdate)
                                <button type="button" wire:click="deleteHistory({{ $history->id }})" wire:confirm="Xóa hành trình đã chọn?" class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-neutral-400 transition hover:bg-red-50 hover:text-red-600" aria-label="Xóa hành trình">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-neutral-500">Chưa có lịch sử vận chuyển.</div>
                @endforelse
            </div>

            @if($this->trackingMoreEnabled())
                <div class="border-t border-neutral-100">
                    <div class="flex items-center justify-between gap-3 px-5 py-4">
                        <div>
                            <h2 class="text-sm font-semibold uppercase text-neutral-900">Tracking theo kiện</h2>
                            <p class="text-xs text-neutral-500">Lịch sử từ hãng vận chuyển, tách theo từng kiện/mã tracking.</p>
                        </div>
                        <span class="text-xs font-semibold text-neutral-500">{{ count($packageTrackingHistories) }} tracking</span>
                    </div>

                    <div class="space-y-3 px-5 pb-5">
                        @forelse($packageTrackingHistories as $packageHistory)
                            <details class="rounded-xl border border-neutral-200 bg-neutral-50" @if($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-4 py-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-semibold text-neutral-900">{{ $packageHistory['label'] }}</span>
                                            @if($packageHistory['tracking_number'])
                                                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-neutral-700">{{ $packageHistory['tracking_number'] }}</span>
                                            @endif
                                            @if($packageHistory['courier_code'])
                                                <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700">{{ $packageHistory['courier_code'] }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-neutral-500">
                                            {{ $packageHistory['latest_status'] ?: 'Chưa có trạng thái từ hãng' }}
                                            @if($packageHistory['latest_time'])
                                                · {{ $packageHistory['latest_time']->format('d/m/Y H:i') }}
                                            @endif
                                        </p>
                                    </div>
                                    <svg class="h-4 w-4 flex-none text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </summary>

                                <div class="border-t border-neutral-200 bg-white">
                                    @if($packageHistory['error'])
                                        <div class="px-4 py-4 text-sm text-red-600">{{ $packageHistory['error'] }}</div>
                                    @elseif(blank($packageHistory['tracking_number']) || blank($packageHistory['courier_code']))
                                        <div class="px-4 py-4 text-sm text-neutral-500">Kiện này chưa đủ mã hãng vận chuyển và mã tracking.</div>
                                    @elseif(empty($packageHistory['events']))
                                        <div class="px-4 py-4 text-sm text-neutral-500">Hãng vận chuyển chưa trả về lịch sử cho mã này.</div>
                                    @else
                                        <div class="divide-y divide-neutral-100">
                                            @foreach($packageHistory['events'] as $event)
                                                <div class="grid gap-3 px-4 py-3 md:grid-cols-12 md:items-start">
                                                    <div class="md:col-span-2">
                                                        <p class="text-sm font-semibold text-neutral-900">{{ $event['time']?->format('d/m/Y') ?: '-' }}</p>
                                                        <p class="mt-0.5 text-xs text-neutral-500">{{ $event['time']?->format('H:i') ?: '-' }}</p>
                                                    </div>
                                                    <div class="md:col-span-3">
                                                        <p class="text-sm text-neutral-700">{{ $event['location'] ?: '-' }}</p>
                                                    </div>
                                                    <div class="md:col-span-3">
                                                        <p class="text-sm font-semibold uppercase text-neutral-900">{{ $event['status'] ?: '-' }}</p>
                                                    </div>
                                                    <div class="md:col-span-4">
                                                        <p class="text-sm text-neutral-600">{{ $event['detail'] ?: '-' }}</p>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </details>
                        @empty
                            <div class="rounded-xl border border-dashed border-neutral-200 px-4 py-6 text-center text-sm text-neutral-500">Chưa có kiện để hiển thị tracking.</div>
                        @endforelse
                    </div>
                </div>
            @endif
        </section>

        <aside class="space-y-5">
            <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="text-sm font-semibold uppercase text-neutral-900">Thông tin tracking</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <span class="text-xs font-medium text-neutral-600">Chế độ tracking</span>
                        <div class="mt-1 grid grid-cols-2 gap-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition {{ $trackingMode === 'common' ? 'border-primary-300 bg-primary-50 text-primary-700' : 'border-neutral-200 bg-white text-neutral-600' }}">
                                <input type="radio" wire:model.live="trackingMode" value="common" @disabled(! $this->canUpdate) class="sr-only">
                                <span>Dùng tracking chung</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition {{ $trackingMode === 'packages' ? 'border-primary-300 bg-primary-50 text-primary-700' : 'border-neutral-200 bg-white text-neutral-600' }}">
                                <input type="radio" wire:model.live="trackingMode" value="packages" @disabled(! $this->canUpdate) class="sr-only">
                                <span>Riêng từng kiện</span>
                            </label>
                        </div>
                    </div>
                    <label class="block">
                        <span class="text-xs font-medium text-neutral-600">Mã tracking chung</span>
                        <input type="text" wire:model="trackingCode" @disabled(! $this->canUpdate || $trackingMode !== 'common') class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500" placeholder="Nhập mã tracking">
                        @error('trackingCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </label>
                    @if($this->trackingMoreEnabled())
                        <label class="block">
                            <span class="text-xs font-medium text-neutral-600">Mã hãng vận chuyển chung</span>
                            <div class="mt-1 flex gap-2">
                                <select wire:model="commonCourierCode" @disabled(! $this->canUpdate || $trackingMode !== 'common') class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                                    <option value="">Chọn hãng vận chuyển</option>
                                    @foreach($courierOptions as $courier)
                                        <option value="{{ $courier['code'] }}">{{ $courier['name'] }} ({{ $courier['code'] }})</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="detectCommonCourier" @disabled(! $this->canUpdate || $trackingMode !== 'common') class="inline-flex flex-none items-center justify-center rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-100 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-400">
                                    Detect
                                </button>
                            </div>
                            @error('commonCourierCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </label>
                    @endif
                    <label class="block">
                        <span class="text-xs font-medium text-neutral-600">Trạng thái đơn</span>
                        <select wire:model="billStatus" @disabled(! $this->canEditStatus()) class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                            @foreach($this->statusOptions() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @if($order->lock_order)
                            <p class="mt-1 text-xs text-red-600">Đơn đã khóa, không thể chỉnh trạng thái.</p>
                        @elseif($order->bill_status === \App\Enums\OrderStatusEnum::DA_GIAO)
                            <p class="mt-1 text-xs text-neutral-500">Đơn đã giao không cho phép chỉnh trạng thái.</p>
                        @endif
                    </label>
                </div>
            </section>

            @if($order->lock_order)
                <section class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-xs">
                    <h2 class="text-sm font-semibold uppercase text-red-800">Đơn đã khóa</h2>
                    <p class="mt-2 text-sm text-red-700">Không thể thêm hành trình vận chuyển cho đến khi admin mở khóa đơn.</p>
                </section>
            @elseif($this->canUpdate)
                <form wire:submit="addHistory" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                    <h2 class="text-sm font-semibold uppercase text-neutral-900">Thêm hành trình</h2>
                    <div class="mt-4 space-y-4">
                        <label class="block">
                            <span class="text-xs font-medium text-neutral-600">Thời gian</span>
                            <div wire:ignore x-data x-init="
                                const picker = window.flatpickr($refs.input, {
                                    enableTime: true,
                                    time_24hr: true,
                                    dateFormat: 'Y-m-d H:i',
                                    defaultDate: $wire.get('historyForm.thoigian'),
                                    onChange: (dates, value) => $wire.set('historyForm.thoigian', value),
                                });

                                Livewire.hook('morph.updated', () => {
                                    const value = $wire.get('historyForm.thoigian');
                                    if (picker.input.value !== value) picker.setDate(value, false);
                                });
                            ">
                                <input x-ref="input" type="text" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none" autocomplete="off">
                            </div>
                            @error('historyForm.thoigian') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-neutral-600">Trạng thái</span>
                            <input type="text" wire:model="historyForm.trangthai" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none">
                            @error('historyForm.trangthai') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-neutral-600">Địa điểm</span>
                            <input type="text" wire:model="historyForm.diadiem" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none">
                            @error('historyForm.diadiem') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </label>
                        <label class="block">
                            <span class="text-xs font-medium text-neutral-600">Ghi chú</span>
                            <textarea wire:model="historyForm.ghichu" rows="3" class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none"></textarea>
                        </label>
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex w-full items-center justify-center rounded-xl border border-primary-600 bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-700">Thêm hành trình</button>
                    </div>
                </form>
            @endif
        </aside>
    </div>

    <section class="rounded-xl border border-neutral-200 bg-white shadow-xs">
        <div class="border-b border-neutral-100 px-5 py-4">
            <h2 class="text-sm font-semibold uppercase text-neutral-900">Tracking theo kiện</h2>
            <p class="text-xs text-neutral-500">
                @if($trackingMode === 'common')
                    Các kiện đang dùng chung mã tracking của đơn.
                @else
                    Lưu mã hãng vận chuyển và mã tham chiếu cho từng kiện hàng.
                @endif
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-neutral-50 text-xs font-semibold uppercase text-neutral-500">
                    <tr>
                        <th class="px-5 py-3">Kiện</th>
                        <th class="px-5 py-3">Kích thước</th>
                        <th class="px-5 py-3">Cân nặng</th>
                        @if($this->trackingMoreEnabled())
                            <th class="px-5 py-3">Mã hãng vận chuyển</th>
                        @endif
                        <th class="px-5 py-3">Mã tham chiếu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($order->packages as $package)
                        <tr>
                            <td class="px-5 py-4 font-semibold text-neutral-900">{{ $package->code ?: 'Kiện #'.$package->id }}</td>
                            <td class="px-5 py-4 text-neutral-600">{{ $package->length ?: 0 }} x {{ $package->width ?: 0 }} x {{ $package->height ?: 0 }} cm</td>
                            <td class="px-5 py-4 text-neutral-600">{{ number_format((float) ($package->c_weight ?? 0), 2) }} kg</td>
                            @if($this->trackingMoreEnabled())
                                <td class="px-5 py-4">
                                    <div class="flex gap-2">
                                        <select wire:model="packageTracking.{{ $package->id }}.id_thamchieu" @disabled(! $this->canUpdate || $trackingMode === 'common') class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                                            <option value="">Chọn hãng</option>
                                            @foreach($courierOptions as $courier)
                                                <option value="{{ $courier['code'] }}">{{ $courier['name'] }} ({{ $courier['code'] }})</option>
                                            @endforeach
                                        </select>
                                        <button type="button" wire:click="detectPackageCourier({{ $package->id }})" @disabled(! $this->canUpdate || $trackingMode === 'common') class="inline-flex flex-none items-center justify-center rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-100 disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-400">
                                            Detect
                                        </button>
                                    </div>
                                    @error("packageTracking.{$package->id}.id_thamchieu") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                            @endif
                            <td class="px-5 py-4">
                                <input type="text" wire:model="packageTracking.{{ $package->id }}.mathamchieu" @disabled(! $this->canUpdate || $trackingMode === 'common') class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500" placeholder="Tracking number">
                                @error("packageTracking.{$package->id}.mathamchieu") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-neutral-500">Đơn hàng chưa có kiện hàng.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
