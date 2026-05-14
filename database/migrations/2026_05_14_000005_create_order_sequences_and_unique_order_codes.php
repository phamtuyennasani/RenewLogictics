<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->date('sequence_date')->primary();
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('orders')) {
            $this->backfillSequences();

            Schema::table('orders', function (Blueprint $table) {
                $table->unique('id_bill', 'orders_id_bill_unique');
                $table->unique('uuid', 'orders_uuid_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_id_bill_unique');
                $table->dropUnique('orders_uuid_unique');
            });
        }

        Schema::dropIfExists('order_sequences');
    }

    protected function backfillSequences(): void
    {
        $ordersTable = DB::getTablePrefix() . 'orders';
        $sequencesTable = DB::getTablePrefix() . 'order_sequences';
        $prefix = preg_quote((string) config('order.code_prefix', 'BEE'), '/');
        $prefixLength = mb_strlen((string) config('order.code_prefix', 'BEE'));
        $dateStart = $prefixLength + 1;
        $numberStart = $prefixLength + 7;

        $rows = DB::select("
            SELECT
                STR_TO_DATE(SUBSTRING(`id_bill`, {$dateStart}, 6), '%y%m%d') AS sequence_date,
                MAX(CAST(SUBSTRING(`id_bill`, {$numberStart}) AS UNSIGNED)) AS current_number
            FROM `{$ordersTable}`
            WHERE `id_bill` REGEXP '^{$prefix}[0-9]{6}[0-9]+$'
            GROUP BY sequence_date
        ");

        foreach ($rows as $row) {
            if (! $row->sequence_date || ! $row->current_number) {
                continue;
            }

            DB::table($sequencesTable)->updateOrInsert(
                ['sequence_date' => $row->sequence_date],
                [
                    'current_number' => (int) $row->current_number,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
};
