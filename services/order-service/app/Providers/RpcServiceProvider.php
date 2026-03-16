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

        $router->aliasMiddleware('rpc.auth', \App\Http\Middleware\RpcAuthenticationMiddleware::class);
        $router->aliasMiddleware('rpc.correlation', \App\Http\Middleware\RpcCorrelationMiddleware::class);
        $router->aliasMiddleware('rpc.performance', \App\Http\Middleware\RpcPerformanceMiddleware::class);
        $router->aliasMiddleware('rpc.logging', \App\Http\Middleware\RpcLoggingMiddleware::class);
    }

    /**
     * Register RPC clients for inter-service communication
     */
    private function registerRpcClients(): void
    {
        // Analytics Service RPC Client
        $this->app->singleton('AnalyticsRpc', function () {
            return new \App\RPC\Clients\AnalyticsServiceRpcClient();
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new \App\RPC\Clients\NotificationServiceRpcClient();
        });

        // Payment Service RPC Client
        $this->app->singleton('PaymentRpc', function () {
            return new \App\RPC\Clients\PaymentServiceRpcClient();
        });

        // User Service RPC Client
        $this->app->singleton('UserRpc', function () {
            return new \App\RPC\Clients\UserServiceRpcClient();
        });

        // Register RPC clients with interface bindings for dependency injection
        $this->app->bind(\App\RPC\Clients\AnalyticsServiceRpcClient::class, function () {
            return app('AnalyticsRpc');
        });

        $this->app->bind(\App\RPC\Clients\NotificationServiceRpcClient::class, function () {
            return app('NotificationRpc');
        });

        $this->app->bind(\App\RPC\Clients\PaymentServiceRpcClient::class, function () {
            return app('PaymentRpc');
        });

        $this->app->bind(\App\RPC\Clients\UserServiceRpcClient::class, function () {
            return app('UserRpc');
        });
    }

    /**
     * Register RPC adapters as singletons
     */
    private function registerRpcAdapters(): void
    {
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\NotificationServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\AnalyticsServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\UserServiceAdapter::class);
    }
}
