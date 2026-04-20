<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            // Primary key giữ nguyên ID từ JSON — dùng cho FK sau này
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('state_code')->nullable();
            $table->unsignedBigInteger('state_id');
            $table->unsignedBigInteger('country_id');
            $table->char('country_code', 3)->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('state_id')
                  ->references('id')
                  ->on('states')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};
