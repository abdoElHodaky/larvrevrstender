<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * Auction Service RPC Adapter for Analytics Service
 * 
 * Provides semantic methods for interacting with auction-service via RPC.
 * Used by analytics service to collect auction data and marketplace metrics.
 */
class AuctionServiceAdapter
{
    private Client $auctionRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->auctionRpc = app('AuctionRpc');
        $this->correlationId = 'analytics-auction-' . bin2hex(random_bytes(16));
    }

    /**
     * Get auction by ID for analytics
     *
     * @param int $auctionId Auction ID to retrieve
     * @return array|null Auction data or null on failure
     */
    public function getAuctionById(int $auctionId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'auction_id' => $auctionId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('AuctionServiceAdapter: Getting auction for analytics', [
                'auction_id' => $auctionId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->auctionRpc->call('auction.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('AuctionServiceAdapter: Auction data retrieved for analytics', [
                    'auction_id' => $auctionId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('AuctionServiceAdapter: Auction data retrieval failed', [
                'auction_id' => $auctionId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('AuctionServiceAdapter: Auction data retrieval error', [
                'auction_id' => $auctionId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get auctions by date range for analytics
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param array $filters Optional filters
     * @return array|null Auctions data or null on failure
     */
    public function getAuctionsByDateRange(string $startDate, string $endDate, array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('AuctionServiceAdapter: Getting auctions by date range for analytics', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->auctionRpc->call('auction.getByDateRange', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('AuctionServiceAdapter: Auctions retrieved for analytics', [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'auction_count' => count($response['data']['auctions'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('AuctionServiceAdapter: Auctions retrieval failed', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('AuctionServiceAdapter: Auctions retrieval error', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get auction metrics for analytics
     *
     * @param array $filters Filters for metrics calculation
     * @return array|null Auction metrics data or null on failure
     */
    public function getAuctionMetrics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('AuctionServiceAdapter: Getting auction metrics for analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->auctionRpc->call('auction.getMetrics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('AuctionServiceAdapter: Auction metrics retrieved for analytics', [
                    'metrics_count' => count($response['data'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('AuctionServiceAdapter: Auction metrics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('AuctionServiceAdapter: Auction metrics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get auction performance analytics
     *
     * @param array $filters Filters for performance calculation
     * @return array|null Auction performance data or null on failure
     */
    public function getAuctionPerformance(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('AuctionServiceAdapter: Getting auction performance analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->auctionRpc->call('auction.getPerformance', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('AuctionServiceAdapter: Auction performance retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('AuctionServiceAdapter: Auction performance retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('AuctionServiceAdapter: Auction performance retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get auction categories analytics
     *
     * @param array $filters Filters for category analysis
     * @return array|null Category analytics data or null on failure
     */
    public function getCategoryAnalytics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('AuctionServiceAdapter: Getting category analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->auctionRpc->call('auction.getCategoryAnalytics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('AuctionServiceAdapter: Category analytics retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('AuctionServiceAdapter: Category analytics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('AuctionServiceAdapter: Category analytics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Check auction service health
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

            $response = $this->auctionRpc->call('auction.getServiceInfo', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response) {
                Log::info('AuctionServiceAdapter: Service info retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response;
            }

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('AuctionServiceAdapter: Service info error', [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }
}
