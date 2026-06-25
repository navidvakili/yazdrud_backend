<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'scopes' => \Laravel\Passport\Http\Middleware\CheckScopes::class,
            'scope' => \Laravel\Passport\Http\Middleware\CheckForAnyScope::class,
        ]);

        // Global middleware — CORS handled before any route matching,
        // so OPTIONS preflight requests get proper headers even for auth-protected routes.
        $middleware->prepend(\App\Http\Middleware\CorsMiddleware::class);

        // Remove the default HandleCors (from Laravel framework) to avoid duplicate CORS processing
        $middleware->remove(\Illuminate\Http\Middleware\HandleCors::class);

        // For API routes, don't redirect to a "login" route on auth failure — return 401 JSON instead
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Catch database / SQL errors and return a friendly message instead of exposing SQL details
        $exceptions->render(function (QueryException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'خطایی در پردازش درخواست رخ داده است. لطفاً مجدداً تلاش کنید.',
                ], 500);
            }
        });

        // Catch 404 errors
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'مسیر درخواستی یافت نشد.',
                ], 404);
            }
        });

        // Catch general HTTP exceptions
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'خطایی در پردازش درخواست رخ داده است.',
                ], $e->getStatusCode());
            }
        });

        // Catch any other unhandled exception
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
                return response()->json([
                    'message' => 'خطای داخلی سرور. لطفاً با پشتیبانی تماس بگیرید.',
                ], $statusCode >= 400 && $statusCode < 600 ? $statusCode : 500);
            }
        });
    })->create();
