<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('invoices')) {
            return;
        }

        if (! Schema::hasColumn('invoices', 'id_order')) {
            return;
        }

        $ordersTable = DB::getTablePrefix() . 'orders';
        $invoicesTable = DB::getTablePrefix() . 'invoices';
        $constraint = 'invoices_id_order_foreign';

        DB::statement("ALTER TABLE `{$invoicesTable}` ENGINE=InnoDB");
        DB::statement("ALTER TABLE `{$invoicesTable}` MODIFY `id_order` BIGINT UNSIGNED NULL");
        DB::statement("
            UPDATE `{$invoicesTable}` i
            LEFT JOIN `{$ordersTable}` o ON o.`id` = i.`id_order`
            SET i.`id_order` = NULL
            WHERE i.`id_order` IS NOT NULL
                AND o.`id` IS NULL
        ");

        $foreignKeyExists = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND COLUMN_NAME = 'id_order'
                AND REFERENCED_TABLE_NAME = ?
        ", [$invoicesTable, $ordersTable]))->isNotEmpty();

        if (! $foreignKeyExists) {
            DB::statement("
                ALTER TABLE `{$invoicesTable}`
                ADD CONSTRAINT `{$constraint}`
                FOREIGN KEY (`id_order`)
                REFERENCES `{$ordersTable}` (`id`)
                ON DELETE CASCADE
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        $invoicesTable = DB::getTablePrefix() . 'invoices';
        $constraint = 'invoices_id_order_foreign';

        $foreignKeyExists = collect(DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
        ", [$invoicesTable, $constraint]))->isNotEmpty();

        if ($foreignKeyExists) {
            DB::statement("ALTER TABLE `{$invoicesTable}` DROP FOREIGN KEY `{$constraint}`");
        }
    }
};
