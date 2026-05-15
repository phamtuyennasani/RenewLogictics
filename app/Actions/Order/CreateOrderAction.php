<?php

namespace App\Actions\Order;

use App\DataTransferObjects\OrderFormData;
use App\Enums\OrderStatusEnum;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Models\OrderPackage;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

class CreateOrderAction
{
    public array $paymentDefault;
    public function __construct(
        protected GenerateOrderCodeAction $generateOrderCode,
        protected CalculateChargeableWeightAction $calculateWeight,
    ) {
        $this->defaultPayment();
    }
    protected function defaultPayment(): array
    {
        $this->paymentDefault = [
            'cuocban' => [
                'dongiaban' => 0,
                'vat_percent' => 0,
                'vat_amount' => 0,
                'ppxd_percent' => 0,
                'ppxd_amount' => 0,
                'tongcuoc' => 0,
                'phuphi' => [],
                'hh_khachhang' => [],
                'total_vat_phuphi' => 0,
                'total_phuphi_no_vat' => 0,
                'total_phuphi' => 0,
                'total_hh_khachhang' => 0,
                'total_tongcuoc_no_vat' => 0,
                'total_tongcuoc' => 0,
            ],
            'cuocvon' => [
                'dongiavon' => 0,
                'vat_percent' => 0,
                'vat_amount' => 0,
                'ppxd_percent' => 0,
                'ppxd_amount' => 0,
                'tongcuoc' => 0,
                'phichiho' => [],
                'phuphi' => [],
                'total_vat_phuphi' => 0,
                'total_phuphi_no_vat' => 0,
                'total_phuphi' => 0,
                'total_tongcuoc' => 0,
                'total_tongcuoc_no_vat' => 0,
                'bonus_sale_percent' => 0,
                'bonus_sale_amount' => 0,
            ],
            'cuocgoc' => [
                'dongiagoc' => 0,
                'vat_percent' => 0,
                'vat_amount' => 0,
                'ppxd_percent' => 0,
                'ppxd_amount' => 0,
                'tongcuoc' => 0,
                'phuphi' => [],
                'total_vat_phuphi' => 0,
                'total_phuphi_no_vat' => 0,
                'total_phuphi' => 0,
                'total_tongcuoc_no_vat' => 0,
                'total_tongcuoc' => 0,
            ],
            'payment_loinhuan' => [
                'cuocban_no_vat' => 0,
                'cuocban' => 0,
                'cuocvon_no_vat' => 0,
                'cuocvon' => 0,
                'cuocgoc_no_vat' => 0,
                'cuocgoc' => 0,
                'loinhuantamtinh' => 0,
                'tysuattamtinh' => 0,
                'loinhuan' => 0,
                'tysuatloinhuan' => 0,
            ],
        ];

        return $this->paymentDefault;
    }
    public function execute(OrderFormData $formData): Order
    {
        $payment = $this->calculatePrice($formData->phuphihaiquan);
        $currentUser = auth()->user();
        $currentUserId = auth()->id();
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

        try {
            $this->createPackages($order, $formData->packages, $formData->dim);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $this->createInvoices($order, $formData->invoiceItems);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $this->createPhotos($order, $formData->orderPhotos);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $this->saveContacts($formData, $order);
        } catch (Throwable $e) {
            report($e);
        }

        return $order;
    }
    protected function calculatePrice(array $phuphihaiquan): array
    {
        $this->paymentDefault['cuocvon']['phuphi'] = $phuphihaiquan;
        $this->paymentDefault['cuocban']['phuphi'] = $phuphihaiquan;
        $this->paymentDefault['cuocgoc']['phuphi'] = $phuphihaiquan;

        $totalPhuphi = array_sum(array_map(
            fn ($item) => (float) ($item['total'] ?? 0),
            $phuphihaiquan
        ));

        $this->paymentDefault['cuocvon']['total_phuphi'] = $totalPhuphi;
        $this->paymentDefault['cuocban']['total_phuphi'] = $totalPhuphi;
        $this->paymentDefault['cuocgoc']['total_phuphi'] = $totalPhuphi;

        $this->paymentDefault['cuocvon']['total_phuphi_no_vat'] = $totalPhuphi;
        $this->paymentDefault['cuocban']['total_phuphi_no_vat'] = $totalPhuphi;
        $this->paymentDefault['cuocgoc']['total_phuphi_no_vat'] = $totalPhuphi;

        $this->paymentDefault['cuocvon']['total_tongcuoc_no_vat'] = $this->paymentDefault['cuocvon']['total_phuphi_no_vat'];
        $this->paymentDefault['cuocvon']['total_tongcuoc'] = $this->paymentDefault['cuocvon']['total_phuphi_no_vat'];

        $this->paymentDefault['cuocban']['total_tongcuoc_no_vat'] = $this->paymentDefault['cuocban']['total_phuphi_no_vat'];
        $this->paymentDefault['cuocban']['total_tongcuoc'] = $this->paymentDefault['cuocban']['total_phuphi_no_vat'];

        $this->paymentDefault['cuocgoc']['total_tongcuoc_no_vat'] = $this->paymentDefault['cuocgoc']['total_phuphi_no_vat'];
        $this->paymentDefault['cuocgoc']['total_tongcuoc'] = $this->paymentDefault['cuocgoc']['total_phuphi_no_vat'];

        $this->paymentDefault['payment_loinhuan']['cuocvon_no_vat'] = $this->paymentDefault['cuocvon']['total_tongcuoc_no_vat'];
        $this->paymentDefault['payment_loinhuan']['cuocvon'] = $this->paymentDefault['cuocvon']['total_tongcuoc'];
        $this->paymentDefault['payment_loinhuan']['cuocban_no_vat'] = $this->paymentDefault['cuocban']['total_tongcuoc_no_vat'];
        $this->paymentDefault['payment_loinhuan']['cuocban'] = $this->paymentDefault['cuocban']['total_tongcuoc'];
        $this->paymentDefault['payment_loinhuan']['cuocgoc_no_vat'] = $this->paymentDefault['cuocgoc']['total_tongcuoc_no_vat'];
        $this->paymentDefault['payment_loinhuan']['cuocgoc'] = $this->paymentDefault['cuocgoc']['total_tongcuoc'];
        return $this->paymentDefault;
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
        if ($formData->saveInfoSender && $formData->idCtv) {
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
                'id_ctv' => $formData->idCtv,
                'type' => 'sender',
                'options' => [
                    'tenlienhe' => $sender['fullname'],
                    'address' => $sender['address'],
                ],
            ]
        );
    }

    protected function saveReceiverContact(OrderFormData $formData, Order $order): void{
        $receiver = $formData->receiver;
        Member::updateOrCreate(
            ['id' => $receiver['id'] ?? null],
            [
                'code' => $receiver['id'] ? null : $this->generateMemberCode(),
                'uuid' => $receiver['id'] ? null : \Illuminate\Support\Str::uuid(),
                'fullname' => $receiver['company'],
                'phone' => $receiver['phone'],
                'email' => $receiver['email'] ?? null,
                'id_ctv' => $formData->idCtv,
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
    protected function acquireOrderCodeLock(string $lockName, int $timeoutSeconds = 10): void{
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }
        $result = DB::selectOne('SELECT GET_LOCK(?, ?) AS lock_acquired', [$lockName, $timeoutSeconds]);
        if (! $result || (int) ($result->lock_acquired ?? 0) !== 1) {
            throw new \RuntimeException('Unable to acquire order code lock.');
        }
    }

    protected function releaseOrderCodeLock(string $lockName): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }
        DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockName]);
    }
}
