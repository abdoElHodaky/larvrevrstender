<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

/**
 * BiddingServiceAdapter - Compatibility layer for internal bidding service RPC calls
 * 
 * Provides the same interface as BiddingServiceClient but routes calls through RPC
 * for seamless migration from HTTP to RPC communication.
 */
class BiddingServiceAdapter
{
    private $biddingRpcClient;
    private string $correlationId;

    public function __construct()
    {
        $this->biddingRpcClient = App::make('BiddingRpc');
        $this->correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
    }

    /**
     * Get all bids for a specific auction.
     */
    public function getAuctionBids(int $auctionId, array $filters = []): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.getAuctionBids', [
                'auction_id' => $auctionId,
                'filters' => $filters
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getAuctionBids', compact('auctionId', 'filters'), $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? [] : [];
        } catch (\Exception $e) {
            $this->logRpcError('getAuctionBids', compact('auctionId', 'filters'), $e);
            return [];
        }
    }

    /**
     * Get the highest bid for an auction.
     */
    public function getHighestBid(int $auctionId): ?array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.getHighestBid', [
                'auction_id' => $auctionId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getHighestBid', compact('auctionId'), $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            $this->logRpcError('getHighestBid', compact('auctionId'), $e);
            return null;
        }
    }

    /**
     * Get bid count for an auction.
     */
    public function getBidCount(int $auctionId): int
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.getBidCount', [
                'auction_id' => $auctionId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getBidCount', compact('auctionId'), $response, $duration);

            return $response['success'] ?? false ? (int) ($response['data']['count'] ?? 0) : 0;
        } catch (\Exception $e) {
            $this->logRpcError('getBidCount', compact('auctionId'), $e);
            return 0;
        }
    }

    /**
     * Get a specific bid by ID.
     */
    public function getBid(int $bidId): ?array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.getBid', [
                'bid_id' => $bidId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getBid', compact('bidId'), $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            $this->logRpcError('getBid', compact('bidId'), $e);
            return null;
        }
    }

    /**
     * Place a new bid on an auction.
     */
    public function placeBid(array $bidData): ?array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.placeBid', $bidData);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('placeBid', ['bid_data' => $bidData], $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            $this->logRpcError('placeBid', ['bid_data' => $bidData], $e);
            return null;
        }
    }

    /**
     * Update bid status.
     */
    public function updateBidStatus(int $bidId, string $status, ?string $reason = null): bool
    {
        try {
            $startTime = microtime(true);
            
            $params = [
                'bid_id' => $bidId,
                'status' => $status
            ];
            
            if ($reason) {
                $params['reason'] = $reason;
            }

            $response = $this->biddingRpcClient->call('bidding.updateBidStatus', $params);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('updateBidStatus', compact('bidId', 'status', 'reason'), $response, $duration);

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('updateBidStatus', compact('bidId', 'status', 'reason'), $e);
            return false;
        }
    }

    /**
     * Cancel/withdraw a bid.
     */
    public function cancelBid(int $bidId, ?string $reason = null): bool
    {
        try {
            $startTime = microtime(true);
            
            $params = ['bid_id' => $bidId];
            if ($reason) {
                $params['reason'] = $reason;
            }

            $response = $this->biddingRpcClient->call('bidding.cancelBid', $params);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('cancelBid', compact('bidId', 'reason'), $response, $duration);

            return $response['success'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('cancelBid', compact('bidId', 'reason'), $e);
            return false;
        }
    }

    /**
     * Get bid history for an auction with pagination.
     */
    public function getBidHistory(int $auctionId, int $limit = 50, int $offset = 0): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.getBidHistory', [
                'auction_id' => $auctionId,
                'limit' => $limit,
                'offset' => $offset,
                'order_by' => 'created_at',
                'order_direction' => 'desc'
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getBidHistory', compact('auctionId', 'limit', 'offset'), $response, $duration);

            if ($response['success'] ?? false) {
                return $response['data'] ?? [
                    'data' => [],
                    'total' => 0,
                    'limit' => $limit,
                    'offset' => $offset
                ];
            }

            return [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset
            ];
        } catch (\Exception $e) {
            $this->logRpcError('getBidHistory', compact('auctionId', 'limit', 'offset'), $e);
            return [
                'data' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset
            ];
        }
    }

    /**
     * Get user's bids for a specific auction.
     */
    public function getUserAuctionBids(int $userId, int $auctionId): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.getUserAuctionBids', [
                'user_id' => $userId,
                'auction_id' => $auctionId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserAuctionBids', compact('userId', 'auctionId'), $response, $duration);

            return $response['success'] ?? false ? $response['data'] ?? [] : [];
        } catch (\Exception $e) {
            $this->logRpcError('getUserAuctionBids', compact('userId', 'auctionId'), $e);
            return [];
        }
    }

    /**
     * Check if user has active bids on an auction.
     */
    public function hasActiveBids(int $userId, int $auctionId): bool
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.hasActiveBids', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'status' => 'active'
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('hasActiveBids', compact('userId', 'auctionId'), $response, $duration);

            return $response['success'] ?? false && $response['data']['has_active_bids'] ?? false;
        } catch (\Exception $e) {
            $this->logRpcError('hasActiveBids', compact('userId', 'auctionId'), $e);
            return false;
        }
    }

    /**
     * Initialize auction in bidding service for saga workflow.
     */
    public function initializeAuction(array $auctionData): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->biddingRpcClient->call('bidding.initializeAuction', $auctionData);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('initializeAuction', ['auction_data' => $auctionData], $response, $duration);

            if ($response['success'] ?? false) {
                return $response['data'] ?? $response;
            }

            return [
                'success' => false,
                'message' => 'Failed to initialize auction in bidding service',
                'error' => $response['error'] ?? 'Unknown error'
            ];
        } catch (\Exception $e) {
            $this->logRpcError('initializeAuction', ['auction_data' => $auctionData], $e);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Exception occurred during auction initialization'
            ];
        }
    }

    /**
     * Health check - compatibility method
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->biddingRpcClient->call('system.health');
            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service info - compatibility method
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->biddingRpcClient->call('system.info');
            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log successful RPC call
     */
    private function logRpcCall(string $method, array $params, $response, float $duration): void
    {
        Log::info('RPC call completed', [
            'adapter' => 'BiddingServiceAdapter',
            'method' => $method,
            'duration' => round($duration * 1000, 2) . 'ms',
            'correlation_id' => $this->correlationId,
            'success' => $response['success'] ?? false,
            'service' => 'bidding-service'
        ]);
    }

    /**
     * Log RPC call error
     */
    private function logRpcError(string $method, array $params, \Exception $e): void
    {
        Log::error('RPC call failed', [
            'adapter' => 'BiddingServiceAdapter',
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $this->correlationId,
            'service' => 'bidding-service'
        ]);
    }
}
