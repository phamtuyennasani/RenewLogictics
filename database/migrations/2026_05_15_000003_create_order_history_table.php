<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasTable('order_history')) {
            Schema::create('order_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_order')->nullable()->constrained('orders')->cascadeOnDelete();
                $table->foreignId('id_user')->nullable()->constrained('user')->nullOnDelete();
                $table->string('action')->nullable();
                $table->longText('content')->nullable();
                $table->timestamps();
            });

            return;
        }

        Schema::table('order_history', function (Blueprint $table) {
            if (! Schema::hasColumn('order_history', 'id_order')) {
                $table->unsignedBigInteger('id_order')->nullable()->after('id');
            }

            if (! Schema::hasColumn('order_history', 'id_user')) {
                $table->unsignedBigInteger('id_user')->nullable()->after('id_order');
            }

            if (! Schema::hasColumn('order_history', 'action')) {
                $table->string('action')->nullable()->after('id_user');
            }

            if (! Schema::hasColumn('order_history', 'content')) {
                $table->longText('content')->nullable()->after('action');
            }

            if (! Schema::hasColumn('order_history', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }

            if (! Schema::hasColumn('order_history', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep edit history intact on rollback; older deployments may already have this legacy table.
    }
};
