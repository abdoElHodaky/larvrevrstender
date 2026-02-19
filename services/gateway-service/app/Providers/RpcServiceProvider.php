<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sajya\Client\Client;

/**
 * RPC Service Provider for Gateway Service
 * 
 * Registers RPC clients for all services that the gateway communicates with.
 * This provider sets up the RPC infrastructure for inter-service communication.
 */
class RpcServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->registerRpcClients();
        $this->registerRpcAdapters();
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register RPC clients for all services
     */
    private function registerRpcClients(): void
    {
        // Auth Service RPC Client
        $this->app->singleton('AuthRpc', function () {
            return new Client(
                config('rpc.services.auth.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.auth.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.auth.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // User Service RPC Client
        $this->app->singleton('UserRpc', function () {
            return new Client(
                config('rpc.services.user.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.user.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.user.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // Order Service RPC Client
        $this->app->singleton('OrderRpc', function () {
            return new Client(
                config('rpc.services.order.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.order.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.order.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // Payment Service RPC Client
        $this->app->singleton('PaymentRpc', function () {
            return new Client(
                config('rpc.services.payment.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.payment.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.payment.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // Bidding Service RPC Client
        $this->app->singleton('BiddingRpc', function () {
            return new Client(
                config('rpc.services.bidding.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.bidding.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.bidding.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // Auction Service RPC Client
        $this->app->singleton('AuctionRpc', function () {
            return new Client(
                config('rpc.services.auction.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.auction.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.auction.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new Client(
                config('rpc.services.notification.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.notification.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.notification.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // Analytics Service RPC Client
        $this->app->singleton('AnalyticsRpc', function () {
            return new Client(
                config('rpc.services.analytics.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.analytics.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.analytics.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });

        // VIN OCR Service RPC Client
        $this->app->singleton('VinOcrRpc', function () {
            return new Client(
                config('rpc.services.vin_ocr.url'),
                \Illuminate\Support\Facades\Http::baseUrl(config('rpc.services.vin_ocr.url'))
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . config('rpc.services.vin_ocr.token'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(config('rpc.client.timeout', 30))
            );
        });
    }

    /**
     * Register RPC adapters as singletons
     */
    private function registerRpcAdapters(): void
    {
        $this->app->singleton(\App\RPC\Adapters\AuthServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\UserServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\OrderServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\PaymentServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\BiddingServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\AuctionServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\NotificationServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\AnalyticsServiceAdapter::class);
        $this->app->singleton(\App\RPC\Adapters\VinOcrServiceAdapter::class);
    }
}
