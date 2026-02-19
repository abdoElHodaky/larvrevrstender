<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * Payment Service RPC Adapter for Analytics Service
 * 
 * Provides semantic methods for interacting with payment-service via RPC.
 * Used by analytics service to collect payment data and financial metrics.
 */
class PaymentServiceAdapter
{
    private Client $paymentRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->paymentRpc = app('PaymentRpc');
        $this->correlationId = uniqid('analytics-payment-', true);
    }

    /**
     * Get payment by ID for analytics
     *
     * @param int $paymentId Payment ID to retrieve
     * @return array|null Payment data or null on failure
     */
    public function getPaymentById(int $paymentId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'payment_id' => $paymentId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('PaymentServiceAdapter: Getting payment for analytics', [
                'payment_id' => $paymentId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->paymentRpc->call('payment.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('PaymentServiceAdapter: Payment data retrieved for analytics', [
                    'payment_id' => $paymentId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('PaymentServiceAdapter: Payment data retrieval failed', [
                'payment_id' => $paymentId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('PaymentServiceAdapter: Payment data retrieval error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get payments by date range for analytics
     *
     * @param string $startDate Start date (Y-m-d format)
     * @param string $endDate End date (Y-m-d format)
     * @param array $filters Optional filters
     * @return array|null Payments data or null on failure
     */
    public function getPaymentsByDateRange(string $startDate, string $endDate, array $filters = []): ?array
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

            Log::info('PaymentServiceAdapter: Getting payments by date range for analytics', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->paymentRpc->call('payment.getByDateRange', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('PaymentServiceAdapter: Payments retrieved for analytics', [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'payment_count' => count($response['data']['payments'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('PaymentServiceAdapter: Payments retrieval failed', [
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
            
            Log::error('PaymentServiceAdapter: Payments retrieval error', [
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
     * Get payment metrics for analytics
     *
     * @param array $filters Filters for metrics calculation
     * @return array|null Payment metrics data or null on failure
     */
    public function getPaymentMetrics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('PaymentServiceAdapter: Getting payment metrics for analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->paymentRpc->call('payment.getMetrics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('PaymentServiceAdapter: Payment metrics retrieved for analytics', [
                    'metrics_count' => count($response['data'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('PaymentServiceAdapter: Payment metrics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('PaymentServiceAdapter: Payment metrics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get payment status history for analytics
     *
     * @param int $paymentId Payment ID to get status history for
     * @return array|null Payment status history or null on failure
     */
    public function getPaymentStatusHistory(int $paymentId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'payment_id' => $paymentId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('PaymentServiceAdapter: Getting payment status history for analytics', [
                'payment_id' => $paymentId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->paymentRpc->call('payment.getStatusHistory', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('PaymentServiceAdapter: Payment status history retrieved for analytics', [
                    'payment_id' => $paymentId,
                    'history_count' => count($response['data']['history'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('PaymentServiceAdapter: Payment status history retrieval failed', [
                'payment_id' => $paymentId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('PaymentServiceAdapter: Payment status history retrieval error', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get revenue analytics data
     *
     * @param array $filters Filters for revenue calculation
     * @return array|null Revenue analytics data or null on failure
     */
    public function getRevenueAnalytics(array $filters = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = array_merge($filters, [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'analytics-service',
                'timestamp' => now()->toISOString()
            ]);

            Log::info('PaymentServiceAdapter: Getting revenue analytics', [
                'filters' => $filters,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->paymentRpc->call('payment.getRevenueAnalytics', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('PaymentServiceAdapter: Revenue analytics retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('PaymentServiceAdapter: Revenue analytics retrieval failed', [
                'filters' => $filters,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('PaymentServiceAdapter: Revenue analytics retrieval error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Check payment service health
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

            $response = $this->paymentRpc->call('payment.getServiceInfo', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response) {
                Log::info('PaymentServiceAdapter: Service info retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response;
            }

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('PaymentServiceAdapter: Service info error', [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }
}
