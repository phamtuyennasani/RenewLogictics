<?php

namespace App\Actions\Order;

use App\DataTransferObjects\CreateOrderResult;
use App\DataTransferObjects\OrderFormData;
use App\Enums\OrderStatusEnum;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\OrderPackage;
use App\Models\User;
use App\Support\OrderPaymentCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class CreateOrderAction
{
    public function __construct(
        protected GenerateOrderCodeAction $generateOrderCode,
        protected CalculateChargeableWeightAction $calculateWeight,
        protected ResolveServicePriceAction $resolveServicePrice,
    ) {
    }

    public function execute(OrderFormData $formData): CreateOrderResult
    {
        $payment = $this->calculatePrice(
            phuphihaiquan: $formData->phuphihaiquan,
            service: $formData->service,
            receiver: $formData->receiver,
            packages: $formData->packages,
            dim: $formData->dim,
        );
        $currentUser = auth()->user();
        $currentUserId = auth()->id();

        // Lõi bắt buộc (all-or-nothing): mã đơn + record orders + history khởi tạo.
        // generateOrderCode có transaction riêng → chạy lồng thành savepoint, an toàn.
        $order = DB::transaction(function () use ($formData, $payment, $currentUser, $currentUserId) {
            $orderCode = $this->generateOrderCode->execute();

            $order = Order::create([
                'uuid' => $this->generateOrderUuid(),
                'id_bill' => $orderCode,
                'id_sale' => $formData->idSale ?? 0,
                'id_customer' => $formData->idCustomer ?? 0,
                'id_manager' => 0,
                'id_ketoan' => 0,
                'id_ops' => 0,
                'id_cs' => $currentUser?->hasRole('cs') ? $currentUserId : ($formData->idCs ?? 0),
                'id_create' => $currentUserId,
                'sender' => $formData->sender,
                'receiver' => $formData->receiver,
                'service' => $formData->service,
                'bill_status' => OrderStatusEnum::MOI_TAO,
                'dim' => $formData->dim,
                'ghichu' => $formData->notes,
                'payment_cuocban' => $payment['cuocban'],
                'payment_cuocvon' => $payment['cuocvon'],
                'payment_cuocgoc' => $payment['cuocgoc'],
                'payment_loinhuan' => $payment['payment_loinhuan'],
            ]);

            RecordTrackingHistoryAction::execute($order, OrderStatusEnum::MOI_TAO, $order->created_at);

            return $order;
        });

        // Các bước bổ sung: fail mềm — đơn vẫn hợp lệ, user bổ sung sau ở trang
        // chi tiết. Bước fail được trả về qua warnings để UI cảnh báo (không im lặng).
        $warnings = [];
        $steps = [
            'kiện hàng' => fn () => $this->createPackages($order, $formData->packages, $formData->dim),
            'khai báo hàng hóa' => fn () => $this->createInvoices($order, $formData->invoiceItems),
            'ảnh đơn hàng' => fn () => $this->createPhotos($order, $formData->orderPhotos),
            'thông tin liên hệ' => fn () => $this->saveContacts($formData, $order),
        ];

        foreach ($steps as $label => $step) {
            try {
                $step();
            } catch (Throwable $e) {
                report($e);
                $warnings[] = $label;
            }
        }

        return new CreateOrderResult($order, $warnings);
    }
    protected function calculatePrice(array $phuphihaiquan, array $service, array $receiver, array $packages, float $dim): array
    {
        $resolvedPrice = $this->resolveServicePrice->execute($service, $receiver, $packages, $dim);
        $priceList = $resolvedPrice['price_list'];
        $detail = $resolvedPrice['detail'];
        $chargeableWeight = (float) $resolvedPrice['chargeable_weight'];

        $priceMeta = [
            'service_price_list_id' => $priceList?->id,
            'service_price_list_name' => $priceList?->name,
            'service_price_detail_id' => $detail?->id,
            'service_price_quycach' => $detail?->quycach,
            'service_price_weight' => $chargeableWeight,
            'service_price_weight_from' => (float) ($detail?->weight_from ?? 0),
            'service_price_weight_to' => (float) ($detail?->weight_to ?? 0),
            'service_price_sale_unit' => (float) ($detail?->sale_price ?? 0),
            'service_price_cost_unit' => (float) ($detail?->cost_price ?? 0),
            'service_price_base_unit' => (float) ($detail?->base_price ?? 0),
            'service_price_sale_amount' => (float) $resolvedPrice['sale_price'],
            'service_price_cost_amount' => (float) $resolvedPrice['cost_price'],
            'service_price_base_amount' => (float) $resolvedPrice['base_price'],
        ];

        // Đổ giá từ bảng giá + phụ phí hải quan vào 3 nhóm, phần tính toán
        // (PPXD/VAT/tổng/lợi nhuận) giao hết cho OrderPaymentCalculator —
        // cùng một công thức với màn cập nhật giá.
        $phuphihaiquan = array_values(array_filter($phuphihaiquan, 'is_array'));

        $payment = OrderPaymentCalculator::recalculateAll([
            'cuocban' => $priceMeta + [
                'service_price_unit' => (float) ($detail?->sale_price ?? 0),
                'dongiaban' => (float) $resolvedPrice['sale_price'],
                'phuphi' => $phuphihaiquan,
            ],
            'cuocvon' => $priceMeta + [
                'service_price_unit' => (float) ($detail?->cost_price ?? 0),
                'dongiavon' => (float) $resolvedPrice['cost_price'],
                'phuphi' => $phuphihaiquan,
            ],
            'cuocgoc' => $priceMeta + [
                'service_price_unit' => (float) ($detail?->base_price ?? 0),
                'dongiagoc' => (float) $resolvedPrice['base_price'],
                'phuphi' => $phuphihaiquan,
            ],
        ]);

        $payment['payment_loinhuan'] = OrderPaymentCalculator::profitSnapshot($payment);

        return $payment;
    }

    protected function calculatePackageWeights(array $packages, float $dim): array {
        return array_map(function ($package) use ($dim) {
            $length = (float) ($package['length'] ?? 0);
            $width = (float) ($package['width'] ?? 0);
            $height = (float) ($package['height'] ?? 0);
            $numb = (float) ($package['number_of_package'] ?? 1);
            $gWeight = (float) ($package['g_weight'] ?? 0);
            $weights = $this->calculateWeight::execute($length, $width, $height, $gWeight, $dim);
            return [
                ...$package,
                'v_weight' => $weights['v_weight'],
                'c_weight' => $weights['c_weight'],
                'row_g_weight' => $gWeight * $numb,
                'row_v_weight' => $weights['v_weight'] * $numb,
                'row_c_weight' => $weights['c_weight'] * $numb,
            ];
        }, $packages);
    }

    protected function createPackages(Order $order, array $packages, float $dim): void
    {
        if ($packages === []) {
            return;
        }

        $now = now();
        $packages = $this->calculatePackageWeights($packages, $dim);

        $rows = [];
        $packageIndex = 1;

        foreach ($packages as $package) {
            $qty = max(1, (int) ($package['number_of_package'] ?? 1));

            for ($i = 0; $i < $qty; $i++) {
                $rows[] = [
                    'id_order' => $order->id,
                    'code' => $this->generatePackageCode($order->id_bill, $packageIndex++),
                    'length' => $package['length'] ?? 0,
                    'width' => $package['width'] ?? 0,
                    'height' => $package['height'] ?? 0,
                    'g_weight' => $package['g_weight'] ?? 0,
                    'v_weight' => $package['v_weight'] ?? 0,
                    'c_weight' => $package['c_weight'] ?? 0,
                    'number_of_package' => 1,
                    'row_g_weight' => $package['g_weight'] ?? 0,
                    'row_v_weight' => $package['v_weight'] ?? 0,
                    'row_c_weight' => $package['c_weight'] ?? 0,
                    'package_type' => $package['package_type'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        OrderPackage::insert($rows);
    }

    protected function createInvoices(Order $order, array $invoiceItems): void
    {
        if ($invoiceItems === []) {
            return;
        }

        $now = now();

        $rows = array_map(fn ($item) => [
            'id_order' => $order->id,
            'tenhang' => $item['tenhang'] ?? '',
            'soluong' => $item['soluong'] ?? 0,
            'xuatxu' => $item['xuatxu'] ?? '',
            'loaihang' => $item['loaihang'] ?? '',
            'hscode' => $item['hscode'] ?? '',
            'price' => $item['price'] ?? 0,
            'total' => $item['total'] ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $invoiceItems);

        Invoice::insert($rows);
    }

    protected function createPhotos(Order $order, array $photos): void
    {
        $photos = array_values(array_filter($photos));

        if ($photos === []) {
            return;
        }

        $uploadDir = public_path('uploads'.DIRECTORY_SEPARATOR.'order');
        File::ensureDirectoryExists($uploadDir);

        foreach ($photos as $photo) {
            if (! is_object($photo) || ! method_exists($photo, 'getRealPath') || ! method_exists($photo, 'getClientOriginalName')) {
                continue;
            }

            $filename = $this->makePhotoFilename($photo);
            $targetPath = $uploadDir.DIRECTORY_SEPARATOR.$filename;

            if (! copy($photo->getRealPath(), $targetPath)) {
                continue;
            }

            OrderPhoto::create([
                'id_order' => $order->id,
                'photo' => $filename,
            ]);
        }
    }

    protected function makePhotoFilename(object $file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $name = Str::slug($originalName) ?: 'order-photo';

        return $name.'-'.now()->format('YmdHis').'-'.Str::lower(Str::random(8)).'.'.$extension;
    }

    protected function saveContacts(OrderFormData $formData, Order $order): void
    {
        if ($formData->saveInfoSender) {
            $this->saveSenderContact($formData, $order);
        }

        if ($formData->saveInfoReceiver) {
            $this->saveReceiverContact($formData, $order);
        }
    }

    protected function generatePackageCode(string $orderCode, int $index): string {
        return $orderCode . '-' . str_pad($index, 2, '0', STR_PAD_LEFT);
    }
    protected function generateOrderUuid(): string {
        do {
            $uuid = collect(range(1, 4))
                ->map(fn () => Str::lower(Str::random(5)))
                ->implode('-');
        } while (Order::where('uuid', $uuid)->exists());

        return $uuid;
    }

    protected function saveSenderContact(OrderFormData $formData, Order $order): void{
        $sender = $formData->sender;
        // Transaction để lockForUpdate trong generateMemberCode có hiệu lực
        // (tránh 2 user cùng lúc sinh trùng mã CUSxxxxxx).
        DB::transaction(function () use ($formData, $sender) {
            Member::updateOrCreate(
                ['id' => $sender['id'] ?? null],
                [
                    'code' => $sender['id'] ? null : $this->generateMemberCode(),
                    'uuid' => $sender['id'] ? null : \Illuminate\Support\Str::uuid(),
                    'fullname' => $sender['company'],
                    'phone' => $sender['phone'],
                    'email' => $sender['email'] ?? null,
                    'id_province' => $sender['province_id'] ?? null,
                    'id_ward' => $sender['ward_id'] ?? null,
                    'id_ctv' => $formData->idCustomer ?: null,
                    'type' => 'sender',
                    'options' => [
                        'tenlienhe' => $sender['fullname'],
                        'address' => $sender['address'],
                    ],
                ]
            );
        });
    }

    protected function saveReceiverContact(OrderFormData $formData, Order $order): void{
        $receiver = $formData->receiver;
        DB::transaction(function () use ($formData, $receiver) {
            Member::updateOrCreate(
                ['id' => $receiver['id'] ?? null],
                [
                    'code' => $receiver['id'] ? null : $this->generateMemberCode(),
                    'uuid' => $receiver['id'] ? null : \Illuminate\Support\Str::uuid(),
                    'fullname' => $receiver['company'],
                    'phone' => $receiver['phone'],
                    'email' => $receiver['email'] ?? null,
                    'id_ctv' => $formData->idCustomer ?: null,
                    'id_sale' => $formData->idSale,
                    'id_khachhang' => $formData->sender['type'] === 'ctv' ? 0 : ($formData->sender['id'] ?? 0),
                    'type' => 'receiver',
                    'options' => [
                        'tenlienhe' => $receiver['tenlienhe'] ?? $receiver['fullname'] ?? '',
                        'id_country' => $receiver['country_id'] ?? null,
                        'mavung' => $receiver['mavung'] ?? '',
                        'address' => $receiver['address'],
                        'state' => $receiver['state'] ?? '',
                        'city' => $receiver['city'] ?? '',
                        'postcode' => $receiver['postcode'],
                    ],
                ]
            );
        });
    }
    protected function generateMemberCode(): string {
        $prefix = 'CUS';
        $lastMember = Member::where('code', 'like', $prefix . '%')
            ->orderByDesc('code')
            ->lockForUpdate()
            ->first();
        if ($lastMember) {
            $numberPart = (int) substr($lastMember->code, strlen($prefix));
            $nextNumber = $numberPart + 1;
        } else {
            $nextNumber = 1;
        }
        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }
    protected function mergeUserOptions(array $user): array{
        return array_merge($user, $user['options'] ?? []);
    }
}
