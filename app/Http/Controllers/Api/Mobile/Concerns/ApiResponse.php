<?php

namespace App\Http\Controllers\Api\Mobile\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Chuẩn hóa response envelope cho toàn bộ API mobile.
 *
 * Format thống nhất (theo MOBILE_API_CONTRACT §1.3):
 *   success: { "success": true,  "message": "...", "data": ... }
 *   failure: { "success": false, "message": "...", "errors": {...}? }
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, string $message = 'Thành công.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function fail(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Đóng gói enum status thành object { value, label, color } cho app.
     *
     * @param  \App\Enums\PickupStatusEnum|\App\Enums\OrderStatusEnum|null  $status
     */
    protected function statusPayload($status): ?array
    {
        if ($status === null) {
            return null;
        }

        return [
            'value' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
        ];
    }
}
