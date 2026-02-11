<?php

namespace App\Http\Clients;

use Illuminate\Support\Facades\Log;

class NotificationServiceClient extends BaseServiceClient
{
    protected string $serviceName = 'notification-service';
    protected string $baseUrl;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.notification_service.url', 'http://notification-service:8000');
    }

    /**
     * Send a notification
     */
    public function send(array $notificationData): array
    {
        try {
            $response = $this->makeRequest('POST', '/api/notifications/send', $notificationData);
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to send notification via service client', [
                'error' => $e->getMessage(),
                'notification_data' => $notificationData
            ]);
            
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
            $response = $this->makeRequest('POST', '/api/notifications/send-batch', [
                'notifications' => $notifications
            ]);
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to send batch notifications via service client', [
                'error' => $e->getMessage(),
                'notification_count' => count($notifications)
            ]);
            
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
            $response = $this->makeRequest('GET', "/api/notifications/{$notificationId}/status");
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get notification status via service client', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId
            ]);
            
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
            $response = $this->makeRequest('GET', "/api/notifications/users/{$userId}/preferences");
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get user notification preferences via service client', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to get user preferences',
                'error' => $e->getMessage()
            ];
        }
    }
}
