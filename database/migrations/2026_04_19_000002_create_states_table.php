<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('states', function (Blueprint $table) {
            // Primary key giữ nguyên ID từ JSON — dùng cho FK với cities sau này
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('country_id');
            $table->char('country_code', 3)->nullable();
            $table->string('iso2')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('country_id')
                  ->references('id')
                  ->on('countries')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
    }
};
