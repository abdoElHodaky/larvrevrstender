<?php

namespace App\RPC\Procedures;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Sajya\Server\Procedure;

/**
 * Cross-Service Web Push Notification Procedure
 * 
 * This procedure can be imported and used by other services via:
 * use App\Procedures\WebPushNotificationProcedure;
 */
class WebPushNotificationProcedure extends Procedure
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
        try {
            $validator = Validator::make($params, [
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

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ];
            }

            $result = $this->webPushService->sendToUser(
                $params['user_id'],
                $params['title'],
                $params['body'],
                $params
            );

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('WebPush sendToUser failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $validator = Validator::make($params, [
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

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ];
            }

            $results = $this->webPushService->sendToUsers(
                $params['user_ids'],
                $params['title'],
                $params['body'],
                $params
            );

            return [
                'success' => true,
                'data' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('WebPush sendToUsers failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $validator = Validator::make($params, [
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

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ];
            }

            $result = $this->webPushService->sendToAll(
                $params['title'],
                $params['body'],
                $params
            );

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('WebPush sendToAll failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $validator = Validator::make($params, [
                'user_id' => 'required|integer',
                'endpoint' => 'required|string|url',
                'public_key' => 'required|string',
                'auth_token' => 'required|string',
                'content_encoding' => 'nullable|string',
                'user_agent' => 'nullable|string',
                'device_type' => 'nullable|string|in:mobile,desktop,tablet',
                'browser' => 'nullable|string',
                'platform' => 'nullable|string',
                'notification_preferences' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ];
            }

            $subscription = $this->webPushService->subscribe($params);

            return [
                'success' => true,
                'data' => [
                    'subscription_id' => $subscription->id,
                    'is_active' => $subscription->is_active,
                    'created_at' => $subscription->created_at,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('WebPush subscribe failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $validator = Validator::make($params, [
                'user_id' => 'required|integer',
                'endpoint' => 'required|string|url',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ];
            }

            $result = $this->webPushService->unsubscribe(
                $params['user_id'],
                $params['endpoint']
            );

            return [
                'success' => true,
                'data' => [
                    'unsubscribed' => $result,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('WebPush unsubscribe failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get user's push subscriptions.
     * 
     * @param array $params
     * @return array
     */
    public function getUserSubscriptions(array $params): array
    {
        try {
            $validator = Validator::make($params, [
                'user_id' => 'required|integer',
                'active_only' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                ];
            }

            $subscriptions = $this->webPushService->getUserSubscriptions(
                $params['user_id'],
                $params['active_only'] ?? true
            );

            return [
                'success' => true,
                'data' => $subscriptions->map(function ($subscription) {
                    return [
                        'id' => $subscription->id,
                        'endpoint' => $subscription->endpoint,
                        'device_info' => $subscription->device_info,
                        'is_active' => $subscription->is_active,
                        'last_used_at' => $subscription->last_used_at,
                        'created_at' => $subscription->created_at,
                    ];
                }),
            ];

        } catch (\Exception $e) {
            Log::error('WebPush getUserSubscriptions failed', [
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $stats = PushSubscription::getStatistics();

            return [
                'success' => true,
                'data' => $stats,
            ];

        } catch (\Exception $e) {
            Log::error('WebPush getStatistics failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
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
            $deletedCount = PushSubscription::cleanupExpired();

            return [
                'success' => true,
                'data' => [
                    'deleted_count' => $deletedCount,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('WebPush cleanupExpired failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
