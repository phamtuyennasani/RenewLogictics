<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectScanToMobile
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isMobileRequest($request)) {
            return redirect()->route('mobile.scan');
        }

        return $next($request);
    }

    protected function isMobileRequest(Request $request): bool
    {
        if ($request->header('Sec-CH-UA-Mobile') === '?1') {
            return true;
        }

        $userAgent = $request->userAgent() ?? '';

        return (bool) preg_match(
            '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i',
            $userAgent
        );
    }
}
