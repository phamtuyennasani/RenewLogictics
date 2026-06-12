<?php

namespace App\Observers;

use App\Jobs\SendPickupAssignedOpsPush;
use App\Jobs\SendPickupAssignedPush;
use App\Models\Pickup;

class PickupObserver
{
    /**
     * Pickup vừa tạo với id_shipper hoặc id_user sẵn → gửi push tương ứng.
     */
    public function created(Pickup $pickup): void
    {
        if (filled($pickup->id_shipper)) {
            $this->dispatchShipperAssigned($pickup, (int) $pickup->id_shipper);
        }

        // Chỉ bắn push OPS nếu id_user khác người đang tạo (tránh tự bắn cho chính mình).
        if (filled($pickup->id_user) && $pickup->id_user != auth()->id()) {
            $this->dispatchOpsAssigned($pickup, (int) $pickup->id_user);
        }
    }

    /**
     * id_shipper hoặc id_user đổi sang người mới → gửi push tương ứng.
     *
     * Dùng getOriginal để chỉ bắn khi giá trị thực sự thay đổi, tránh
     * gửi trùng khi save lại pickup mà không đổi người.
     */
    public function updated(Pickup $pickup): void
    {
        // Push cho shipper khi id_shipper thay đổi.
        if ($pickup->wasChanged('id_shipper')) {
            $new = $pickup->id_shipper;
            if (filled($new)) {
                $this->dispatchShipperAssigned($pickup, (int) $new);
            }
        }

        // Push cho OPS khi id_user thay đổi (và khác người thao tác).
        if ($pickup->wasChanged('id_user')) {
            $new = $pickup->id_user;
            if (filled($new) && $new != auth()->id()) {
                $this->dispatchOpsAssigned($pickup, (int) $new);
            }
        }
    }

    private function dispatchShipperAssigned(Pickup $pickup, int $shipperId): void
    {
        // Tắt push toàn cục (chưa có app để nhận) → không enqueue job rác.
        if (!config('services.firebase.push_enabled')) {
            return;
        }

        SendPickupAssignedPush::dispatch((int) $pickup->id, $shipperId);
    }

    private function dispatchOpsAssigned(Pickup $pickup, int $opsId): void
    {
        if (!config('services.firebase.push_enabled')) {
            return;
        }

        SendPickupAssignedOpsPush::dispatch((int) $pickup->id, $opsId);
    }
}
