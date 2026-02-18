<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NotificationServiceAdapter for Order Service
 * 
 * Provides HTTP-like interface for RPC calls to the notification service.
 * Order service needs to send notifications for order status changes.
 */
class NotificationServiceAdapter
{
    private $notificationRpc;

    public function __construct()
    {
        $this->notificationRpc = app('NotificationRpc');
    }

    /**
     * Send order notification to user
     */
    public function sendOrderNotification(int $userId, string $type, array $data): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendOrderNotification', ['user_id' => $userId, 'type' => $type], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => $type,
                'data' => $data,
                'category' => 'order'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendOrderNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendOrderNotification', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send order status update notification
     */
    public function sendOrderStatusUpdate(int $userId, int $orderId, string $status, array $details = []): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendOrderStatusUpdate', ['user_id' => $userId, 'order_id' => $orderId, 'status' => $status], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => 'order_status_update',
                'data' => [
                    'order_id' => $orderId,
                    'status' => $status,
                    'details' => $details
                ],
                'category' => 'order'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendOrderStatusUpdate', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendOrderStatusUpdate', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send order confirmation notification
     */
    public function sendOrderConfirmation(int $userId, array $orderData): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendOrderConfirmation', ['user_id' => $userId, 'order_id' => $orderData['id'] ?? 'N/A'], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'user_id' => $userId,
                'type' => 'order_confirmation',
                'data' => $orderData,
                'category' => 'order'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendOrderConfirmation', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendOrderConfirmation', $e, $correlationId, $duration);
            return false;
        }
    }

    /**
     * Send bulk notifications to multiple users
     */
    public function sendBulkOrderNotification(array $userIds, string $type, array $data): bool
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendBulkOrderNotification', ['user_count' => count($userIds), 'type' => $type], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendBulkNotification', [
                'user_ids' => $userIds,
                'type' => $type,
                'data' => $data,
                'category' => 'order'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendBulkOrderNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            return isset($response['success']) && $response['success'];
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendBulkOrderNotification', $e, $correlationId, $duration);
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
     * Get notification history for user
     */
    public function getNotificationHistory(int $userId, int $limit = 50): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getNotificationHistory', ['user_id' => $userId, 'limit' => $limit], $correlationId);
            
            $response = $this->notificationRpc->call('notification.getNotificationHistory', [
                'user_id' => $userId,
                'limit' => $limit,
                'category' => 'order'
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getNotificationHistory', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getNotificationHistory', $e, $correlationId, $duration);
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
                'service' => 'order-service'
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
            'service' => 'order-service',
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
            'service' => 'order-service',
            'error' => $e->getMessage(),
            'duration_ms' => $duration,
            'trace' => $e->getTraceAsString()
        ]);
    }
}
