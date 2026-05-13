<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentProviderManager
{
    public function driver(?string $provider = null): object
    {
        $provider ??= config('payment_providers.default');

        if (! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('Default payment provider is not configured.');
        }

        $className = config("payment_providers.drivers.{$provider}");

        if (! is_string($className) || $className === '') {
            throw new InvalidArgumentException("Payment provider [{$provider}] is not registered.");
        }

        return app($className);
    }
}
