<?php

namespace App\Services\Payments\Data;

use Illuminate\Support\Carbon;

class PaymentIntentData
{
    public function __construct(
        public readonly string $provider,
        public readonly string $channel,
        public readonly string $reference,
        public readonly int $amount,
        public readonly ?string $paymentUrl = null,
        public readonly ?string $qrUrl = null,
        public readonly ?string $providerIntentId = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly array $raw = [],
    ) {
    }
}
