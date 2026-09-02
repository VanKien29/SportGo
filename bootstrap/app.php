<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Ensure broadcasting/auth uses Sanctum (Bearer token) instead of cookie-based web auth
        $middleware->web(append: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (PostTooLargeException $exception, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                return response()->json([
                    'message' => 'Tổng dung lượng file tải lên vượt quá giới hạn 80 MB. Vui lòng giảm bớt hoặc nén file trước khi gửi.',
                ], 413);
            }

            return null;
        });

        $exceptions->render(function (ThrottleRequestsException $exception, $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
                $message = $retryAfter
                    ? "Bạn đang thao tác quá nhanh. Vui lòng thử lại sau {$retryAfter} giây."
                    : 'Bạn đang thao tác quá nhanh. Vui lòng thử lại sau giây lát.';

                return response()->json([
                    'message' => $message,
                ], 429, $exception->getHeaders());
            }

            return null;
        });
    })->create();
