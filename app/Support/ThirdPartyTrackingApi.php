<?php

namespace App\Support;

use App\Models\Setting;
use Throwable;

class ThirdPartyTrackingApi
{
    public const ENABLED_KEY = 'third_party_tracking_api_enabled';
    public const API_KEY = 'third_party_tracking_api_key';
    public const BLOCKED_IPS_KEY = 'third_party_tracking_api_blocked_ips';
    public const RATE_LIMIT_KEY = 'third_party_tracking_api_rate_limit_per_minute';

    /**
     * @return array<string, mixed>
     */
    public static function options(): array
    {
        try {
            $options = data_get(Setting::first(), 'options', []);
        } catch (Throwable) {
            return [];
        }

        return is_array($options) ? $options : [];
    }

    public static function enabled(?array $options = null): bool
    {
        $options ??= self::options();

        return filter_var($options[self::ENABLED_KEY] ?? false, FILTER_VALIDATE_BOOL);
    }

    public static function apiKey(?array $options = null): string
    {
        $options ??= self::options();

        return trim((string) ($options[self::API_KEY] ?? ''));
    }

    public static function rateLimitPerMinute(?array $options = null): int
    {
        $options ??= self::options();
        $limit = (int) ($options[self::RATE_LIMIT_KEY] ?? 60);

        return max(1, min(1000, $limit));
    }

    /**
     * @return array<int, string>
     */
    public static function blockedIps(?array $options = null): array
    {
        $options ??= self::options();
        $raw = (string) ($options[self::BLOCKED_IPS_KEY] ?? '');

        if (trim($raw) === '') {
            return [];
        }

        return collect(preg_split('/[\r\n,;]+/', $raw) ?: [])
            ->map(fn ($ip) => trim((string) $ip))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function ipBlocked(string $ip, ?array $options = null): bool
    {
        return in_array($ip, self::blockedIps($options), true);
    }

    public static function keyMatches(string $providedKey, ?array $options = null): bool
    {
        $configuredKey = self::apiKey($options);

        return $configuredKey !== '' && hash_equals($configuredKey, trim($providedKey));
    }
}