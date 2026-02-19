<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * AuctionServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the auction service.
 * Gateway service needs auction operations for request routing and auction management.
 */
class AuctionServiceAdapter
{
    private $auctionRpc;

    public function __construct()
    {
        $this->auctionRpc = app('AuctionRpc');
    }

    /**
     * Get auction by ID
     */
    public function getAuction(int $auctionId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getAuction', ['auction_id' => $auctionId], $correlationId);
            
            $response = $this->auctionRpc->call('auction.getAuction', [
                'auction_id' => $auctionId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getAuction', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getAuction', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Create a new auction
     */
    public function createAuction(array $auctionData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('createAuction', ['auction_data' => $auctionData], $correlationId);
            
            $response = $this->auctionRpc->call('auction.createAuction', [
                'auction_data' => $auctionData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('createAuction', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('createAuction', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get active auctions
     */
    public function getActiveAuctions(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getActiveAuctions', ['filters' => $filters], $correlationId);
            
            $response = $this->auctionRpc->call('auction.getActiveAuctions', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getActiveAuctions', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getActiveAuctions', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Update auction status
     */
    public function updateAuctionStatus(int $auctionId, string $status): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('updateAuctionStatus', ['auction_id' => $auctionId, 'status' => $status], $correlationId);
            
            $response = $this->auctionRpc->call('auction.updateAuctionStatus', [
                'auction_id' => $auctionId,
                'status' => $status
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('updateAuctionStatus', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('updateAuctionStatus', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get auction statistics
     */
    public function getAuctionStats(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getAuctionStats', ['filters' => $filters], $correlationId);
            
            $response = $this->auctionRpc->call('auction.getAuctionStats', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getAuctionStats', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getAuctionStats', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway AuctionService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'auction-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway AuctionService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'auction-service',
            'caller' => 'gateway-service'
        ]);
    }
}
