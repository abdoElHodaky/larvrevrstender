<?php

namespace App\RPC\Procedures;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Shared\Core\BaseProcedure;
use Exception;

/**
 * Cross-Service Web Push Notification Procedure
 * 
 * This procedure extends the shared service BaseProcedure and provides
 * web push notification functionality for cross-service operations.
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
        parent::__construct();
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
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
                'title' => ['required' => true, 'type' => 'string', 'max' => 255],
                'body' => ['required' => true, 'type' => 'string', 'max' => 1000],
                'icon' => ['type' => 'url'],
                'badge' => ['type' => 'url'],
                'image' => ['type' => 'url'],
                'url' => ['type' => 'url'],
                'data' => ['type' => 'array'],
                'actions' => ['type' => 'array'],
                'category' => ['type' => 'string'],
                'urgency' => ['type' => 'string'],
                'ttl' => ['type' => 'integer'],
                'tag' => ['type' => 'string', 'max' => 100],
                'require_interaction' => ['type' => 'boolean'],
                'silent' => ['type' => 'boolean'],
                'vibrate' => ['type' => 'array'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $result = $this->webPushService->sendToUser(
                $params['user_id'],
                $params['title'],
                $params['body'],
                $params
            );

            return $this->successResponse($result, 'Web push notification sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush sendToUser failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Web push notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Send web push notification to multiple users.
     * 
     * @param array $params
     * @return array
     */
    public function sendToUsers(array $params): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_ids' => ['required' => true, 'type' => 'array'],
                'title' => ['required' => true, 'type' => 'string', 'max' => 255],
                'body' => ['required' => true, 'type' => 'string', 'max' => 1000],
                'icon' => ['type' => 'url'],
                'badge' => ['type' => 'url'],
                'image' => ['type' => 'url'],
                'url' => ['type' => 'url'],
                'data' => ['type' => 'array'],
                'actions' => ['type' => 'array'],
                'category' => ['type' => 'string'],
                'urgency' => ['type' => 'string'],
                'ttl' => ['type' => 'integer'],
                'tag' => ['type' => 'string', 'max' => 100],
                'require_interaction' => ['type' => 'boolean'],
                'silent' => ['type' => 'boolean'],
                'vibrate' => ['type' => 'array'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $result = $this->webPushService->sendToUsers(
                $params['user_ids'],
                $params['title'],
                $params['body'],
                $params
            );

            return $this->successResponse($result, 'Web push notifications sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush sendToUsers failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Web push notifications failed: ' . $e->getMessage());
        }
    }

    /**
     * Send web push notification to all active subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function sendToAll(array $params): array
    {
        try {
            $validation = $this->validateParams($params, [
                'title' => ['required' => true, 'type' => 'string', 'max' => 255],
                'body' => ['required' => true, 'type' => 'string', 'max' => 1000],
                'icon' => ['type' => 'url'],
                'badge' => ['type' => 'url'],
                'image' => ['type' => 'url'],
                'url' => ['type' => 'url'],
                'data' => ['type' => 'array'],
                'actions' => ['type' => 'array'],
                'category' => ['type' => 'string'],
                'urgency' => ['type' => 'string'],
                'ttl' => ['type' => 'integer'],
                'tag' => ['type' => 'string', 'max' => 100],
                'require_interaction' => ['type' => 'boolean'],
                'silent' => ['type' => 'boolean'],
                'vibrate' => ['type' => 'array'],
                'device_types' => ['type' => 'array'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $result = $this->webPushService->sendToAll(
                $params['title'],
                $params['body'],
                $params
            );

            return $this->successResponse($result, 'Web push broadcast sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush sendToAll failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Web push broadcast failed: ' . $e->getMessage());
        }
    }

    /**
     * Subscribe user to web push notifications.
     * 
     * @param array $params
     * @return array
     */
    public function subscribe(array $params): array
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
                'notification_preferences' => ['type' => 'array'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $result = $this->webPushService->subscribe(
                $params['user_id'],
                $params['endpoint'],
                $params['public_key'],
                $params['auth_token'],
                $params
            );

            return $this->successResponse($result, 'Web push subscription created successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush subscribe failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Web push subscription failed: ' . $e->getMessage());
        }
    }

    /**
     * Unsubscribe user from web push notifications.
     * 
     * @param array $params
     * @return array
     */
    public function unsubscribe(array $params): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
                'endpoint' => ['required' => true, 'type' => 'string'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $result = $this->webPushService->unsubscribe(
                $params['user_id'],
                $params['endpoint']
            );

            return $this->successResponse($result, 'Web push unsubscription successful');

        } catch (Exception $e) {
            $this->log('error', 'WebPush unsubscribe failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Web push unsubscription failed: ' . $e->getMessage());
        }
    }

    /**
     * Get user's web push subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function getUserSubscriptions(array $params): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'integer'],
                'active_only' => ['type' => 'boolean'],
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $result = $this->webPushService->getUserSubscriptions(
                $params['user_id'],
                $params['active_only'] ?? true
            );

            return $this->successResponse($result, 'User subscriptions retrieved successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush getUserSubscriptions failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to get user subscriptions: ' . $e->getMessage());
        }
    }

    /**
     * Get web push statistics.
     * 
     * @param array $params
     * @return array
     */
    public function getStatistics(array $params = []): array
    {
        try {
            $result = PushSubscription::getStatistics();

            return $this->successResponse($result, 'Web push statistics retrieved successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush getStatistics failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to get statistics: ' . $e->getMessage());
        }
    }

    /**
     * Clean up expired subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function cleanupExpired(array $params = []): array
    {
        try {
            $result = PushSubscription::cleanupExpired();

            return $this->successResponse($result, 'Expired subscriptions cleaned up successfully');

        } catch (Exception $e) {
            $this->log('error', 'WebPush cleanupExpired failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to cleanup expired subscriptions: ' . $e->getMessage());
        }
    }
}
