<?php

namespace App\Actions\Order;

use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Support\Facades\Auth;

class RecordOrderEditHistoryAction
{
    public static function execute(
        Order $order,
        string $action,
        string $label,
        array $before,
        array $after,
        ?string $summary = null,
    ): ?OrderHistory {
        $changes = self::diff($before, $after);

        if ($changes === []) {
            return null;
        }

        return $order->histories()->create([
            'id_user' => Auth::id(),
            'action' => $action,
            'content' => json_encode([
                'label' => $label,
                'summary' => $summary ?: 'sua '.$label,
                'changes' => $changes,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    protected static function diff(array $before, array $after): array
    {
        $changes = [];
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            if (self::normalize($oldValue) === self::normalize($newValue)) {
                continue;
            }

            $changes[$key] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $changes;
    }

    protected static function normalize(mixed $value): string
    {
        if (is_array($value)) {
            ksort($value);

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) (float) $value;
        }

        return trim((string) $value);
    }
}
