<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            // Primary key giữ nguyên ID từ JSON — dùng cho FK với states/cities sau này
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->char('iso2', 2)->nullable();
            $table->char('iso3', 3)->nullable();
            $table->string('phonecode', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
