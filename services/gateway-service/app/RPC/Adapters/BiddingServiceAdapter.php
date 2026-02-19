<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * BiddingServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the bidding service.
 * Gateway service needs bidding operations for request routing and auction management.
 */
class BiddingServiceAdapter
{
    private $biddingRpc;

    public function __construct()
    {
        $this->biddingRpc = app('BiddingRpc');
    }

    /**
     * Place a bid
     */
    public function placeBid(array $bidData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('placeBid', ['bid_data' => $bidData], $correlationId);
            
            $response = $this->biddingRpc->call('bidding.placeBid', [
                'bid_data' => $bidData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('placeBid', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('placeBid', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get bid by ID
     */
    public function getBid(int $bidId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getBid', ['bid_id' => $bidId], $correlationId);
            
            $response = $this->biddingRpc->call('bidding.getBid', [
                'bid_id' => $bidId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getBid', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getBid', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get bids for an auction
     */
    public function getAuctionBids(int $auctionId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getAuctionBids', ['auction_id' => $auctionId], $correlationId);
            
            $response = $this->biddingRpc->call('bidding.getAuctionBids', [
                'auction_id' => $auctionId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getAuctionBids', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getAuctionBids', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user bids
     */
    public function getUserBids(int $userId, array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserBids', ['user_id' => $userId, 'filters' => $filters], $correlationId);
            
            $response = $this->biddingRpc->call('bidding.getUserBids', [
                'user_id' => $userId,
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserBids', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserBids', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Update bid status
     */
    public function updateBidStatus(int $bidId, string $status): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('updateBidStatus', ['bid_id' => $bidId, 'status' => $status], $correlationId);
            
            $response = $this->biddingRpc->call('bidding.updateBidStatus', [
                'bid_id' => $bidId,
                'status' => $status
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('updateBidStatus', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('updateBidStatus', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get bidding statistics
     */
    public function getBiddingStats(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getBiddingStats', ['filters' => $filters], $correlationId);
            
            $response = $this->biddingRpc->call('bidding.getBiddingStats', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getBiddingStats', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getBiddingStats', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway BiddingService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'bidding-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway BiddingService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'bidding-service',
            'caller' => 'gateway-service'
        ]);
    }
}
