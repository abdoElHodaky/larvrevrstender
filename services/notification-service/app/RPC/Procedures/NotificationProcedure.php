<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Sajya\Server\Exceptions\RuntimeException;

class NotificationProcedure extends BaseProcedure
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    /**
     * Send notification
     */
    public function send(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'type' => 'required|string|in:email,sms,push,in_app',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'sometimes|array',
            'scheduled_at' => 'sometimes|date|after:now',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
        ]);

        return $this->executeWithLogging('Notification@send', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for notifications
            $key = 'notification_send:'.$params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 50)) {
                throw new RuntimeException(
                    'Too many notification attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $notification = $this->notificationService->sendNotification([
                    'user_id' => $params['user_id'],
                    'type' => $params['type'],
                    'title' => $params['title'],
                    'message' => $params['message'],
                    'data' => $params['data'] ?? [],
                    'scheduled_at' => $params['scheduled_at'] ?? null,
                    'priority' => $params['priority'] ?? 'normal',
                ]);

                // Clear rate limiting on successful send
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'notification' => $notification,
                    'sent_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                // Increment rate limiting on failed send
                RateLimiter::hit($key, 60); // 1 minute

                throw new RuntimeException(
                    'Notification send failed: '.$e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id'], 'type' => $params['type']]
                );
            }
        });
    }

    /**
     * Send bulk notifications
     */
    public function sendBulk(array $params): array
    {
        $this->validate($params, [
            'user_ids' => 'required|array|min:1|max:1000',
            'user_ids.*' => 'integer|min:1',
            'type' => 'required|string|in:email,sms,push,in_app',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'data' => 'sometimes|array',
            'scheduled_at' => 'sometimes|date|after:now',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
        ]);

        return $this->executeWithLogging('Notification@sendBulk', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for bulk notifications
            $key = 'notification_bulk:'.request()->ip();
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw new RuntimeException(
                    'Too many bulk notification attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $result = $this->notificationService->sendBulkNotifications([
                    'user_ids' => $params['user_ids'],
                    'type' => $params['type'],
                    'title' => $params['title'],
                    'message' => $params['message'],
                    'data' => $params['data'] ?? [],
                    'scheduled_at' => $params['scheduled_at'] ?? null,
                    'priority' => $params['priority'] ?? 'normal',
                ]);

                // Clear rate limiting on successful send
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'batch_id' => $result['batch_id'],
                    'total_recipients' => count($params['user_ids']),
                    'queued_count' => $result['queued_count'],
                    'failed_count' => $result['failed_count'],
                    'sent_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                // Increment rate limiting on failed send
                RateLimiter::hit($key, 300); // 5 minutes

                throw new RuntimeException(
                    'Bulk notification send failed: '.$e->getMessage(),
                    -32002,
                    ['recipients_count' => count($params['user_ids'])]
                );
            }
        });
    }

    /**
     * Get user notifications
     */
    public function getUserNotifications(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'type' => 'sometimes|string|in:email,sms,push,in_app',
            'read' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return $this->executeWithLogging('Notification@getUserNotifications', $params, function () use ($params) {
            try {
                $results = $this->notificationService->getUserNotifications([
                    'user_id' => $params['user_id'],
                    'type' => $params['type'] ?? null,
                    'read' => $params['read'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'per_page' => $params['per_page'] ?? 20,
                ]);

                return [
                    'success' => true,
                    'notifications' => $results['data'],
                    'pagination' => $results['pagination'],
                    'unread_count' => $results['unread_count'],
                    'retrieved_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user notifications: '.$e->getMessage(),
                    -32003,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(array $params): array
    {
        $this->validate($params, [
            'notification_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('Notification@markAsRead', $params, function () use ($params) {
            try {
                $result = $this->notificationService->markAsRead(
                    $params['notification_id'],
                    $params['user_id']
                );

                return [
                    'success' => true,
                    'notification_id' => $params['notification_id'],
                    'read' => $result['read'],
                    'read_at' => $result['read_at'],
                    'marked_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to mark notification as read: '.$e->getMessage(),
                    -32004,
                    ['notification_id' => $params['notification_id']]
                );
            }
        });
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'type' => 'sometimes|string|in:email,sms,push,in_app',
        ]);

        return $this->executeWithLogging('Notification@markAllAsRead', $params, function () use ($params) {
            try {
                $result = $this->notificationService->markAllAsRead(
                    $params['user_id'],
                    $params['type'] ?? null
                );

                return [
                    'success' => true,
                    'user_id' => $params['user_id'],
                    'marked_count' => $result['marked_count'],
                    'marked_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to mark all notifications as read: '.$e->getMessage(),
                    -32005,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Delete notification
     */
    public function delete(array $params): array
    {
        $this->validate($params, [
            'notification_id' => 'required|integer|min:1',
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('Notification@delete', $params, function () use ($params) {
            try {
                $result = $this->notificationService->deleteNotification(
                    $params['notification_id'],
                    $params['user_id']
                );

                return [
                    'success' => true,
                    'notification_id' => $params['notification_id'],
                    'deleted' => $result['deleted'],
                    'deleted_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to delete notification: '.$e->getMessage(),
                    -32006,
                    ['notification_id' => $params['notification_id']]
                );
            }
        });
    }

    /**
     * Get notification preferences
     */
    public function getPreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
        ]);

        return $this->executeWithLogging('Notification@getPreferences', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'notification_preferences:'.$params['user_id'];
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $preferences = $this->notificationService->getUserPreferences($params['user_id']);

                $result = [
                    'success' => true,
                    'user_id' => $params['user_id'],
                    'preferences' => $preferences,
                    'retrieved_at' => now()->toISOString(),
                ];

                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve notification preferences: '.$e->getMessage(),
                    -32007,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'preferences' => 'required|array',
            'preferences.email' => 'sometimes|array',
            'preferences.sms' => 'sometimes|array',
            'preferences.push' => 'sometimes|array',
            'preferences.in_app' => 'sometimes|array',
        ]);

        return $this->executeWithLogging('Notification@updatePreferences', $params, function () use ($params) {
            try {
                $preferences = $this->notificationService->updateUserPreferences(
                    $params['user_id'],
                    $params['preferences']
                );

                // Clear cache
                Cache::forget('notification_preferences:'.$params['user_id']);

                return [
                    'success' => true,
                    'user_id' => $params['user_id'],
                    'preferences' => $preferences,
                    'updated_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to update notification preferences: '.$e->getMessage(),
                    -32008,
                    ['user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get notification templates
     */
    public function getTemplates(array $params): array
    {
        $this->validate($params, [
            'type' => 'sometimes|string|in:email,sms,push,in_app',
            'category' => 'sometimes|string|max:100',
            'active_only' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Notification@getTemplates', $params, function () use ($params) {
            // Check cache first
            $cacheKey = 'notification_templates:'.
                       ($params['type'] ?? 'all').':'.
                       ($params['category'] ?? 'all').':'.
                       ($params['active_only'] ?? true ? 'active' : 'all');
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $templates = $this->notificationService->getTemplates([
                    'type' => $params['type'] ?? null,
                    'category' => $params['category'] ?? null,
                    'active_only' => $params['active_only'] ?? true,
                ]);

                $result = [
                    'success' => true,
                    'templates' => $templates,
                    'retrieved_at' => now()->toISOString(),
                ];

                // Cache for 1 hour
                Cache::put($cacheKey, $result, 3600);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve notification templates: '.$e->getMessage(),
                    -32009,
                    ['type' => $params['type'] ?? null]
                );
            }
        });
    }

    /**
     * Send template-based notification
     */
    public function sendFromTemplate(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'template_id' => 'required|integer|min:1',
            'variables' => 'sometimes|array',
            'scheduled_at' => 'sometimes|date|after:now',
            'priority' => 'sometimes|string|in:low,normal,high,urgent',
        ]);

        return $this->executeWithLogging('Notification@sendFromTemplate', $this->sanitizeForLogging($params), function () use ($params) {
            // Rate limiting for template notifications
            $key = 'notification_template:'.$params['user_id'];
            if (RateLimiter::tooManyAttempts($key, 30)) {
                throw new RuntimeException(
                    'Too many template notification attempts. Please try again later.',
                    -32007,
                    ['retry_after' => RateLimiter::availableIn($key)]
                );
            }

            try {
                $notification = $this->notificationService->sendFromTemplate([
                    'user_id' => $params['user_id'],
                    'template_id' => $params['template_id'],
                    'variables' => $params['variables'] ?? [],
                    'scheduled_at' => $params['scheduled_at'] ?? null,
                    'priority' => $params['priority'] ?? 'normal',
                ]);

                // Clear rate limiting on successful send
                RateLimiter::clear($key);

                return [
                    'success' => true,
                    'notification' => $notification,
                    'template_id' => $params['template_id'],
                    'sent_at' => now()->toISOString(),
                ];

            } catch (\Exception $e) {
                // Increment rate limiting on failed send
                RateLimiter::hit($key, 60); // 1 minute

                throw new RuntimeException(
                    'Template notification send failed: '.$e->getMessage(),
                    -32010,
                    ['template_id' => $params['template_id'], 'user_id' => $params['user_id']]
                );
            }
        });
    }

    /**
     * Get notification statistics
     */
    public function getStatistics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:today,week,month,quarter,year',
            'type' => 'sometimes|string|in:email,sms,push,in_app',
            'user_id' => 'sometimes|integer|min:1',
        ]);

        return $this->executeWithLogging('Notification@getStatistics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $type = $params['type'] ?? null;
            $userId = $params['user_id'] ?? null;

            // Check cache first
            $cacheKey = 'notification_stats:'.$period.':'.($type ?? 'all').':'.($userId ?? 'all');
            $cached = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }

            try {
                $statistics = $this->notificationService->getNotificationStatistics($period, $type, $userId);

                $result = [
                    'success' => true,
                    'statistics' => $statistics,
                    'period' => $period,
                    'filters' => [
                        'type' => $type,
                        'user_id' => $userId,
                    ],
                    'generated_at' => now()->toISOString(),
                ];

                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);

                return $result;

            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve notification statistics: '.$e->getMessage(),
                    -32011,
                    ['period' => $period]
                );
            }
        });
    }
}
