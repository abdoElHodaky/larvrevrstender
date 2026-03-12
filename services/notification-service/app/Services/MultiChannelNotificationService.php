<?php

namespace App\Services;

use App\Services\WhatsAppService;
use App\Services\TelegramService;
use App\Services\SignalService;
use App\Services\WebPushService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Exception;

/**
 * Multi-Channel Notification Service
 * 
 * Orchestrates notifications across multiple channels:
 * - Email
 * - SMS (via MENA-compatible providers)
 * - WhatsApp (via multiple MENA providers)
 * - Telegram
 * - Signal
 * - Web Push
 * - In-app notifications
 */
class MultiChannelNotificationService
{
    private WhatsAppService $whatsAppService;
    private TelegramService $telegramService;
    private SignalService $signalService;
    private WebPushService $webPushService;

    public function __construct(
        WhatsAppService $whatsAppService,
        TelegramService $telegramService,
        SignalService $signalService,
        WebPushService $webPushService
    ) {
        $this->whatsAppService = $whatsAppService;
        $this->telegramService = $telegramService;
        $this->signalService = $signalService;
        $this->webPushService = $webPushService;
    }

    /**
     * Send notification across multiple channels
     */
    public function sendMultiChannelNotification(array $data): array
    {
        $results = [
            'notification_id' => $data['notification_id'] ?? uniqid('notif_'),
            'channels' => [],
            'success_count' => 0,
            'failure_count' => 0,
            'total_channels' => 0
        ];

        $channels = $data['channels'] ?? ['email']; // Default to email
        $results['total_channels'] = count($channels);

        foreach ($channels as $channel) {
            try {
                $channelResult = $this->sendToChannel($channel, $data);
                $results['channels'][$channel] = $channelResult;

                if ($channelResult['success']) {
                    $results['success_count']++;
                } else {
                    $results['failure_count']++;
                }

            } catch (Exception $e) {
                $results['channels'][$channel] = [
                    'success' => false,
                    'error' => 'CHANNEL_ERROR',
                    'message' => $e->getMessage()
                ];
                $results['failure_count']++;

                Log::error('Multi-channel notification failed for channel', [
                    'channel' => $channel,
                    'notification_id' => $results['notification_id'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Multi-channel notification completed', [
            'notification_id' => $results['notification_id'],
            'success_count' => $results['success_count'],
            'failure_count' => $results['failure_count'],
            'channels' => array_keys($results['channels'])
        ]);

        return $results;
    }

    /**
     * Send notification to a specific channel
     */
    private function sendToChannel(string $channel, array $data): array
    {
        return match ($channel) {
            'email' => $this->sendEmail($data),
            'sms' => $this->sendSms($data),
            'whatsapp' => $this->sendWhatsApp($data),
            'telegram' => $this->sendTelegram($data),
            'signal' => $this->sendSignal($data),
            'web_push' => $this->sendWebPush($data),
            'in_app' => $this->sendInApp($data),
            default => [
                'success' => false,
                'error' => 'UNSUPPORTED_CHANNEL',
                'message' => "Unsupported notification channel: {$channel}"
            ]
        };
    }

    /**
     * Send email notification
     */
    private function sendEmail(array $data): array
    {
        try {
            $to = $data['email'] ?? $data['recipient']['email'] ?? null;
            
            if (empty($to)) {
                return [
                    'success' => false,
                    'error' => 'EMAIL_MISSING',
                    'message' => 'Email address not provided'
                ];
            }

            $subject = $data['subject'] ?? $data['title'] ?? 'Notification';
            $message = $data['message'] ?? $data['body'] ?? '';
            $template = $data['email_template'] ?? 'notifications.default';

            // Send email using Laravel's Mail facade
            Mail::send($template, [
                'title' => $subject,
                'message' => $message,
                'data' => $data
            ], function ($mail) use ($to, $subject) {
                $mail->to($to)->subject($subject);
            });

            return [
                'success' => true,
                'channel' => 'email',
                'recipient' => $to,
                'message_id' => uniqid('email_')
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'EMAIL_SEND_FAILED',
                'message' => 'Failed to send email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS notification via MENA-compatible providers
     */
    private function sendSms(array $data): array
    {
        try {
            $to = $data['phone'] ?? $data['recipient']['phone'] ?? null;
            
            if (empty($to)) {
                return [
                    'success' => false,
                    'error' => 'PHONE_MISSING',
                    'message' => 'Phone number not provided'
                ];
            }

            $message = $data['message'] ?? $data['body'] ?? '';
            $provider = config('services.sms.provider', 'unifonic');

            // Send via configured SMS provider
            $result = match ($provider) {
                'unifonic' => $this->sendSmsViaUnifonic($to, $message, $data),
                'msegat' => $this->sendSmsViaMsegat($to, $message, $data),
                'oursms' => $this->sendSmsViaOursms($to, $message, $data),
                'infobip' => $this->sendSmsViaInfobip($to, $message, $data),
                default => [
                    'success' => false,
                    'error' => 'SMS_PROVIDER_NOT_SUPPORTED',
                    'message' => "SMS provider {$provider} not supported"
                ]
            };

            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'SMS_SEND_FAILED',
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWhatsApp(array $data): array
    {
        try {
            $to = $data['whatsapp'] ?? $data['phone'] ?? $data['recipient']['whatsapp'] ?? $data['recipient']['phone'] ?? null;
            
            if (empty($to)) {
                return [
                    'success' => false,
                    'error' => 'WHATSAPP_NUMBER_MISSING',
                    'message' => 'WhatsApp number not provided'
                ];
            }

            $message = $data['message'] ?? $data['body'] ?? '';
            $template = $data['whatsapp_template'] ?? null;

            if ($template) {
                $parameters = $data['template_parameters'] ?? [];
                return $this->whatsAppService->sendTemplate($to, $template, $parameters, $data);
            } else {
                return $this->whatsAppService->sendMessage($to, $message, $data);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'WHATSAPP_SEND_FAILED',
                'message' => 'Failed to send WhatsApp: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send Telegram notification
     */
    private function sendTelegram(array $data): array
    {
        try {
            $chatId = $data['telegram_chat_id'] ?? $data['recipient']['telegram_chat_id'] ?? null;
            
            if (empty($chatId)) {
                return [
                    'success' => false,
                    'error' => 'TELEGRAM_CHAT_ID_MISSING',
                    'message' => 'Telegram chat ID not provided'
                ];
            }

            $message = $data['message'] ?? $data['body'] ?? '';
            $buttons = $data['telegram_buttons'] ?? null;

            if ($buttons) {
                return $this->telegramService->sendMessageWithButtons($chatId, $message, $buttons, $data);
            } else {
                return $this->telegramService->sendMessage($chatId, $message, $data);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'TELEGRAM_SEND_FAILED',
                'message' => 'Failed to send Telegram: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send Signal notification
     */
    private function sendSignal(array $data): array
    {
        try {
            $to = $data['signal'] ?? $data['phone'] ?? $data['recipient']['signal'] ?? $data['recipient']['phone'] ?? null;
            
            if (empty($to)) {
                return [
                    'success' => false,
                    'error' => 'SIGNAL_NUMBER_MISSING',
                    'message' => 'Signal number not provided'
                ];
            }

            $message = $data['message'] ?? $data['body'] ?? '';
            $attachment = $data['signal_attachment'] ?? null;

            if ($attachment) {
                return $this->signalService->sendMessageWithAttachment($to, $message, $attachment, $data);
            } else {
                return $this->signalService->sendMessage($to, $message, $data);
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'SIGNAL_SEND_FAILED',
                'message' => 'Failed to send Signal: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send web push notification
     */
    private function sendWebPush(array $data): array
    {
        try {
            $userId = $data['user_id'] ?? $data['recipient']['user_id'] ?? null;
            
            if (empty($userId)) {
                return [
                    'success' => false,
                    'error' => 'USER_ID_MISSING',
                    'message' => 'User ID not provided for web push'
                ];
            }

            $title = $data['title'] ?? 'Notification';
            $body = $data['message'] ?? $data['body'] ?? '';
            $options = [
                'icon' => $data['icon'] ?? null,
                'badge' => $data['badge'] ?? null,
                'image' => $data['image'] ?? null,
                'actions' => $data['actions'] ?? [],
                'data' => $data['push_data'] ?? []
            ];

            return $this->webPushService->sendToUser($userId, $title, $body, $options);

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'WEB_PUSH_SEND_FAILED',
                'message' => 'Failed to send web push: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send in-app notification
     */
    private function sendInApp(array $data): array
    {
        try {
            $userId = $data['user_id'] ?? $data['recipient']['user_id'] ?? null;
            
            if (empty($userId)) {
                return [
                    'success' => false,
                    'error' => 'USER_ID_MISSING',
                    'message' => 'User ID not provided for in-app notification'
                ];
            }

            // Store in-app notification in database
            $notification = [
                'user_id' => $userId,
                'title' => $data['title'] ?? 'Notification',
                'message' => $data['message'] ?? $data['body'] ?? '',
                'type' => $data['type'] ?? 'general',
                'data' => json_encode($data['notification_data'] ?? []),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Insert into notifications table using Eloquent (Laravel 12)
            $notificationRecord = \App\Models\Notification::create($notification);
            $notificationId = $notificationRecord->id;

            // Broadcast real-time notification if WebSocket is available
            if (config('broadcasting.default') !== 'null') {
                broadcast(new \App\Events\InAppNotificationEvent($userId, $notification));
            }

            return [
                'success' => true,
                'channel' => 'in_app',
                'notification_id' => $notificationId,
                'user_id' => $userId
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'IN_APP_SEND_FAILED',
                'message' => 'Failed to send in-app notification: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via Unifonic (Saudi-based)
     */
    private function sendSmsViaUnifonic(string $to, string $message, array $data): array
    {
        $config = config('services.sms.unifonic');
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key']
        ])->post($config['base_url'] . '/v1/messages', [
            'recipient' => $to,
            'body' => $message,
            'sender_id' => $config['sender_id']
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            return [
                'success' => true,
                'channel' => 'sms',
                'provider' => 'unifonic',
                'message_id' => $responseData['data']['message_id'] ?? null,
                'recipient' => $to
            ];
        }

        return [
            'success' => false,
            'error' => 'SMS_UNIFONIC_FAILED',
            'message' => 'Unifonic SMS failed: ' . $response->body()
        ];
    }

    /**
     * Send SMS via Msegat (UAE-based)
     */
    private function sendSmsViaMsegat(string $to, string $message, array $data): array
    {
        $config = config('services.sms.msegat');
        
        $response = Http::post($config['base_url'] . '/gw/sendsms.php', [
            'userName' => $config['username'],
            'apiKey' => $config['api_key'],
            'numbers' => $to,
            'userSender' => $config['sender'],
            'msg' => $message,
            'msgEncoding' => 'UTF8'
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            return [
                'success' => $responseData['code'] === '1',
                'channel' => 'sms',
                'provider' => 'msegat',
                'message_id' => $responseData['msgID'] ?? null,
                'recipient' => $to
            ];
        }

        return [
            'success' => false,
            'error' => 'SMS_MSEGAT_FAILED',
            'message' => 'Msegat SMS failed: ' . $response->body()
        ];
    }

    /**
     * Send SMS via Oursms (Egypt-based)
     */
    private function sendSmsViaOursms(string $to, string $message, array $data): array
    {
        $config = config('services.sms.oursms');
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key']
        ])->post($config['base_url'] . '/api/v1/sms/send', [
            'to' => $to,
            'message' => $message,
            'from' => $config['from']
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            return [
                'success' => $responseData['success'] ?? false,
                'channel' => 'sms',
                'provider' => 'oursms',
                'message_id' => $responseData['message_id'] ?? null,
                'recipient' => $to
            ];
        }

        return [
            'success' => false,
            'error' => 'SMS_OURSMS_FAILED',
            'message' => 'Oursms SMS failed: ' . $response->body()
        ];
    }

    /**
     * Send SMS via Infobip
     */
    private function sendSmsViaInfobip(string $to, string $message, array $data): array
    {
        $config = config('services.sms.infobip');
        
        $response = Http::withHeaders([
            'Authorization' => 'App ' . $config['api_key']
        ])->post($config['base_url'] . '/sms/2/text/advanced', [
            'messages' => [
                [
                    'from' => $config['from'],
                    'destinations' => [
                        ['to' => $to]
                    ],
                    'text' => $message
                ]
            ]
        ]);

        if ($response->successful()) {
            $responseData = $response->json();
            return [
                'success' => true,
                'channel' => 'sms',
                'provider' => 'infobip',
                'message_id' => $responseData['messages'][0]['messageId'] ?? null,
                'recipient' => $to
            ];
        }

        return [
            'success' => false,
            'error' => 'SMS_INFOBIP_FAILED',
            'message' => 'Infobip SMS failed: ' . $response->body()
        ];
    }

    /**
     * Get channel availability for a user
     */
    public function getAvailableChannels(array $recipient): array
    {
        $available = [];

        // Check email
        if (!empty($recipient['email'])) {
            $available[] = 'email';
        }

        // Check phone (for SMS, WhatsApp, Signal)
        if (!empty($recipient['phone'])) {
            $available[] = 'sms';
            $available[] = 'whatsapp';
            $available[] = 'signal';
        }

        // Check Telegram
        if (!empty($recipient['telegram_chat_id'])) {
            $available[] = 'telegram';
        }

        // Check user ID (for web push and in-app)
        if (!empty($recipient['user_id'])) {
            $available[] = 'web_push';
            $available[] = 'in_app';
        }

        return $available;
    }

    /**
     * Send notification with automatic channel selection
     */
    public function sendWithAutoChannelSelection(array $data): array
    {
        $recipient = $data['recipient'] ?? [];
        $preferredChannels = $data['preferred_channels'] ?? [];
        $fallbackChannels = $data['fallback_channels'] ?? ['email'];

        $availableChannels = $this->getAvailableChannels($recipient);
        
        // Use preferred channels if available, otherwise use fallback
        $channelsToUse = array_intersect($preferredChannels, $availableChannels);
        if (empty($channelsToUse)) {
            $channelsToUse = array_intersect($fallbackChannels, $availableChannels);
        }

        if (empty($channelsToUse)) {
            return [
                'success' => false,
                'error' => 'NO_AVAILABLE_CHANNELS',
                'message' => 'No available notification channels for recipient',
                'available_channels' => $availableChannels
            ];
        }

        $data['channels'] = $channelsToUse;
        return $this->sendMultiChannelNotification($data);
    }
}
