<?php

namespace App\Http\Controllers\Api\ZaloMiniApp;

use App\Actions\Order\CreateOrderAction;
use App\DataTransferObjects\OrderFormData;
use App\Enums\OrderStatusEnum;
use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\News;
use App\Models\Order;
use App\Models\Setting;
use App\Support\OrderAccess;
use App\Support\ThirdPartyOrderShippingHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ZaloMiniAppOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CreateOrderAction $createOrder,
        protected ThirdPartyOrderShippingHistory $shippingHistory,
    ) {
    }

    public function formBootstrap(Request $request): JsonResponse
    {
        if (! $request->user()->can('orders.create')) {
            return $this->fail('Bạn không có quyền tạo đơn trên Mini App.', 403);
        }

        return $this->ok([
            'services' => News::query()
                ->where('type', News::TYPE_MAIN_SERVICE)
                ->orderBy('namevi')
                ->get(['id', 'namevi as name']),
            'countries' => Country::query()
                ->orderBy('name')
                ->get(['id', 'name', 'iso2', 'iso3', 'phonecode']),
            'statuses' => collect(OrderStatusEnum::cases())
                ->map(fn (OrderStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                    'color' => $status->color(),
                ])
                ->values(),
            'dim' => $this->systemDim(),
        ], 'OK');
    }

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->can('orders.index')) {
            return $this->fail('Bạn không có quyền xem đơn hàng.', 403);
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(OrderStatusEnum::values())],
            'search' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        $query = Order::query()
            ->with(['packages:id,id_order,c_weight,row_c_weight'])
            ->latest('id');

        OrderAccess::scopeVisibleTo($query, $request->user());

        if (filled($validated['status'] ?? null)) {
            $query->where('bill_status', $validated['status']);
        }

        if (filled($validated['search'] ?? null)) {
            $keyword = trim((string) $validated['search']);
            $like = '%'.$keyword.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('id_bill', 'like', $like)
                    ->orWhere('tracking_code', 'like', $like)
                    ->orWhere('uuid', 'like', $like)
                    ->orWhere('receiver->fullname', 'like', $like)
                    ->orWhere('receiver->phone', 'like', $like);
            });
        }

        $page = $query->paginate(
            perPage: (int) ($validated['per_page'] ?? 15),
            page: (int) ($validated['page'] ?? 1),
        );

        return $this->ok([
            'items' => $page->getCollection()
                ->map(fn (Order $order) => $this->orderPayload($order, false, $this->canViewFinance($request)))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
                'has_more' => $page->hasMorePages(),
            ],
            'statuses' => collect(OrderStatusEnum::cases())
                ->map(fn (OrderStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ])
                ->values(),
        ], 'OK');
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if (! $request->user()->can('orders.index') || ! OrderAccess::canView($request->user(), $order)) {
            return $this->fail('Không tìm thấy đơn hàng.', 404);
        }

        $order->load([
            'packages',
            'invoices',
            'histories',
            'shipmentLoadHistories.shipmentLoad:id,code',
        ]);

        return $this->ok($this->orderPayload($order, true, $this->canViewFinance($request)), 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('orders.create')) {
            return $this->fail('Bạn không có quyền tạo đơn trên Mini App.', 403);
        }

        $validated = $request->validate([
            'service_id' => ['required', 'integer', Rule::exists('news', 'id')->where('type', News::TYPE_MAIN_SERVICE)],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'id_sale' => ['nullable', 'integer', 'exists:user,id'],
            'id_customer' => ['nullable', 'integer', 'exists:user,id'],
            'sender.company' => ['nullable', 'string', 'max:191'],
            'sender.name' => ['required', 'string', 'max:191'],
            'sender.phone' => ['required', 'string', 'max:30'],
            'sender.email' => ['nullable', 'email', 'max:191'],
            'sender.address' => ['required', 'string', 'max:500'],
            'receiver.company' => ['nullable', 'string', 'max:191'],
            'receiver.name' => ['required', 'string', 'max:191'],
            'receiver.phone' => ['required', 'string', 'max:30'],
            'receiver.email' => ['nullable', 'email', 'max:191'],
            'receiver.address' => ['required', 'string', 'max:500'],
            'receiver.state' => ['nullable', 'string', 'max:191'],
            'receiver.city' => ['nullable', 'string', 'max:191'],
            'receiver.postcode' => ['nullable', 'string', 'max:30'],
            'packages' => ['required', 'array', 'min:1', 'max:50'],
            'packages.*.number_of_package' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'packages.*.length' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'packages.*.width' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'packages.*.height' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'packages.*.g_weight' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'packages.*.package_type' => ['nullable', 'string', 'max:80'],
            'invoice_items' => ['nullable', 'array', 'max:100'],
            'invoice_items.*.tenhang' => ['required_with:invoice_items', 'string', 'max:191'],
            'invoice_items.*.soluong' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'invoice_items.*.xuatxu' => ['nullable', 'string', 'max:100'],
            'invoice_items.*.loaihang' => ['nullable', 'string', 'max:100'],
            'invoice_items.*.hscode' => ['nullable', 'string', 'max:100'],
            'invoice_items.*.price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $country = Country::query()->findOrFail((int) $validated['country_id']);
        $service = News::query()->findOrFail((int) $validated['service_id']);

        $idSale = $user->hasRole('sale') ? $user->id : (int) ($validated['id_sale'] ?? 0);
        $idCustomer = $user->hasRole('ctv') ? $user->id : (int) ($validated['id_customer'] ?? 0);
        $idCs = $user->hasRole('cs') ? $user->id : null;

        $result = $this->createOrder->execute(new OrderFormData(
            idSale: $idSale ?: null,
            idCustomer: $idCustomer ?: null,
            service: [
                'id_dichvu' => (int) $service->id,
                'tensanpham' => $service->namevi,
            ],
            sender: [
                'id' => null,
                'type' => $user->hasRole('ctv') ? 'ctv' : 'khach',
                'company' => data_get($validated, 'sender.company') ?: data_get($validated, 'sender.name'),
                'fullname' => data_get($validated, 'sender.name'),
                'phone' => data_get($validated, 'sender.phone'),
                'email' => data_get($validated, 'sender.email'),
                'address' => data_get($validated, 'sender.address'),
            ],
            receiver: [
                'id' => null,
                'company' => data_get($validated, 'receiver.company') ?: data_get($validated, 'receiver.name'),
                'fullname' => data_get($validated, 'receiver.name'),
                'tenlienhe' => data_get($validated, 'receiver.name'),
                'phone' => data_get($validated, 'receiver.phone'),
                'email' => data_get($validated, 'receiver.email'),
                'country_id' => (int) $country->id,
                'id_country' => (int) $country->id,
                'country' => $country->name,
                'address' => data_get($validated, 'receiver.address'),
                'state' => data_get($validated, 'receiver.state'),
                'city' => data_get($validated, 'receiver.city'),
                'postcode' => data_get($validated, 'receiver.postcode'),
            ],
            packages: $this->packageRows($validated['packages']),
            notes: $validated['notes'] ?? null,
            saveInfoSender: false,
            saveInfoReceiver: false,
            dim: $this->systemDim(),
            phuphihaiquan: [],
            invoiceItems: $this->invoiceRows($validated['invoice_items'] ?? []),
            orderPhotos: [],
            idCs: $idCs,
        ));

        $order = $result->order->fresh(['packages', 'invoices']);

        return $this->ok([
            'order' => $this->orderPayload($order, true, $this->canViewFinance($request)),
            'warnings' => $result->warnings,
        ], $result->hasWarnings() ? 'Đã tạo đơn, một số dữ liệu phụ cần bổ sung trên web.' : 'Đã tạo đơn hàng.', 201);
    }

    private function orderPayload(Order $order, bool $detail, bool $includeFinance): array
    {
        $receiver = $order->receiver ?? [];
        $sender = $order->sender ?? [];
        $service = $order->service ?? [];

        $payload = [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'id_bill' => $order->id_bill,
            'tracking_code' => $order->tracking_code,
            'status' => $this->statusPayload($order->bill_status),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
            'sender' => [
                'name' => data_get($sender, 'fullname') ?: data_get($sender, 'company') ?: '-',
                'phone' => data_get($sender, 'phone') ?: '-',
                'address' => data_get($sender, 'address') ?: '-',
            ],
            'receiver' => [
                'name' => data_get($receiver, 'fullname') ?: data_get($receiver, 'tenlienhe') ?: data_get($receiver, 'company') ?: '-',
                'phone' => data_get($receiver, 'phone') ?: '-',
                'address' => data_get($receiver, 'address') ?: '-',
                'destination' => $this->destination($receiver),
                'country' => data_get($receiver, 'country'),
                'country_id' => data_get($receiver, 'country_id', data_get($receiver, 'id_country')),
            ],
            'service' => $this->servicePayload($service),
            'package_count' => $order->packages->count(),
            'chargeable_weight' => [
                'value' => $this->chargeableWeight($order),
                'unit' => 'kg',
            ],
            'notes' => $order->ghichu,
        ];

        if ($includeFinance) {
            $payload['payment'] = [
                'sale_total' => (float) data_get($order->payment_cuocban, 'total_tongcuoc', data_get($order->payment_cuocban, 'dongiaban', 0)),
                'cost_total' => (float) data_get($order->payment_cuocvon, 'total_tongcuoc', 0),
                'base_total' => (float) data_get($order->payment_cuocgoc, 'total_tongcuoc', 0),
                'profit' => (float) data_get($order->payment_loinhuan, 'loinhuan', 0),
            ];
        }

        if ($detail) {
            $payload['packages'] = $order->packages
                ->map(fn ($package) => [
                    'id' => $package->id,
                    'code' => $package->code,
                    'length' => (float) $package->length,
                    'width' => (float) $package->width,
                    'height' => (float) $package->height,
                    'g_weight' => (float) $package->g_weight,
                    'v_weight' => (float) $package->v_weight,
                    'c_weight' => (float) $package->c_weight,
                    'package_type' => $package->package_type,
                ])
                ->values();
            $payload['invoice_items'] = $order->invoices
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'tenhang' => $item->tenhang,
                    'soluong' => (float) $item->soluong,
                    'xuatxu' => $item->xuatxu,
                    'loaihang' => $item->loaihang,
                    'hscode' => $item->hscode,
                    'price' => (float) $item->price,
                    'total' => (float) $item->total,
                ])
                ->values();
            $payload['shipping_history'] = $this->shippingHistory->forOrder($order);
        }

        return $payload;
    }

    private function packageRows(array $packages): array
    {
        return collect($packages)
            ->map(fn (array $row) => [
                'number_of_package' => max(1, (int) ($row['number_of_package'] ?? 1)),
                'length' => (float) ($row['length'] ?? 0),
                'width' => (float) ($row['width'] ?? 0),
                'height' => (float) ($row['height'] ?? 0),
                'g_weight' => (float) $row['g_weight'],
                'package_type' => $row['package_type'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function invoiceRows(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item) && filled($item['tenhang'] ?? null))
            ->map(function (array $item) {
                $quantity = (float) ($item['soluong'] ?? 1);
                $price = (float) ($item['price'] ?? 0);

                return [
                    'tenhang' => (string) $item['tenhang'],
                    'soluong' => $quantity,
                    'xuatxu' => $item['xuatxu'] ?? '',
                    'loaihang' => $item['loaihang'] ?? '',
                    'hscode' => $item['hscode'] ?? '',
                    'price' => $price,
                    'total' => $quantity * $price,
                ];
            })
            ->values()
            ->all();
    }

    private function chargeableWeight(Order $order): float
    {
        return round($order->packages->sum(fn ($package) => (float) ($package->row_c_weight ?: $package->c_weight)), 3);
    }

    private function destination(array $receiver): string
    {
        $destination = implode(', ', array_filter([
            data_get($receiver, 'city'),
            data_get($receiver, 'state'),
            data_get($receiver, 'country'),
            data_get($receiver, 'postcode'),
        ]));

        return $destination !== '' ? $destination : '-';
    }

    private function servicePayload(array $service): array
    {
        $mainId = data_get($service, 'id_dichvu');
        $detailId = data_get($service, 'id_chitiet_dichvu');
        $shipmentTypeId = data_get($service, 'loaibuugui');
        $ids = collect([$mainId, $detailId, $shipmentTypeId])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $labels = $ids->isEmpty() ? collect() : News::query()->whereIn('id', $ids)->pluck('namevi', 'id');

        return [
            'main' => [
                'id' => $mainId,
                'name' => $mainId ? ($labels[(int) $mainId] ?? data_get($service, 'tensanpham')) : null,
            ],
            'detail' => [
                'id' => $detailId,
                'name' => $detailId ? ($labels[(int) $detailId] ?? null) : null,
            ],
            'shipment_type' => [
                'id' => $shipmentTypeId,
                'name' => $shipmentTypeId ? ($labels[(int) $shipmentTypeId] ?? null) : null,
            ],
        ];
    }

    private function canViewFinance(Request $request): bool
    {
        return $request->user()->hasAnyRole(['admin', 'manager', 'ketoan']);
    }

    private function systemDim(): float
    {
        $dim = (float) data_get(Setting::query()->first()?->options, 'dim', 0);

        return $dim > 0 ? $dim : 6000;
    }
}
