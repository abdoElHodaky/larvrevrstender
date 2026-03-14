<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        // Database Failover System Service Provider
        \Shared\Providers\SharedServiceProvider::class,
        // Modern RPC Ecosystem Service Provider
        \Shared\RPC\Providers\RpcServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Database Failover Middleware - CRITICAL for system reliability
        $middleware->append(\Shared\Middleware\DatabaseFailoverMiddleware::class);

        // API middleware stack with Sanctum for stateful requests
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        // Laravel 12 enhanced security middleware
        $middleware->throttleApi();
        $middleware->validateCsrfTokens(except: [
            'api/*', // Exclude API routes from CSRF
        ]);

        // Middleware aliases
        $middleware->alias([
            'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
            'db.failover' => \Shared\Middleware\DatabaseFailoverMiddleware::class,
            // Modern RPC Middleware
            'rpc.correlation' => \Shared\RPC\Middleware\CorrelationIdMiddleware::class,
            'rpc.auth' => \Shared\RPC\Middleware\RpcAuthMiddleware::class,
            'jwt.auth' => \Tymon\JWTAuth\Http\Middleware\Authenticate::class,
            'jwt.refresh' => \Tymon\JWTAuth\Http\Middleware\RefreshToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
