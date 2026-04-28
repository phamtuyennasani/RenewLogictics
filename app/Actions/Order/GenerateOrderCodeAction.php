<?php

namespace App\Actions\Order;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

class GenerateOrderCodeAction
{
    /**
     * Generate order code với DB transaction lock
     * Thread-safe khi nhiều user cùng thao tác
     * Format: AVN{YYMMDD}{NNN}
     */
    public function execute(): string
    {
        $prefix = 'AVN' . now()->format('ymd');

        return DB::transaction(function () use ($prefix) {
            // Lock row cuối cùng có cùng prefix để prevent race condition
            $lastOrder = Order::where('id_bill', 'like', $prefix . '%')
                ->orderByDesc('id_bill')
                ->lockForUpdate()
                ->first();

            if ($lastOrder) {
                $numberPart = (int) substr($lastOrder->id_bill, strlen($prefix));
                $nextNumber = $numberPart + 1;
            } else {
                $nextNumber = 1;
            }

            return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        });
    }
}
