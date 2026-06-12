<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shipper/OPS luôn được redirect sang giao diện mobile.
 *
 * Khi user có role "shipper" hoặc "ops" truy cập bất kỳ route desktop nào trong nhóm
 * web (trừ các route mobile, logout, api), middleware sẽ tự động redirect
 * sang giao diện mobile tương ứng.
 */
class RedirectShipperToMobile
{
    /**
     * Các route name mà shipper ĐƯỢC phép truy cập (không redirect).
     */
    protected array $allowedRoutes = [
        'shipper.pickups',
        'shipper.scan',
        'shipper.notifications',
        'shipper.profile',
        'ops.mobile.home',
        'ops.mobile.orders.index',
        'ops.mobile.orders.show',
        'ops.mobile.pickups.index',
        'ops.mobile.pickups.show',
        'ops.mobile.notifications',
        'ops.mobile.profile',
        'mobile.scan',
        'logout',
        'login',
    ];

    /**
     * Các prefix URI mà shipper ĐƯỢC phép truy cập.
     */
    protected array $allowedPrefixes = [
        'shipper/',
        'ops/mobile',
        'mobile/scan',
        'livewire',
        'api/',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $path = ltrim($request->path(), '/');

        // Cho phép Livewire internal requests (AJAX/update/upload)
        if ($request->header('X-Livewire') || str_starts_with($path, 'livewire')) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->hasAnyRole(['shipper', 'ops'])) {
            return $next($request);
        }

        // Cho phép các route name trong whitelist
        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $this->allowedRoutes, true)) {
            return $next($request);
        }

        // Cho phép các URI prefix trong whitelist
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        if ($user->hasRole('ops')) {
            return redirect()->route('ops.mobile.orders.index');
        }

        return redirect()->route('shipper.pickups');
    }
}
