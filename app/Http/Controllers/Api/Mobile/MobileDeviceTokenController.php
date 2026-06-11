<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Api\Mobile\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\UserDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Đăng ký / thu hồi FCM device token cho push notification.
 * Xem docs/PUSH_NOTIFICATION_PLAN.md (Phase 3).
 */
class MobileDeviceTokenController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/mobile/device-tokens — upsert token theo fcm_token, gắn user hiện tại.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ], [
            'fcm_token.required' => 'Thiếu FCM token.',
        ]);

        UserDeviceToken::query()->updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? null,
                'device_name' => $validated['device_name'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $this->ok(null, 'Đã đăng ký thiết bị nhận thông báo.');
    }

    /**
     * POST /api/mobile/device-tokens/revoke — thu hồi token (logout / gỡ thiết bị).
     * Chỉ revoke token thuộc user hiện tại.
     */
    public function revoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
        ], [
            'fcm_token.required' => 'Thiếu FCM token.',
        ]);

        UserDeviceToken::query()
            ->where('fcm_token', $validated['fcm_token'])
            ->where('user_id', $request->user()->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return $this->ok(null, 'Đã hủy đăng ký thiết bị.');
    }
}
