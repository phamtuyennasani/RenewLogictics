<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('congno_einvoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Liên kết công nợ
            $table->unsignedBigInteger('id_congno')->index();
            $table->unsignedInteger('id_user')->nullable()->index()->comment('Người tạo');

            // Provider info
            $table->string('provider')->index()->comment('sepay, vnpt, viettel...');
            $table->string('provider_account_id')->nullable()->comment('Tài khoản provider');

            // Thông tin hóa đơn
            $table->string('reference')->index()->comment('Mã tham chiếu nội bộ');
            $table->string('template_code')->nullable()->comment('Mã mẫu hóa đơn');
            $table->string('invoice_series')->nullable()->comment('Ký hiệu hóa đơn');
            $table->string('invoice_number')->nullable()->index()->comment('Số hóa đơn (sau khi issue)');
            $table->date('issued_date')->nullable()->comment('Ngày phát hành');

            // Tracking
            $table->string('tracking_code')->nullable()->index()->comment('Mã tracking từ provider');
            $table->string('provider_reference_code')->nullable()->index()->comment('Reference code từ provider');
            $table->text('tracking_url')->nullable();
            $table->text('invoice_url')->nullable()->comment('URL xem/download hóa đơn');

            // Số tiền
            $table->decimal('amount', 15, 2)->default(0)->comment('Tổng tiền trên hóa đơn');
            $table->string('currency', 10)->default('VND');

            // Trạng thái
            $table->string('status')->default('pending')->index()->comment('pending, success, failed, cancelled');

            // Thông tin buyer
            $table->json('buyer')->nullable()->comment('Thông tin người mua');
            $table->json('items')->nullable()->comment('Danh sách hàng hóa/dịch vụ');

            // Metadata
            $table->json('provider_payload')->nullable()->comment('Response thô từ provider');
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();

            // Timestamps
            $table->timestamp('issued_at')->nullable()->comment('Thời điểm issue thành công');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('id_congno')->references('id')->on('congno')->cascadeOnDelete();
            $table->foreign('id_user')->references('id')->on('user')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('congno_einvoices');
    }
};
