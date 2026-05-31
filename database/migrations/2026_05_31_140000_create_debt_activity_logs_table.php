<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('debt_activity_logs')) {
            return;
        }

        Schema::create('debt_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('congno_id')->nullable()->index();
            $table->unsignedBigInteger('congno_daily_id')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('title');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_activity_logs');
    }
};
