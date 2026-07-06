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
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // --- API envelope (success/message/errors) cho mobile + Zalo Mini App ---
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/mobile/*') || $request->is('api/zalo-mini-app/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $e->errors(),
                ], 422);
            }
            return null;
        });
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/mobile/*') || $request->is('api/zalo-mini-app/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                ], 401);
            }
            return null;
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if (($request->is('api/mobile/*') || $request->is('api/zalo-mini-app/*')) && $e->getStatusCode() === 403) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản không có quyền thực hiện thao tác này.',
                ], 403);
            }
            return null;
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/zalo-mini-app/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy dữ liệu.',
                ], 404);
            }
            return null;
        });
        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->is('api/zalo-mini-app/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản không có quyền thực hiện thao tác này.',
                ], 403);
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
