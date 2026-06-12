<?php

namespace App\Jobs;

use App\Models\Pickup;
use App\Models\User;
use App\Services\Push\FcmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi push "pickup được giao" tới các thiết bị của shipper.
 *
 * Truyền $shipperId tường minh (không đọc lại pickup->id_shipper trong handle)
 * để tránh race: nếu pickup bị gán lại shipper khác sau khi job enqueue,
 * push vẫn tới đúng shipper được gán tại thời điểm sự kiện.
 */
class SendPickupAssignedPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $pickupId,
        public int $shipperId,
    ) {
    }

    public function handle(FcmSender $sender): void
    {
        $shipper = User::query()->find($this->shipperId);
        if ($shipper === null) {
            return;
        }

        $tokens = $shipper->deviceTokens()->whereNull('revoked_at')->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $pickup = Pickup::query()->find($this->pickupId);
        if ($pickup === null) {
            return;
        }

        $code = $pickup->ma_pickup ?? (string) $pickup->id;
        
        $sender->sendToTokens(
            $tokens,
            'Pickup mới được giao',
            "Bạn được giao phiếu lấy hàng {$code}.",
            [
                'type' => 'pickup_assigned',
                'pickup_id' => (string) $pickup->id,
                'pickup_code' => $code,
            ],
        );
    }
}
