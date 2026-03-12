<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\Notification;
use App\Models\NotificationEvent;
use App\Models\UserNotificationPreference;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Send a notification
     */
    public function sendNotification(array $data): array
    {
        try {
            // Validate required fields
            $this->validateNotificationData($data);

            // Create notification record
            $notificationId = $this->createNotificationRecord($data);

            // Send notification based on type
            $result = $this->dispatchNotification($notificationId, $data);

            // Update notification status
            $this->updateNotificationStatus($notificationId, $result['success'] ? 'sent' : 'failed', $result);

            return [
                'id' => $notificationId,
                'status' => $result['success'] ? 'sent' : 'failed',
                'type' => $data['type'],
                'sent_at' => $result['success'] ? now()->toISOString() : null,
                'details' => $result,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'id' => null,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'sent_at' => null,
            ];
        }
    }

    /**
     * Send batch notifications
     */
    public function sendBatch(array $notifications): array
    {
        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($notifications as $index => $notification) {
            try {
                $result = $this->sendNotification($notification);
                $results[] = $result;

                if ($result['status'] === 'sent') {
                    $successful++;
                } else {
                    $failed++;
                }

            } catch (\Exception $e) {
                $results[] = [
                    'id' => null,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'index' => $index,
                ];
                $failed++;
            }
        }

        return [
            'total' => count($notifications),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results,
            'batch_id' => Str::uuid(),
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Track notification event
     */
    public function trackEvent(array $data): void
    {
        try {
            // Check if notification_events table exists before querying
            if (!DB::getSchemaBuilder()->hasTable('notification_events')) {
                Log::warning('notification_events table does not exist, skipping event tracking');
                return;
            }

            NotificationEvent::create([
                'notification_id' => $data['notification_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'event_type' => $data['event_type'], // opened, clicked, delivered, bounced, etc.
                'event_data' => $data['event_data'] ?? [],
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
            ]);

            Log::info('Notification event tracked', [
                'notification_id' => $data['notification_id'] ?? null,
                'event_type' => $data['event_type'],
                'user_id' => $data['user_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track notification event', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }

    /**
     * Get notification status
     */
    public function getStatus(string $notificationId): array
    {
        try {
            $notification = Notification::find($notificationId);

            if (!$notification) {
                return [
                    'found' => false,
                    'error' => 'Notification not found',
                ];
            }

            // Get delivery events using Eloquent with defensive check
            $events = collect();
            if (DB::getSchemaBuilder()->hasTable('notification_events')) {
                $events = NotificationEvent::forNotification($notificationId)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return [
                'found' => true,
                'notification' => [
                    'id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'type' => $notification->type,
                    'status' => $notification->status,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'sent_at' => $notification->sent_at,
                    'created_at' => $notification->created_at,
                ],
                'events' => $events->toArray(),
                'delivery_status' => $this->calculateDeliveryStatus($notification, $events),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get notification status', [
                'error' => $e->getMessage(),
                'notification_id' => $notificationId,
            ]);

            return [
                'found' => false,
                'error' => 'Failed to retrieve notification status',
            ];
        }
    }

    /**
     * Get user notification preferences
     */
    public function getUserPreferences(int $userId): array
    {
        try {
            // Check if user_notification_preferences table exists
            if (!DB::getSchemaBuilder()->hasTable('user_notification_preferences')) {
                return $this->getDefaultPreferences();
            }

            $preferences = UserNotificationPreference::forUser($userId)->first();

            if (!$preferences) {
                // Return default preferences
                return $this->getDefaultPreferences();
            }

            return [
                'user_id' => $userId,
                'email_enabled' => $preferences->email_enabled,
                'sms_enabled' => $preferences->sms_enabled,
                'push_enabled' => $preferences->push_enabled,
                'in_app_enabled' => $preferences->in_app_enabled,
                'frequency' => $preferences->frequency ?? 'immediate',
                'quiet_hours' => [
                    'enabled' => $preferences->quiet_hours_enabled,
                    'start' => $preferences->quiet_hours_start ?? '22:00',
                    'end' => $preferences->quiet_hours_end ?? '08:00',
                ],
                'categories' => json_decode($preferences->categories ?? '{}', true),
                'updated_at' => $preferences->updated_at,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get user preferences', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return $this->getDefaultPreferences();
        }
    }

    /**
     * Update user notification preferences
     */
    public function updatePreferences(int $userId, array $preferences): array
    {
        try {
            // Check if user_notification_preferences table exists
            if (!DB::getSchemaBuilder()->hasTable('user_notification_preferences')) {
                Log::warning('user_notification_preferences table does not exist, cannot update preferences');
                return [
                    'success' => false,
                    'error' => 'Preferences table not available',
                ];
            }

            $data = [
                'user_id' => $userId,
                'email_enabled' => $preferences['email_enabled'] ?? true,
                'sms_enabled' => $preferences['sms_enabled'] ?? true,
                'push_enabled' => $preferences['push_enabled'] ?? true,
                'in_app_enabled' => $preferences['in_app_enabled'] ?? true,
                'frequency' => $preferences['frequency'] ?? 'immediate',
                'quiet_hours_enabled' => $preferences['quiet_hours']['enabled'] ?? false,
                'quiet_hours_start' => $preferences['quiet_hours']['start'] ?? '22:00',
                'quiet_hours_end' => $preferences['quiet_hours']['end'] ?? '08:00',
                'categories' => $preferences['categories'] ?? [],
                'timezone' => $preferences['timezone'] ?? 'UTC',
            ];

            // Use updateOrCreate for cleaner upsert logic
            UserNotificationPreference::updateOrCreate(
                ['user_id' => $userId],
                $data
            );

            return [
                'success' => true,
                'message' => 'Preferences updated successfully',
                'preferences' => $this->getUserPreferences($userId),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update user preferences', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'preferences' => $preferences,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to update preferences',
            ];
        }
    }

    /**
     * Get delivery statistics
     */
    public function getDeliveryStats(): array
    {
        try {
            $stats = [
                'total_sent' => Notification::where('status', 'sent')->count(),
                'total_failed' => Notification::where('status', 'failed')->count(),
                'total_pending' => Notification::where('status', 'pending')->count(),
            ];

            $stats['total'] = $stats['total_sent'] + $stats['total_failed'] + $stats['total_pending'];
            $stats['success_rate'] = $stats['total'] > 0 ? round(($stats['total_sent'] / $stats['total']) * 100, 2) : 0;

            // Get stats by type using Eloquent
            $typeStats = Notification::select('type', DB::raw('count(*) as count'), 'status')
                ->groupBy('type', 'status')
                ->get();

            $statsByType = [];
            foreach ($typeStats as $stat) {
                if (!isset($statsByType[$stat->type])) {
                    $statsByType[$stat->type] = [
                        'sent' => 0,
                        'failed' => 0,
                        'pending' => 0,
                    ];
                }
                $statsByType[$stat->type][$stat->status] = $stat->count;
            }

            // Get recent activity (last 24 hours) using Eloquent
            $recentActivity = Notification::where('created_at', '>=', now()->subDay())
                ->select('type', 'status', DB::raw('count(*) as count'))
                ->groupBy('type', 'status')
                ->get();

            return [
                'overall' => $stats,
                'by_type' => $statsByType,
                'recent_activity' => $recentActivity->toArray(),
                'generated_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get delivery stats', [
                'error' => $e->getMessage(),
            ]);

            return [
                'overall' => [
                    'total' => 0,
                    'total_sent' => 0,
                    'total_failed' => 0,
                    'total_pending' => 0,
                    'success_rate' => 0,
                ],
                'error' => 'Failed to retrieve statistics',
            ];
        }
    }

    /**
     * Get notification history for a user
     */
    public function getNotificationHistory(int $userId, array $filters = []): array
    {
        try {
            $query = Notification::where('user_id', $userId)
                ->orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            $limit = $filters['limit'] ?? 50;
            $offset = $filters['offset'] ?? 0;

            $notifications = $query->limit($limit)->offset($offset)->get();
            $total = $query->count();

            return [
                'user_id' => $userId,
                'notifications' => $notifications->toArray(),
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total,
                ],
                'filters_applied' => $filters,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get notification history', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'filters' => $filters,
            ]);

            return [
                'user_id' => $userId,
                'notifications' => [],
                'error' => 'Failed to retrieve notification history',
            ];
        }
    }

    /**
     * Validate notification data
     */
    private function validateNotificationData(array $data): void
    {
        $required = ['user_id', 'type', 'title', 'message'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing or empty");
            }
        }

        $validTypes = ['email', 'sms', 'push', 'in_app'];
        if (!in_array($data['type'], $validTypes)) {
            throw new \InvalidArgumentException("Invalid notification type. Must be one of: " . implode(', ', $validTypes));
        }

        $validPriorities = ['low', 'normal', 'high', 'urgent'];
        if (isset($data['priority']) && !in_array($data['priority'], $validPriorities)) {
            throw new \InvalidArgumentException("Invalid priority. Must be one of: " . implode(', ', $validPriorities));
        }
    }

    /**
     * Create notification record in database
     */
    private function createNotificationRecord(array $data): string
    {
        $notificationId = Str::uuid();

        Notification::create([
            'id' => $notificationId,
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'data' => $data['data'] ?? [],
            'priority' => $data['priority'] ?? 'normal',
            'status' => 'pending',
            'scheduled_at' => $data['scheduled_at'] ?? now(),
        ]);

        return $notificationId;
    }

    /**
     * Dispatch notification based on type
     */
    private function dispatchNotification(string $notificationId, array $data): array
    {
        // Check user preferences first
        $preferences = $this->getUserPreferences($data['user_id']);
        
        if (!$this->shouldSendNotification($data['type'], $preferences)) {
            return [
                'success' => false,
                'reason' => 'User has disabled this notification type',
                'skipped' => true,
            ];
        }

        // Check quiet hours
        if ($this->isQuietHours($preferences)) {
            return [
                'success' => false,
                'reason' => 'Notification blocked by quiet hours',
                'skipped' => true,
            ];
        }

        return match ($data['type']) {
            'email' => $this->sendEmailNotification($notificationId, $data),
            'sms' => $this->sendSmsNotification($notificationId, $data),
            'push' => $this->sendPushNotification($notificationId, $data),
            'in_app' => $this->sendInAppNotification($notificationId, $data),
            default => [
                'success' => false,
                'reason' => 'Unknown notification type',
            ]
        };
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(string $notificationId, array $data): array
    {
        try {
            // In a real implementation, you would use Laravel's Mail facade
            // For now, we'll simulate the email sending
            
            Log::info('Sending email notification', [
                'notification_id' => $notificationId,
                'user_id' => $data['user_id'],
                'title' => $data['title'],
            ]);

            // Simulate email sending delay
            usleep(100000); // 0.1 seconds

            // Simulate 95% success rate
            $success = rand(1, 100) <= 95;

            if ($success) {
                return [
                    'success' => true,
                    'provider' => 'email',
                    'message_id' => 'email_' . Str::random(10),
                    'sent_at' => now()->toISOString(),
                ];
            } else {
                return [
                    'success' => false,
                    'provider' => 'email',
                    'reason' => 'Email delivery failed',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => 'email',
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send SMS notification
     */
    private function sendSmsNotification(string $notificationId, array $data): array
    {
        try {
            Log::info('Sending SMS notification', [
                'notification_id' => $notificationId,
                'user_id' => $data['user_id'],
                'message' => $data['message'],
            ]);

            // Simulate SMS sending delay
            usleep(200000); // 0.2 seconds

            // Simulate 90% success rate
            $success = rand(1, 100) <= 90;

            if ($success) {
                return [
                    'success' => true,
                    'provider' => 'sms',
                    'message_id' => 'sms_' . Str::random(10),
                    'sent_at' => now()->toISOString(),
                ];
            } else {
                return [
                    'success' => false,
                    'provider' => 'sms',
                    'reason' => 'SMS delivery failed',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => 'sms',
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send push notification
     */
    private function sendPushNotification(string $notificationId, array $data): array
    {
        try {
            Log::info('Sending push notification', [
                'notification_id' => $notificationId,
                'user_id' => $data['user_id'],
                'title' => $data['title'],
            ]);

            // Simulate push notification delay
            usleep(150000); // 0.15 seconds

            // Simulate 85% success rate (push notifications can fail if device is offline)
            $success = rand(1, 100) <= 85;

            if ($success) {
                return [
                    'success' => true,
                    'provider' => 'push',
                    'message_id' => 'push_' . Str::random(10),
                    'sent_at' => now()->toISOString(),
                ];
            } else {
                return [
                    'success' => false,
                    'provider' => 'push',
                    'reason' => 'Push notification delivery failed',
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => 'push',
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send in-app notification
     */
    private function sendInAppNotification(string $notificationId, array $data): array
    {
        try {
            // In-app notifications are stored in database for user to see in the app
            InAppNotification::create([
                'id' => Str::uuid(),
                'notification_id' => $notificationId,
                'user_id' => $data['user_id'],
                'title' => $data['title'],
                'message' => $data['message'],
                'data' => $data['data'] ?? [],
                'read' => false,
            ]);

            Log::info('In-app notification created', [
                'notification_id' => $notificationId,
                'user_id' => $data['user_id'],
            ]);

            return [
                'success' => true,
                'provider' => 'in_app',
                'message_id' => 'in_app_' . $notificationId,
                'sent_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'provider' => 'in_app',
                'reason' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update notification status
     */
    private function updateNotificationStatus(string $notificationId, string $status, array $result): void
    {
        try {
            $updateData = [
                'status' => $status,
            ];

            if ($status === 'sent') {
                $updateData['sent_at'] = now();
                $updateData['provider_response'] = $result;
            } else {
                $updateData['error_message'] = $result['reason'] ?? 'Unknown error';
            }

            Notification::where('id', $notificationId)->update($updateData);

        } catch (\Exception $e) {
            Log::error('Failed to update notification status', [
                'notification_id' => $notificationId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if notification should be sent based on user preferences
     */
    private function shouldSendNotification(string $type, array $preferences): bool
    {
        $typeKey = $type . '_enabled';
        return $preferences[$typeKey] ?? true;
    }

    /**
     * Check if current time is within quiet hours
     */
    private function isQuietHours(array $preferences): bool
    {
        if (!($preferences['quiet_hours']['enabled'] ?? false)) {
            return false;
        }

        $now = now();
        $start = Carbon::createFromTimeString($preferences['quiet_hours']['start'] ?? '22:00');
        $end = Carbon::createFromTimeString($preferences['quiet_hours']['end'] ?? '08:00');

        // Handle overnight quiet hours (e.g., 22:00 to 08:00)
        if ($start->gt($end)) {
            return $now->gte($start) || $now->lte($end);
        }

        return $now->between($start, $end);
    }

    /**
     * Calculate delivery status based on notification and events
     */
    private function calculateDeliveryStatus($notification, $events): string
    {
        if ($notification->status !== 'sent') {
            return $notification->status;
        }

        // Check for delivery events
        $hasDelivered = $events->contains('event_type', 'delivered');
        $hasBounced = $events->contains('event_type', 'bounced');
        $hasOpened = $events->contains('event_type', 'opened');

        if ($hasBounced) {
            return 'bounced';
        }

        if ($hasOpened) {
            return 'opened';
        }

        if ($hasDelivered) {
            return 'delivered';
        }

        return 'sent';
    }

    /**
     * Get default notification preferences
     */
    private function getDefaultPreferences(): array
    {
        return [
            'email_enabled' => true,
            'sms_enabled' => true,
            'push_enabled' => true,
            'in_app_enabled' => true,
            'frequency' => 'immediate',
            'quiet_hours' => [
                'enabled' => false,
                'start' => '22:00',
                'end' => '08:00',
            ],
            'categories' => [],
        ];
    }
}
