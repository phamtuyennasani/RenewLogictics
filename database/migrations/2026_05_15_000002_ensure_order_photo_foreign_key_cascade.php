<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'order_photo` ENGINE=InnoDB');
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'order_photo` MODIFY `id_order` BIGINT UNSIGNED NOT NULL');
            DB::statement(
                'DELETE p FROM `'.DB::getTablePrefix().'order_photo` p
                 LEFT JOIN `'.DB::getTablePrefix().'orders` o ON o.id = p.id_order
                 WHERE o.id IS NULL'
            );
        }

        if (! $this->foreignKeyExists()) {
            Schema::table('order_photo', function (Blueprint $table) {
                $table->foreign('id_order', 'order_photo_id_order_foreign')
                    ->references('id')
                    ->on('orders')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists()) {
            Schema::table('order_photo', function (Blueprint $table) {
                $table->dropForeign('order_photo_id_order_foreign');
            });
        }
    }

    private function foreignKeyExists(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $result = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?',
            [DB::getTablePrefix().'order_photo', 'order_photo_id_order_foreign']
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
