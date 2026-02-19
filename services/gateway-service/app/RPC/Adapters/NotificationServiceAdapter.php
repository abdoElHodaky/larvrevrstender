<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * NotificationServiceAdapter for Gateway Service
 * 
 * Provides HTTP-like interface for RPC calls to the notification service.
 * Gateway service needs notification operations for request routing and messaging.
 */
class NotificationServiceAdapter
{
    private $notificationRpc;

    public function __construct()
    {
        $this->notificationRpc = app('NotificationRpc');
    }

    /**
     * Send notification
     */
    public function sendNotification(array $notificationData): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('sendNotification', ['notification_data' => $notificationData], $correlationId);
            
            $response = $this->notificationRpc->call('notification.sendNotification', [
                'notification_data' => $notificationData
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('sendNotification', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('sendNotification', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, array $filters = []): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('getUserNotifications', ['user_id' => $userId, 'filters' => $filters], $correlationId);
            
            $response = $this->notificationRpc->call('notification.getUserNotifications', [
                'user_id' => $userId,
                'filters' => $filters
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('getUserNotifications', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('getUserNotifications', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): ?array
    {
        $startTime = microtime(true);
        $correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
        
        try {
            $this->logRpcCall('markAsRead', ['notification_id' => $notificationId], $correlationId);
            
            $response = $this->notificationRpc->call('notification.markAsRead', [
                'notification_id' => $notificationId
            ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcCall('markAsRead', ['duration_ms' => $duration], $correlationId, 'success');
            
            if (isset($response['success']) && $response['success']) {
                return $response['data'] ?? null;
            }
            
            return null;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logRpcError('markAsRead', $e, $correlationId, $duration);
            return null;
        }
    }

    /**
     * Log RPC call for debugging and monitoring
     */
    private function logRpcCall(string $method, array $params, string $correlationId, string $status = 'start'): void
    {
        Log::info("Gateway NotificationService RPC Call", [
            'method' => $method,
            'params' => $params,
            'correlation_id' => $correlationId,
            'status' => $status,
            'service' => 'notification-service',
            'caller' => 'gateway-service'
        ]);
    }

    /**
     * Log RPC error for debugging and monitoring
     */
    private function logRpcError(string $method, Exception $e, string $correlationId, float $duration): void
    {
        Log::error("Gateway NotificationService RPC Error", [
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $correlationId,
            'duration_ms' => $duration,
            'service' => 'notification-service',
            'caller' => 'gateway-service'
        ]);
    }
}
