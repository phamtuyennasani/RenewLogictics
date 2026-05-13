<?php

namespace Tests\Feature;

use App\Services\EInvoices\EInvoiceProviderManager;
use App\Services\Providers\Sepay\SepayEInvoiceService;
use InvalidArgumentException;
use Tests\TestCase;

class EInvoiceProviderManagerTest extends TestCase
{
    public function test_it_can_resolve_named_einvoice_provider(): void
    {
        config([
            'einvoice_providers.default' => 'sepay',
            'einvoice_providers.drivers.sepay' => SepayEInvoiceService::class,
        ]);

        $manager = app(EInvoiceProviderManager::class);

        $this->assertInstanceOf(SepayEInvoiceService::class, $manager->driver('sepay'));
    }

    public function test_it_throws_when_einvoice_provider_is_not_registered(): void
    {
        config([
            'einvoice_providers.default' => 'unknown',
            'einvoice_providers.drivers' => [],
        ]);

        $manager = app(EInvoiceProviderManager::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('eInvoice provider [unknown] is not registered.');

        $manager->driver();
    }
}
