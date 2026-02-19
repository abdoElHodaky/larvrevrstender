<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Auction Service (Analytics Context)
 * 
 * Provides RPC-based communication with the auction service for
 * collecting auction data and metrics for analytics purposes.
 */
class AuctionServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('auction-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get auction details for analytics
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction details
     */
    public function getAuctionForAnalytics(int $auctionId): array
    {
        return $this->call('auction.get', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get auctions for analytics with filtering
     *
     * @param array $filters Filters for analytics data collection
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with auction data
     */
    public function getAuctionsForAnalytics(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        return $this->call('auction.list', [
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => 'created_at',
            'order_direction' => 'desc',
        ]);
    }
    
    /**
     * Get auction statistics
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction statistics
     */
    public function getAuctionStatistics(int $auctionId): array
    {
        return $this->call('auction.getStatistics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get auction performance metrics
     *
     * @param array $auctionIds Array of auction IDs
     * @return array RPC response with performance metrics
     */
    public function getAuctionPerformanceMetrics(array $auctionIds): array
    {
        return $this->call('auction.getPerformanceMetrics', [
            'auction_ids' => $auctionIds,
        ]);
    }
    
    /**
     * Get auction categories for analytics
     *
     * @return array RPC response with categories and their metrics
     */
    public function getCategoriesForAnalytics(): array
    {
        return $this->call('auction.getCategories');
    }
    
    /**
     * Get auction lifecycle events
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with lifecycle events
     */
    public function getAuctionLifecycleEvents(int $auctionId): array
    {
        return $this->call('auction.getLifecycleEvents', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get auction engagement metrics
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with engagement metrics
     */
    public function getAuctionEngagementMetrics(int $auctionId): array
    {
        return $this->call('auction.getEngagementMetrics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get auctions by date range for analytics
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param array $additionalFilters Additional filters
     * @return array RPC response with auction data
     */
    public function getAuctionsByDateRange(string $startDate, string $endDate, array $additionalFilters = []): array
    {
        $filters = array_merge([
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ], $additionalFilters);
        
        return $this->call('auction.list', [
            'filters' => $filters,
            'limit' => 1000, // Large limit for analytics
            'offset' => 0,
        ]);
    }
    
    /**
     * Get auction conversion metrics
     *
     * @param array $filters Optional filters
     * @return array RPC response with conversion metrics
     */
    public function getAuctionConversionMetrics(array $filters = []): array
    {
        return $this->call('auction.getConversionMetrics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Batch operation: Get multiple auction statistics
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getBatchAuctionStatistics(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'auction.getStatistics',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get multiple auctions for analytics
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getBatchAuctionsForAnalytics(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'auction.get',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

