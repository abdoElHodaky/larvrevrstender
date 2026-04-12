<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Sajya\Client\Client;
use Exception;

/**
 * Order Service RPC Adapter for Payment Service
 * 
 * Provides semantic methods for interacting with order-service via RPC.
 * Used by payment workflow activities to maintain order state consistency.
 */
class OrderServiceAdapter
{
    private Client $orderRpc;
    private string $correlationId;

    public function __construct()
    {
        $this->orderRpc = app('OrderRpc');
        $this->correlationId = uniqid('payment-order-', true);
    }

    /**
     * Get order by ID with customer validation
     *
     * @param int $orderId Order ID to retrieve
     * @param int|null $customerId Customer ID for access validation
     * @return array|null Order data or null on failure
     */
    public function getOrderById(int $orderId, ?int $customerId = null): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'order_id' => $orderId,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'payment-service',
                'timestamp' => now()->toISOString()
            ];

            if ($customerId) {
                $params['customer_id'] = $customerId;
            }

            Log::info('OrderServiceAdapter: Getting order by ID', [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.getById', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Order retrieved successfully', [
                    'order_id' => $orderId,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Order retrieval failed', [
                'order_id' => $orderId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Order retrieval error', [
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Update order status after payment processing
     *
     * @param int $orderId Order ID to update
     * @param string $status New order status
     * @param array $paymentData Payment context data
     * @return array|null Update result or null on failure
     */
    public function updateOrderStatus(int $orderId, string $status, array $paymentData = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'order_id' => $orderId,
                'status' => $status,
                'payment_id' => $paymentData['payment_id'] ?? null,
                'payment_reference' => $paymentData['payment_reference'] ?? null,
                'payment_amount' => $paymentData['amount'] ?? null,
                'payment_currency' => $paymentData['currency'] ?? 'USD',
                'payment_method' => $paymentData['payment_method'] ?? null,
                'payment_confirmed_at' => $paymentData['confirmed_at'] ?? now()->toISOString(),
                'updated_by' => 'payment_service',
                'saga_id' => $paymentData['saga_id'] ?? null,
                'update_reason' => 'payment_status_change',
                'correlation_id' => $this->correlationId,
                'timestamp' => now()->toISOString()
            ];

            Log::info('OrderServiceAdapter: Updating order status', [
                'order_id' => $orderId,
                'new_status' => $status,
                'payment_id' => $paymentData['payment_id'] ?? null,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.updateStatus', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Order status updated successfully', [
                    'order_id' => $orderId,
                    'new_status' => $status,
                    'previous_status' => $response['data']['previous_status'] ?? null,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Order status update failed', [
                'order_id' => $orderId,
                'status' => $status,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Order status update error', [
                'order_id' => $orderId,
                'status' => $status,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Restore order status (compensation action)
     *
     * @param int $orderId Order ID to restore
     * @param string $previousStatus Status to restore to
     * @param array $compensationData Compensation context data
     * @return array|null Restoration result or null on failure
     */
    public function restoreOrderStatus(int $orderId, string $previousStatus, array $compensationData = []): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'order_id' => $orderId,
                'status' => $previousStatus,
                'restoration_reason' => $compensationData['reason'] ?? 'payment_failed_compensation',
                'failed_payment_id' => $compensationData['payment_id'] ?? null,
                'failed_payment_reference' => $compensationData['payment_reference'] ?? null,
                'compensation_type' => 'payment_failure',
                'restored_by' => 'payment_service_compensation',
                'saga_id' => $compensationData['saga_id'] ?? null,
                'correlation_id' => $this->correlationId,
                'timestamp' => now()->toISOString()
            ];

            Log::info('OrderServiceAdapter: Restoring order status (compensation)', [
                'order_id' => $orderId,
                'previous_status' => $previousStatus,
                'reason' => $compensationData['reason'] ?? 'payment_failed_compensation',
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.restoreStatus', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Order status restored successfully', [
                    'order_id' => $orderId,
                    'restored_status' => $previousStatus,
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Order status restoration failed', [
                'order_id' => $orderId,
                'previous_status' => $previousStatus,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Order status restoration error', [
                'order_id' => $orderId,
                'previous_status' => $previousStatus,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Get order payment history
     *
     * @param int $orderId Order ID to get payment history for
     * @return array|null Payment history or null on failure
     */
    public function getOrderPaymentHistory(int $orderId): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'order_id' => $orderId,
                'include_payment_details' => true,
                'correlation_id' => $this->correlationId,
                'requested_by' => 'payment-service',
                'timestamp' => now()->toISOString()
            ];

            Log::info('OrderServiceAdapter: Getting order payment history', [
                'order_id' => $orderId,
                'correlation_id' => $this->correlationId
            ]);

            $response = $this->orderRpc->call('order.getPaymentHistory', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response && isset($response['success']) && $response['success']) {
                Log::info('OrderServiceAdapter: Payment history retrieved successfully', [
                    'order_id' => $orderId,
                    'payment_count' => count($response['data']['payments'] ?? []),
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response['data'] ?? $response;
            }

            Log::warning('OrderServiceAdapter: Payment history retrieval failed', [
                'order_id' => $orderId,
                'response' => $response,
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Payment history retrieval error', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }

    /**
     * Check order service health
     *
     * @return array|null Service health status or null on failure
     */
    public function getServiceInfo(): ?array
    {
        $startTime = microtime(true);
        
        try {
            $params = [
                'correlation_id' => $this->correlationId,
                'requested_by' => 'payment-service',
                'timestamp' => now()->toISOString()
            ];

            $response = $this->orderRpc->call('order.getServiceInfo', $params);

            $duration = microtime(true) - $startTime;
            
            if ($response) {
                Log::info('OrderServiceAdapter: Service info retrieved', [
                    'duration_ms' => round($duration * 1000, 2),
                    'correlation_id' => $this->correlationId
                ]);

                return $response;
            }

            return null;

        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            Log::error('OrderServiceAdapter: Service info error', [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
                'correlation_id' => $this->correlationId
            ]);

            return null;
        }
    }
}
