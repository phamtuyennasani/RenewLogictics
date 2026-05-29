<?php

namespace App\Services\EInvoices;

use App\Models\Setting;
use App\Services\EInvoices\Contracts\EInvoiceProvider;
use InvalidArgumentException;

class EInvoiceProviderManager
{
    protected const PROVIDERS = ['sepay'];

    public function driver(?string $provider = null): EInvoiceProvider
    {
        $provider ??= config('einvoice_providers.default');

        if (! is_string($provider) || $provider === '') {
            throw new InvalidArgumentException('Default eInvoice provider is not configured.');
        }

        $className = config("einvoice_providers.drivers.{$provider}");

        if (! is_string($className) || $className === '') {
            throw new InvalidArgumentException("eInvoice provider [{$provider}] is not registered.");
        }

        $driver = app($className);

        if (! $driver instanceof EInvoiceProvider) {
            throw new InvalidArgumentException("eInvoice provider [{$provider}] must implement ".EInvoiceProvider::class.'.');
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
            $result[$provider] = (bool) ($options["einvoice_{$provider}_enabled"] ?? false);
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
                'name' => 'SePay eInvoice',
                'description' => 'Hóa đơn điện tử qua SePay API.',
                'color' => 'primary',
            ],
            // Thêm provider mới tại đây:
            // 'vnpt' => [
            //     'name' => 'VNPT eInvoice',
            //     'description' => 'Hóa đơn điện tử VNPT.',
            //     'color' => 'sky',
            // ],
        ];
    }
}
