<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentProvider;
use InvalidArgumentException;

class PaymentProviderManager
{
    public function driver(?string $provider = null): PaymentProvider
    {
        $provider ??= config('payment_providers.default');

        if (! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('Default payment provider is not configured.');
        }

        $className = config("payment_providers.drivers.{$provider}");

        if (! is_string($className) || $className === '') {
            throw new InvalidArgumentException("Payment provider [{$provider}] is not registered.");
        }

        $driver = app($className);

        if (! $driver instanceof PaymentProvider) {
            throw new InvalidArgumentException("Payment provider [{$provider}] must implement ".PaymentProvider::class.'.');
        }

        return $driver;
    }
}
