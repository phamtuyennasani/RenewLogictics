<?php

use App\Enums\DebtStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureCongNoDailyTable();
        $this->ensureCongNoDailyDetailTable();
        $this->ensureCongNoDailyPaymentsTable();
        $this->ensureOrderAgencyColumns();
    }

    public function down(): void
    {
        Schema::dropIfExists('congno_daily_payments');
        Schema::dropIfExists('congno_daily_detail');
        Schema::dropIfExists('congno_daily');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                foreach ([
                    'agency_payment_status',
                    'agency_paid_at',
                    'agency_payment_photo',
                    'agency_invoice_ref',
                ] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function ensureCongNoDailyTable(): void
    {
        if (! Schema::hasTable('congno_daily')) {
            Schema::create('congno_daily', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('sohoadon')->unique();
                $table->string('sohoadon_thamchieu')->nullable();
                $table->unsignedBigInteger('id_daily')->index();
                $table->unsignedBigInteger('id_sale')->nullable()->index();
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
                $table->string('photo')->nullable();
                $table->text('ghichu')->nullable();
                $table->unsignedInteger('total_orders')->default(0);
                $table->decimal('total_weight', 12, 3)->default(0);
                $table->decimal('total_cuocvon', 15, 2)->default(0);
                $table->decimal('total_cuocgoc', 15, 2)->default(0);
                $table->decimal('total_cuocban', 15, 2)->default(0);
                $table->decimal('total_vat', 15, 2)->default(0);
                $table->decimal('total_ppxd', 15, 2)->default(0);
                $table->decimal('total_phuphi', 15, 2)->default(0);
                $table->decimal('total_hoahong', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    private function ensureCongNoDailyDetailTable(): void
    {
        if (! Schema::hasTable('congno_daily_detail')) {
            Schema::create('congno_daily_detail', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_congno_daily')->index();
                $table->unsignedBigInteger('id_order')->index();
                $table->decimal('weight', 12, 3)->default(0);
                $table->decimal('cuocvon', 15, 2)->default(0);
                $table->decimal('cuocgoc', 15, 2)->default(0);
                $table->decimal('cuocban', 15, 2)->default(0);
                $table->decimal('vat', 15, 2)->default(0);
                $table->decimal('ppxd', 15, 2)->default(0);
                $table->decimal('phuphi', 15, 2)->default(0);
                $table->decimal('hoahong', 15, 2)->default(0);
                $table->json('snapshot')->nullable();
                $table->timestamps();
                $table->unique(['id_congno_daily', 'id_order'], 'congno_daily_detail_unique_order');
            });
        }
    }

    private function ensureCongNoDailyPaymentsTable(): void
    {
        if (! Schema::hasTable('congno_daily_payments')) {
            Schema::create('congno_daily_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_congno_daily')->index();
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
    }

    private function ensureOrderAgencyColumns(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'agency_payment_status')) {
                $table->string('agency_payment_status')->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'agency_paid_at')) {
                $table->dateTime('agency_paid_at')->nullable()->index();
            }
            if (! Schema::hasColumn('orders', 'agency_payment_photo')) {
                $table->string('agency_payment_photo')->nullable();
            }
            if (! Schema::hasColumn('orders', 'agency_invoice_ref')) {
                $table->string('agency_invoice_ref')->nullable();
            }
        });
    }
};
