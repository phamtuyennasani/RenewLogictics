<?php

namespace App\Http\Middleware;

use App\Support\ThirdPartyTrackingApi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureThirdPartyTrackingApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $options = ThirdPartyTrackingApi::options();

        if (! ThirdPartyTrackingApi::enabled($options) || ThirdPartyTrackingApi::apiKey($options) === '') {
            abort(404);
        }

        if (ThirdPartyTrackingApi::ipBlocked($request->ip(), $options)) {
            return response()->json([
                'success' => false,
                'message' => 'IP nay da bi chan truy cap API.',
            ], 403);
        }

        $providedKey = (string) ($request->header('X-API-Key') ?: $request->bearerToken() ?: '');

        if (! ThirdPartyTrackingApi::keyMatches($providedKey, $options)) {
            return response()->json([
                'success' => false,
                'message' => 'API key không hợp lệ.',
            ], 401);
        }

        return $next($request);
    }
}