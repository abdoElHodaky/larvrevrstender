<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * Bidding Service RPC Adapter for Analytics Service
 * 
 * Provides semantic methods for interacting with bidding-service via RPC.
 * Used by analytics service to collect bidding data and auction participation metrics.
 */
class BiddingServiceAdapter
{
    private Client $biddingRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->biddingRpc = app('BiddingRpc');
        $this->correlationId = 'analytics-bidding-' . bin2hex(random_bytes(16));
    }

    /**
     * Get bid by ID for analytics
     *
     * @param int $bidId Bid ID to retrieve
     * @return array|null Bid data or null on failure
     */
    public function getBidById(int $bidId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'bid_id' => $bidId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('BiddingServiceAdapter: Getting bid for analytics', [
                'bid_id' => $bidId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->biddingRpc->call('bidding.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('BiddingServiceAdapter: Bid data retrieved for analytics', [
                    'bid_id' => $bidId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('BiddingServiceAdapter: Bid data retrieval failed', [
                'bid_id' => $bidId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Bid data retrieval error', [
                'bid_id' => $bidId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get bids by auction ID for analytics
     *
     * @param int $auctionId Auction ID to get bids for
     * @param array $filters Optional filters
     * @return array|null Bids data or null on failure
     */
    public function getBidsByAuction(int $auctionId, array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'auction_id' => $auctionId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('BiddingServiceAdapter: Getting bids by auction for analytics', [
                'auction_id' => $auctionId,
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->biddingRpc->call('bidding.getByAuction', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('BiddingServiceAdapter: Bids retrieved for analytics', [
                    'auction_id' => $auctionId,
                    'bid_count' => count($response['data']['bids'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('BiddingServiceAdapter: Bids retrieval failed', [
                'auction_id' => $auctionId,
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Bids retrieval error', [
                'auction_id' => $auctionId,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get bidding metrics for analytics
     *
     * @param array $filters Filters for metrics calculation
     * @return array|null Bidding metrics data or null on failure
     */
    public function getBiddingMetrics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('BiddingServiceAdapter: Getting bidding metrics for analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->biddingRpc->call('bidding.getMetrics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('BiddingServiceAdapter: Bidding metrics retrieved for analytics', [
                    'metrics_count' => count($response['data'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('BiddingServiceAdapter: Bidding metrics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Bidding metrics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get bidder analytics data
     *
     * @param array $filters Filters for bidder analysis
     * @return array|null Bidder analytics data or null on failure
     */
    public function getBidderAnalytics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('BiddingServiceAdapter: Getting bidder analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->biddingRpc->call('bidding.getBidderAnalytics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('BiddingServiceAdapter: Bidder analytics retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('BiddingServiceAdapter: Bidder analytics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Bidder analytics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get bid patterns analytics
     *
     * @param array $filters Filters for pattern analysis
     * @return array|null Bid patterns data or null on failure
     */
    public function getBidPatterns(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('BiddingServiceAdapter: Getting bid patterns analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->biddingRpc->call('bidding.getBidPatterns', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('BiddingServiceAdapter: Bid patterns retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('BiddingServiceAdapter: Bid patterns retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Bid patterns retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get winning bid analytics
     *
     * @param array $filters Filters for winning bid analysis
     * @return array|null Winning bid analytics data or null on failure
     */
    public function getWinningBidAnalytics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('BiddingServiceAdapter: Getting winning bid analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->biddingRpc->call('bidding.getWinningBidAnalytics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('BiddingServiceAdapter: Winning bid analytics retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('BiddingServiceAdapter: Winning bid analytics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Winning bid analytics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Check bidding service health
     *
     * @return array|null Service health status or null on failure
     */
    public function getServiceInfo(): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            $response = $this->biddingRpc->call('bidding.getServiceInfo', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response) {
                Log::info('BiddingServiceAdapter: Service info retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response;
            }

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('BiddingServiceAdapter: Service info error', [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }
}
