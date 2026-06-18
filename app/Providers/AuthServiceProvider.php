<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Gate for assigning sale to orders
        Gate::define('assign-sale', function ($user) {
            return $user->hasAnyRole(['admin', 'cs']);
        });

        // Gate for orders management
        Gate::define('orders.index', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale', 'ctv', 'manager', 'ketoan', 'ops']);
        });

        // Gate for creating orders
        Gate::define('orders.create', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale', 'ctv']);
        });

        // Gate for pickups — creator: admin, manager, ops, cs
        // CS: tạo pickup, chọn OPS, sửa ở trạng thái "Mới tạo", KHÔNG chọn shipper
        Gate::define('pickups.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ops', 'shipper', 'sale', 'cs', 'ctv']);
        });
        Gate::define('pickups.create', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ops', 'cs']);
        });
        Gate::define('pickups.update', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ops', 'cs']);
        });

        // Gate for scan
        Gate::define('scan', function ($user) {
            return $user->hasAnyRole(['admin', 'ops']);
        });

        // Gate for packages — CS thao tác đầy đủ như admin/manager nhưng KHÔNG
        // được xóa tải; OPS không tham gia quản lý tải hàng nữa.
        Gate::define('packages.view', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs']);
        });
        Gate::define('packages.create', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs']);
        });
        Gate::define('packages.update', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs']);
        });
        Gate::define('packages.delete', function ($user) {
            return $user->hasAnyRole(['admin', 'manager']);
        });
        Gate::define('packages.scan', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for congno
        Gate::define('congno.index', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan', 'manager', 'sale', 'ctv']);
        });

        Gate::define('invoice.index', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan', 'manager', 'sale']);
        });

        Gate::define('congno.daily', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan', 'manager']);
        });

        // Route công nợ đại lý dùng `can:congno_daily.view`; sale không được cấp.
        Gate::define('congno_daily.view', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan', 'manager']);
        });

        // Gate for dashboard
        Gate::define('dashboard', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ketoan', 'sale', 'ctv', 'cs', 'ops']);
        });

        // Gate for customers — CS chỉ xem/tạo/sửa, KHÔNG xóa (chỉ admin)
        Gate::define('customers.index', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale']);
        });
        Gate::define('customers.create', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale']);
        });
        Gate::define('customers.update', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale']);
        });
        Gate::define('customers.delete', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for sender/receiver — CS chỉ xem/tạo/sửa, KHÔNG xóa (chỉ admin)
        Gate::define('sender.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv']);
        });
        Gate::define('sender.create', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv']);
        });
        Gate::define('sender.update', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv']);
        });
        Gate::define('sender.delete', function ($user) {
            return $user->hasRole('admin');
        });

        Gate::define('receiver.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv']);
        });
        Gate::define('receiver.create', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv']);
        });
        Gate::define('receiver.update', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale', 'ctv']);
        });
        Gate::define('receiver.delete', function ($user) {
            return $user->hasRole('admin');
        });

        // CTV — admin/manager/cs thấy tất cả; sale chỉ thấy CTV thuộc mình (lọc trong query)
        Gate::define('ctv.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs', 'sale']);
        });


        // Gate for nhansu
        Gate::define('nhansu.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager']);
        });

        // Gate for dulieu — admin, manager, cs: create/edit; admin only: delete
        Gate::define('dulieu.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'cs']);
        });

        // Gate for chinhsach — admin only
        Gate::define('chinhsach.index', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for settings — admin, manager, ketoan, cs
        Gate::define('settings.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ketoan', 'cs']);
        });

        // Gate for notification list — all internal roles can view notifications for their role.
        Gate::define('notifications.view', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ketoan', 'cs', 'sale', 'ops', 'ctv', 'shipper']);
        });

        // Gate for settings children — admin only (logo, favicon, banner, he-thong, social, company)
        Gate::define('settings.admin', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for phuphi — admin, manager, ketoan
        Gate::define('phuphi.index', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ketoan']);
        });

        // Gate for service price lists — admin can delete, manager/ketoan can add/edit only.
        Gate::define('service-prices.manage', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ketoan']);
        });

        Gate::define('service-prices.delete', function ($user) {
            return $user->hasRole('admin');
        });
    }
}
