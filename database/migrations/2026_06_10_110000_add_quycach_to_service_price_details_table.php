<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_price_details', function (Blueprint $table) {
            if (! Schema::hasColumn('service_price_details', 'quycach')) {
                $table->string('quycach', 20)->default('DON_GIA')->after('service_price_list_id');
            }
        });

        Schema::table('service_price_details', function (Blueprint $table) {
            if (! $this->indexExists('service_price_details_quycach_weight_unique')) {
                $table->unique(['service_price_list_id', 'quycach', 'weight_from', 'weight_to'], 'service_price_details_quycach_weight_unique');
            }

            if ($this->indexExists('service_price_details_weight_unique')) {
                $table->dropUnique('service_price_details_weight_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_price_details', function (Blueprint $table) {
            if (! $this->indexExists('service_price_details_weight_unique')) {
                $table->unique(['service_price_list_id', 'weight_from', 'weight_to'], 'service_price_details_weight_unique');
            }

            if ($this->indexExists('service_price_details_quycach_weight_unique')) {
                $table->dropUnique('service_price_details_quycach_weight_unique');
            }

            if (Schema::hasColumn('service_price_details', 'quycach')) {
                $table->dropColumn('quycach');
            }
        });
    }

    private function indexExists(string $indexName): bool
    {
        $result = DB::selectOne(
            'select count(*) as aggregate from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
            [DB::getTablePrefix().'service_price_details', $indexName],
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
