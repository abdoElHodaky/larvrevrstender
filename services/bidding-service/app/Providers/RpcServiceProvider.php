<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Shared\Procedures\ProcedureEngine;
use App\RPC\Procedures\BidProcedure;
use App\RPC\Procedures\AuctionProcedure;
use App\Services\BidService;
use App\Services\AuctionService;

/**
 * RPC Service Provider for Bidding Service
 * 
 * Registers RPC procedures with the ProcedureEngine for handling
 * incoming RPC calls from other services.
 */
class RpcServiceProvider extends ServiceProvider
{
    /**
     * Register RPC procedures
     *
     * @return void
     */
    public function register(): void
    {
        // Register procedure classes in the container
        $this->app->singleton(BidProcedure::class, function ($app) {
            return new BidProcedure(
                $app->make(BidService::class),
                $app->make(AuctionService::class)
            );
        });
        
        $this->app->singleton(AuctionProcedure::class, function ($app) {
            return new AuctionProcedure(
                $app->make(AuctionService::class),
                $app->make(BidService::class)
            );
        });
    }
    
    /**
     * Bootstrap RPC procedures
     *
     * @return void
     */
    public function boot(): void
    {
        // Register RPC clients for inter-service communication
        $this->registerRpcClients();
        
        $procedureEngine = $this->app->make(ProcedureEngine::class);
        
        // Register bid-related procedures
        $bidProcedure = $this->app->make(BidProcedure::class);
        $procedureEngine->register('bid.getByAuction', [$bidProcedure, 'getByAuction']);
        $procedureEngine->register('bid.getHighest', [$bidProcedure, 'getHighest']);
        $procedureEngine->register('bid.place', [$bidProcedure, 'place']);
        $procedureEngine->register('bid.updateStatus', [$bidProcedure, 'updateStatus']);
        $procedureEngine->register('bid.cancel', [$bidProcedure, 'cancel']);
        $procedureEngine->register('bid.getHistory', [$bidProcedure, 'getHistory']);
        $procedureEngine->register('bid.checkActive', [$bidProcedure, 'checkActive']);
        $procedureEngine->register('bid.getStatistics', [$bidProcedure, 'getStatistics']);
        
        // Register auction-related procedures (from bidding perspective)
        $auctionProcedure = $this->app->make(AuctionProcedure::class);
        $procedureEngine->register('auction.initialize', [$auctionProcedure, 'initialize']);
        $procedureEngine->register('auction.updateHighestBid', [$auctionProcedure, 'updateHighestBid']);
        $procedureEngine->register('auction.validateBidEligibility', [$auctionProcedure, 'validateBidEligibility']);
        $procedureEngine->register('auction.getStatus', [$auctionProcedure, 'getStatus']);
        $procedureEngine->register('auction.close', [$auctionProcedure, 'close']);
        $procedureEngine->register('auction.isActive', [$auctionProcedure, 'isActive']);
        
        // Register system procedures for health checks and monitoring
        $procedureEngine->register('health.check', function () {
            return [
                'success' => true,
                'service' => 'bidding-service',
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
            ];
        });
        
        $procedureEngine->register('system.info', function () {
            return [
                'success' => true,
                'service' => 'bidding-service',
                'description' => 'Handles auction bidding operations and bid management',
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'procedures' => [
                    'bid.getByAuction',
                    'bid.getHighest',
                    'bid.place',
                    'bid.updateStatus',
                    'bid.cancel',
                    'bid.getHistory',
                    'bid.checkActive',
                    'bid.getStatistics',
                    'auction.initialize',
                    'auction.updateHighestBid',
                    'auction.validateBidEligibility',
                    'auction.getStatus',
                    'auction.close',
                    'auction.isActive',
                    'health.check',
                    'system.info',
                    'system.metrics',
                    'system.ping',
                ],
            ];
        });
        
        $procedureEngine->register('system.metrics', function () {
            // Get basic metrics - in production this would include more detailed metrics
            return [
                'success' => true,
                'service' => 'bidding-service',
                'metrics' => [
                    'total_bids' => \App\Models\Bid::count(),
                    'active_bids' => \App\Models\Bid::where('status', 'active')->count(),
                    'total_auctions' => \App\Models\Auction::count(),
                    'active_auctions' => \App\Models\Auction::where('status', 'active')->count(),
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true),
                ],
                'timestamp' => now()->toISOString(),
            ];
        });
        
        $procedureEngine->register('system.ping', function () {
            return [
                'success' => true,
                'service' => 'bidding-service',
                'message' => 'pong',
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Register RPC clients for inter-service communication
     */
    private function registerRpcClients(): void
    {
        // Auction Service RPC Client
        $this->app->singleton('AuctionRpc', function () {
            return new \App\RPC\Clients\AuctionServiceRpcClient();
        });

        // Notification Service RPC Client
        $this->app->singleton('NotificationRpc', function () {
            return new \App\RPC\Clients\NotificationServiceRpcClient();
        });

        // Register RPC clients with interface bindings for dependency injection
        $this->app->bind(\App\RPC\Clients\AuctionServiceRpcClient::class, function () {
            return app('AuctionRpc');
        });

        $this->app->bind(\App\RPC\Clients\NotificationServiceRpcClient::class, function () {
            return app('NotificationRpc');
        });
    }
}
