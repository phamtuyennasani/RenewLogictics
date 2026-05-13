<?php

namespace App\Services\Providers;

use App\Services\EInvoices\EInvoiceProviderManager;
use App\Services\Payments\PaymentProviderManager;

class ProviderHub
{
    public function __construct(
        protected PaymentProviderManager $paymentProviders,
        protected EInvoiceProviderManager $einvoiceProviders,
    ) {
    }

    public function payment(?string $provider = null): object
    {
        return $this->paymentProviders->driver($provider);
    }

    public function einvoice(?string $provider = null): object
    {
        return $this->einvoiceProviders->driver($provider);
    }
}
