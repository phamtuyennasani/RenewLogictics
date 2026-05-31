<?php

namespace App\Actions\Pickup;

use App\Enums\PickupStatusEnum;
use App\Models\Pickup;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransitionPickupStatusAction
{
    public static function execute(Pickup $pickup, PickupStatusEnum $status): Pickup
    {
        return DB::transaction(function () use ($pickup, $status) {
            $lockedPickup = Pickup::query()
                ->whereKey($pickup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPickup->status->canTransitionTo($status)) {
                throw new RuntimeException('Không thể chuyển Pickup từ '.$lockedPickup->status->label().' sang '.$status->label().'.');
            }

            $lockedPickup->update(['status' => $status]);

            return $lockedPickup->fresh();
        });
    }
}
