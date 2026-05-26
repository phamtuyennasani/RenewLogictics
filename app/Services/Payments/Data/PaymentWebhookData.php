<?php

namespace App\Services\Payments\Data;

use Illuminate\Support\Carbon;

class PaymentWebhookData
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $reference,
        public readonly int $amount,
        public readonly string $status,
        public readonly ?string $providerTransactionId = null,
        public readonly ?Carbon $paidAt = null,
        public readonly array $raw = [],
        public readonly ?string $message = null,
    ) {
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
