<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Exception;

/**
 * Web Push Notification Micro Procedure
 * 
 * Provides comprehensive web push notification infrastructure for cross-service operations.
 * Integrates with the notification service to send web push notifications via RPC.
 */
trait WebPushProcedure
{
    /**
     * Send web push notification to specific user
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendWebPushToUser(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
                'title' => ['required' => true, 'type' => 'string'],
                'body' => ['required' => true, 'type' => 'string'],
                'icon' => ['type' => 'string'],
                'badge' => ['type' => 'string'],
                'image' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'actions' => ['type' => 'array'],
                'category' => ['type' => 'string'],
                'urgency' => ['type' => 'string'],
                'ttl' => ['type' => 'integer'],
                'tag' => ['type' => 'string'],
                'require_interaction' => ['type' => 'boolean'],
                'silent' => ['type' => 'boolean'],
                'vibrate' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Generate notification ID
            $notificationId = $this->generateNotificationId('webpush');

            // Call notification service via RPC
            $rpcResult = $this->callNotificationServiceRPC('webpush.sendToUser', $params);

            if (!$rpcResult['success']) {
                return $this->errorResponse('Web push notification sending failed', $rpcResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'webpush', [
                'user_id' => $params['user_id'],
                'title' => $params['title'],
                'body' => substr($params['body'], 0, 100) . '...',
                'category' => $params['category'] ?? 'system',
                'status' => 'sent',
                'sent_count' => $rpcResult['data']['sent'] ?? 0,
                'failed_count' => $rpcResult['data']['failed'] ?? 0,
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('webpush_sent', 1, [
                'user_id' => $params['user_id'],
                'category' => $params['category'] ?? 'system',
                'success' => $rpcResult['success']
            ]);

            $this->log('info', 'Web push notification sent to user', [
                'notification_id' => $notificationId,
                'user_id' => $params['user_id'],
                'title' => $params['title'],
                'sent_count' => $rpcResult['data']['sent'] ?? 0
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'webpush',
                'user_id' => $params['user_id'],
                'sent_count' => $rpcResult['data']['sent'] ?? 0,
                'failed_count' => $rpcResult['data']['failed'] ?? 0,
                'sent_at' => now()->toISOString()
            ], 'Web push notification sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'Web push notification to user failed', [
                'error' => $e->getMessage(),
                'user_id' => $params['user_id'] ?? null
            ]);

            return $this->errorResponse('Web push notification sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send web push notification to multiple users
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendWebPushToUsers(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_ids' => ['required' => true, 'type' => 'array'],
                'title' => ['required' => true, 'type' => 'string'],
                'body' => ['required' => true, 'type' => 'string'],
                'icon' => ['type' => 'string'],
                'badge' => ['type' => 'string'],
                'image' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'actions' => ['type' => 'array'],
                'category' => ['type' => 'string'],
                'urgency' => ['type' => 'string'],
                'ttl' => ['type' => 'integer'],
                'tag' => ['type' => 'string'],
                'require_interaction' => ['type' => 'boolean'],
                'silent' => ['type' => 'boolean'],
                'vibrate' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Generate notification ID
            $notificationId = $this->generateNotificationId('webpush');

            // Call notification service via RPC
            $rpcResult = $this->callNotificationServiceRPC('webpush.sendToUsers', $params);

            if (!$rpcResult['success']) {
                return $this->errorResponse('Web push notification sending failed', $rpcResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'webpush', [
                'user_count' => count($params['user_ids']),
                'title' => $params['title'],
                'body' => substr($params['body'], 0, 100) . '...',
                'category' => $params['category'] ?? 'system',
                'status' => 'sent',
                'sent_count' => $rpcResult['data']['sent'] ?? 0,
                'failed_count' => $rpcResult['data']['failed'] ?? 0,
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('webpush_bulk_sent', 1, [
                'user_count' => count($params['user_ids']),
                'category' => $params['category'] ?? 'system',
                'success' => $rpcResult['success']
            ]);

            $this->log('info', 'Web push notification sent to multiple users', [
                'notification_id' => $notificationId,
                'user_count' => count($params['user_ids']),
                'title' => $params['title'],
                'sent_count' => $rpcResult['data']['sent'] ?? 0
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'webpush',
                'user_count' => count($params['user_ids']),
                'sent_count' => $rpcResult['data']['sent'] ?? 0,
                'failed_count' => $rpcResult['data']['failed'] ?? 0,
                'sent_at' => now()->toISOString()
            ], 'Web push notification sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'Web push notification to users failed', [
                'error' => $e->getMessage(),
                'user_count' => count($params['user_ids'] ?? [])
            ]);

            return $this->errorResponse('Web push notification sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send web push notification to all active subscriptions
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendWebPushToAll(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'title' => ['required' => true, 'type' => 'string'],
                'body' => ['required' => true, 'type' => 'string'],
                'icon' => ['type' => 'string'],
                'badge' => ['type' => 'string'],
                'image' => ['type' => 'string'],
                'url' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'actions' => ['type' => 'array'],
                'category' => ['type' => 'string'],
                'urgency' => ['type' => 'string'],
                'ttl' => ['type' => 'integer'],
                'tag' => ['type' => 'string'],
                'require_interaction' => ['type' => 'boolean'],
                'silent' => ['type' => 'boolean'],
                'vibrate' => ['type' => 'array'],
                'device_types' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Generate notification ID
            $notificationId = $this->generateNotificationId('webpush');

            // Call notification service via RPC
            $rpcResult = $this->callNotificationServiceRPC('webpush.sendToAll', $params);

            if (!$rpcResult['success']) {
                return $this->errorResponse('Web push broadcast failed', $rpcResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'webpush', [
                'type' => 'broadcast',
                'title' => $params['title'],
                'body' => substr($params['body'], 0, 100) . '...',
                'category' => $params['category'] ?? 'system',
                'device_types' => $params['device_types'] ?? ['all'],
                'status' => 'sent',
                'sent_count' => $rpcResult['data']['sent'] ?? 0,
                'failed_count' => $rpcResult['data']['failed'] ?? 0,
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('webpush_broadcast_sent', 1, [
                'category' => $params['category'] ?? 'system',
                'device_types' => $params['device_types'] ?? ['all'],
                'success' => $rpcResult['success']
            ]);

            $this->log('info', 'Web push broadcast notification sent', [
                'notification_id' => $notificationId,
                'title' => $params['title'],
                'sent_count' => $rpcResult['data']['sent'] ?? 0
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'webpush_broadcast',
                'sent_count' => $rpcResult['data']['sent'] ?? 0,
                'failed_count' => $rpcResult['data']['failed'] ?? 0,
                'sent_at' => now()->toISOString()
            ], 'Web push broadcast sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'Web push broadcast failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Web push broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Subscribe user to web push notifications
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function subscribeWebPush(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
                'endpoint' => ['required' => true, 'type' => 'string'],
                'public_key' => ['required' => true, 'type' => 'string'],
                'auth_token' => ['required' => true, 'type' => 'string'],
                'content_encoding' => ['type' => 'string'],
                'user_agent' => ['type' => 'string'],
                'device_type' => ['type' => 'string'],
                'browser' => ['type' => 'string'],
                'platform' => ['type' => 'string'],
                'notification_preferences' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Call notification service via RPC
            $rpcResult = $this->callNotificationServiceRPC('webpush.subscribe', $params);

            if (!$rpcResult['success']) {
                return $this->errorResponse('Web push subscription failed', $rpcResult);
            }

            $this->recordMetric('webpush_subscription_created', 1, [
                'user_id' => $params['user_id'],
                'device_type' => $params['device_type'] ?? 'unknown'
            ]);

            $this->log('info', 'Web push subscription created', [
                'user_id' => $params['user_id'],
                'subscription_id' => $rpcResult['data']['subscription_id'] ?? null
            ]);

            return $this->successResponse($rpcResult['data'], 'Web push subscription created successfully');

        } catch (Exception $e) {
            $this->log('error', 'Web push subscription failed', [
                'error' => $e->getMessage(),
                'user_id' => $params['user_id'] ?? null
            ]);

            return $this->errorResponse('Web push subscription failed: ' . $e->getMessage());
        }
    }

    /**
     * Unsubscribe user from web push notifications
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function unsubscribeWebPush(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
                'endpoint' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Call notification service via RPC
            $rpcResult = $this->callNotificationServiceRPC('webpush.unsubscribe', $params);

            if (!$rpcResult['success']) {
                return $this->errorResponse('Web push unsubscription failed', $rpcResult);
            }

            $this->recordMetric('webpush_subscription_removed', 1, [
                'user_id' => $params['user_id']
            ]);

            $this->log('info', 'Web push subscription removed', [
                'user_id' => $params['user_id']
            ]);

            return $this->successResponse($rpcResult['data'], 'Web push unsubscription successful');

        } catch (Exception $e) {
            $this->log('error', 'Web push unsubscription failed', [
                'error' => $e->getMessage(),
                'user_id' => $params['user_id'] ?? null
            ]);

            return $this->errorResponse('Web push unsubscription failed: ' . $e->getMessage());
        }
    }

    /**
     * Get web push statistics
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getWebPushStatistics(array $params, array $context = []): array
    {
        try {
            // Call notification service via RPC
            $rpcResult = $this->callNotificationServiceRPC('webpush.getStatistics', $params);

            if (!$rpcResult['success']) {
                return $this->errorResponse('Failed to get web push statistics', $rpcResult);
            }

            $this->log('info', 'Web push statistics retrieved');

            return $this->successResponse($rpcResult['data'], 'Web push statistics retrieved successfully');

        } catch (Exception $e) {
            $this->log('error', 'Failed to get web push statistics', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Failed to get web push statistics: ' . $e->getMessage());
        }
    }

    /**
     * Call notification service RPC method
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    private function callNotificationServiceRPC(string $method, array $params): array
    {
        try {
            // Get notification service URL from config
            $notificationServiceUrl = $this->getServiceUrl('notification_service');
            
            if (!$notificationServiceUrl) {
                throw new Exception('Notification service URL not configured');
            }

            // Make RPC call to notification service
            $response = $this->makeRpcCall($notificationServiceUrl, $method, $params);

            return $response;

        } catch (Exception $e) {
            $this->log('error', 'RPC call to notification service failed', [
                'method' => $method,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get service URL from configuration
     *
     * @param string $serviceName
     * @return string|null
     */
    private function getServiceUrl(string $serviceName): ?string
    {
        // This would typically come from service discovery or configuration
        $serviceUrls = [
            'notification_service' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8000'),
            'auth_service' => env('AUTH_SERVICE_URL', 'http://auth-service:8000'),
            'user_service' => env('USER_SERVICE_URL', 'http://user-service:8000'),
            'order_service' => env('ORDER_SERVICE_URL', 'http://order-service:8000'),
            'payment_service' => env('PAYMENT_SERVICE_URL', 'http://payment-service:8000'),
            'bidding_service' => env('BIDDING_SERVICE_URL', 'http://bidding-service:8000'),
            'auction_service' => env('AUCTION_SERVICE_URL', 'http://auction-service:8000'),
            'analytics_service' => env('ANALYTICS_SERVICE_URL', 'http://analytics-service:8000'),
            'vin_ocr_service' => env('VIN_OCR_SERVICE_URL', 'http://vin-ocr-service:8000'),
            'gateway_service' => env('GATEWAY_SERVICE_URL', 'http://gateway-service:8000'),
        ];

        return $serviceUrls[$serviceName] ?? null;
    }

    /**
     * Make RPC call to external service
     *
     * @param string $serviceUrl
     * @param string $method
     * @param array $params
     * @return array
     */
    private function makeRpcCall(string $serviceUrl, string $method, array $params): array
    {
        try {
            // This is a simplified RPC call implementation
            // In production, you'd use a proper RPC client library
            
            $rpcPayload = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => uniqid()
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $serviceUrl . '/rpc');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rpcPayload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("HTTP error: {$httpCode}");
            }

            $decodedResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response');
            }

            if (isset($decodedResponse['error'])) {
                throw new Exception($decodedResponse['error']['message'] ?? 'RPC error');
            }

            return $decodedResponse['result'] ?? ['success' => true];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
