<?php

namespace App\Services\EInvoices;

use InvalidArgumentException;

class EInvoiceProviderManager
{
    public function driver(?string $provider = null): object
    {
        $provider ??= config('einvoice_providers.default');

        if (! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('Default eInvoice provider is not configured.');
        }

        $className = config("einvoice_providers.drivers.{$provider}");

        if (! is_string($className) || $className === '') {
            throw new InvalidArgumentException("eInvoice provider [{$provider}] is not registered.");
        }

        return app($className);
    }
}
