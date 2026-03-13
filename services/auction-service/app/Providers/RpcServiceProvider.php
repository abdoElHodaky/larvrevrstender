<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sajya\Server\ServerServiceProvider;
use Shared\Core\RpcClient;
use Shared\Core\ServiceDiscoveryClient;

class RpcServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Sajya RPC Server
        $this->app->register(ServerServiceProvider::class);

        // Register shared RPC infrastructure
        $this->registerSharedRpcServices();
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
    }

    /**
     * Register shared RPC services
     */
    private function registerSharedRpcServices(): void
    {
        // Register Service Discovery Client
        $this->app->singleton(ServiceDiscoveryClient::class, function ($app) {
            return new ServiceDiscoveryClient([
                'registry_url' => config('rpc.service_discovery.url', 'http://service-registry:8080'),
                'cache_ttl' => config('rpc.service_discovery.cache_ttl', 300),
                'environment' => config('app.env', 'production'),
                'service_name' => 'auction-service'
            ]);
        });

        // Register shared RPC Client
        $this->app->singleton(RpcClient::class, function ($app) {
            return new RpcClient(
                $app->make(ServiceDiscoveryClient::class),
                [
                    'timeout' => config('rpc.client.timeout', 30),
                    'retry_attempts' => config('rpc.client.retry_attempts', 3),
                    'retry_delay' => config('rpc.client.retry_delay', 1000),
                    'circuit_breaker' => [
                        'failure_threshold' => config('rpc.circuit_breaker.failure_threshold', 5),
                        'recovery_timeout' => config('rpc.circuit_breaker.recovery_timeout', 60),
                        'expected_exception_types' => ['ConnectionException', 'TimeoutException']
                    ],
                    'correlation_id_header' => 'X-Correlation-ID',
                    'service_name' => 'auction-service'
                ]
            );
        });

        // Register service-specific RPC client aliases for backward compatibility
        $this->app->alias(RpcClient::class, 'BiddingRpc');
        $this->app->alias(RpcClient::class, 'OrderRpc');
        $this->app->alias(RpcClient::class, 'UserRpc');
        $this->app->alias(RpcClient::class, 'NotificationRpc');
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
}
