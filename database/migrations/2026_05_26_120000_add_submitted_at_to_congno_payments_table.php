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
            if (! Schema::hasColumn('congno_payments', 'submitted_at')) {
                $table->dateTime('submitted_at')->nullable()->after('paid_at')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('congno_payments', function (Blueprint $table) {
            if (Schema::hasColumn('congno_payments', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
        });
    }
};
