<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng audit log CHUNG cho các hành động nhạy cảm toàn hệ thống
 * (xóa đơn, xóa công nợ, xóa hóa đơn...). Polymorphic theo subject
 * và lưu snapshot dữ liệu trước khi xóa để truy vết về sau.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            // Đối tượng bị tác động (polymorphic) — id có thể null sau khi xóa cứng.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // Người thực hiện.
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            // Hành động: order.delete, congno.delete, invoice.delete...
            $table->string('action')->index();
            $table->string('title');
            $table->text('note')->nullable();
            // Snapshot dữ liệu chính của đối tượng trước khi xóa.
            $table->json('snapshot')->nullable();
            // Bối cảnh request (ip, route...).
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
