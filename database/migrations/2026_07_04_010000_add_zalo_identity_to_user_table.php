<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            if (! Schema::hasColumn('user', 'zalo_user_id')) {
                $table->string('zalo_user_id', 100)->nullable()->after('id_sale');
                $table->unique('zalo_user_id', 'user_zalo_user_id_unique');
            }

            if (! Schema::hasColumn('user', 'zalo_linked_at')) {
                $table->timestamp('zalo_linked_at')->nullable()->after('zalo_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            if (Schema::hasColumn('user', 'zalo_user_id')) {
                $table->dropUnique('user_zalo_user_id_unique');
                $table->dropColumn('zalo_user_id');
            }

            if (Schema::hasColumn('user', 'zalo_linked_at')) {
                $table->dropColumn('zalo_linked_at');
            }
        });
    }
};
