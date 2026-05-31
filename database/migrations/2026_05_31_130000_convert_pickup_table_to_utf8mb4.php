<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pickup')) {
            return;
        }

        $table = str_replace('`', '``', DB::connection()->getTablePrefix().'pickup');

        DB::statement("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        // Do not convert back to latin1 because that can destroy Vietnamese text.
    }
};
