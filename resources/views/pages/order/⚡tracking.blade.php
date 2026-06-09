<?php

use App\Actions\Order\RecordOrderEditHistoryAction;
use App\Actions\Order\RecordTrackingHistoryAction;
use App\Enums\OrderStatusEnum;
use App\Models\News;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderPackage;
use App\Services\TrackingMore\TrackingMore;
use App\Services\TrackingMore\TrackingMoreException;
use App\Support\OrderAccess;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
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

    // Partner info (Đại lý, Hãng bay, Đối tác chung chuyển)
    public ?int $partnerDailyId = null;
    public ?int $partnerHangbayId = null;
    public ?int $partnerDoitacChungchuyenId = null;
    public array $dailyOptions = [];
    public array $hangbayOptions = [];
    public array $doitacChungchuyenOptions = [];

    public function mount(string $uuid): void
    {
        $this->order = Order::query()
            ->with([
                'packages',
                'customer:id,fullname,code',
                'sale:id,fullname,username,code',
                'shipmentLoadHistories.shipmentLoad:id,code',
            ])
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
                    'package_delivery_status' => (string) ($package->package_delivery_status ?? ''),
                ],
            ])
            ->toArray();

        $this->trackingMode = $this->detectTrackingMode();
        $this->courierOptions = $this->loadCourierOptions();

        $this->loadPartnerInfo();
    }

    protected function loadPartnerInfo(): void
    {
        $this->partnerDailyId = ($id = data_get($this->order->service, 'id_daily')) !== null && $id !== '' ? (int) $id : null;
        $this->partnerHangbayId = ($id = data_get($this->order->service, 'id_hangbay')) !== null && $id !== '' ? (int) $id : null;
        $this->partnerDoitacChungchuyenId = ($id = data_get($this->order->service, 'id_doitac_chungchuyen')) !== null && $id !== '' ? (int) $id : null;

        $options = Cache::remember('order_partner_options_v1', 3600, function () {
            return News::whereIn('type', ['daily', 'hangbay', 'doitacchungchuyen'])
                ->orderBy('numb', 'asc')
                ->get(['id', 'namevi', 'type'])
                ->toArray();
        });

        $options = collect($options);
        $this->dailyOptions = $options->where('type', 'daily')->values()->all();
        $this->hangbayOptions = $options->where('type', 'hangbay')->values()->all();
        $this->doitacChungchuyenOptions = $options->where('type', 'doitacchungchuyen')->values()->all();
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

    /**
     * Quyền edit Đại lý / Hãng bay / Đối tác chung chuyển: chỉ admin, cs, manager.
     * Đây là 3 thông tin partner đặc thù, tách riêng khỏi quyền edit chung của order.
     */
    public function getCanEditPartnerProperty(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'cs', 'manager']) ?? false;
    }

    public function savePartnerInfo(): void
    {
        abort_unless($this->canEditPartner, 403);

        $this->validate([
            'partnerDailyId' => ['nullable', 'integer', 'exists:news,id'],
            'partnerHangbayId' => ['nullable', 'integer', 'exists:news,id'],
            'partnerDoitacChungchuyenId' => ['nullable', 'integer', 'exists:news,id'],
        ], [], [
            'partnerDailyId' => 'Đại lý',
            'partnerHangbayId' => 'Hãng bay',
            'partnerDoitacChungchuyenId' => 'Đối tác chung chuyển',
        ]);

        $service = is_array($this->order->service) ? $this->order->service : [];
        $before = [
            'id_daily' => data_get($service, 'id_daily'),
            'id_hangbay' => data_get($service, 'id_hangbay'),
            'id_doitac_chungchuyen' => data_get($service, 'id_doitac_chungchuyen'),
        ];

        $service['id_daily'] = $this->partnerDailyId;
        $service['id_hangbay'] = $this->partnerHangbayId;
        $service['id_doitac_chungchuyen'] = $this->partnerDoitacChungchuyenId;

        $this->order->forceFill(['service' => $service])->save();
        $this->order->refresh();

        RecordOrderEditHistoryAction::execute(
            $this->order,
            'edit_partner_info',
            'partner_info',
            $before,
            [
                'id_daily' => $this->partnerDailyId,
                'id_hangbay' => $this->partnerHangbayId,
                'id_doitac_chungchuyen' => $this->partnerDoitacChungchuyenId,
            ],
            'cập nhật đại lý / hãng bay / đối tác chung chuyển'
        );

        Flux::toast(duration: 2500, heading: 'Thành công', text: 'Đã cập nhật thông tin đối tác.', variant: 'success');
    }

    public function getTrackingHistoriesProperty()
    {
        return $this->orderTrackingHistoryRows()
            ->merge($this->shipmentLoadHistoryRows())
            ->merge($this->commonTrackingMoreHistoryRows())
            ->sortByDesc(fn (array $row) => $row['sort_time'])
            ->values();
    }

    protected function orderTrackingHistoryRows()
    {
        return $this->order->histories()
            ->where(function ($query) {
                $query->whereNotNull('thoigian')
                    ->orWhereNotNull('trangthai')
                    ->orWhere('action', 'tracking_history');
            })
            ->orderByRaw('COALESCE(thoigian, created_at) desc')
            ->get()
            ->map(function (OrderHistory $history) {
                $source = $this->orderHistorySourceKey($history);
                $sourceLabel = $this->orderHistorySourceLabel($history);

                return [
                    'id' => 'order-'.$history->id,
                    'history_id' => $history->id,
                    'source' => $source,
                    'source_label' => $sourceLabel,
                    'source_meta' => $this->trackingHistorySourceMeta($source, $sourceLabel),
                    'time' => $history->thoigian ?: $history->created_at,
                    'location' => $history->diadiem,
                    'status' => $history->trangthai,
                    'detail' => $history->ghichu ?: $this->orderHistorySummary($history),
                    'can_delete' => true,
                    'sort_time' => ($history->thoigian ?: $history->created_at)?->timestamp ?? 0,
                ];
            });
    }

    protected function shipmentLoadHistoryRows()
    {
        return $this->order->shipmentLoadHistories()
            ->with('shipmentLoad:id,code')
            ->get()
            ->map(function ($history) {
                $loadCode = $history->shipmentLoad?->code;

                $sourceLabel = $loadCode ? 'Tải '.$loadCode : 'Tải hàng';

                return [
                    'id' => 'shipment-load-'.$history->id,
                    'history_id' => null,
                    'source' => 'shipment_load',
                    'source_label' => $sourceLabel,
                    'source_meta' => $this->trackingHistorySourceMeta('shipment_load', $sourceLabel),
                    'time' => $history->thoigian ?: $history->created_at,
                    'location' => $history->diadiem,
                    'status' => $history->trangthai,
                    'detail' => $history->ghichu,
                    'can_delete' => false,
                    'sort_time' => ($history->thoigian ?: $history->created_at)?->timestamp ?? 0,
                ];
            });
    }

    protected function commonTrackingMoreHistoryRows()
    {
        if (! $this->trackingMoreEnabled() || $this->trackingMode !== 'common') {
            return collect();
        }

        $trackingNumber = trim((string) ($this->trackingCode ?: $this->order->mathamchieu));
        $courierCode = trim((string) ($this->commonCourierCode ?: $this->order->id_thamchieu));

        if ($trackingNumber === '' || $courierCode === '') {
            return collect();
        }

        $result = $this->trackingResultFor($trackingNumber, $courierCode);

        if (filled($result['error'] ?? null)) {
            return collect();
        }

        return collect($result['events'] ?? [])
            ->map(fn (array $event, int $index) => [
                'id' => 'tracking-more-'.$index.'-'.(($event['time']?->timestamp) ?? 0),
                'history_id' => null,
                'source' => 'tracking_more',
                'source_label' => 'TrackingMore',
                'source_meta' => $this->trackingHistorySourceMeta('tracking_more', 'TrackingMore'),
                'time' => $event['time'] ?? null,
                'location' => $event['location'] ?? null,
                'status' => $event['status'] ?? null,
                'detail' => $event['detail'] ?? null,
                'can_delete' => false,
                'sort_time' => ($event['time']?->timestamp) ?? 0,
            ]);
    }

    protected function orderHistorySourceKey(OrderHistory $history): string
    {
        return match ($history->action) {
            'tracking_history' => 'manual',
            'shipment_load_approved' => 'shipment_load',
            default => 'order',
        };
    }

    protected function orderHistorySourceLabel(OrderHistory $history): string
    {
        return match ($history->action) {
            'tracking_history' => 'Nhập tay',
            'tracking_status_auto' => 'Đơn hàng',
            'shipment_load_approved' => 'Tải hàng',
            default => 'Đơn hàng',
        };
    }

    protected function trackingHistorySourceMeta(string $source, string $label): array
    {
        return match ($source) {
            'manual' => [
                'title' => $label,
                'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zM19.5 7.125L16.875 4.5M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
            ],
            'shipment_load' => [
                'title' => $label,
                'class' => 'border-amber-200 bg-amber-50 text-amber-700',
                'icon' => 'M8.25 18.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM15.75 18.75a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM3.75 6.75h9v9h-9v-9zM12.75 9.75h3.879c.398 0 .779.158 1.06.44l2.121 2.121c.281.281.44.663.44 1.06v2.379h-7.5v-6z',
            ],
            'tracking_more' => [
                'title' => $label,
                'class' => 'border-sky-200 bg-sky-50 text-sky-700',
                'icon' => 'M3.75 15a4.5 4.5 0 014.5-4.5h.474A6.75 6.75 0 0121 12.75 4.5 4.5 0 0116.5 17.25H8.25A4.5 4.5 0 013.75 15z',
            ],
            default => [
                'title' => $label,
                'class' => 'border-neutral-200 bg-neutral-100 text-neutral-600',
                'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5A3.375 3.375 0 0010.125 2.25H6.75A2.25 2.25 0 004.5 4.5v15A2.25 2.25 0 006.75 21h10.5a2.25 2.25 0 002.25-2.25v-4.5zM13.5 2.25V6a2.25 2.25 0 002.25 2.25h3.75',
            ],
        };
    }

    protected function orderHistorySummary(OrderHistory $history): string
    {
        $content = json_decode((string) $history->content, true);

        return is_array($content)
            ? (string) ($content['summary'] ?? '')
            : (string) $history->content;
    }

    public function getPackageTrackingHistoriesProperty(): array
    {
        if (! $this->trackingMoreEnabled() || $this->trackingMode !== 'packages') {
            return [];
        }

        $packages = $this->order->packages;

        return $packages
            ->map(function (OrderPackage $package) {
                $courierCode = trim((string) data_get($this->packageTracking, "{$package->id}.id_thamchieu", $package->id_thamchieu));
                $trackingNumber = trim((string) data_get($this->packageTracking, "{$package->id}.mathamchieu", $package->mathamchieu));
                $trackingResult = $this->trackingResultFor($trackingNumber, $courierCode);
                $deliveryStatus = $this->syncPackageDeliveryFromTracking($package, $trackingResult);

                return array_merge([
                    'id' => $package->id,
                    'label' => $package->code ?: 'Kien #'.$package->id,
                    'code' => $package->code ?: 'Kien #'.$package->id,
                    'courier_code' => $courierCode,
                    'tracking_number' => $trackingNumber,
                    'delivery_status' => $deliveryStatus,
                    'delivered_at' => $package->package_delivered_at,
                    'latest_status' => null,
                    'latest_time' => null,
                    'events' => [],
                    'error' => null,
                ], $trackingResult);
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

    protected function syncPackageDeliveryFromTracking(OrderPackage $package, array $trackingResult): ?string
    {
        if (! $this->trackingMoreEnabled() || filled($trackingResult['error'] ?? null)) {
            return $package->package_delivery_status;
        }

        $latestStatus = (string) ($trackingResult['latest_status'] ?? '');
        $latestTime = $trackingResult['latest_time'] ?? null;

        if ($latestStatus === '') {
            return $package->package_delivery_status;
        }

        $nextStatus = $this->isDeliveredTrackingStatus($latestStatus)
            ? 'delivered'
            : ($package->package_delivery_status ?: 'in_transit');

        $payload = [
            'package_delivery_status' => $nextStatus,
        ];

        if ($nextStatus === 'delivered') {
            $payload['package_delivered_at'] = $package->package_delivered_at ?: $latestTime ?: now();
        }

        $dirty = collect($payload)->contains(function ($value, string $key) use ($package) {
            if ($value instanceof Carbon) {
                return ! $package->{$key} || ! $package->{$key}->equalTo($value);
            }

            return $package->{$key} !== $value;
        });

        if ($dirty) {
            $payload['package_delivery_synced_at'] = now();
            $package->forceFill($payload)->save();
            $package->refresh();
        }

        return $package->package_delivery_status;
    }

    public function packageDeliverySummary(array $packageTrackingHistories): array
    {
        if ($this->trackingMode !== 'packages') {
            return [
                'label' => null,
                'class' => null,
                'delivered' => 0,
                'total' => 0,
            ];
        }

        $total = $this->order->packages->count();

        if ($total <= 1) {
            return [
                'label' => null,
                'class' => null,
                'delivered' => 0,
                'total' => $total,
            ];
        }

        $delivered = $this->order->packages
            ->filter(fn (OrderPackage $package) => $package->package_delivery_status === 'delivered')
            ->count();

        if ($delivered > 0 && $delivered < $total) {
            return [
                'label' => "Giao một phần {$delivered}/{$total} kiện",
                'class' => 'bg-amber-100 text-amber-800',
                'delivered' => $delivered,
                'total' => $total,
            ];
        }

        if ($delivered === $total && $this->order->bill_status !== OrderStatusEnum::DA_GIAO) {
            return [
                'label' => "Tất cả kiện đã giao {$delivered}/{$total}",
                'class' => 'bg-emerald-100 text-emerald-800',
                'delivered' => $delivered,
                'total' => $total,
            ];
        }

        return [
            'label' => null,
            'class' => null,
            'delivered' => $delivered,
            'total' => $total,
        ];
    }

    protected function isDeliveredTrackingStatus(string $status): bool
    {
        $normalized = str($status)->lower()->ascii()->replace(['_', '-'], ' ')->squish()->toString();

        if (str_contains($normalized, 'delivered') || str_contains($normalized, 'da giao')) {
            return true;
        }

        return in_array($normalized, [
            'delivered',
            'da giao',
            'signed',
            'success',
            'completed',
        ], true);
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

    public function canEditTrackingMode(): bool
    {
        return $this->canUpdate
            && in_array($this->order->bill_status, [
                OrderStatusEnum::MOI_TAO,
                OrderStatusEnum::DA_XAC_NHAN,
                OrderStatusEnum::DA_NHAN_HANG,
                OrderStatusEnum::DUYET_XUAT_HANG,
            ], true);
    }

    public function updatedTrackingMode(string $mode): void
    {
        if ($this->canEditTrackingMode()) {
            return;
        }

        $this->trackingMode = $this->detectTrackingMode();
    }

    public function trackingMoreEnabled(): bool
    {
        return filled(config('services.trackingmore.key'));
    }

    public function packageDeliveryStatusOptions(): array
    {
        return [
            '' => 'Chưa xác định',
            'in_transit' => 'Đang vận chuyển',
            'delivered' => 'Đã giao',
        ];
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

        $detectedTrackingMode = $this->detectTrackingMode();

        if (! $this->canEditTrackingMode() && $this->trackingMode !== $detectedTrackingMode) {
            $this->trackingMode = $detectedTrackingMode;
        }

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
            $rules['packageTracking.*.package_delivery_status'] = 'nullable|in:in_transit,delivered';
        } else {
            foreach ($this->order->packages as $package) {
                $rules["packageTracking.{$package->id}.id_thamchieu"] = 'nullable|string|max:100';
                $rules["packageTracking.{$package->id}.mathamchieu"] = 'required|string|max:255';
                $rules["packageTracking.{$package->id}.package_delivery_status"] = 'nullable|in:in_transit,delivered';
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
                    'package_delivery_status' => $package->package_delivery_status,
                    'package_delivered_at' => $package->package_delivered_at?->toDateTimeString(),
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

        $this->order->refresh()->load(['packages', 'shipmentLoadHistories.shipmentLoad']);
        $this->trackingCode = (string) ($this->order->tracking_code ?? '');
        $this->commonCourierCode = (string) ($this->order->id_thamchieu ?? '');
        $this->billStatus = $this->order->bill_status?->value ?? '';
        $this->packageTracking = $this->order->packages
            ->mapWithKeys(fn (OrderPackage $package) => [
                $package->id => [
                    'id_thamchieu' => (string) ($package->id_thamchieu ?? $this->order->id_thamchieu ?? ''),
                    'mathamchieu' => (string) ($package->mathamchieu ?? $this->order->mathamchieu ?? ''),
                    'package_delivery_status' => (string) ($package->package_delivery_status ?? ''),
                ],
            ])
            ->toArray();
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
                    'package_delivery_status' => null,
                    'package_delivered_at' => null,
                    'package_delivery_synced_at' => null,
                ])->save();

                continue;
            }

            $courierCode = trim((string) ($tracking['id_thamchieu'] ?? ''));
            $trackingNumber = trim((string) ($tracking['mathamchieu'] ?? ''));
            $deliveryStatus = $this->normalizePackageDeliveryStatus($tracking['package_delivery_status'] ?? null);

            $package->forceFill([
                'id_thamchieu' => $courierCode ?: null,
                'mathamchieu' => $trackingNumber ?: null,
                'tracking_id' => $this->ensureTrackingRegistered($trackingNumber, $courierCode, $package->tracking_id),
                'package_delivery_status' => $deliveryStatus,
                'package_delivered_at' => $deliveryStatus === 'delivered' ? ($package->package_delivered_at ?: now()) : null,
            ])->save();
        }
    }

    protected function normalizePackageDeliveryStatus(mixed $status): ?string
    {
        $status = trim((string) $status);

        return in_array($status, ['in_transit', 'delivered'], true) ? $status : null;
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
        $this->order->refresh()->load(['packages', 'shipmentLoadHistories.shipmentLoad']);

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
    $packageDeliverySummary = $this->packageDeliverySummary($packageTrackingHistories);
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
                @if($packageDeliverySummary['label'])
                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $packageDeliverySummary['class'] }}">
                        {{ $packageDeliverySummary['label'] }}
                    </span>
                @endif
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

        @if($packageDeliverySummary['label'])
            <div class="mb-4 rounded-xl border px-4 py-3 text-sm {{ $packageDeliverySummary['delivered'] === $packageDeliverySummary['total'] ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                Trạng thái đơn vẫn giữ là <span class="font-semibold">{{ $order->bill_status?->label() ?? 'Chưa rõ' }}</span>.
                {{ $packageDeliverySummary['label'] }} theo tracking từng kiện.
            </div>
        @endif

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
                </div>
            </div>

            <div class="divide-y divide-neutral-100">
                @forelse($historyRows as $history)
                    @php($sourceMeta = $history['source_meta'] ?? ['title' => $history['source_label'] ?? 'Nguồn', 'class' => 'border-neutral-200 bg-neutral-100 text-neutral-600', 'icon' => 'M13.5 6H5.25A2.25 2.25 0 003 8.25v7.5A2.25 2.25 0 005.25 18h13.5A2.25 2.25 0 0021 15.75v-7.5A2.25 2.25 0 0018.75 6H13.5z'])
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-12 md:items-start">
                        <div class="flex items-start gap-3 md:col-span-2">
                            <span class="mt-0.5 inline-flex h-8 w-8 flex-none items-center justify-center rounded-full border {{ $sourceMeta['class'] }}" title="{{ $sourceMeta['title'] }}" aria-label="{{ $sourceMeta['title'] }}">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $sourceMeta['icon'] }}"/>
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-neutral-900">{{ $history['time']?->format('d/m/Y') ?: '-' }}</p>
                                <p class="mt-0.5 text-xs text-neutral-500">{{ $history['time']?->format('H:i') ?: '-' }}</p>
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <p class="text-sm text-neutral-700">{{ $history['location'] ?: '-' }}</p>
                        </div>
                        <div class="md:col-span-3">
                            <p class="text-sm font-semibold uppercase text-neutral-900">{{ $history['status'] ?: '-' }}</p>
                        </div>
                        <div class="flex gap-3 md:col-span-4">
                            <p class="min-w-0 flex-1 text-sm text-neutral-600">{{ $history['detail'] ?: '-' }}</p>
                            @if($this->canUpdate && $history['can_delete'])
                                <button type="button" wire:click="deleteHistory({{ $history['history_id'] }})" wire:confirm="Xóa hành trình đã chọn?" class="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-neutral-400 transition hover:bg-red-50 hover:text-red-600" aria-label="Xóa hành trình">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm text-neutral-500">Chưa có lịch sử vận chuyển.</div>
                @endforelse
            </div>

            @if($this->trackingMoreEnabled() && $this->trackingMode === 'packages')
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
                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition {{ $trackingMode === 'common' ? 'border-primary-300 bg-primary-50 text-primary-700' : 'border-neutral-200 bg-white text-neutral-600' }} {{ ! $this->canEditTrackingMode() ? 'cursor-not-allowed opacity-70' : 'cursor-pointer' }}">
                                <input type="radio" wire:model.live="trackingMode" value="common" @disabled(! $this->canEditTrackingMode()) class="sr-only">
                                <span>Dùng tracking chung</span>
                            </label>
                            <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition {{ $trackingMode === 'packages' ? 'border-primary-300 bg-primary-50 text-primary-700' : 'border-neutral-200 bg-white text-neutral-600' }} {{ ! $this->canEditTrackingMode() ? 'cursor-not-allowed opacity-70' : 'cursor-pointer' }}">
                                <input type="radio" wire:model.live="trackingMode" value="packages" @disabled(! $this->canEditTrackingMode()) class="sr-only">
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

            <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-xs">
                <h2 class="text-sm font-semibold uppercase text-neutral-900">Đối tác vận chuyển</h2>
                <p class="mt-1 text-xs text-neutral-500">Đại lý, hãng bay và đối tác chung chuyển</p>
                <div class="mt-4 space-y-4">
                    <label class="block">
                        <span class="text-xs font-medium text-neutral-600">Đại lý</span>
                        <select wire:model="partnerDailyId" @disabled(! $this->canEditPartner) class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                            <option value="">-- Chọn đại lý --</option>
                            @foreach($dailyOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['namevi'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-neutral-600">Hãng bay</span>
                        <select wire:model="partnerHangbayId" @disabled(! $this->canEditPartner) class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                            <option value="">-- Chọn hãng bay --</option>
                            @foreach($hangbayOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['namevi'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-neutral-600">Đối tác chung chuyển</span>
                        <select wire:model="partnerDoitacChungchuyenId" @disabled(! $this->canEditPartner) class="mt-1 w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                            <option value="">-- Chọn đối tác --</option>
                            @foreach($doitacChungchuyenOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['namevi'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if($this->canEditPartner)
                        <button type="button" wire:click="savePartnerInfo" wire:loading.attr="disabled" class="inline-flex w-full items-center justify-center rounded-xl border border-primary-600 bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-primary-700">
                            Lưu đối tác
                        </button>
                    @endif
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
                        @if($trackingMode === 'packages')
                            <th class="px-5 py-3">Trạng thái giao</th>
                        @endif
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
                            @if($trackingMode === 'packages')
                                <td class="px-5 py-4">
                                    <select wire:model="packageTracking.{{ $package->id }}.package_delivery_status" @disabled(! $this->canUpdate) class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm shadow-xs focus:border-primary-500 focus:outline-none disabled:bg-neutral-100 disabled:text-neutral-500">
                                        @foreach($this->packageDeliveryStatusOptions() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @if($package->package_delivery_synced_at)
                                        <p class="mt-1 text-xs text-neutral-500">Sync API: {{ $package->package_delivery_synced_at->format('d/m/Y H:i') }}</p>
                                    @endif
                                    @if($package->package_delivered_at)
                                        <p class="mt-1 text-xs text-emerald-600">Giao lúc: {{ $package->package_delivered_at->format('d/m/Y H:i') }}</p>
                                    @endif
                                    @error("packageTracking.{$package->id}.package_delivery_status") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $trackingMode === 'packages' ? 6 : 5 }}" class="px-5 py-8 text-center text-neutral-500">Đơn hàng chưa có kiện hàng.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
