<?php

namespace App\DataTransferObjects;

class OrderFormData
{
    public function __construct(
        public ?int $idSale,
        public ?int $idCtv,
        public ?int $idCustomer,
        public array $service,
        public array $sender,
        public array $receiver,
        public array $packages,
        public ?string $notes,
        public bool $saveInfoSender,
        public bool $saveInfoReceiver,
        public float $dim,
    ) {}

    public function toArray(): array
    {
        return [
            'id_sale' => $this->idSale ?? 0,
            'id_ctv' => $this->idCtv ?? 0,
            'id_customer' => $this->idCustomer ?? 0,
            'dichvu' => $this->service,
            'info_sender' => $this->sender,
            'info_receiver' => $this->receiver,
            'packages' => $this->packages,
            'ghichu' => $this->notes,
            'dim' => $this->dim,
        ];
    }
}
