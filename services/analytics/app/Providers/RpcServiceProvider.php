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

        // Register RPC adapters
        $this->registerRpcAdapters();
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
        $router->aliasMiddleware('rpc.auth', \Shared\RPC\Middleware\RpcAuthMiddleware::class);
    }

    /**
     * Register RPC clients for inter-service communication
     */
    private function registerRpcClients(): void
    {
        // Legacy RPC client singletons removed - using modern RPC clients via ModernRpcServiceProvider

        // Legacy RPC client bindings removed - using modern RPC clients via ModernRpcServiceProvider
    }

    /**
     * Register RPC adapters for analytics service
     */
    private function registerRpcAdapters(): void
    {
        // User Service Adapter
        $this->app->singleton(\App\RPC\Adapters\UserServiceAdapter::class, function () {
            return new \App\RPC\Adapters\UserServiceAdapter();
        });

        // Order Service Adapter
        $this->app->singleton(\App\RPC\Adapters\OrderServiceAdapter::class, function () {
            return new \App\RPC\Adapters\OrderServiceAdapter();
        });

        // Payment Service Adapter
        $this->app->singleton(\App\RPC\Adapters\PaymentServiceAdapter::class, function () {
            return new \App\RPC\Adapters\PaymentServiceAdapter();
        });

        // Auction Service Adapter
        $this->app->singleton(\App\RPC\Adapters\AuctionServiceAdapter::class, function () {
            return new \App\RPC\Adapters\AuctionServiceAdapter();
        });

        // Bidding Service Adapter
        $this->app->singleton(\App\RPC\Adapters\BiddingServiceAdapter::class, function () {
            return new \App\RPC\Adapters\BiddingServiceAdapter();
        });
    }
}
