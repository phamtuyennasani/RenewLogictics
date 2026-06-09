<?php

namespace App\Providers;

use App\Services\TrackingMore\TrackingMore;
use App\Support\ThirdPartyTrackingApi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TrackingMore::class, fn () => new TrackingMore());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('third-party-tracking', function (Request $request) {
            return Limit::perMinute(ThirdPartyTrackingApi::rateLimitPerMinute())
                ->by((string) $request->ip());
        });

        // Mobile API rate limits (xem docs/MOBILE_API_CONTRACT §1.6).
        RateLimiter::for('mobile-login', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('mobile-scan', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()
            );
        });

        RateLimiter::for('mobile-receive', function (Request $request) {
            return Limit::perMinute(30)->by(
                $request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()
            );
        });
    }
}
