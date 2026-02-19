<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NotificationServiceAdapter for Payment Service
 * 
 * Provides HTTP-like interface for RPC calls to the notification service.
 * Payment service needs to send notifications for payment status changes.
 */
class NotificationServiceAdapter
{
    private $notificationRpc;

    public function __construct()
    {
        $this->notificationRpc = app('NotificationRpc');
    }

    /**
     * Send payment notification to user
     */
    public function sendPaymentNotification(int $userId, string $type, array $data): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendPaymentNotification', ['user_id' => $userId, 'type' => $type], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => $type,
                'data' => $data,
                'category' => 'payment'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendPaymentNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendPaymentNotification', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send payment success notification
     */
    public function sendPaymentSuccess(int $userId, array $paymentData): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendPaymentSuccess', ['user_id' => $userId, 'payment_id' => $paymentData['id'] ?? 'N/A'], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => 'payment_success',
                'data' => $paymentData,
                'category' => 'payment'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendPaymentSuccess', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendPaymentSuccess', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send payment failure notification
     */
    public function sendPaymentFailure(int $userId, array $paymentData, string $reason): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendPaymentFailure', ['user_id' => $userId, 'payment_id' => $paymentData['id'] ?? 'N/A', 'reason' => $reason], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => 'payment_failure',
                'data' => array_merge($paymentData, ['failure_reason' => $reason]),
                'category' => 'payment'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendPaymentFailure', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendPaymentFailure', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send refund notification
     */
    public function sendRefundNotification(int $userId, array $refundData): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendRefundNotification', ['user_id' => $userId, 'refund_id' => $refundData['id'] ?? 'N/A'], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => 'payment_refund',
                'data' => $refundData,
                'category' => 'payment'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendRefundNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendRefundNotification', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send payment method update notification
     */
    public function sendPaymentMethodUpdate(int $userId, array $methodData): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendPaymentMethodUpdate', ['user_id' => $userId], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => 'payment_method_update',
                'data' => $methodData,
                'category' => 'payment'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendPaymentMethodUpdate', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendPaymentMethodUpdate', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send bulk payment notifications
     */
    public function sendBulkPaymentNotification(array $userIds, string $type, array $data): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendBulkPaymentNotification', ['user_count' => count($userIds), 'type' => $type], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendBulkNotification', [
                'user_ids' => $userIds,
                'type' => $type,
                'data' => $data,
                'category' => 'payment'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendBulkPaymentNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendBulkPaymentNotification', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Get notification preferences for user
     */
    public function getNotificationPreferences(int $userId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getNotificationPreferences', ['user_id' => $userId], $correlationId);
            
            $response = $this->notificationRpc->call('notification.getNotificationPreferences', [
                'user_id' => $userId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getNotificationPreferences', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getNotificationPreferences', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get service health status
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->notificationRpc->call('notification.getServiceInfo');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            Log::warning('Failed to get NotificationService info', [
                'error' => $e->getMessage(),
                'service' => 'payment-service'
            ]);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $context, string $correlationId, string $status = 'start'): void
    {
        Log::info("RPC Call: notification.{$method} ({$status})", [
            'method' => "notification.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'payment-service',
            'status' => $status,
            'context' => $context
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("RPC Error: notification.{$method}", [
            'method' => "notification.{$method}",
            'correlation_id' => $correlationId,
            'service' => 'payment-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
