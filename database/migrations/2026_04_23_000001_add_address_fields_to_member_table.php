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
        Schema::table('member', function (Blueprint $table) {
            if (!Schema::hasColumn('member', 'id_province')) {
                $table->unsignedBigInteger('id_province')->nullable()->after('id_ctv');
            }
            if (!Schema::hasColumn('member', 'id_ward')) {
                $table->unsignedBigInteger('id_ward')->nullable()->after('id_province');
            }
            if (!Schema::hasColumn('member', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable()->after('id_ward');
            }
            if (!Schema::hasColumn('member', 'state')) {
                $table->string('state')->nullable()->after('country_id');
            }
            if (!Schema::hasColumn('member', 'cities')) {
                $table->string('cities')->nullable()->after('state');
            }
            if (!Schema::hasColumn('member', 'postcode')) {
                $table->string('postcode', 20)->nullable()->after('cities');
            }
            if (!Schema::hasColumn('member', 'id_sender')) {
                $table->unsignedBigInteger('id_sender')->nullable()->after('id_ctv');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member', function (Blueprint $table) {
            $table->dropColumn(['id_province', 'id_ward', 'country_id', 'state', 'cities', 'postcode', 'id_sender']);
        });
    }
};
