<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sajya\Server\ServerServiceProvider;

class RpcServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Sajya RPC Server
        $this->app->register(ServerServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load RPC routes
        $this->loadRoutesFrom(base_path('routes/rpc.php'));

        // Register RPC middleware
        $this->registerRpcMiddleware();

        // Register RPC clients for other services
        $this->registerRpcClients();
    }

    /**
     * Register RPC middleware
     */
    private function registerRpcMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware('rpc.correlation', \App\Http\Middleware\RpcCorrelationMiddleware::class);
        $router->aliasMiddleware('rpc.performance', \App\Http\Middleware\RpcPerformanceMiddleware::class);
        $router->aliasMiddleware('rpc.logging', \App\Http\Middleware\RpcLoggingMiddleware::class);
    }

    /**
     * Register RPC clients for inter-service communication
     */
    private function registerRpcClients(): void
    {
        // User Service RPC Client
        $this->app->singleton('UserRpc', function () {
            return new \Sajya\Client\Client(
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.user.url'))
                    ->withToken(config('rpc.services.user.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'auth-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.client.timeout', 5))
            );
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new \Sajya\Client\Client(
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.notification.url'))
                    ->withToken(config('rpc.services.notification.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'auth-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.client.timeout', 5))
            );
        });

        // Analytics Service RPC Client
        $this->app->singleton('AnalyticsRpc', function () {
            return new \Sajya\Client\Client(
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.analytics.url'))
                    ->withToken(config('rpc.services.analytics.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'auth-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.client.timeout', 5))
            );
        });
    }
}
