<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\RedirectShipperToMobile::class,
        ]);

        $middleware->alias([
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'third-party.tracking-api' => \App\Http\Middleware\EnsureThirdPartyTrackingApiAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Không có quyền truy cập.'], 403);
            }
            return response()->view('errors.403', [
                'primaryColor' => config('theme.primary.hex', '#3b82f6'),
                'accentColor'  => config('theme.accent.hex', '#0ea5e9'),
                'primaryDark'  => config('theme.primary.dark', '#2563eb'),
            ], 403);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Không có quyền truy cập.'], 403);
            }
            return response()->view('errors.403', [
                'primaryColor' => config('theme.primary.hex', '#3b82f6'),
                'accentColor'  => config('theme.accent.hex', '#0ea5e9'),
                'primaryDark'  => config('theme.primary.dark', '#2563eb'),
            ], 403);
        });
    })->create();

$app->usePublicPath(dirname(__DIR__).'/public_html');

return $app;
