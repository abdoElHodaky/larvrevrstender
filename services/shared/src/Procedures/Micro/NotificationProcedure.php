<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Exception;

/**
 * Notification Micro Procedure (Step 3)
 * 
 * Provides comprehensive notification infrastructure including email, SMS,
 * push notifications, and subscription management for cross-service operations.
 */
trait NotificationProcedure
{
    /**
     * Send email notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendEmail(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'to' => ['required' => true, 'type' => 'string'],
                'subject' => ['required' => true, 'type' => 'string'],
                'template' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'from' => ['type' => 'string'],
                'priority' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $to = $params['to'];
            $subject = $params['subject'];
            $template = $params['template'] ?? 'default';
            $data = $params['data'] ?? [];
            $from = $params['from'] ?? config('mail.from.address');
            $priority = $params['priority'] ?? 'normal';

            // Generate notification ID
            $notificationId = $this->generateNotificationId('email');

            // Send email
            $emailResult = $this->performEmailSend($to, $subject, $template, $data, $from, $priority, $notificationId);

            if (!$emailResult['success']) {
                return $this->errorResponse('Email sending failed', $emailResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'email', [
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'status' => $emailResult['status'],
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('email_sent', 1, [
                'template' => $template,
                'priority' => $priority,
                'success' => $emailResult['success']
            ]);

            $this->log('info', 'Email notification sent', [
                'notification_id' => $notificationId,
                'to' => $to,
                'subject' => $subject,
                'template' => $template
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'email',
                'status' => $emailResult['status'],
                'sent_at' => now()->toISOString()
            ], 'Email sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'Email sending failed', [
                'error' => $e->getMessage(),
                'to' => $params['to'] ?? null
            ]);

            return $this->errorResponse('Email sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send SMS notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendSms(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'to' => ['required' => true, 'type' => 'string'],
                'message' => ['required' => true, 'type' => 'string'],
                'template' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'priority' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $to = $params['to'];
            $message = $params['message'];
            $template = $params['template'] ?? null;
            $data = $params['data'] ?? [];
            $priority = $params['priority'] ?? 'normal';

            // Generate notification ID
            $notificationId = $this->generateNotificationId('sms');

            // Process template if provided
            if ($template) {
                $message = $this->processTemplate($template, $data, 'sms');
            }

            // Send SMS
            $smsResult = $this->performSmsSend($to, $message, $priority, $notificationId);

            if (!$smsResult['success']) {
                return $this->errorResponse('SMS sending failed', $smsResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'sms', [
                'to' => $to,
                'message' => substr($message, 0, 100) . '...',
                'status' => $smsResult['status'],
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('sms_sent', 1, [
                'priority' => $priority,
                'success' => $smsResult['success'],
                'message_length' => strlen($message)
            ]);

            $this->log('info', 'SMS notification sent', [
                'notification_id' => $notificationId,
                'to' => $to,
                'message_length' => strlen($message)
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'sms',
                'status' => $smsResult['status'],
                'sent_at' => now()->toISOString()
            ], 'SMS sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'SMS sending failed', [
                'error' => $e->getMessage(),
                'to' => $params['to'] ?? null
            ]);

            return $this->errorResponse('SMS sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send push notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendPushNotification(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'device_tokens' => ['required' => true, 'type' => 'array'],
                'title' => ['required' => true, 'type' => 'string'],
                'body' => ['required' => true, 'type' => 'string'],
                'data' => ['type' => 'array'],
                'badge' => ['type' => 'int'],
                'sound' => ['type' => 'string'],
                'priority' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $deviceTokens = $params['device_tokens'];
            $title = $params['title'];
            $body = $params['body'];
            $data = $params['data'] ?? [];
            $badge = $params['badge'] ?? null;
            $sound = $params['sound'] ?? 'default';
            $priority = $params['priority'] ?? 'normal';

            // Generate notification ID
            $notificationId = $this->generateNotificationId('push');

            // Send push notification
            $pushResult = $this->performPushSend($deviceTokens, $title, $body, $data, $badge, $sound, $priority, $notificationId);

            if (!$pushResult['success']) {
                return $this->errorResponse('Push notification sending failed', $pushResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'push', [
                'device_count' => count($deviceTokens),
                'title' => $title,
                'body' => substr($body, 0, 100) . '...',
                'status' => $pushResult['status'],
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('push_sent', 1, [
                'device_count' => count($deviceTokens),
                'priority' => $priority,
                'success' => $pushResult['success']
            ]);

            $this->log('info', 'Push notification sent', [
                'notification_id' => $notificationId,
                'device_count' => count($deviceTokens),
                'title' => $title
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'push',
                'status' => $pushResult['status'],
                'device_count' => count($deviceTokens),
                'sent_at' => now()->toISOString()
            ], 'Push notification sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'Push notification sending failed', [
                'error' => $e->getMessage(),
                'device_count' => count($params['device_tokens'] ?? [])
            ]);

            return $this->errorResponse('Push notification sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Get notification status
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getNotificationStatus(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'notification_id' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $notificationId = $params['notification_id'];

            // Get notification record
            $notificationRecord = $this->getNotificationRecord($notificationId);

            if (!$notificationRecord) {
                return $this->errorResponse('Notification not found', [
                    'notification_id' => $notificationId
                ]);
            }

            // Get delivery status from provider
            $deliveryStatus = $this->getDeliveryStatus($notificationId, $notificationRecord['type']);

            $this->recordMetric('notification_status_checked', 1, [
                'type' => $notificationRecord['type'],
                'status' => $deliveryStatus['status']
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => $notificationRecord['type'],
                'status' => $deliveryStatus['status'],
                'sent_at' => $notificationRecord['sent_at'],
                'delivered_at' => $deliveryStatus['delivered_at'] ?? null,
                'details' => $deliveryStatus['details'] ?? []
            ], 'Notification status retrieved');

        } catch (Exception $e) {
            $this->log('error', 'Notification status check failed', [
                'error' => $e->getMessage(),
                'notification_id' => $params['notification_id'] ?? null
            ]);

            return $this->errorResponse('Notification status check failed: ' . $e->getMessage());
        }
    }

    /**
     * Manage user notification subscriptions
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function manageSubscriptions(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'user_id' => ['required' => true, 'type' => 'int'],
                'action' => ['required' => true, 'type' => 'string'],
                'notification_types' => ['type' => 'array'],
                'preferences' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $userId = $params['user_id'];
            $action = $params['action'];
            $notificationTypes = $params['notification_types'] ?? [];
            $preferences = $params['preferences'] ?? [];

            switch ($action) {
                case 'subscribe':
                    $result = $this->subscribeUser($userId, $notificationTypes, $preferences);
                    break;
                case 'unsubscribe':
                    $result = $this->unsubscribeUser($userId, $notificationTypes);
                    break;
                case 'update':
                    $result = $this->updateUserPreferences($userId, $preferences);
                    break;
                case 'get':
                    $result = $this->getUserSubscriptions($userId);
                    break;
                default:
                    return $this->errorResponse('Invalid action', ['action' => $action]);
            }

            $this->recordMetric('subscription_managed', 1, [
                'action' => $action,
                'user_id' => $userId,
                'success' => $result['success']
            ]);

            return $this->successResponse($result, "Subscription {$action} completed");

        } catch (Exception $e) {
            $this->log('error', 'Subscription management failed', [
                'error' => $e->getMessage(),
                'user_id' => $params['user_id'] ?? null,
                'action' => $params['action'] ?? null
            ]);

            return $this->errorResponse('Subscription management failed: ' . $e->getMessage());
        }
    }

    /**
     * Send WhatsApp notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendWhatsApp(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'to' => ['required' => true, 'type' => 'string'],
                'message' => ['required' => true, 'type' => 'string'],
                'template' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'media_url' => ['type' => 'string'],
                'priority' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $to = $params['to'];
            $message = $params['message'];
            $template = $params['template'] ?? null;
            $data = $params['data'] ?? [];
            $mediaUrl = $params['media_url'] ?? null;
            $priority = $params['priority'] ?? 'normal';

            // Generate notification ID
            $notificationId = $this->generateNotificationId('whatsapp');

            // Process template if provided
            if ($template) {
                $message = $this->processTemplate($template, $data, 'whatsapp');
            }

            // Send WhatsApp message
            $whatsappResult = $this->performWhatsAppSend($to, $message, $mediaUrl, $priority, $notificationId);

            if (!$whatsappResult['success']) {
                return $this->errorResponse('WhatsApp sending failed', $whatsappResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'whatsapp', [
                'to' => $to,
                'message' => substr($message, 0, 100) . '...',
                'media_url' => $mediaUrl,
                'status' => $whatsappResult['status'],
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('whatsapp_sent', 1, [
                'priority' => $priority,
                'success' => $whatsappResult['success'],
                'has_media' => !empty($mediaUrl)
            ]);

            $this->log('info', 'WhatsApp notification sent', [
                'notification_id' => $notificationId,
                'to' => $to,
                'has_media' => !empty($mediaUrl)
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'whatsapp',
                'status' => $whatsappResult['status'],
                'sent_at' => now()->toISOString()
            ], 'WhatsApp message sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'WhatsApp sending failed', [
                'error' => $e->getMessage(),
                'to' => $params['to'] ?? null
            ]);

            return $this->errorResponse('WhatsApp sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send Telegram notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendTelegram(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'chat_id' => ['required' => true, 'type' => 'string'],
                'message' => ['required' => true, 'type' => 'string'],
                'template' => ['type' => 'string'],
                'data' => ['type' => 'array'],
                'parse_mode' => ['type' => 'string'],
                'priority' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $chatId = $params['chat_id'];
            $message = $params['message'];
            $template = $params['template'] ?? null;
            $data = $params['data'] ?? [];
            $parseMode = $params['parse_mode'] ?? 'HTML';
            $priority = $params['priority'] ?? 'normal';

            // Generate notification ID
            $notificationId = $this->generateNotificationId('telegram');

            // Process template if provided
            if ($template) {
                $message = $this->processTemplate($template, $data, 'telegram');
            }

            // Send Telegram message
            $telegramResult = $this->performTelegramSend($chatId, $message, $parseMode, $priority, $notificationId);

            if (!$telegramResult['success']) {
                return $this->errorResponse('Telegram sending failed', $telegramResult);
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'telegram', [
                'chat_id' => $chatId,
                'message' => substr($message, 0, 100) . '...',
                'parse_mode' => $parseMode,
                'status' => $telegramResult['status'],
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('telegram_sent', 1, [
                'priority' => $priority,
                'success' => $telegramResult['success'],
                'parse_mode' => $parseMode
            ]);

            $this->log('info', 'Telegram notification sent', [
                'notification_id' => $notificationId,
                'chat_id' => $chatId,
                'parse_mode' => $parseMode
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'telegram',
                'status' => $telegramResult['status'],
                'sent_at' => now()->toISOString()
            ], 'Telegram message sent successfully');

        } catch (Exception $e) {
            $this->log('error', 'Telegram sending failed', [
                'error' => $e->getMessage(),
                'chat_id' => $params['chat_id'] ?? null
            ]);

            return $this->errorResponse('Telegram sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send multi-channel notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendMultiChannel(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'channels' => ['required' => true, 'type' => 'array'],
                'recipient' => ['required' => true, 'type' => 'array'],
                'template' => ['required' => true, 'type' => 'string'],
                'data' => ['type' => 'array'],
                'priority' => ['type' => 'string'],
                'fallback_order' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $channels = $params['channels'];
            $recipient = $params['recipient'];
            $template = $params['template'];
            $data = $params['data'] ?? [];
            $priority = $params['priority'] ?? 'normal';
            $fallbackOrder = $params['fallback_order'] ?? $channels;

            // Generate notification ID
            $notificationId = $this->generateNotificationId('multichannel');

            $results = [];
            $successCount = 0;
            $failedChannels = [];

            // Try each channel in fallback order
            foreach ($fallbackOrder as $channel) {
                if (!in_array($channel, $channels)) {
                    continue;
                }

                try {
                    $channelResult = $this->sendChannelNotification($channel, $recipient, $template, $data, $priority);
                    
                    $results[$channel] = $channelResult;
                    
                    if ($channelResult['success']) {
                        $successCount++;
                        // If we have at least one success and priority is not 'all', we can stop
                        if ($priority !== 'all' && $successCount > 0) {
                            break;
                        }
                    } else {
                        $failedChannels[] = $channel;
                    }
                } catch (Exception $e) {
                    $results[$channel] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $failedChannels[] = $channel;
                }
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'multichannel', [
                'channels' => $channels,
                'template' => $template,
                'success_count' => $successCount,
                'failed_channels' => $failedChannels,
                'results' => $results,
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('multichannel_sent', 1, [
                'channels' => implode(',', $channels),
                'success_count' => $successCount,
                'total_channels' => count($channels),
                'template' => $template
            ]);

            $this->log('info', 'Multi-channel notification sent', [
                'notification_id' => $notificationId,
                'channels' => $channels,
                'success_count' => $successCount,
                'template' => $template
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'multichannel',
                'success_count' => $successCount,
                'total_channels' => count($channels),
                'results' => $results,
                'sent_at' => now()->toISOString()
            ], "Multi-channel notification sent to {$successCount} channels");

        } catch (Exception $e) {
            $this->log('error', 'Multi-channel sending failed', [
                'error' => $e->getMessage(),
                'channels' => $params['channels'] ?? null
            ]);

            return $this->errorResponse('Multi-channel sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Send bulk notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function sendBulkNotification(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'recipients' => ['required' => true, 'type' => 'array'],
                'channel' => ['required' => true, 'type' => 'string'],
                'template' => ['required' => true, 'type' => 'string'],
                'data' => ['type' => 'array'],
                'priority' => ['type' => 'string'],
                'batch_size' => ['type' => 'int']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $recipients = $params['recipients'];
            $channel = $params['channel'];
            $template = $params['template'];
            $data = $params['data'] ?? [];
            $priority = $params['priority'] ?? 'normal';
            $batchSize = $params['batch_size'] ?? 100;

            // Generate notification ID
            $notificationId = $this->generateNotificationId('bulk');

            $totalRecipients = count($recipients);
            $successCount = 0;
            $failedCount = 0;
            $batches = array_chunk($recipients, $batchSize);
            $results = [];

            foreach ($batches as $batchIndex => $batch) {
                $batchResults = [];
                
                foreach ($batch as $recipient) {
                    try {
                        $result = $this->sendChannelNotification($channel, $recipient, $template, $data, $priority);
                        $batchResults[] = $result;
                        
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $failedCount++;
                        }
                    } catch (Exception $e) {
                        $batchResults[] = [
                            'success' => false,
                            'error' => $e->getMessage(),
                            'recipient' => $recipient
                        ];
                        $failedCount++;
                    }
                }
                
                $results["batch_{$batchIndex}"] = $batchResults;
                
                // Add small delay between batches to prevent overwhelming
                if ($batchIndex < count($batches) - 1) {
                    usleep(100000); // 100ms delay
                }
            }

            // Store notification record
            $this->storeNotificationRecord($notificationId, 'bulk', [
                'channel' => $channel,
                'template' => $template,
                'total_recipients' => $totalRecipients,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'batch_count' => count($batches),
                'sent_at' => now()->toISOString()
            ]);

            $this->recordMetric('bulk_sent', 1, [
                'channel' => $channel,
                'template' => $template,
                'total_recipients' => $totalRecipients,
                'success_count' => $successCount,
                'success_rate' => $totalRecipients > 0 ? ($successCount / $totalRecipients) * 100 : 0
            ]);

            $this->log('info', 'Bulk notification sent', [
                'notification_id' => $notificationId,
                'channel' => $channel,
                'template' => $template,
                'total_recipients' => $totalRecipients,
                'success_count' => $successCount
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'bulk',
                'channel' => $channel,
                'total_recipients' => $totalRecipients,
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'success_rate' => $totalRecipients > 0 ? round(($successCount / $totalRecipients) * 100, 2) : 0,
                'sent_at' => now()->toISOString()
            ], "Bulk notification sent to {$successCount}/{$totalRecipients} recipients");

        } catch (Exception $e) {
            $this->log('error', 'Bulk notification sending failed', [
                'error' => $e->getMessage(),
                'channel' => $params['channel'] ?? null
            ]);

            return $this->errorResponse('Bulk notification sending failed: ' . $e->getMessage());
        }
    }

    /**
     * Schedule notification for future delivery
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function scheduleNotification(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'scheduled_at' => ['required' => true, 'type' => 'string'],
                'channel' => ['required' => true, 'type' => 'string'],
                'recipient' => ['required' => true, 'type' => 'array'],
                'template' => ['required' => true, 'type' => 'string'],
                'data' => ['type' => 'array'],
                'priority' => ['type' => 'string'],
                'timezone' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $scheduledAt = $params['scheduled_at'];
            $channel = $params['channel'];
            $recipient = $params['recipient'];
            $template = $params['template'];
            $data = $params['data'] ?? [];
            $priority = $params['priority'] ?? 'normal';
            $timezone = $params['timezone'] ?? 'UTC';

            // Generate notification ID
            $notificationId = $this->generateNotificationId('scheduled');

            // Validate scheduled time
            try {
                $scheduledTime = new \DateTime($scheduledAt, new \DateTimeZone($timezone));
                if ($scheduledTime <= new \DateTime()) {
                    return $this->errorResponse('Scheduled time must be in the future');
                }
            } catch (Exception $e) {
                return $this->errorResponse('Invalid scheduled time or timezone');
            }

            // Store scheduled notification
            $scheduledData = [
                'notification_id' => $notificationId,
                'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
                'timezone' => $timezone,
                'channel' => $channel,
                'recipient' => $recipient,
                'template' => $template,
                'data' => $data,
                'priority' => $priority,
                'status' => 'scheduled',
                'created_at' => now()->toISOString()
            ];

            $this->cache("scheduled_notification:{$notificationId}", $scheduledData, 86400 * 30); // 30 days

            $this->recordMetric('notification_scheduled', 1, [
                'channel' => $channel,
                'template' => $template,
                'scheduled_hours_ahead' => $scheduledTime->diff(new \DateTime())->h
            ]);

            $this->log('info', 'Notification scheduled', [
                'notification_id' => $notificationId,
                'scheduled_at' => $scheduledAt,
                'channel' => $channel,
                'template' => $template
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'type' => 'scheduled',
                'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
                'timezone' => $timezone,
                'channel' => $channel,
                'template' => $template,
                'status' => 'scheduled'
            ], 'Notification scheduled successfully');

        } catch (Exception $e) {
            $this->log('error', 'Notification scheduling failed', [
                'error' => $e->getMessage(),
                'scheduled_at' => $params['scheduled_at'] ?? null
            ]);

            return $this->errorResponse('Notification scheduling failed: ' . $e->getMessage());
        }
    }

    /**
     * Cancel scheduled notification
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cancelNotification(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'notification_id' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $notificationId = $params['notification_id'];
            
            // Get scheduled notification
            $scheduledData = $this->getCached("scheduled_notification:{$notificationId}");
            
            if (!$scheduledData) {
                return $this->errorResponse('Scheduled notification not found');
            }

            if ($scheduledData['status'] !== 'scheduled') {
                return $this->errorResponse('Notification cannot be cancelled (status: ' . $scheduledData['status'] . ')');
            }

            // Update status to cancelled
            $scheduledData['status'] = 'cancelled';
            $scheduledData['cancelled_at'] = now()->toISOString();
            
            $this->cache("scheduled_notification:{$notificationId}", $scheduledData, 86400 * 30);

            $this->recordMetric('notification_cancelled', 1, [
                'channel' => $scheduledData['channel'],
                'template' => $scheduledData['template']
            ]);

            $this->log('info', 'Notification cancelled', [
                'notification_id' => $notificationId,
                'original_scheduled_at' => $scheduledData['scheduled_at']
            ]);

            return $this->successResponse([
                'notification_id' => $notificationId,
                'status' => 'cancelled',
                'cancelled_at' => $scheduledData['cancelled_at']
            ], 'Notification cancelled successfully');

        } catch (Exception $e) {
            $this->log('error', 'Notification cancellation failed', [
                'error' => $e->getMessage(),
                'notification_id' => $params['notification_id'] ?? null
            ]);

            return $this->errorResponse('Notification cancellation failed: ' . $e->getMessage());
        }
    }

    /**
     * Send notification via specific channel
     *
     * @param string $channel
     * @param array $recipient
     * @param string $template
     * @param array $data
     * @param string $priority
     * @return array
     */
    private function sendChannelNotification(string $channel, array $recipient, string $template, array $data, string $priority): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmail([
                    'to' => $recipient['email'] ?? $recipient['to'],
                    'subject' => $data['subject'] ?? 'Notification',
                    'template' => $template,
                    'data' => $data,
                    'priority' => $priority
                ]);
                
