<?php

use App\Enums\DebtStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCongNoTable();
        $this->ensureCongNoDetailTable();
        $this->ensureCongNoPaymentsTable();
        $this->ensureOrderDebtColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('congno_payments');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'customer_payment_status',
                    'customer_paid_at',
                    'customer_payment_photo',
                    'customer_invoice_ref',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function ensureCongNoTable(): void
    {
        if (! Schema::hasTable('congno')) {
            Schema::create('congno', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('sohoadon')->unique();
                $table->string('sohoadon_thamchieu')->nullable();
                $table->unsignedBigInteger('id_sale')->nullable()->index();
                $table->unsignedBigInteger('id_customer')->nullable()->index();
                $table->unsignedBigInteger('id_ctv')->nullable()->index();
                $table->unsignedBigInteger('id_user')->nullable()->index();
                $table->unsignedBigInteger('id_ketoan')->nullable()->index();
                $table->unsignedBigInteger('id_success')->nullable()->index();
                $table->dateTime('tungay')->nullable()->index();
                $table->dateTime('denngay')->nullable()->index();
                $table->dateTime('ngaytaohoadon')->nullable();
                $table->dateTime('ngaychothoadon')->nullable();
                $table->dateTime('hanthanhtoan')->nullable()->index();
                $table->dateTime('ngaythanhtoan')->nullable();
                $table->unsignedInteger('songaythanhtoan')->default(0);
                $table->string('status')->default(DebtStatusEnum::MOI_TAO->value)->index();
                $table->string('type')->default('customer')->index();
                $table->string('photo')->nullable();
                $table->text('ghichu')->nullable();
                $table->unsignedInteger('total_orders')->default(0);
                $table->decimal('total_weight', 12, 3)->default(0);
                $table->decimal('total_cuocban', 15, 2)->default(0);
                $table->decimal('total_cuocvon', 15, 2)->default(0);
                $table->decimal('total_cuocgoc', 15, 2)->default(0);
                $table->decimal('total_vat', 15, 2)->default(0);
                $table->decimal('total_ppxd', 15, 2)->default(0);
                $table->decimal('total_phuphi', 15, 2)->default(0);
                $table->decimal('total_hoahong', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->timestamps();
            });

            return;
        }

        Schema::table('congno', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'congno', 'uuid', fn () => $table->uuid('uuid')->nullable()->after('id'));
            $this->addColumnIfMissing($table, 'congno', 'sohoadon', fn () => $table->string('sohoadon')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'sohoadon_thamchieu', fn () => $table->string('sohoadon_thamchieu')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'id_sale', fn () => $table->unsignedBigInteger('id_sale')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'id_customer', fn () => $table->unsignedBigInteger('id_customer')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'id_ctv', fn () => $table->unsignedBigInteger('id_ctv')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'id_user', fn () => $table->unsignedBigInteger('id_user')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'id_ketoan', fn () => $table->unsignedBigInteger('id_ketoan')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'id_success', fn () => $table->unsignedBigInteger('id_success')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'tungay', fn () => $table->dateTime('tungay')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'denngay', fn () => $table->dateTime('denngay')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'ngaytaohoadon', fn () => $table->dateTime('ngaytaohoadon')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'ngaychothoadon', fn () => $table->dateTime('ngaychothoadon')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'hanthanhtoan', fn () => $table->dateTime('hanthanhtoan')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno', 'ngaythanhtoan', fn () => $table->dateTime('ngaythanhtoan')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'songaythanhtoan', fn () => $table->unsignedInteger('songaythanhtoan')->default(0));
            $this->addColumnIfMissing($table, 'congno', 'status', fn () => $table->string('status')->default(DebtStatusEnum::MOI_TAO->value)->index());
            $this->addColumnIfMissing($table, 'congno', 'type', fn () => $table->string('type')->default('customer')->index());
            $this->addColumnIfMissing($table, 'congno', 'photo', fn () => $table->string('photo')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'ghichu', fn () => $table->text('ghichu')->nullable());
            $this->addColumnIfMissing($table, 'congno', 'total_orders', fn () => $table->unsignedInteger('total_orders')->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_weight', fn () => $table->decimal('total_weight', 12, 3)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_cuocban', fn () => $table->decimal('total_cuocban', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_cuocvon', fn () => $table->decimal('total_cuocvon', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_cuocgoc', fn () => $table->decimal('total_cuocgoc', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_vat', fn () => $table->decimal('total_vat', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_ppxd', fn () => $table->decimal('total_ppxd', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_phuphi', fn () => $table->decimal('total_phuphi', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'total_hoahong', fn () => $table->decimal('total_hoahong', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno', 'paid_amount', fn () => $table->decimal('paid_amount', 15, 2)->default(0));
        });
    }

    private function ensureCongNoDetailTable(): void
    {
        if (! Schema::hasTable('congno_detail')) {
            Schema::create('congno_detail', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_congno')->index();
                $table->unsignedBigInteger('id_order')->index();
                $table->decimal('weight', 12, 3)->default(0);
                $table->decimal('cuocban', 15, 2)->default(0);
                $table->decimal('cuocvon', 15, 2)->default(0);
                $table->decimal('cuocgoc', 15, 2)->default(0);
                $table->decimal('vat', 15, 2)->default(0);
                $table->decimal('ppxd', 15, 2)->default(0);
                $table->decimal('phuphi', 15, 2)->default(0);
                $table->decimal('hoahong', 15, 2)->default(0);
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->unique(['id_congno', 'id_order'], 'congno_detail_unique_order');
            });

            return;
        }

        Schema::table('congno_detail', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'congno_detail', 'id_congno', fn () => $table->unsignedBigInteger('id_congno')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno_detail', 'id_order', fn () => $table->unsignedBigInteger('id_order')->nullable()->index());
            $this->addColumnIfMissing($table, 'congno_detail', 'weight', fn () => $table->decimal('weight', 12, 3)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'cuocban', fn () => $table->decimal('cuocban', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'cuocvon', fn () => $table->decimal('cuocvon', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'cuocgoc', fn () => $table->decimal('cuocgoc', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'vat', fn () => $table->decimal('vat', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'ppxd', fn () => $table->decimal('ppxd', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'phuphi', fn () => $table->decimal('phuphi', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'hoahong', fn () => $table->decimal('hoahong', 15, 2)->default(0));
            $this->addColumnIfMissing($table, 'congno_detail', 'snapshot', fn () => $table->json('snapshot')->nullable());
        });
    }

    private function ensureCongNoPaymentsTable(): void
    {
        if (Schema::hasTable('congno_payments')) {
            return;
        }

        Schema::create('congno_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_congno')->index();
            $table->unsignedBigInteger('id_user')->nullable()->index();
            $table->decimal('amount', 15, 2);
            $table->dateTime('paid_at')->nullable()->index();
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->string('photo')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    private function ensureOrderDebtColumns(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'orders', 'customer_payment_status', fn () => $table->string('customer_payment_status')->nullable()->index());
            $this->addColumnIfMissing($table, 'orders', 'customer_paid_at', fn () => $table->dateTime('customer_paid_at')->nullable()->index());
            $this->addColumnIfMissing($table, 'orders', 'customer_payment_photo', fn () => $table->string('customer_payment_photo')->nullable());
            $this->addColumnIfMissing($table, 'orders', 'customer_invoice_ref', fn () => $table->string('customer_invoice_ref')->nullable());
        });
    }

    private function addColumnIfMissing(Blueprint $table, string $tableName, string $column, callable $callback): void
    {
        if (! Schema::hasColumn($tableName, $column)) {
            $callback($table);
        }
    }
};
