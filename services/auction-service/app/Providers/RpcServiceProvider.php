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
        // Auth Service RPC Client
        $this->app->singleton('AuthRpc', function () {
            return new \Sajya\Client\Client(
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.auth.url'))
                    ->withToken(config('rpc.services.auth.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'auction-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.client.timeout', 5))
            );
        });

        // Bidding Service RPC Client
        $this->app->singleton('BiddingRpc', function () {
            return new \App\RPC\Clients\BiddingServiceRpcClient();
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new \App\RPC\Clients\NotificationServiceRpcClient();
        });

        // Payment Service RPC Client
        $this->app->singleton('PaymentRpc', function () {
            return new \App\RPC\Clients\PaymentServiceRpcClient();
        });

        // Register RPC clients with interface bindings for dependency injection
        $this->app->bind(\App\RPC\Clients\BiddingServiceRpcClient::class, function () {
            return app('BiddingRpc');
        });

        $this->app->bind(\App\RPC\Clients\NotificationServiceRpcClient::class, function () {
            return app('NotificationRpc');
        });

        $this->app->bind(\App\RPC\Clients\PaymentServiceRpcClient::class, function () {
            return app('PaymentRpc');
        });

        // Register RPC Adapters (compatibility layer)
        $this->registerRpcAdapters();
    }

    /**
     * Register RPC adapters for seamless HTTP-to-RPC migration
     */
    private function registerRpcAdapters(): void
    {
        // Auth Service Adapter
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);

        // User Service Adapter
        $this->app->singleton(\App\RPC\Adapters\UserServiceAdapter::class);

        // Bidding Service Adapter
        $this->app->singleton(\App\RPC\Adapters\BiddingServiceAdapter::class);

        // Notification Service Adapter
        $this->app->singleton(\App\RPC\Adapters\NotificationServiceAdapter::class);
    }
}
