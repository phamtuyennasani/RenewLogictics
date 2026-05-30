<?php

namespace Tests\Feature;

use App\Services\Payments\PaymentProviderManager;
use App\Services\Providers\MoMo\MoMoPaymentService;
use App\Services\Providers\Sepay\SepayPaymentService;
use App\Services\Providers\VNPay\VNPayPaymentService;
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

    public function test_config_schemas_aggregate_meta_and_fields_for_every_provider(): void
    {
        config([
            'payment_providers.drivers.sepay' => SepayPaymentService::class,
            'payment_providers.drivers.momo' => MoMoPaymentService::class,
            'payment_providers.drivers.vnpay' => VNPayPaymentService::class,
        ]);

        $schemas = PaymentProviderManager::configSchemas();

        $this->assertSame(['sepay', 'momo', 'vnpay'], array_keys($schemas));

        foreach ($schemas as $schema) {
            $this->assertArrayHasKey('name', $schema);
            $this->assertArrayHasKey('icon', $schema);
            $this->assertArrayHasKey('fields', $schema);
            $this->assertNotEmpty($schema['fields']);

            foreach ($schema['fields'] as $field) {
                $this->assertArrayHasKey('key', $field);
                $this->assertArrayHasKey('label', $field);
                $this->assertArrayHasKey('type', $field);
            }
        }
    }

    public function test_config_schemas_preserve_existing_storage_keys(): void
    {
        config([
            'payment_providers.drivers.sepay' => SepayPaymentService::class,
            'payment_providers.drivers.momo' => MoMoPaymentService::class,
            'payment_providers.drivers.vnpay' => VNPayPaymentService::class,
        ]);

        $schemas = PaymentProviderManager::configSchemas();

        $keysOf = fn (string $provider) => collect($schemas[$provider]['fields'])->pluck('key')->all();

        // Giữ nguyên khóa cũ để không phải migrate dữ liệu.
        $this->assertSame(['bank_account_name', 'bank_account_number', 'bank_code'], $keysOf('sepay'));
        $this->assertSame(['payment_momo_environment', 'payment_momo_partner_code', 'payment_momo_access_key', 'payment_momo_secret_key'], $keysOf('momo'));
        $this->assertSame(['payment_vnpay_environment', 'payment_vnpay_tmn_code', 'payment_vnpay_hash_secret'], $keysOf('vnpay'));

        // SePay hiển thị công khai; MoMo/VNPay là field nhạy cảm (gate sau xác thực Admin).
        $this->assertFalse(collect($schemas['sepay']['fields'])->contains(fn ($f) => $f['sensitive'] ?? false));
        $this->assertTrue(collect($schemas['momo']['fields'])->contains(fn ($f) => $f['sensitive'] ?? false));
        $this->assertTrue(collect($schemas['vnpay']['fields'])->contains(fn ($f) => $f['sensitive'] ?? false));
    }
}
