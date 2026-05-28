<?php

namespace App\Services\Payments;

use App\Models\Setting;
use App\Services\Payments\Contracts\PaymentProvider;
use InvalidArgumentException;

class PaymentProviderManager
{
    protected const PROVIDERS = ['sepay', 'momo', 'vnpay'];

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

    public static function allProviders(): array
    {
        return self::PROVIDERS;
    }

    public static function enabledProviders(): array
    {
        $options = data_get(Setting::first(), 'options', []);
        $result = [];

        foreach (self::PROVIDERS as $provider) {
            $result[$provider] = (bool) ($options["payment_{$provider}_enabled"] ?? false);
        }

        return $result;
    }

    public static function defaultProvider(): string
    {
        $enabled = self::enabledProviders();

        foreach (self::PROVIDERS as $provider) {
            if ($enabled[$provider] ?? false) {
                return $provider;
            }
        }

        return self::PROVIDERS[0];
    }

    public static function providerLabels(): array
    {
        return [
            'sepay' => [
                'name' => 'SePay (QR Banking)',
                'description' => 'Thanh toán qua app ngân hàng, ví điện tử.',
                'color' => 'primary',
            ],
            'momo' => [
                'name' => 'MoMo',
                'description' => 'Thanh toán nhanh qua ví MoMo.',
                'color' => 'pink',
            ],
            'vnpay' => [
                'name' => 'VNPAY',
                'description' => 'Chuyển hướng sang cổng thanh toán VNPAY.',
                'color' => 'sky',
            ],
        ];
    }
}
