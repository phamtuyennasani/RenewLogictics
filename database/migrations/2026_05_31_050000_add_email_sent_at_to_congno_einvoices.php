<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congno_einvoices', function (Blueprint $table) {
            if (! Schema::hasColumn('congno_einvoices', 'email_sent_at')) {
                $table->timestamp('email_sent_at')->nullable()->after('files_downloaded_at')->comment('Thời điểm gửi email hóa đơn cho khách');
            }
        });
    }

    public function down(): void
    {
        Schema::table('congno_einvoices', function (Blueprint $table) {
            if (Schema::hasColumn('congno_einvoices', 'email_sent_at')) {
                $table->dropColumn('email_sent_at');
            }
        });
    }
};
