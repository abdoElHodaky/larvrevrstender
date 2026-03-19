<?php

namespace App\RPC\Procedures;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use App\RPC\BaseProcedure;
use Exception;

/**
 * Web Push Notification RPC Procedure
 * 
 * This procedure extends the local notification service BaseProcedure and provides
 * web push notification functionality with proper RPC error handling and validation.
 */
class WebPushNotificationProcedure extends BaseProcedure
{
    /**
     * The procedure name for RPC calls.
     */
    public static string $name = 'webpush';

    /**
     * @var WebPushService
     */
    protected WebPushService $webPushService;

    public function __construct(WebPushService $webPushService)
    {
        $this->webPushService = $webPushService;
    }

    /**
     * Send web push notification to specific user.
     * 
     * @param array $params
     * @return array
     */
    public function sendToUser(array $params): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'icon' => 'nullable|string|url',
                'badge' => 'nullable|string|url',
                'image' => 'nullable|string|url',
                'url' => 'nullable|string|url',
                'data' => 'nullable|array',
                'actions' => 'nullable|array',
                'category' => 'nullable|string|in:order,bid,payment,auction,system',
                'urgency' => 'nullable|string|in:very-low,low,normal,high',
                'ttl' => 'nullable|integer|min:0',
                'tag' => 'nullable|string|max:100',
                'require_interaction' => 'nullable|boolean',
                'silent' => 'nullable|boolean',
                'vibrate' => 'nullable|array',
            ]);

            $result = $this->webPushService->sendToUser(
                $params['user_id'],
                $params['title'],
                $params['body'],
                $params
            );

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Web push notification sent successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Send web push notification to multiple users.
     * 
     * @param array $params
     * @return array
     */
    public function sendToUsers(array $params): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $this->validate($params, [
                'user_ids' => 'required|array',
                'user_ids.*' => 'integer',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'icon' => 'nullable|string|url',
                'badge' => 'nullable|string|url',
                'image' => 'nullable|string|url',
                'url' => 'nullable|string|url',
                'data' => 'nullable|array',
                'actions' => 'nullable|array',
                'category' => 'nullable|string|in:order,bid,payment,auction,system',
                'urgency' => 'nullable|string|in:very-low,low,normal,high',
                'ttl' => 'nullable|integer|min:0',
                'tag' => 'nullable|string|max:100',
                'require_interaction' => 'nullable|boolean',
                'silent' => 'nullable|boolean',
                'vibrate' => 'nullable|array',
            ]);

            $result = $this->webPushService->sendToUsers(
                $params['user_ids'],
                $params['title'],
                $params['body'],
                $params
            );

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Web push notifications sent successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Send web push notification to all active subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function sendToAll(array $params): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $this->validate($params, [
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:1000',
                'icon' => 'nullable|string|url',
                'badge' => 'nullable|string|url',
                'image' => 'nullable|string|url',
                'url' => 'nullable|string|url',
                'data' => 'nullable|array',
                'actions' => 'nullable|array',
                'category' => 'nullable|string|in:order,bid,payment,auction,system',
                'urgency' => 'nullable|string|in:very-low,low,normal,high',
                'ttl' => 'nullable|integer|min:0',
                'tag' => 'nullable|string|max:100',
                'require_interaction' => 'nullable|boolean',
                'silent' => 'nullable|boolean',
                'vibrate' => 'nullable|array',
                'device_types' => 'nullable|array',
                'device_types.*' => 'string|in:mobile,desktop,tablet',
            ]);

            $result = $this->webPushService->sendToAll(
                $params['title'],
                $params['body'],
                $params
            );

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Web push broadcast sent successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Subscribe user to web push notifications.
     * 
     * @param array $params
     * @return array
     */
    public function subscribe(array $params): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'endpoint' => 'required|string|url',
                'public_key' => 'required|string',
                'auth_token' => 'required|string',
                'content_encoding' => 'nullable|string|in:aesgcm,aes128gcm',
                'user_agent' => 'nullable|string|max:500',
                'device_type' => 'nullable|string|in:mobile,desktop,tablet',
                'browser' => 'nullable|string|max:100',
                'platform' => 'nullable|string|max:100',
                'notification_preferences' => 'nullable|array',
            ]);

            $result = $this->webPushService->subscribe(
                $params['user_id'],
                $params['endpoint'],
                $params['public_key'],
                $params['auth_token'],
                $params
            );

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Web push subscription created successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Unsubscribe user from web push notifications.
     * 
     * @param array $params
     * @return array
     */
    public function unsubscribe(array $params): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'endpoint' => 'required|string|url',
            ]);

            $result = $this->webPushService->unsubscribe(
                $params['user_id'],
                $params['endpoint']
            );

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Web push unsubscription successful',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Get user's web push subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function getUserSubscriptions(array $params): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'active_only' => 'nullable|boolean',
            ]);

            $result = $this->webPushService->getUserSubscriptions(
                $params['user_id'],
                $params['active_only'] ?? true
            );

            return [
                'success' => true,
                'data' => $result,
                'message' => 'User subscriptions retrieved successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Get web push statistics.
     * 
     * @param array $params
     * @return array
     */
    public function getStatistics(array $params = []): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $result = PushSubscription::getStatistics();

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Web push statistics retrieved successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }

    /**
     * Clean up expired subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function cleanupExpired(array $params = []): array
    {
        return $this->executeWithLogging(__FUNCTION__, $params, function () use ($params) {
            $result = PushSubscription::cleanupExpired();

            return [
                'success' => true,
                'data' => $result,
                'message' => 'Expired subscriptions cleaned up successfully',
                'correlation_id' => $this->getCorrelationId()
            ];
        });
    }
}
