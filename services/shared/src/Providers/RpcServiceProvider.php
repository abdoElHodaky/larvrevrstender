<?php

declare(strict_types=1);

namespace Shared\Providers;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Shared\RPC\Clients\AnalyticsServiceClient;
use Shared\RPC\Clients\AuctionServiceClient;
use Shared\RPC\Clients\AuthServiceClient;
use Shared\RPC\Clients\BiddingServiceClient;
use Shared\RPC\Clients\NotificationServiceClient;
use Shared\RPC\Clients\OrderServiceClient;
use Shared\RPC\Clients\PaymentServiceClient;
use Shared\RPC\Clients\UserServiceClient;
use Shared\RPC\Clients\VinOcrServiceClient;
use Shared\Health\HealthChecker;

/**
 * RPC Service Provider - PHP 8.3 & Laravel 12 Implementation
 * 
 * Registers all RPC clients and health checking infrastructure
 * with Laravel's service container for dependency injection.
 */
class RpcServiceProvider extends ServiceProvider
{
    /**
     * Register RPC services
     */
    public function register(): void
    {
        $this->registerRpcClients();
        $this->registerHealthChecker();
        $this->registerRpcClientManager();
    }

    /**
     * Bootstrap RPC services
     */
    public function boot(): void
    {
        $this->publishConfiguration();
        $this->registerMiddleware();
    }

    /**
     * Register all RPC clients as singletons
     */
    private function registerRpcClients(): void
    {
        $environment = config('app.env', 'local');

        // Auth Service Client
        $this->app->singleton(AuthServiceClient::class, function ($app) use ($environment) {
            return new AuthServiceClient($app->make(HttpFactory::class), $environment);
        });

        // User Service Client
        $this->app->singleton(UserServiceClient::class, function ($app) use ($environment) {
            return new UserServiceClient($app->make(HttpFactory::class), $environment);
        });

        // Auction Service Client
        $this->app->singleton(AuctionServiceClient::class, function ($app) use ($environment) {
            return new AuctionServiceClient($app->make(HttpFactory::class), $environment);
        });

        // Bidding Service Client
        $this->app->singleton(BiddingServiceClient::class, function ($app) use ($environment) {
            return new BiddingServiceClient($app->make(HttpFactory::class), $environment);
        });

        // Payment Service Client
        $this->app->singleton(PaymentServiceClient::class, function ($app) use ($environment) {
            return new PaymentServiceClient($app->make(HttpFactory::class), $environment);
        });

        // Order Service Client
        $this->app->singleton(OrderServiceClient::class, function ($app) use ($environment) {
            return new OrderServiceClient($app->make(HttpFactory::class), $environment);
        });

        // Notification Service Client
        $this->app->singleton(NotificationServiceClient::class, function ($app) use ($environment) {
            return new NotificationServiceClient($app->make(HttpFactory::class), $environment);
        });

        // Analytics Service Client
        $this->app->singleton(AnalyticsServiceClient::class, function ($app) use ($environment) {
            return new AnalyticsServiceClient($app->make(HttpFactory::class), $environment);
        });

        // VIN OCR Service Client
        $this->app->singleton(VinOcrServiceClient::class, function ($app) use ($environment) {
            return new VinOcrServiceClient($app->make(HttpFactory::class), $environment);
        });
    }

    /**
     * Register health checker with RPC clients
     */
    private function registerHealthChecker(): void
    {
        $this->app->singleton(HealthChecker::class, function ($app) {
            $healthChecker = new HealthChecker(
                database: $app->make('db'),
                redis: $app->make('redis'),
                queue: $app->make('queue'),
                rpcClients: []
            );

            // Add RPC clients for health monitoring
            $rpcClients = [
                'auth' => $app->make(AuthServiceClient::class),
                'user' => $app->make(UserServiceClient::class),
                'auction' => $app->make(AuctionServiceClient::class),
                'bidding' => $app->make(BiddingServiceClient::class),
                'payment' => $app->make(PaymentServiceClient::class),
                'order' => $app->make(OrderServiceClient::class),
                'notification' => $app->make(NotificationServiceClient::class),
                'analytics' => $app->make(AnalyticsServiceClient::class),
                'vin-ocr' => $app->make(VinOcrServiceClient::class),
            ];

            foreach ($rpcClients as $name => $client) {
                $healthChecker->addRpcClient($name, $client);
            }

            return $healthChecker;
        });
    }

    /**
     * Register RPC client manager for centralized access
     */
    private function registerRpcClientManager(): void
    {
        $this->app->singleton('rpc.clients', function ($app) {
            return [
                'auth' => $app->make(AuthServiceClient::class),
                'user' => $app->make(UserServiceClient::class),
                'auction' => $app->make(AuctionServiceClient::class),
                'bidding' => $app->make(BiddingServiceClient::class),
                'payment' => $app->make(PaymentServiceClient::class),
                'order' => $app->make(OrderServiceClient::class),
                'notification' => $app->make(NotificationServiceClient::class),
                'analytics' => $app->make(AnalyticsServiceClient::class),
                'vin-ocr' => $app->make(VinOcrServiceClient::class),
            ];
        });
    }

    /**
     * Publish configuration files
     */
    private function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__ . '/../config/rpc.php' => config_path('rpc.php'),
        ], 'rpc-config');
    }

    /**
     * Register RPC middleware
     */
    private function registerMiddleware(): void
    {
        // Register correlation ID middleware
        $this->app['router']->aliasMiddleware('rpc.correlation', \Shared\RPC\Middleware\CorrelationIdMiddleware::class);
        
        // Register RPC authentication middleware
        $this->app['router']->aliasMiddleware('rpc.auth', \Shared\RPC\Middleware\RpcAuthMiddleware::class);
    }

    /**
     * Get the services provided by the provider
     */
    public function provides(): array
    {
        return [
            AuthServiceClient::class,
            UserServiceClient::class,
            AuctionServiceClient::class,
            BiddingServiceClient::class,
            PaymentServiceClient::class,
            OrderServiceClient::class,
            NotificationServiceClient::class,
            AnalyticsServiceClient::class,
            VinOcrServiceClient::class,
            HealthChecker::class,
            'rpc.clients',
        ];
    }
}
