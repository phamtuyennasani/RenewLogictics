<?php

namespace App\DataTransferObjects;

class OrderFormData
{
    public function __construct(
        public ?int $idSale,
        public ?int $idCustomer,
        public array $service,
        public array $sender,
        public array $receiver,
        public array $packages,
        public ?string $notes,
        public bool $saveInfoSender,
        public bool $saveInfoReceiver,
        public float $dim,
        public array $phuphihaiquan = [],
        public array $invoiceItems = [],
        public array $orderPhotos = [],
        public ?int $idCs = null,
    ) {}

    public function toArray(): array
    {
        return [
            'id_sale' => $this->idSale ?? 0,
            'id_customer' => $this->idCustomer ?? 0,
            'id_cs' => $this->idCs ?? 0,
            'service' => $this->service,
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'packages' => $this->packages,
            'invoice_items' => $this->invoiceItems,
            'ghichu' => $this->notes?? '',
            'dim' => $this->dim,
            'phuphihaiquan' => $this->phuphihaiquan,
            'order_photos' => $this->orderPhotos,
        ];
    }
}
