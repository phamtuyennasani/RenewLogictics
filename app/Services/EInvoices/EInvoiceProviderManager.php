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
        try {
            $options = data_get(Setting::first(), 'options', []);
        } catch (\Throwable) {
            $options = [];
        }
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
                'icon' => 'receipt-percent',
            ],
            // Thêm provider mới tại đây:
            // 'vnpt' => [
            //     'name' => 'VNPT eInvoice',
            //     'description' => 'Hóa đơn điện tử VNPT.',
            //     'color' => 'sky',
            //     'icon' => 'receipt-percent',
            // ],
        ];
    }

    /**
     * Gom cấu hình hiển thị + schema field của mọi cổng e-invoice để trang
     * Cấu hình render form động. Thêm cổng mới chỉ cần khai báo ở service +
     * manager, không phải sửa giao diện settings.
     *
     * @return array<string, array{name:string,description:string,color:string,icon:string,fields:array<int,array<string,mixed>>}>
     */
    public static function configSchemas(): array
    {
        $labels = self::providerLabels();
        $schemas = [];

        foreach (self::PROVIDERS as $provider) {
            $className = config("einvoice_providers.drivers.{$provider}");

            if (! is_string($className) || ! class_exists($className)) {
                continue;
            }

            if (! is_subclass_of($className, EInvoiceProvider::class)) {
                continue;
            }

            $meta = $labels[$provider] ?? [
                'name' => ucfirst($provider),
                'description' => '',
                'color' => 'primary',
                'icon' => 'receipt-percent',
            ];

            $schemas[$provider] = [
                ...$meta,
                'fields' => $className::configSchema(),
            ];
        }

        return $schemas;
    }
}
