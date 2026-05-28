<?php

namespace App\Actions\ShipmentLoad;

use App\Models\ShipmentLoad;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecordShipmentLoadHistoryAction
{
    public static function execute(
        ShipmentLoad $load,
        CarbonInterface $time,
        string $location,
        string $status,
        ?string $note = null,
        ?int $userId = null,
    ): void {
        DB::transaction(function () use ($load, $time, $location, $status, $note, $userId) {
            $lockedLoad = ShipmentLoad::query()
                ->whereKey($load->id)
                ->lockForUpdate()
                ->firstOrFail();

            $history = $lockedLoad->histories()->create([
                'id_user' => $userId ?? auth()->id(),
                'thoigian' => $time,
                'diadiem' => $location,
                'trangthai' => $status,
                'ghichu' => $note,
            ]);

        });
    }
}

