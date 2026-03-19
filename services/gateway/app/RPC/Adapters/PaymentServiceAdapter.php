<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * PaymentServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the payment service.
 * Gateway service needs payment operations for request routing and transaction handling.
 */
class PaymentServiceAdapter
{
    private $paymentRpc;

    public function __construct()
    {
        $this->paymentRpc = app('PaymentRpc');
    }

    /**
     * Process a payment
     */
    public function processPayment(array $paymentData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('processPayment', ['payment_data' => $paymentData], $correlationId);
            
            $response = $this->paymentRpc->call('payment.processPayment', [
                'payment_data' => $paymentData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('processPayment', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('processPayment', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get payment by ID
     */
    public function getPayment(int $paymentId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getPayment', ['payment_id' => $paymentId], $correlationId);
            
            $response = $this->paymentRpc->call('payment.getPayment', [
                'payment_id' => $paymentId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getPayment', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getPayment', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $transactionId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getPaymentStatus', ['transaction_id' => $transactionId], $correlationId);
            
            $response = $this->paymentRpc->call('payment.getPaymentStatus', [
                'transaction_id' => $transactionId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getPaymentStatus', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getPaymentStatus', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Refund a payment
     */
    public function refundPayment(int $paymentId, float $amount = null): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('refundPayment', ['payment_id' => $paymentId, 'amount' => $amount], $correlationId);
            
            $response = $this->paymentRpc->call('payment.refundPayment', [
                'payment_id' => $paymentId,
                'amount' => $amount
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('refundPayment', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('refundPayment', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get payment methods for a user
     */
    public function getPaymentMethods(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getPaymentMethods', ['user_id' => $userId], $correlationId);
            
            $response = $this->paymentRpc->call('payment.getPaymentMethods', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getPaymentMethods', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getPaymentMethods', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats(array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getPaymentStats', ['filters' => $filters], $correlationId);
            
            $response = $this->paymentRpc->call('payment.getPaymentStats', [
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getPaymentStats', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getPaymentStats', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway PaymentService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'payment-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway PaymentService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'payment-service',
            'caller' => 'gateway-service'
        ]);
    }
}
