<?php

namespace App\Services\Push;

use App\Models\UserDeviceToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * Gửi push qua FCM HTTP v1 (kreait/firebase-php).
 *
 * Thiết kế no-op an toàn: nếu chưa cấu hình FIREBASE_CREDENTIALS thì
 * resolve Messaging sẽ ném exception → service log warning và bỏ qua,
 * KHÔNG làm vỡ luồng nghiệp vụ (gán shipper vẫn chạy bình thường).
 */
class FcmSender
{
    /**
     * Gửi tới nhiều device token; tự revoke token invalid/unknown.
     *
     * @param  Collection<int, UserDeviceToken>  $deviceTokens
     * @param  array<string, string>  $data  payload định tuyến (chỉ string)
     */
    public function sendToTokens(
        Collection $deviceTokens,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $deviceTokens = $deviceTokens->filter(fn (UserDeviceToken $t) => filled($t->fcm_token));
        if ($deviceTokens->isEmpty()) {
            return;
        }

        $messaging = $this->resolveMessaging();
        if ($messaging === null) {
            return;
        }

        // FCM yêu cầu data payload toàn bộ là string.
        $stringData = array_map(static fn ($v) => (string) $v, $data);

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($stringData)
            ->withDefaultSounds();

        $tokenStrings = $deviceTokens->pluck('fcm_token')->all();

        try {
            $report = $messaging->sendMulticast($message, $tokenStrings);
        } catch (Throwable $e) {
            Log::warning('[FcmSender] Gửi FCM thất bại.', ['error' => $e->getMessage()]);
            return;
        }

        $stale = array_merge($report->unknownTokens(), $report->invalidTokens());
        if ($stale !== []) {
            UserDeviceToken::query()
                ->whereIn('fcm_token', $stale)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }
    }

    private function resolveMessaging(): ?Messaging
    {
        if (! config('services.firebase.push_enabled')) {
            Log::info('[FcmSender] PUSH_ENABLED=false → bỏ qua gửi push.');
            return null;
        }

        if (blank(config('services.firebase.credentials'))
            && blank(env('FIREBASE_CREDENTIALS'))) {
            Log::info('[FcmSender] Chưa cấu hình FIREBASE_CREDENTIALS → bỏ qua gửi push.');
            return null;
        }

        try {
            return app(Messaging::class);
        } catch (Throwable $e) {
            Log::warning('[FcmSender] Không khởi tạo được Firebase Messaging.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
