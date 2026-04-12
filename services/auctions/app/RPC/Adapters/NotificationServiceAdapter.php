<?php

namespace App\RPC\Adapters;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

/**
 * NotificationServiceAdapter - Compatibility layer between HTTP client interface and RPC
 * 
 * Provides the same interface as NotificationServiceClient but routes calls through RPC
 * for seamless migration from HTTP to RPC communication.
 */
class NotificationServiceAdapter
{
    private $notificationRpcClient;
    private string $correlationId;

    public function __construct()
    {
        $this->notificationRpcClient = App::make('NotificationRpc');
        $this->correlationId = request()->header('X-Correlation-ID', uniqid('rpc_', true));
    }

    /**
     * Send a notification
     */
    public function send(array $notificationData): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->notificationRpcClient->call('notification.send', $notificationData);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('send', ['notification_data' => $notificationData], $response, $duration);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send notification',
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            $this->logRpcError('send', ['notification_data' => $notificationData], $e);
            
            return [
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send multiple notifications
     */
    public function sendBatch(array $notifications): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->notificationRpcClient->call('notification.sendBatch', [
                'notifications' => $notifications
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('sendBatch', ['notification_count' => count($notifications)], $response, $duration);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send batch notifications',
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            $this->logRpcError('sendBatch', ['notification_count' => count($notifications)], $e);
            
            return [
                'success' => false,
                'message' => 'Failed to send batch notifications',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get notification status
     */
    public function getStatus(string $notificationId): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->notificationRpcClient->call('notification.getStatus', [
                'notification_id' => $notificationId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getStatus', compact('notificationId'), $response, $duration);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get notification status',
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            $this->logRpcError('getStatus', compact('notificationId'), $e);
            
            return [
                'success' => false,
                'message' => 'Failed to get notification status',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user notification preferences
     */
    public function getUserPreferences(int $userId): array
    {
        try {
            $startTime = microtime(true);
            
            $response = $this->notificationRpcClient->call('notification.getUserPreferences', [
                'user_id' => $userId
            ]);

            $duration = microtime(true) - $startTime;
            $this->logRpcCall('getUserPreferences', compact('userId'), $response, $duration);

            if ($response['success'] ?? false) {
                return [
                    'success' => true,
                    'data' => $response['data'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to get user preferences',
                    'error' => $response['error'] ?? 'Unknown error'
                ];
            }
        } catch (\Exception $e) {
            $this->logRpcError('getUserPreferences', compact('userId'), $e);
            
            return [
                'success' => false,
                'message' => 'Failed to get user preferences',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Health check - compatibility method
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->notificationRpcClient->call('system.health');
            return $response['success'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get service info - compatibility method
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->notificationRpcClient->call('system.info');
            return $response['success'] ?? false ? $response['data'] ?? null : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log successful RPC call
     */
    private function logRpcCall(string $method, array $params, $response, float $duration): void
    {
        Log::info('RPC call completed', [
            'adapter' => 'NotificationServiceAdapter',
            'method' => $method,
            'duration' => round($duration * 1000, 2) . 'ms',
            'correlation_id' => $this->correlationId,
            'success' => $response['success'] ?? false,
            'service' => 'notification-service'
        ]);
    }

    /**
     * Log RPC call error
     */
    private function logRpcError(string $method, array $params, \Exception $e): void
    {
        Log::error('RPC call failed', [
            'adapter' => 'NotificationServiceAdapter',
            'method' => $method,
            'error' => $e->getMessage(),
            'correlation_id' => $this->correlationId,
            'service' => 'notification-service'
        ]);
    }
}
