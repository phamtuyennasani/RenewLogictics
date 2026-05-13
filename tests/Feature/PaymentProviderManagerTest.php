<?php

namespace Tests\Feature;

use App\Services\Payments\PaymentProviderManager;
use App\Services\Providers\Sepay\SepayPaymentService;
use InvalidArgumentException;
use Tests\TestCase;

class PaymentProviderManagerTest extends TestCase
{
    public function test_it_can_resolve_default_payment_provider(): void
    {
        config([
            'payment_providers.default' => 'sepay',
            'payment_providers.drivers.sepay' => SepayPaymentService::class,
        ]);

        $manager = app(PaymentProviderManager::class);

        $this->assertInstanceOf(SepayPaymentService::class, $manager->driver());
    }

    public function test_it_throws_when_payment_provider_is_not_registered(): void
    {
        config([
            'payment_providers.default' => 'unknown',
            'payment_providers.drivers' => [],
        ]);

        $manager = app(PaymentProviderManager::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment provider [unknown] is not registered.');

        $manager->driver();
    }
}
