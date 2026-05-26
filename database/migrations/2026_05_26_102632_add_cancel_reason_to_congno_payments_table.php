<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('congno_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('congno_payments', 'cancel_reason')) {
                $table->text('cancel_reason')->nullable()->after('id_cancelled_by');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('congno_payments', function (Blueprint $table) {
            if (Schema::hasColumn('congno_payments', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
        });
    }
};
