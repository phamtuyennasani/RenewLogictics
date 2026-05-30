<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setting')) {
            return;
        }

        Schema::create('setting', function (Blueprint $table) {
            $table->id();
            $table->string('namevi')->nullable();
            $table->json('options')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        // Tạo row mặc định để các service có thể đọc
        DB::table('setting')->insert([
            'namevi' => 'Cấu hình hệ thống',
            'options' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('setting');
    }
};
