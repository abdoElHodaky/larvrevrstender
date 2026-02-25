<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * PaymentServiceAdapter for Order Service
 * 
 * Provides HTTP-like interface for RPC calls to the payment service.
 * Order service needs payment operations for invoice and escrow management.
 */
class PaymentServiceAdapter
{
    private $paymentRpc;

    public function __construct()
    {
        $this->paymentRpc = app('PaymentRpc');
    }

    /**
     * Create invoice from order data
     */
    public function createInvoice(array $invoiceData): array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('createInvoice', ['invoice_data' => $invoiceData], $correlationId);
            
            $response = $this->paymentRpc->call('payment.createInvoice', [
                'invoice_data' => $invoiceData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('createInvoice', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? null,
                    'message' => $response['message'] ?? 'Invoice created successfully'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => $response['message'] ?? 'Failed to create invoice'
            ];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('createInvoice', $e, $correlationId, $duration);
            return [
                'success' => false,
                'data' => null,
                'message' => 'RPC call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send invoice to customer
     */
    public function sendInvoice(int $invoiceId): array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendInvoice', ['invoice_id' => $invoiceId], $correlationId);
            
            $response = $this->paymentRpc->call('payment.sendInvoice', [
                'invoice_id' => $invoiceId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendInvoice', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? null,
                    'message' => $response['message'] ?? 'Invoice sent successfully'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => $response['message'] ?? 'Failed to send invoice'
            ];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendInvoice', $e, $correlationId, $duration);
            return [
                'success' => false,
                'data' => null,
                'message' => 'RPC call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get invoice by ID
     */
    public function getInvoice(int $invoiceId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getInvoice', ['invoice_id' => $invoiceId], $correlationId);
            
            $response = $this->paymentRpc->call('payment.getInvoice', [
                'invoice_id' => $invoiceId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getInvoice', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getInvoice', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Create escrow account
     */
    public function createEscrow(array $escrowData): array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('createEscrow', ['escrow_data' => $escrowData], $correlationId);
            
            $response = $this->paymentRpc->call('payment.createEscrow', [
                'escrow_data' => $escrowData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('createEscrow', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? null,
                    'message' => $response['message'] ?? 'Escrow created successfully'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => $response['message'] ?? 'Failed to create escrow'
            ];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('createEscrow', $e, $correlationId, $duration);
            return [
                'success' => false,
                'data' => null,
                'message' => 'RPC call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Fund escrow account
     */
    public function fundEscrow(int $escrowId, array $paymentData): array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('fundEscrow', ['escrow_id' => $escrowId, 'payment_data' => $paymentData], $correlationId);
            
            $response = $this->paymentRpc->call('payment.fundEscrow', [
                'escrow_id' => $escrowId,
                'payment_data' => $paymentData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('fundEscrow', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? null,
                    'message' => $response['message'] ?? 'Escrow funded successfully'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => $response['message'] ?? 'Failed to fund escrow'
            ];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('fundEscrow', $e, $correlationId, $duration);
            return [
                'success' => false,
                'data' => null,
                'message' => 'RPC call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Release escrow funds
     */
    public function releaseEscrow(int $escrowId, array $data): array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('releaseEscrow', ['escrow_id' => $escrowId, 'data' => $data], $correlationId);
            
            $response = $this->paymentRpc->call('payment.releaseEscrow', [
                'escrow_id' => $escrowId,
                'release_data' => $data
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('releaseEscrow', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? null,
                    'message' => $response['message'] ?? 'Escrow released successfully'
                ];
            }
            
            return [
                'success' => false,
                'data' => null,
                'message' => $response['message'] ?? 'Failed to release escrow'
            ];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('releaseEscrow', $e, $correlationId, $duration);
            return [
                'success' => false,
                'data' => null,
                'message' => 'RPC call failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get escrow by order ID
     */
    public function getEscrowByOrderId(int $orderId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getEscrowByOrderId', ['order_id' => $orderId], $correlationId);
            
            $response = $this->paymentRpc->call('payment.getEscrowByOrderId', [
                'order_id' => $orderId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getEscrowByOrderId', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getEscrowByOrderId', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $data, string $correlationId, string $status = 'start'): void
    {
        Log::info("PaymentServiceAdapter RPC call", [
            'method' => $method,
            'status' => $status,
            'correlation_id' => $correlationId,
            'service' => 'payment-service',
            'caller' => 'order-service',
            'data' => $data
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("PaymentServiceAdapter RPC error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'service' => 'payment-service',
            'caller' => 'order-service',
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
