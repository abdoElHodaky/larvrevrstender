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
            return new \App\RPC\Clients\UserServiceRpcClient();
        });

        // Auction Service RPC Client  
        $this->app->singleton('AuctionRpc', function () {
            return new \App\RPC\Clients\AuctionServiceRpcClient();
        });

        // Bidding Service RPC Client
        $this->app->singleton('BiddingRpc', function () {
            return new \App\RPC\Clients\BiddingServiceRpcClient();
        });

        // Payment Service RPC Client
        $this->app->singleton('PaymentRpc', function () {
            return new \App\RPC\Clients\PaymentServiceRpcClient();
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new \App\RPC\Clients\NotificationServiceRpcClient();
        });

        // VIN OCR Service RPC Client
        $this->app->singleton('VinOcrRpc', function () {
            return new \App\RPC\Clients\VinOcrServiceRpcClient();
        });

        // Register RPC clients with interface bindings for dependency injection
        $this->app->bind(\App\RPC\Clients\UserServiceRpcClient::class, function () {
            return app('UserRpc');
        });

        $this->app->bind(\App\RPC\Clients\AuctionServiceRpcClient::class, function () {
            return app('AuctionRpc');
        });

        $this->app->bind(\App\RPC\Clients\BiddingServiceRpcClient::class, function () {
            return app('BiddingRpc');
        });

        $this->app->bind(\App\RPC\Clients\PaymentServiceRpcClient::class, function () {
            return app('PaymentRpc');
        });

        $this->app->bind(\App\RPC\Clients\NotificationServiceRpcClient::class, function () {
            return app('NotificationRpc');
        });

        $this->app->bind(\App\RPC\Clients\VinOcrServiceRpcClient::class, function () {
            return app('VinOcrRpc');
        });
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
