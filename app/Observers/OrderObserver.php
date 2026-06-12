<?php

namespace App\Observers;

use App\Jobs\SendOrderAssignedPush;
use App\Models\Order;

class OrderObserver
{
    /**
     * Order vừa tạo với id_ops sẵn → gửi push.
     */
    public function created(Order $order): void
    {
        if (filled($order->id_ops) && $order->id_ops != 0) {
            $this->dispatchAssigned($order, (int) $order->id_ops);
        }
    }

    /**
     * id_ops đổi sang một OPS mới (không null/0) → gửi push.
     *
     * Dùng getOriginal để chỉ bắn khi giá trị thực sự thay đổi, tránh
     * gửi trùng khi save lại order mà không đổi OPS.
     */
    public function updated(Order $order): void
    {
        if (!$order->wasChanged('id_ops')) {
            return;
        }

        $new = $order->id_ops;
        if (blank($new) || $new == 0) {
            return;
        }

        $this->dispatchAssigned($order, (int) $new);
    }

    private function dispatchAssigned(Order $order, int $opsId): void
    {
        // Tắt push toàn cục (chưa có app để nhận) → không enqueue job rác.
        if (!config('services.firebase.push_enabled')) {
            return;
        }

        SendOrderAssignedPush::dispatch((int) $order->id, $opsId);
    }
}
