<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->extendCongNoPayments();
        $this->extendCongNoDailyPayments();
        $this->backfillExistingPayments();
    }

    public function down(): void
    {
        $this->dropAddedColumns('congno_payments', [
            'loai_hoa_don', 'ma_hoa_don', 'status',
            'id_ketoan', 'ngay_duyet',
            'qr_url', 'qr_generated_at', 'qr_expires_at',
            'qr_payment_code', 'sepay_transaction_id',
            'cancelled_at', 'id_cancelled_by',
        ]);

        $this->dropAddedColumns('congno_daily_payments', [
            'loai_hoa_don', 'ma_hoa_don', 'status',
            'id_ketoan', 'ngay_duyet',
            'cancelled_at', 'id_cancelled_by',
        ]);
    }

    private function extendCongNoPayments(): void
    {
        if (! Schema::hasTable('congno_payments')) {
            return;
        }

        Schema::table('congno_payments', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'congno_payments', 'loai_hoa_don',
                fn () => $table->string('loai_hoa_don', 8)->default('thu')->index());

            $this->addColumnIfMissing($table, 'congno_payments', 'ma_hoa_don',
                fn () => $table->string('ma_hoa_don')->nullable()->unique());

            $this->addColumnIfMissing($table, 'congno_payments', 'status',
                fn () => $table->string('status', 32)->default('moi_tao')->index());

            $this->addColumnIfMissing($table, 'congno_payments', 'id_ketoan',
                fn () => $table->unsignedBigInteger('id_ketoan')->nullable()->index());

            $this->addColumnIfMissing($table, 'congno_payments', 'ngay_duyet',
                fn () => $table->dateTime('ngay_duyet')->nullable());

            $this->addColumnIfMissing($table, 'congno_payments', 'qr_url',
                fn () => $table->text('qr_url')->nullable());

            $this->addColumnIfMissing($table, 'congno_payments', 'qr_generated_at',
                fn () => $table->dateTime('qr_generated_at')->nullable()->index());

            $this->addColumnIfMissing($table, 'congno_payments', 'qr_expires_at',
                fn () => $table->dateTime('qr_expires_at')->nullable());

            $this->addColumnIfMissing($table, 'congno_payments', 'qr_payment_code',
                fn () => $table->string('qr_payment_code')->nullable()->unique());

            $this->addColumnIfMissing($table, 'congno_payments', 'sepay_transaction_id',
                fn () => $table->string('sepay_transaction_id')->nullable()->index());

            $this->addColumnIfMissing($table, 'congno_payments', 'cancelled_at',
                fn () => $table->dateTime('cancelled_at')->nullable());

            $this->addColumnIfMissing($table, 'congno_payments', 'id_cancelled_by',
                fn () => $table->unsignedBigInteger('id_cancelled_by')->nullable()->index());
        });
    }

    private function extendCongNoDailyPayments(): void
    {
        if (! Schema::hasTable('congno_daily_payments')) {
            return;
        }

        Schema::table('congno_daily_payments', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'congno_daily_payments', 'loai_hoa_don',
                fn () => $table->string('loai_hoa_don', 8)->default('chi')->index());

            $this->addColumnIfMissing($table, 'congno_daily_payments', 'ma_hoa_don',
                fn () => $table->string('ma_hoa_don')->nullable()->unique());

            $this->addColumnIfMissing($table, 'congno_daily_payments', 'status',
                fn () => $table->string('status', 32)->default('moi_tao')->index());

            $this->addColumnIfMissing($table, 'congno_daily_payments', 'id_ketoan',
                fn () => $table->unsignedBigInteger('id_ketoan')->nullable()->index());

            $this->addColumnIfMissing($table, 'congno_daily_payments', 'ngay_duyet',
                fn () => $table->dateTime('ngay_duyet')->nullable());

            $this->addColumnIfMissing($table, 'congno_daily_payments', 'cancelled_at',
                fn () => $table->dateTime('cancelled_at')->nullable());

            $this->addColumnIfMissing($table, 'congno_daily_payments', 'id_cancelled_by',
                fn () => $table->unsignedBigInteger('id_cancelled_by')->nullable()->index());
        });
    }

    private function backfillExistingPayments(): void
    {
        if (Schema::hasTable('congno_payments')) {
            // All old payments predate the invoice system and were already completed
            DB::table('congno_payments')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update([
                    'status' => 'da_thanh_toan',
                    'loai_hoa_don' => 'thu',
                ]);

            DB::table('congno_payments')
                ->whereNull('ma_hoa_don')
                ->orderBy('id')
                ->get(['id', 'created_at'])
                ->each(function ($row) {
                    $date = $row->created_at
                        ? date('Ymd', strtotime($row->created_at))
                        : date('Ymd');
                    DB::table('congno_payments')
                        ->where('id', $row->id)
                        ->update([
                            'ma_hoa_don' => sprintf('HD-TH-%s-LEG%05d', $date, $row->id),
                        ]);
                });
        }

        if (Schema::hasTable('congno_daily_payments')) {
            DB::table('congno_daily_payments')
                ->whereNull('status')
                ->orWhere('status', '')
                ->update([
                    'status' => 'da_thanh_toan',
                    'loai_hoa_don' => 'chi',
                ]);

            DB::table('congno_daily_payments')
                ->whereNull('ma_hoa_don')
                ->orderBy('id')
                ->get(['id', 'created_at'])
                ->each(function ($row) {
                    $date = $row->created_at
                        ? date('Ymd', strtotime($row->created_at))
                        : date('Ymd');
                    DB::table('congno_daily_payments')
                        ->where('id', $row->id)
                        ->update([
                            'ma_hoa_don' => sprintf('HD-CH-%s-LEG%05d', $date, $row->id),
                        ]);
                });
        }
    }

    private function addColumnIfMissing(Blueprint $table, string $tableName, string $column, callable $callback): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            $callback($table);
        }
    }

    private function dropAddedColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $columns) {
            foreach ($columns as $column) {
                if (Schema::hasColumn($tableName, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
