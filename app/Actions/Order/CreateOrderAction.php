<?php

namespace App\Actions\Order;

use App\DataTransferObjects\OrderFormData;
use App\Models\Order;
use App\Models\Member;
use App\Models\User;

class CreateOrderAction
{
    public function __construct(
        protected GenerateOrderCodeAction $generateOrderCode,
        protected CalculateChargeableWeightAction $calculateWeight,
    ) {}

    public function execute(OrderFormData $formData): Order
    {
        // Generate order code
        $orderCode = $this->generateOrderCode->execute();

        // Get CTV info if exists
        $infoCTV = [];
        if ($formData->idCtv) {
            $ctv = User::find($formData->idCtv);
            if ($ctv) {
                $infoCTV = $this->mergeUserOptions($ctv->toArray());
            }
        }

        // Calculate package weights
        $packages = $this->calculatePackageWeights($formData->packages, $formData->dim);

        // Create order
        $order = Order::create([
            'id_bill' => $orderCode,
            'id_sale' => $formData->idSale ?? 0,
            'id_ctv' => $formData->idCtv ?? 0,
            'id_customer' => $formData->idCustomer ?? 0,
            'id_manager' => 0,
            'id_ketoan' => 0,
            'id_ops' => 0,
            'id_cs' => 0,
            'id_create' => auth()->id(),
            'info_ctv' => $infoCTV,
            'info_sender' => $formData->sender,
            'info_receiver' => $formData->receiver,
            'dichvu' => $formData->service,
            'bill_status' => 'moi-tao',
            'payment_status' => 'chua-thanh-toan',
            'payment_status_ncc' => 'chua-thanh-toan-ncc',
            'dim' => $formData->dim,
            'ghichu' => $formData->notes,
            'payment' => [],
            'shipper_status' => [],
        ]);

        // Create packages
        foreach ($packages as $index => $package) {
            $order->packages()->create([
                'code' => $this->generatePackageCode($orderCode, $index + 1),
                'package_type' => $package['package_type'],
                'length' => $package['length'],
                'width' => $package['width'],
                'height' => $package['height'],
                'g_weight' => $package['g_weight'],
                'v_weight' => $package['v_weight'],
                'c_weight' => $package['c_weight'],
            ]);
        }

        // Save sender to contacts if requested
        if ($formData->saveInfoSender && $formData->idCtv) {
            $this->saveSenderContact($formData, $order);
        }

        // Save receiver to contacts if requested
        if ($formData->saveInfoReceiver) {
            $this->saveReceiverContact($formData, $order);
        }

        return $order;
    }

    protected function calculatePackageWeights(array $packages, float $dim): array
    {
        return array_map(function ($package) use ($dim) {
            $length = (float) $package['length'];
            $width = (float) $package['width'];
            $height = (float) $package['height'];
            $gWeight = (float) $package['g_weight'];

            $vWeight = ($length * $width * $height) / $dim;
            $cWeight = max($vWeight, $gWeight);

            // Apply rounding rules
            if ($cWeight < 21) {
                $cWeight = ceil($cWeight / 0.5) * 0.5;
            } else {
                $cWeight = ceil($cWeight);
            }

            return [
                ...$package,
                'v_weight' => round($vWeight, 3),
                'c_weight' => round($cWeight, 3),
            ];
        }, $packages);
    }

    protected function generatePackageCode(string $orderCode, int $index): string
    {
        return $orderCode . '-' . str_pad($index, 2, '0', STR_PAD_LEFT);
    }

    protected function saveSenderContact(OrderFormData $formData, Order $order): void
    {
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

    protected function saveReceiverContact(OrderFormData $formData, Order $order): void
    {
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
                    'tenlienhe' => $receiver['tenlienhe'],
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

    protected function generateMemberCode(): string
    {
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

    protected function mergeUserOptions(array $user): array
    {
        return array_merge($user, $user['options'] ?? []);
    }
}
