<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_package_daily')) {
            return;
        }

        if (! Schema::hasColumn('order_package_daily', 'id_order')) {
            return;
        }

        $ordersTable = DB::getTablePrefix() . 'orders';
        $packagesTable = DB::getTablePrefix() . 'order_package_daily';
        $constraint = 'order_package_daily_id_order_foreign';

        DB::statement("ALTER TABLE `{$packagesTable}` ENGINE=InnoDB");
        DB::statement("ALTER TABLE `{$packagesTable}` MODIFY `id_order` BIGINT UNSIGNED NULL");
        DB::statement("
            UPDATE `{$packagesTable}` p
            LEFT JOIN `{$ordersTable}` o ON o.`id` = p.`id_order`
            SET p.`id_order` = NULL
            WHERE p.`id_order` IS NOT NULL
                AND o.`id` IS NULL
        ");

        $foreignKeyExists = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = 'id_order'
                AND REFERENCED_TABLE_NAME = ?
        ", [$packagesTable, $ordersTable]))->isNotEmpty();

        if (! $foreignKeyExists) {
            DB::statement("
                ALTER TABLE `{$packagesTable}`
                ADD CONSTRAINT `{$constraint}`
                FOREIGN KEY (`id_order`)
                REFERENCES `{$ordersTable}` (`id`)
                ON DELETE CASCADE
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_package_daily')) {
            return;
        }

        $packagesTable = DB::getTablePrefix() . 'order_package_daily';
        $constraint = 'order_package_daily_id_order_foreign';

        $foreignKeyExists = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
        ", [$packagesTable, $constraint]))->isNotEmpty();

        if ($foreignKeyExists) {
            DB::statement("ALTER TABLE `{$packagesTable}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
