<?php

namespace App\Services\TrackingMore;

use RuntimeException;

class TrackingMoreException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $metaCode = null,
        public readonly array $response = [],
    ) {
        parent::__construct($message, $metaCode ?? $httpStatus ?? 0);
    }
}
