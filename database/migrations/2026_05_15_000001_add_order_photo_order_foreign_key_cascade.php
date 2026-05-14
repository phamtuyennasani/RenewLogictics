<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignKeys('order_photo', 'id_order');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'order_photo` ENGINE=InnoDB');
            DB::statement('ALTER TABLE `'.DB::getTablePrefix().'order_photo` MODIFY `id_order` BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('order_photo', function (Blueprint $table) {
            $table->index('id_order', 'order_photo_id_order_index');
            $table->foreign('id_order', 'order_photo_id_order_foreign')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_photo', function (Blueprint $table) {
            $table->dropForeign('order_photo_id_order_foreign');
            $table->dropIndex('order_photo_id_order_index');
        });
    }

    private function dropForeignKeys(string $table, string $column): void
    {
        $database = DB::getDatabaseName();
        $prefixedTable = DB::getTablePrefix().$table;

        $keys = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $prefixedTable, $column]
        );

        foreach ($keys as $key) {
            Schema::table($table, function (Blueprint $table) use ($key) {
                $table->dropForeign($key->CONSTRAINT_NAME);
            });
        }
    }
};
