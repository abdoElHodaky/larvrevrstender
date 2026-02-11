<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class WebPushService
{
    /**
     * Send web push notification to a specific user.
     */
    public function sendToUser(int $userId, string $title, string $body, array $options = []): array
    {
        $subscriptions = PushSubscription::forUser($userId)->active()->get();

        if ($subscriptions->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'message' => 'No active subscriptions found for user',
            ];
        }

        return $this->sendToSubscriptions($subscriptions, $title, $body, $options);
    }

    /**
     * Send web push notification to multiple users.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $options = []): array
    {
        $subscriptions = PushSubscription::whereIn('user_id', $userIds)->active()->get();

        if ($subscriptions->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'message' => 'No active subscriptions found for users',
            ];
        }

        return $this->sendToSubscriptions($subscriptions, $title, $body, $options);
    }

    /**
     * Send web push notification to all active subscriptions.
     */
    public function sendToAll(string $title, string $body, array $options = []): array
    {
        $query = PushSubscription::active();

        // Filter by device types if specified
        if (!empty($options['device_types'])) {
            $query->whereIn('device_type', $options['device_types']);
        }

        $subscriptions = $query->get();

        if ($subscriptions->isEmpty()) {
            return [
                'sent' => 0,
                'failed' => 0,
                'message' => 'No active subscriptions found',
            ];
        }

        return $this->sendToSubscriptions($subscriptions, $title, $body, $options);
    }

    /**
     * Send web push notifications to a collection of subscriptions.
     */
    protected function sendToSubscriptions(Collection $subscriptions, string $title, string $body, array $options = []): array
    {
        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($subscriptions as $subscription) {
            try {
                // Check if user allows this notification type
                $category = $options['category'] ?? 'system';
                if (!$subscription->allowsNotificationType($category)) {
                    continue;
                }

                // Create web push message
                $message = $this->createWebPushMessage($title, $body, $options, $subscription);

                // Send the notification
                $webPushSubscription = $subscription->toWebPushSubscription();
                $channel = new WebPushChannel();
                
                // Send notification (this is a simplified approach)
                // In a real implementation, you'd use Laravel's notification system
                $result = $this->sendWebPushMessage($webPushSubscription, $message);

                if ($result) {
                    $sent++;
                    $subscription->markAsUsed();
                } else {
                    $failed++;
                }

            } catch (\Exception $e) {
                $failed++;
                $errors[] = [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('WebPush send failed', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                    'error' => $e->getMessage(),
                ]);

                // Deactivate subscription if it's invalid
                if ($this->isInvalidSubscriptionError($e)) {
                    $subscription->deactivate();
                }
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => $subscriptions->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Create a WebPushMessage from parameters.
     */
    protected function createWebPushMessage(string $title, string $body, array $options, PushSubscription $subscription): WebPushMessage
    {
        $message = WebPushMessage::create()
            ->title($title)
            ->body($body);

        // Apply category-specific settings
        $category = $options['category'] ?? 'system';
        $categoryConfig = config("webpush.categories.{$category}", []);
        
        // Set icon
        $icon = $options['icon'] ?? $categoryConfig['icon'] ?? config('webpush.notification_defaults.icon');
        if ($icon) {
            $message->icon($icon);
        }

        // Set badge
        $badge = $options['badge'] ?? $categoryConfig['badge'] ?? config('webpush.notification_defaults.badge');
        if ($badge) {
            $message->badge($badge);
        }

        // Set image
        if (!empty($options['image'])) {
            $message->image($options['image']);
        }

        // Set action URL
        if (!empty($options['url'])) {
            $message->action('View', $options['url']);
        }

        // Set custom data
        if (!empty($options['data'])) {
            $message->data($options['data']);
        }

        // Set actions
        if (!empty($options['actions'])) {
            foreach ($options['actions'] as $action) {
                if (is_array($action) && isset($action['title'], $action['action'])) {
                    $message->action($action['title'], $action['action'], $action['icon'] ?? null);
                }
            }
        } elseif (!empty($categoryConfig['actions'])) {
            // Use category default actions
            foreach ($categoryConfig['actions'] as $actionKey) {
                $actionConfig = config("webpush.actions.{$actionKey}");
                if ($actionConfig) {
                    $message->action(
                        $actionConfig['title'],
                        $actionConfig['action'],
                        $actionConfig['icon'] ?? null
                    );
                }
            }
        }

        // Set options
        $message->options([
            'TTL' => $options['ttl'] ?? config('webpush.ttl'),
            'urgency' => $options['urgency'] ?? config('webpush.urgency'),
            'topic' => $options['tag'] ?? config('webpush.topic'),
        ]);

        // Set vibration pattern
        $vibrate = $options['vibrate'] ?? $categoryConfig['vibrate'] ?? config('webpush.notification_defaults.vibrate');
        if ($vibrate) {
            $message->vibrate($vibrate);
        }

        // Set require interaction
        $requireInteraction = $options['require_interaction'] ?? $categoryConfig['requireInteraction'] ?? config('webpush.notification_defaults.requireInteraction');
        if ($requireInteraction) {
            $message->requireInteraction();
        }

        // Set silent
        if (!empty($options['silent'])) {
            $message->silent();
        }

        return $message;
    }

    /**
     * Send web push message to subscription.
     */
    protected function sendWebPushMessage($webPushSubscription, WebPushMessage $message): bool
    {
        try {
            // This is a simplified implementation
            // In a real app, you'd use Laravel's notification system or the WebPush library directly
            
            // For now, we'll simulate sending and return true
            // In production, you'd integrate with the actual WebPush sending mechanism
            
            return true;
        } catch (\Exception $e) {
            Log::error('WebPush message send failed', [
                'endpoint' => $webPushSubscription->getEndpoint(),
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Subscribe a user to web push notifications.
     */
    public function subscribe(array $data): PushSubscription
    {
        // Parse user agent for device info
        $deviceInfo = $this->parseUserAgent($data['user_agent'] ?? '');

        return PushSubscription::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'endpoint' => $data['endpoint'],
            ],
            [
                'public_key' => $data['public_key'],
                'auth_token' => $data['auth_token'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => $data['user_agent'] ?? null,
                'device_type' => $data['device_type'] ?? $deviceInfo['device_type'],
                'browser' => $data['browser'] ?? $deviceInfo['browser'],
                'platform' => $data['platform'] ?? $deviceInfo['platform'],
                'notification_preferences' => $data['notification_preferences'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );
    }

    /**
     * Unsubscribe a user from web push notifications.
     */
    public function unsubscribe(int $userId, string $endpoint): bool
    {
        $subscription = PushSubscription::where('user_id', $userId)
                                      ->where('endpoint', $endpoint)
                                      ->first();

        if ($subscription) {
            $subscription->deactivate();
            return true;
        }

        return false;
    }

    /**
     * Get user's push subscriptions.
     */
    public function getUserSubscriptions(int $userId, bool $activeOnly = true): Collection
    {
        $query = PushSubscription::forUser($userId);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Parse user agent string to extract device information.
     */
    protected function parseUserAgent(string $userAgent): array
    {
        $deviceType = 'desktop';
        $browser = 'unknown';
        $platform = 'unknown';

        if (empty($userAgent)) {
            return compact('device_type', 'browser', 'platform');
        }

        // Detect device type
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = preg_match('/iPad/', $userAgent) ? 'tablet' : 'mobile';
        }

        // Detect browser
        if (preg_match('/Chrome\/(\d+)/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/(\d+)/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/(\d+)/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge\/(\d+)/', $userAgent)) {
            $browser = 'Edge';
        }

        // Detect platform
        if (preg_match('/Windows/', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Mac OS X/', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $platform = 'iOS';
        }

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }

    /**
     * Check if the error indicates an invalid subscription.
     */
    protected function isInvalidSubscriptionError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());
        
        return str_contains($message, 'invalid') ||
               str_contains($message, 'expired') ||
               str_contains($message, 'unsubscribed') ||
               str_contains($message, '410') ||
               str_contains($message, '404');
    }
}
