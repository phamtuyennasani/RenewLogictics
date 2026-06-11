<?php

namespace App\Observers;

use App\Jobs\SendPickupAssignedPush;
use App\Models\Pickup;

class PickupObserver
{
    /**
     * Pickup vừa tạo với id_shipper sẵn → gửi push.
     */
    public function created(Pickup $pickup): void
    {
        if (filled($pickup->id_shipper)) {
            $this->dispatchAssigned($pickup, (int) $pickup->id_shipper);
        }
    }

    /**
     * id_shipper đổi sang một shipper mới (không null) → gửi push.
     *
     * Dùng getOriginal để chỉ bắn khi giá trị thực sự thay đổi, tránh
     * gửi trùng khi save lại pickup mà không đổi shipper.
     */
    public function updated(Pickup $pickup): void
    {
        if (! $pickup->wasChanged('id_shipper')) {
            return;
        }

        $new = $pickup->id_shipper;
        if (blank($new)) {
            return;
        }

        $this->dispatchAssigned($pickup, (int) $new);
    }

    private function dispatchAssigned(Pickup $pickup, int $shipperId): void
    {
        // Tắt push toàn cục (chưa có app để nhận) → không enqueue job rác.
        if (! config('services.firebase.push_enabled')) {
            return;
        }

        SendPickupAssignedPush::dispatch((int) $pickup->id, $shipperId);
    }
}
