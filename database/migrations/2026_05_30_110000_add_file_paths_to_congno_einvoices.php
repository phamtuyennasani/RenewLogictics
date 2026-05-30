<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('congno_einvoices', function (Blueprint $table) {
            if (! Schema::hasColumn('congno_einvoices', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('invoice_url')->comment('Path file PDF lưu local');
            }
            if (! Schema::hasColumn('congno_einvoices', 'xml_path')) {
                $table->string('xml_path')->nullable()->after('pdf_path')->comment('Path file XML lưu local');
            }
            if (! Schema::hasColumn('congno_einvoices', 'files_downloaded_at')) {
                $table->timestamp('files_downloaded_at')->nullable()->after('xml_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('congno_einvoices', function (Blueprint $table) {
            foreach (['pdf_path', 'xml_path', 'files_downloaded_at'] as $column) {
                if (Schema::hasColumn('congno_einvoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
