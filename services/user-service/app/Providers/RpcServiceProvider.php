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
     */
    private function registerRpcClients(): void
    {
        // Analytics Service RPC Client
        $this->app->singleton(AnalyticsServiceClient::class, function ($app) {
            return new AnalyticsServiceClient(
                config('rpc.analytics_service.base_url', 'http://analytics-service:8080'),
                config('rpc.analytics_service.timeout', 30)
            );
        });

        // VIN OCR Service RPC Client
        $this->app->singleton(VinOcrServiceClient::class, function ($app) {
            return new VinOcrServiceClient(
                config('rpc.vin_ocr_service.base_url', 'http://vin-ocr-service:8080'),
                config('rpc.vin_ocr_service.timeout', 60)
            );
        });

        // Payment Service RPC Client
        $this->app->singleton(PaymentServiceClient::class, function ($app) {
            return new PaymentServiceClient(
                config('rpc.payment_service.base_url', 'http://payment-service:8080'),
                config('rpc.payment_service.timeout', 30)
            );
        });
    }

    /**
     * Register RPC adapters as singletons
     */
    private function registerRpcAdapters(): void
    {
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);
    }
}
