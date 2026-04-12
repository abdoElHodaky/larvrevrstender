<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Clients\ModernAuthServiceClient;
use Illuminate\Support\ServiceProvider;
use Shared\RPC\Clients\AuthServiceClient;
use Shared\RPC\Clients\UserServiceClient;
use Shared\RPC\Clients\AuctionServiceClient;
use Shared\RPC\Clients\BiddingServiceClient;
use Shared\RPC\Clients\PaymentServiceClient;
use Shared\RPC\Clients\OrderServiceClient;
use Shared\RPC\Clients\NotificationServiceClient;
use Shared\RPC\Clients\VinOcrServiceClient;

/**
 * Modern RPC Service Provider - PHP 8.3 & Laravel 12 Implementation
 * 
 * Binds modern RPC clients to replace legacy HTTP and RPC clients
 * while maintaining backward compatibility with existing code.
 */
class ModernRpcServiceProvider extends ServiceProvider
{
    /**
     * Register modern RPC client bindings
     */
    public function register(): void
    {
        // Bind modern auth service client
        $this->app->bind(ModernAuthServiceClient::class, function ($app) {
            return new ModernAuthServiceClient(
                $app->make(AuthServiceClient::class)
            );
        });

        // Replace legacy AuthServiceClient with modern implementation
        $this->app->bind(\App\Http\Clients\AuthServiceClient::class, function ($app) {
            return $app->make(ModernAuthServiceClient::class);
        });

        // Bind standardized RPC clients for direct use
        $this->app->bind('rpc.auth', function ($app) {
            return $app->make(AuthServiceClient::class);
        });

        $this->app->bind('rpc.user', function ($app) {
            return $app->make(UserServiceClient::class);
        });

        $this->app->bind('rpc.auction', function ($app) {
            return $app->make(AuctionServiceClient::class);
        });

        $this->app->bind('rpc.bidding', function ($app) {
            return $app->make(BiddingServiceClient::class);
        });

        $this->app->bind('rpc.payment', function ($app) {
            return $app->make(PaymentServiceClient::class);
        });

        $this->app->bind('rpc.order', function ($app) {
            return $app->make(OrderServiceClient::class);
        });

        $this->app->bind('rpc.notification', function ($app) {
            return $app->make(NotificationServiceClient::class);
        });

        $this->app->bind('rpc.vin-ocr', function ($app) {
            return $app->make(VinOcrServiceClient::class);
        });
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        // Register middleware for RPC correlation
        $this->app['router']->pushMiddlewareToGroup('api', 'rpc.correlation');
    }
}
