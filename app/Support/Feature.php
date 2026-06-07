<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

class Feature
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return config('features.items', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function availableDefinitions(): array
    {
        return array_filter(
            self::definitions(),
            fn (array $definition, string $key): bool => self::available($key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $key): array
    {
        return self::definitions()[$key] ?? [];
    }

    public static function available(string $key): bool
    {
        if (! array_key_exists($key, self::definitions())) {
            return false;
        }

        return self::toBool(data_get(self::definition($key), 'available', true));
    }

    public static function optionKey(string $key): string
    {
        return "feature_{$key}_enabled";
    }

    public static function defaultEnabled(string $key): bool
    {
        if (! self::available($key)) {
            return false;
        }

        return self::toBool(data_get(self::definition($key), 'default', true));
    }

    /**
     * @param  array<string, mixed>|null  $options
     */
    public static function enabled(string $key, ?array $options = null): bool
    {
        if (! self::available($key)) {
            return false;
        }

        if ($options === null) {
            try {
                $options = data_get(Setting::first(), 'options', []);
            } catch (Throwable) {
                $options = [];
            }
        }

        $options = is_array($options) ? $options : [];

        $optionKey = self::optionKey($key);

        if (array_key_exists($optionKey, $options)) {
            return self::toBool($options[$optionKey]);
        }

        return self::defaultEnabled($key);
    }

    public static function label(string $key): string
    {
        return (string) data_get(self::definition($key), 'label', $key);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}