            case 'sms':
                return $this->sendSms([
                    'to' => $recipient['phone'] ?? $recipient['to'],
                    'message' => $data['message'] ?? '',
                    'template' => $template,
                    'data' => $data,
                    'priority' => $priority
                ]);
                
            case 'push':
                return $this->sendPushNotification([
                    'device_tokens' => $recipient['device_tokens'] ?? [$recipient['to']],
                    'title' => $data['title'] ?? 'Notification',
                    'body' => $data['body'] ?? $data['message'] ?? '',
                    'data' => $data,
                    'priority' => $priority
                ]);
                
            case 'whatsapp':
                return $this->sendWhatsApp([
                    'to' => $recipient['phone'] ?? $recipient['to'],
                    'message' => $data['message'] ?? '',
                    'template' => $template,
                    'data' => $data,
                    'priority' => $priority
                ]);
                
            case 'telegram':
                return $this->sendTelegram([
                    'chat_id' => $recipient['chat_id'] ?? $recipient['to'],
                    'message' => $data['message'] ?? '',
                    'template' => $template,
                    'data' => $data,
                    'priority' => $priority
                ]);
                
            default:
                return [
                    'success' => false,
                    'error' => "Unsupported channel: {$channel}"
                ];
        }
    }

    /**
     * Perform WhatsApp sending
     *
     * @param string $to
     * @param string $message
     * @param string|null $mediaUrl
     * @param string $priority
     * @param string $notificationId
     * @return array
     */
    private function performWhatsAppSend(string $to, string $message, ?string $mediaUrl, string $priority, string $notificationId): array
    {
        try {
            // This would integrate with WhatsApp Business API
            // For now, return a mock successful send
            
            // Mock WhatsApp sending logic
            $success = preg_match('/^\+?[1-9]\d{1,14}$/', $to);
            
            return [
                'success' => $success,
                'status' => $success ? 'sent' : 'failed',
                'provider_id' => 'mock_whatsapp_' . uniqid(),
                'message' => $success ? 'WhatsApp message sent successfully' : 'Invalid phone number',
                'media_delivered' => !empty($mediaUrl) && $success
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform Telegram sending
     *
     * @param string $chatId
     * @param string $message
     * @param string $parseMode
     * @param string $priority
     * @param string $notificationId
     * @return array
     */
    private function performTelegramSend(string $chatId, string $message, string $parseMode, string $priority, string $notificationId): array
    {
        try {
            // This would integrate with Telegram Bot API
            // For now, return a mock successful send
            
            // Mock Telegram sending logic
            $success = !empty($chatId) && strlen($message) > 0;
            
            return [
                'success' => $success,
                'status' => $success ? 'sent' : 'failed',
                'provider_id' => 'mock_telegram_' . uniqid(),
                'message' => $success ? 'Telegram message sent successfully' : 'Invalid chat ID or message',
                'message_id' => $success ? rand(1000, 9999) : null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform email sending
     *
     * @param string $to
     * @param string $subject
     * @param string $template
     * @param array $data
     * @param string $from
     * @param string $priority
     * @param string $notificationId
     * @return array
     */
    private function performEmailSend(string $to, string $subject, string $template, array $data, string $from, string $priority, string $notificationId): array
    {
        try {
            // This would integrate with your email service (SendGrid, SES, etc.)
            // For now, return a mock successful send
            
            $emailContent = $this->processTemplate($template, $data, 'email');
            
            // Mock email sending logic
            $success = filter_var($to, FILTER_VALIDATE_EMAIL) !== false;
            
            return [
                'success' => $success,
                'status' => $success ? 'sent' : 'failed',
                'provider_id' => 'mock_' . uniqid(),
                'message' => $success ? 'Email sent successfully' : 'Invalid email address'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform SMS sending
     *
     * @param string $to
     * @param string $message
     * @param string $priority
     * @param string $notificationId
     * @return array
     */
    private function performSmsSend(string $to, string $message, string $priority, string $notificationId): array
    {
        try {
            // This would integrate with your SMS service (Twilio, AWS SNS, etc.)
            // For now, return a mock successful send
            
            // Mock SMS sending logic
            $success = preg_match('/^\+?[1-9]\d{1,14}$/', $to);
            
            return [
                'success' => $success,
                'status' => $success ? 'sent' : 'failed',
                'provider_id' => 'mock_sms_' . uniqid(),
                'message' => $success ? 'SMS sent successfully' : 'Invalid phone number'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Perform push notification sending
     *
     * @param array $deviceTokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @param int|null $badge
     * @param string $sound
     * @param string $priority
     * @param string $notificationId
     * @return array
     */
    private function performPushSend(array $deviceTokens, string $title, string $body, array $data, ?int $badge, string $sound, string $priority, string $notificationId): array
    {
        try {
            // This would integrate with FCM, APNS, etc.
            // For now, return a mock successful send
            
            $successCount = 0;
            $failedTokens = [];
            
            foreach ($deviceTokens as $token) {
                if (strlen($token) > 10) { // Mock validation
                    $successCount++;
                } else {
                    $failedTokens[] = $token;
                }
            }
            
            return [
                'success' => $successCount > 0,
                'status' => $successCount > 0 ? 'sent' : 'failed',
                'success_count' => $successCount,
                'failed_count' => count($failedTokens),
                'failed_tokens' => $failedTokens,
                'provider_id' => 'mock_push_' . uniqid()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process notification template
     *
     * @param string $template
     * @param array $data
     * @param string $type
     * @return string
     */
    private function processTemplate(string $template, array $data, string $type): string
    {
        // This would load and process templates from storage
        // For now, return a simple template processing
        
        $templates = [
            'email' => [
                'welcome' => 'Welcome {{name}}! Thank you for joining us.',
                'order_confirmation' => 'Your order #{{order_id}} has been confirmed.',
                'password_reset' => 'Click here to reset your password: {{reset_link}}',
                'order.created' => 'Hi {{customer_name}}, your order #{{order_number}} has been created successfully!',
                'auction.created' => 'Your auction "{{auction_title}}" has been created and will start at {{starts_at}}.',
                'bid.placed' => 'A new bid of {{bid_amount}} has been placed on your auction "{{auction_title}}".',
                'payment.success' => 'Payment of {{amount}} has been processed successfully for order #{{order_id}}.'
            ],
            'sms' => [
                'verification' => 'Your verification code is: {{code}}',
                'order_update' => 'Order #{{order_id}} status: {{status}}',
                'order.created' => 'Order #{{order_number}} created successfully. Total: {{total_amount}}',
                'auction.ending' => 'Auction "{{auction_title}}" ends in {{time_remaining}}. Current bid: {{current_bid}}',
                'payment.failed' => 'Payment failed for order #{{order_id}}. Please try again.'
            ],
            'whatsapp' => [
                'order.created' => '🛍️ Hi {{customer_name}}! Your order #{{order_number}} has been created.\n\nTotal: {{total_amount}}\nStatus: {{status}}\n\nThank you for shopping with us!',
                'auction.created' => '🏷️ Your auction "{{auction_title}}" is now live!\n\nStarting Price: {{starting_price}}\nEnds: {{ends_at}}\n\nGood luck!',
                'bid.placed' => '💰 New bid on "{{auction_title}}"!\n\nBid Amount: {{bid_amount}}\nBidder: {{bidder_name}}\nTime Left: {{time_remaining}}',
                'payment.success' => '✅ Payment Successful!\n\nAmount: {{amount}}\nOrder: #{{order_id}}\nMethod: {{payment_method}}'
            ],
            'telegram' => [
                'order.created' => '<b>🛍️ Order Created</b>\n\nHi {{customer_name}}!\nYour order #{{order_number}} has been created successfully.\n\n<b>Total:</b> {{total_amount}}\n<b>Status:</b> {{status}}',
                'auction.created' => '<b>🏷️ Auction Live</b>\n\n"{{auction_title}}" is now active!\n\n<b>Starting Price:</b> {{starting_price}}\n<b>Ends:</b> {{ends_at}}',
                'bid.placed' => '<b>💰 New Bid</b>\n\nAuction: "{{auction_title}}"\n<b>Bid:</b> {{bid_amount}}\n<b>Bidder:</b> {{bidder_name}}\n<b>Time Left:</b> {{time_remaining}}',
                'payment.success' => '<b>✅ Payment Successful</b>\n\n<b>Amount:</b> {{amount}}\n<b>Order:</b> #{{order_id}}\n<b>Method:</b> {{payment_method}}'
            ],
            'push' => [
                'order.created' => 'Order #{{order_number}} created successfully!',
                'auction.ending' => 'Auction "{{auction_title}}" ends soon!',
                'bid.placed' => 'New bid of {{bid_amount}} on your auction',
                'payment.success' => 'Payment of {{amount}} processed successfully'
            ]
        ];
        
        $templateContent = $templates[$type][$template] ?? $template;
        
        // Simple template variable replacement
        foreach ($data as $key => $value) {
            $templateContent = str_replace('{{' . $key . '}}', $value, $templateContent);
        }
        
        return $templateContent;
    }

    /**
     * Generate notification ID
     *
     * @param string $type
     * @return string
     */
    private function generateNotificationId(string $type): string
    {
        return $type . '_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Store notification record
     *
     * @param string $notificationId
     * @param string $type
     * @param array $data
     * @return void
     */
    private function storeNotificationRecord(string $notificationId, string $type, array $data): void
    {
        $record = array_merge($data, [
            'notification_id' => $notificationId,
            'type' => $type,
            'created_at' => now()->toISOString()
        ]);
        
        // Store in cache for 30 days
        $this->cache("notification:{$notificationId}", $record, 86400 * 30);
    }

    /**
     * Get notification record
     *
     * @param string $notificationId
     * @return array|null
     */
    private function getNotificationRecord(string $notificationId): ?array
    {
        return $this->getCached("notification:{$notificationId}");
    }

    /**
     * Get delivery status from provider
     *
     * @param string $notificationId
     * @param string $type
     * @return array
     */
    private function getDeliveryStatus(string $notificationId, string $type): array
    {
        // This would query the actual provider for delivery status
        // For now, return mock status
        
        return [
            'status' => 'delivered',
            'delivered_at' => now()->subMinutes(rand(1, 60))->toISOString(),
            'details' => [
                'provider' => 'mock_provider',
                'attempts' => 1
            ]
        ];
    }

    /**
     * Subscribe user to notifications
     *
     * @param int $userId
     * @param array $notificationTypes
     * @param array $preferences
     * @return array
     */
    private function subscribeUser(int $userId, array $notificationTypes, array $preferences): array
    {
        $subscriptions = $this->getUserSubscriptions($userId);
        
        foreach ($notificationTypes as $type) {
            $subscriptions['subscriptions'][$type] = true;
        }
        
        $subscriptions['preferences'] = array_merge($subscriptions['preferences'], $preferences);
        $subscriptions['updated_at'] = now()->toISOString();
        
        $this->cache("user_subscriptions:{$userId}", $subscriptions, 86400 * 365); // 1 year
        
        return [
            'success' => true,
            'subscriptions' => $subscriptions
        ];
    }

    /**
     * Unsubscribe user from notifications
     *
     * @param int $userId
     * @param array $notificationTypes
     * @return array
     */
    private function unsubscribeUser(int $userId, array $notificationTypes): array
    {
        $subscriptions = $this->getUserSubscriptions($userId);
        
        foreach ($notificationTypes as $type) {
            $subscriptions['subscriptions'][$type] = false;
        }
        
        $subscriptions['updated_at'] = now()->toISOString();
        
        $this->cache("user_subscriptions:{$userId}", $subscriptions, 86400 * 365);
        
        return [
            'success' => true,
            'subscriptions' => $subscriptions
        ];
    }

    /**
     * Update user notification preferences
     *
     * @param int $userId
     * @param array $preferences
     * @return array
     */
    private function updateUserPreferences(int $userId, array $preferences): array
    {
        $subscriptions = $this->getUserSubscriptions($userId);
        $subscriptions['preferences'] = array_merge($subscriptions['preferences'], $preferences);
        $subscriptions['updated_at'] = now()->toISOString();
        
        $this->cache("user_subscriptions:{$userId}", $subscriptions, 86400 * 365);
        
        return [
            'success' => true,
            'subscriptions' => $subscriptions
        ];
    }

    /**
     * Get user subscriptions
     *
     * @param int $userId
     * @return array
     */
    private function getUserSubscriptions(int $userId): array
    {
        return $this->getCached("user_subscriptions:{$userId}", [
            'user_id' => $userId,
            'subscriptions' => [
                'email' => true,
                'sms' => true,
                'push' => true,
                'order_updates' => true,
                'marketing' => false
            ],
            'preferences' => [
                'email_frequency' => 'immediate',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '08:00',
                'timezone' => 'UTC'
            ],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ]);
    }
}
