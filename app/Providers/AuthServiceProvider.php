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

        // Gate for pickups
        Gate::define('pickups.index', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale', 'ops']);
        });

        // Gate for scan
        Gate::define('scan', function ($user) {
            return $user->hasAnyRole(['admin', 'ops', 'warehouse']);
        });

        // Gate for packages
        Gate::define('packages.index', function ($user) {
            return $user->hasAnyRole(['admin', 'ops', 'warehouse']);
        });

        // Gate for congno
        Gate::define('congno.index', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan', 'manager']);
        });

        Gate::define('invoice.index', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan', 'manager']);
        });

        Gate::define('congno.daily', function ($user) {
            return $user->hasAnyRole(['admin', 'ketoan']);
        });

        // Gate for thongke
        Gate::define('thongke', function ($user) {
            return $user->hasAnyRole(['admin', 'manager', 'ketoan', 'sale', 'SALE', 'ctv', 'CTV', 'cs', 'ops']);
        });

        // Gate for customers
        Gate::define('customers.index', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale']);
        });

        // Gate for sender/receiver
        Gate::define('sender.index', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale', 'ctv']);
        });

        Gate::define('receiver.index', function ($user) {
            return $user->hasAnyRole(['admin', 'cs', 'sale', 'ctv']);
        });

        // Gate for CTV management
        Gate::define('ctv.index', function ($user) {
            return $user->hasAnyRole(['admin', 'sale']);
        });

        // Gate for nhansu
        Gate::define('nhansu.index', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for dulieu
        Gate::define('dulieu.index', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for chinhsach
        Gate::define('chinhsach.index', function ($user) {
            return $user->hasRole('admin');
        });

        // Gate for settings
        Gate::define('settings.index', function ($user) {
            return $user->hasRole('admin');
        });
    }
}
