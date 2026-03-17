<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sajya\Server\ServerServiceProvider;
use Shared\RPC\Clients\PaymentServiceClient;
use Shared\RPC\Clients\VinOcrServiceClient;
use Shared\RPC\Clients\AnalyticsServiceClient;

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
     * 
     * DEPRECATED: RPC clients are now registered via Shared\RPC\Providers\RpcServiceProvider
     * using modern enum-based service discovery with environment-aware URL generation.
     */
    private function registerRpcClients(): void
    {
        // Legacy RPC client registrations removed - using modern RPC clients via Shared\RPC\Providers\RpcServiceProvider
        
        // The following clients are now automatically registered by the shared provider:
        // - AnalyticsServiceClient (analytics-service:8007)
        // - VinOcrServiceClient (vin-ocr-service:8008) 
        // - PaymentServiceClient (payment-service:8004)
        //
        // URLs are generated based on environment (local/docker/kubernetes) using ServiceType enum
    }

    /**
     * Register RPC adapters as singletons
     */
    private function registerRpcAdapters(): void
    {
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);
    }
}
