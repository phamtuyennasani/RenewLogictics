<?php

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Dựng schema SQLite in-memory cho test API mobile.
 *
 * Dự án dùng DB legacy (không có migration cho bảng `user` và bảng Spatie),
 * nên test tự tạo schema giống pattern của ThirdPartyOrderTrackingApiTest.
 */
trait BuildsMobileSchema
{
    protected function buildMobileSchema(): void
    {
        $this->dropMobileSchema();

        // --- User ---
        Schema::create('user', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->string('fullname')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedInteger('numb')->nullable();
            $table->string('code')->nullable();
            $table->json('options')->nullable();
            $table->unsignedBigInteger('id_sale')->nullable();
            $table->string('zalo_user_id', 100)->nullable()->unique();
            $table->timestamp('zalo_linked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // --- Sanctum ---
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // --- Spatie permission ---
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        // --- Orders + packages + history (cho OPS scan/receive) ---
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('id_bill')->unique();
            $table->unsignedBigInteger('id_sale')->nullable();
            $table->unsignedBigInteger('id_customer')->nullable();
            $table->unsignedBigInteger('id_cs')->nullable();
            $table->unsignedBigInteger('id_ops')->nullable();
            $table->unsignedBigInteger('id_create')->nullable();
            $table->string('bill_status');
            $table->timestamp('ngaynhanhang')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('mathamchieu')->nullable();
            $table->boolean('lock_order')->default(false);
            $table->json('sender')->nullable();
            $table->json('receiver')->nullable();
            $table->json('service')->nullable();
            $table->timestamps();
        });

        Schema::create('order_package', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->decimal('c_weight', 12, 3)->default(0);
            $table->decimal('row_c_weight', 12, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('order_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('id_order')->constrained('orders')->cascadeOnDelete();
            $table->unsignedInteger('id_user')->nullable();
            $table->string('action')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('thoigian')->nullable();
            $table->string('trangthai')->nullable();
            $table->string('diadiem')->nullable();
            $table->text('ghichu')->nullable();
            $table->boolean('main')->default(false);
            $table->timestamps();
        });

        // --- Pickup + pickup_orders (cho shipper) ---
        Schema::create('pickup', function (Blueprint $table): void {
            $table->id();
            $table->string('ma_pickup')->unique();
            $table->unsignedInteger('id_user')->nullable();
            $table->unsignedInteger('id_shipper')->nullable();
            $table->timestamp('ngay_tao')->nullable();
            $table->decimal('total_weight', 12, 2)->default(0);
            $table->decimal('total_c_weight', 12, 2)->default(0);
            $table->string('status')->default('moi_tao_pickup');
            $table->unsignedInteger('numb')->default(0);
            $table->text('note')->nullable();
            $table->json('options')->nullable();
            $table->json('info_pickup')->nullable();
            $table->json('info_khachhang')->nullable();
            $table->timestamps();
        });

        Schema::create('pickup_orders', function (Blueprint $table): void {
            $table->id();
            $table->integer('pickup_id');
            $table->unsignedBigInteger('id_order');
            $table->unsignedInteger('added_by')->nullable();
            $table->timestamps();
        });

        // --- news (RecordTrackingHistoryAction tham chiếu chi nhánh) ---
        Schema::create('news', function (Blueprint $table): void {
            $table->id();
            $table->string('namevi');
            $table->string('type')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // --- activity_logs (shipper update status ghi audit log) ---
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('title');
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // --- user_device_tokens (push notification cho shipper/OPS) ---
        Schema::create('user_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('token', 512);
            $table->string('platform')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    protected function dropMobileSchema(): void
    {
        foreach ([
            'user_device_tokens', 'activity_logs',
            'pickup_orders', 'pickup', 'order_history', 'order_package', 'orders',
            'role_has_permissions', 'model_has_permissions', 'model_has_roles',
            'permissions', 'roles', 'personal_access_tokens', 'user', 'news',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Tạo user kèm role (tạo role nếu chưa có).
     *
     * @param  array<int, string>  $roles
     * @param  array<string, mixed>  $attributes
     */
    protected function makeUser(array $roles = [], array $attributes = []): User
    {
        $user = User::query()->create(array_merge([
            'username' => 'user'.uniqid(),
            'password' => Hash::make('secret'),
            'fullname' => 'Test User',
            'phone' => '0900000000',
            'email' => 'test@example.com',
            'status' => true,
        ], $attributes));

        foreach ($roles as $role) {
            Role::findOrCreate($role, 'web');
        }

        if ($roles !== []) {
            $user->assignRole($roles);
        }

        return $user;
    }
}
