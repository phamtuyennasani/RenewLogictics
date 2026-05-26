<?php

namespace App\Services\Payments\Data;

use Illuminate\Support\Carbon;

class PaymentRequestData
{
    public function __construct(
        public readonly int $amount,
        public readonly string $reference,
        public readonly ?string $description = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly array $metadata = [],
    ) {
    }
}
