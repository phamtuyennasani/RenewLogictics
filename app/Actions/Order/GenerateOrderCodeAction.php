<?php

namespace App\Actions\Order;

use Illuminate\Support\Facades\DB;

class GenerateOrderCodeAction
{
    /**
     * Generate order code với DB transaction lock
     * Thread-safe khi nhiều user cùng thao tác
     * Format: {ORDER_CODE_PREFIX}{YYMMDD}{NNN}
     */
    public function execute(): string
    {
        $date = now();
        $sequenceDate = $date->toDateString();
        $prefix = config('order.code_prefix', 'BEE') . $date->format('ymd');

        $nextNumber = DB::transaction(function () use ($sequenceDate) {
            $sequence = DB::table('order_sequences')
                ->where('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('order_sequences')->insert([
                    'sequence_date' => $sequenceDate,
                    'current_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $nextNumber = ((int) $sequence->current_number) + 1;

            DB::table('order_sequences')
                ->where('sequence_date', $sequenceDate)
                ->update([
                    'current_number' => $nextNumber,
                    'updated_at' => now(),
                ]);

            return $nextNumber;
        });

        return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
