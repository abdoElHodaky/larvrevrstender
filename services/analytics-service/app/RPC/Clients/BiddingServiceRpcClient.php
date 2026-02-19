<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Bidding Service (Analytics Context)
 * 
 * Provides RPC-based communication with the bidding service for
 * collecting bidding data and metrics for analytics purposes.
 */
class BiddingServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('bidding-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get bid details for analytics
     *
     * @param int $bidId Bid ID
     * @return array RPC response with bid details
     */
    public function getBidForAnalytics(int $bidId): array
    {
        return $this->call('bid.get', [
            'bid_id' => $bidId,
        ]);
    }
    
    /**
     * Get auction bids for analytics
     *
     * @param int $auctionId Auction ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with bid data
     */
    public function getAuctionBidsForAnalytics(int $auctionId, array $filters = [], int $limit = 500, int $offset = 0): array
    {
        return $this->call('bid.getByAuction', [
            'auction_id' => $auctionId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get user bids for analytics
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with user bid data
     */
    public function getUserBidsForAnalytics(int $userId, array $filters = [], int $limit = 500, int $offset = 0): array
    {
        return $this->call('bid.getUserBids', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get bid statistics for auction
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with bid statistics
     */
    public function getBidStatistics(int $auctionId): array
    {
        return $this->call('bid.getStatistics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get bid history for analytics
     *
     * @param int $auctionId Auction ID
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with bid history
     */
    public function getBidHistoryForAnalytics(int $auctionId, int $limit = 1000, int $offset = 0): array
    {
        return $this->call('bid.getHistory', [
            'auction_id' => $auctionId,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => 'created_at',
            'order_direction' => 'asc',
        ]);
    }
    
    /**
     * Get bidding patterns for analytics
     *
     * @param array $filters Filters for pattern analysis
     * @return array RPC response with bidding patterns
     */
    public function getBiddingPatterns(array $filters = []): array
    {
        return $this->call('bid.getBiddingPatterns', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get bid frequency metrics
     *
     * @param int $userId User ID
     * @param array $dateRange Date range for analysis
     * @return array RPC response with frequency metrics
     */
    public function getBidFrequencyMetrics(int $userId, array $dateRange = []): array
    {
        return $this->call('bid.getFrequencyMetrics', [
            'user_id' => $userId,
            'date_range' => $dateRange,
        ]);
    }
    
    /**
     * Get bid success rate metrics
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @return array RPC response with success rate metrics
     */
    public function getBidSuccessRateMetrics(int $userId, array $filters = []): array
    {
        return $this->call('bid.getSuccessRateMetrics', [
            'user_id' => $userId,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get bid timing analytics
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with timing analytics
     */
    public function getBidTimingAnalytics(int $auctionId): array
    {
        return $this->call('bid.getTimingAnalytics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get bid amount distribution
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with amount distribution
     */
    public function getBidAmountDistribution(int $auctionId): array
    {
        return $this->call('bid.getAmountDistribution', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get competitive bidding metrics
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with competitive metrics
     */
    public function getCompetitiveBiddingMetrics(int $auctionId): array
    {
        return $this->call('bid.getCompetitiveMetrics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Get bids by date range for analytics
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param array $additionalFilters Additional filters
     * @return array RPC response with bid data
     */
    public function getBidsByDateRange(string $startDate, string $endDate, array $additionalFilters = []): array
    {
        $filters = array_merge([
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ], $additionalFilters);
        
        return $this->call('bid.list', [
            'filters' => $filters,
            'limit' => 2000, // Large limit for analytics
            'offset' => 0,
        ]);
    }
    
    /**
     * Batch operation: Get multiple bid statistics
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getBatchBidStatistics(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'bid.getStatistics',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get multiple bid histories
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getBatchBidHistories(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'bid.getHistory',
                'params' => [
                    'auction_id' => $auctionId,
                    'limit' => 1000,
                    'offset' => 0,
                ],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

