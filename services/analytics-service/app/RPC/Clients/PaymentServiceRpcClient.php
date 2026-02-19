<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Payment Service (Analytics Context)
 * 
 * Provides RPC-based communication with the payment service for
 * collecting payment data and financial metrics for analytics purposes.
 */
class PaymentServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('payment-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get payment details for analytics
     *
     * @param int $paymentId Payment ID
     * @return array RPC response with payment details
     */
    public function getPaymentForAnalytics(int $paymentId): array
    {
        return $this->call('payment.get', [
            'payment_id' => $paymentId,
        ]);
    }
    
    /**
     * Get user payments for analytics
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with payment data
     */
    public function getUserPaymentsForAnalytics(int $userId, array $filters = [], int $limit = 500, int $offset = 0): array
    {
        return $this->call('payment.getUserPayments', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get auction payments for analytics
     *
     * @param int $auctionId Auction ID
     * @param array $filters Optional filters
     * @return array RPC response with auction payment data
     */
    public function getAuctionPaymentsForAnalytics(int $auctionId, array $filters = []): array
    {
        return $this->call('payment.getAuctionPayments', [
            'auction_id' => $auctionId,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment statistics for analytics
     *
     * @param array $filters Optional filters (date_range, status, etc.)
     * @return array RPC response with payment statistics
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        return $this->call('payment.getStatistics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment method analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with payment method analytics
     */
    public function getPaymentMethodAnalytics(array $filters = []): array
    {
        return $this->call('payment.getMethodAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment success rate metrics
     *
     * @param array $filters Optional filters
     * @return array RPC response with success rate metrics
     */
    public function getPaymentSuccessRateMetrics(array $filters = []): array
    {
        return $this->call('payment.getSuccessRateMetrics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment failure analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with failure analytics
     */
    public function getPaymentFailureAnalytics(array $filters = []): array
    {
        return $this->call('payment.getFailureAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment processing time metrics
     *
     * @param array $filters Optional filters
     * @return array RPC response with processing time metrics
     */
    public function getPaymentProcessingTimeMetrics(array $filters = []): array
    {
        return $this->call('payment.getProcessingTimeMetrics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get revenue analytics
     *
     * @param array $filters Optional filters (date_range, category, etc.)
     * @return array RPC response with revenue analytics
     */
    public function getRevenueAnalytics(array $filters = []): array
    {
        return $this->call('payment.getRevenueAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get refund analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with refund analytics
     */
    public function getRefundAnalytics(array $filters = []): array
    {
        return $this->call('payment.getRefundAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment volume trends
     *
     * @param string $period Period for trend analysis (daily, weekly, monthly)
     * @param array $filters Optional filters
     * @return array RPC response with volume trends
     */
    public function getPaymentVolumeTrends(string $period, array $filters = []): array
    {
        return $this->call('payment.getVolumeTrends', [
            'period' => $period,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment conversion funnel
     *
     * @param array $filters Optional filters
     * @return array RPC response with conversion funnel data
     */
    public function getPaymentConversionFunnel(array $filters = []): array
    {
        return $this->call('payment.getConversionFunnel', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payments by date range for analytics
     *
     * @param string $startDate Start date
     * @param string $endDate End date
     * @param array $additionalFilters Additional filters
     * @return array RPC response with payment data
     */
    public function getPaymentsByDateRange(string $startDate, string $endDate, array $additionalFilters = []): array
    {
        $filters = array_merge([
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ], $additionalFilters);
        
        return $this->call('payment.list', [
            'filters' => $filters,
            'limit' => 1000, // Large limit for analytics
            'offset' => 0,
        ]);
    }
    
    /**
     * Get escrow payment analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with escrow analytics
     */
    public function getEscrowPaymentAnalytics(array $filters = []): array
    {
        return $this->call('payment.getEscrowAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get payment fraud analytics
     *
     * @param array $filters Optional filters
     * @return array RPC response with fraud analytics
     */
    public function getPaymentFraudAnalytics(array $filters = []): array
    {
        return $this->call('payment.getFraudAnalytics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Batch operation: Get multiple payment statistics
     *
     * @param array $paymentIds Array of payment IDs
     * @return array Array of RPC responses
     */
    public function getBatchPaymentDetails(array $paymentIds): array
    {
        $calls = [];
        foreach ($paymentIds as $paymentId) {
            $calls[] = [
                'method' => 'payment.get',
                'params' => ['payment_id' => $paymentId],
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get multiple auction payment data
     *
     * @param array $auctionIds Array of auction IDs
     * @return array Array of RPC responses
     */
    public function getBatchAuctionPayments(array $auctionIds): array
    {
        $calls = [];
        foreach ($auctionIds as $auctionId) {
            $calls[] = [
                'method' => 'payment.getAuctionPayments',
                'params' => ['auction_id' => $auctionId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

