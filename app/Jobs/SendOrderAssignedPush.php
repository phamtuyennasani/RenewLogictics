<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\Push\FcmSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Gửi push "order được giao" tới các thiết bị của OPS.
 *
 * Truyền $opsId tường minh (không đọc lại order->id_ops trong handle)
 * để tránh race: nếu order bị gán lại OPS khác sau khi job enqueue,
 * push vẫn tới đúng OPS được gán tại thời điểm sự kiện.
 */
class SendOrderAssignedPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public int $orderId,
        public int $opsId,
    ) {
    }

    public function handle(FcmSender $sender): void
    {
        $ops = User::query()->find($this->opsId);
        if ($ops === null) {
            return;
        }

        $tokens = $ops->deviceTokens()->whereNull('revoked_at')->get();
        if ($tokens->isEmpty()) {
            return;
        }

        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        $code = $order->id_bill ?? (string) $order->id;

        $sender->sendToTokens(
            $tokens,
            'Đơn hàng mới được giao',
            "Bạn được giao đơn hàng {$code}.",
            [
                'type' => 'order_assigned',
                'order_id' => (string) $order->id,
                'id_bill' => $code,
            ],
        );
    }
}
