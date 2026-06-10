<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_price_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('service_id');
            $table->foreign('service_id')->references('id')->on('news')->cascadeOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('user')->nullOnDelete();
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('user')->nullOnDelete();
            $table->timestamps();

            $table->index(['service_id', 'name'], 'service_price_lists_service_name_index');
        });

        Schema::create('service_price_list_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_price_list_id')->constrained('service_price_lists')->cascadeOnDelete();
            $table->unsignedBigInteger('country_id');
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_price_list_id', 'country_id'], 'service_price_list_country_unique');
            $table->index('country_id', 'service_price_list_countries_country_index');
        });

        Schema::create('service_price_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_price_list_id')->constrained('service_price_lists')->cascadeOnDelete();
            $table->string('quycach', 20)->default('DON_GIA');
            $table->decimal('weight_from', 12, 2);
            $table->decimal('weight_to', 12, 2);
            $table->decimal('sale_price', 14, 2)->default(0);
            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('base_price', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['service_price_list_id', 'quycach', 'weight_from', 'weight_to'], 'service_price_details_quycach_weight_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_price_details');
        Schema::dropIfExists('service_price_list_countries');
        Schema::dropIfExists('service_price_lists');
    }
};
