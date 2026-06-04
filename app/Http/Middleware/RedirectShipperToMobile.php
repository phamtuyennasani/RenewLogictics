<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shipper luôn được redirect sang giao diện mobile.
 *
 * Khi user có role "shipper" truy cập bất kỳ route nào trong nhóm
 * web (trừ các route mobile, logout, profile, api), middleware sẽ
 * tự động redirect sang /shipper/pickups.
 */
class RedirectShipperToMobile
{
    /**
     * Các route name mà shipper ĐƯỢC phép truy cập (không redirect).
     */
    protected array $allowedRoutes = [
        'shipper.pickups',
        'profile',
        'logout',
        'login',
    ];

    /**
     * Các prefix URI mà shipper ĐƯỢC phép truy cập.
     */
    protected array $allowedPrefixes = [
        'shipper/',
        'livewire/',
        'api/',
        'ho-so',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('shipper')) {
            return $next($request);
        }

        // Cho phép Livewire internal requests (AJAX/update)
        if ($request->header('X-Livewire')) {
            return $next($request);
        }

        // Cho phép các route name trong whitelist
        $routeName = $request->route()?->getName();
        if ($routeName && in_array($routeName, $this->allowedRoutes, true)) {
            return $next($request);
        }

        // Cho phép các URI prefix trong whitelist
        $path = ltrim($request->path(), '/');
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        return redirect()->route('shipper.pickups');
    }
}
