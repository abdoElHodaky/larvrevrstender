<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Telegram Service
 * 
 * Supports Telegram Bot API for sending messages via Telegram bots.
 * Works well in MENA region with reliable delivery.
 */
class TelegramService
{
    private string $botToken;
    private string $baseUrl;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token');
        $this->baseUrl = config('services.telegram.base_url', 'https://api.telegram.org/bot');
    }

    /**
     * Send message to a Telegram chat
     */
    public function sendMessage(string $chatId, string $message, array $options = []): array
    {
        try {
            if (empty($this->botToken)) {
                return [
                    'success' => false,
                    'error' => 'BOT_TOKEN_MISSING',
                    'message' => 'Telegram bot token not configured'
                ];
            }

            // Check rate limiting
            if (!$this->checkRateLimit($chatId)) {
                return [
                    'success' => false,
                    'error' => 'RATE_LIMITED',
                    'message' => 'Rate limit exceeded for this chat'
                ];
            }

            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
                'disable_web_page_preview' => $options['disable_preview'] ?? false,
                'disable_notification' => $options['silent'] ?? false
            ];

            // Add reply markup if provided
            if (isset($options['keyboard'])) {
                $payload['reply_markup'] = json_encode($options['keyboard']);
            }

            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/sendMessage', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    $this->updateRateLimit($chatId);
                    
                    Log::info('Telegram message sent', [
                        'chat_id' => $chatId,
                        'message_id' => $data['result']['message_id']
                    ]);

                    return [
                        'success' => true,
                        'message_id' => $data['result']['message_id'],
                        'chat_id' => $data['result']['chat']['id'],
                        'date' => $data['result']['date'],
                        'provider_response' => $data
                    ];
                }
            }

            $errorData = $response->json();
            return [
                'success' => false,
                'error' => 'TELEGRAM_API_ERROR',
                'message' => $errorData['description'] ?? 'Unknown Telegram API error',
                'error_code' => $errorData['error_code'] ?? null
            ];

        } catch (Exception $e) {
            Log::error('Telegram message failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'SEND_FAILED',
                'message' => 'Failed to send Telegram message: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send photo message
     */
    public function sendPhoto(string $chatId, string $photo, string $caption = '', array $options = []): array
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'photo' => $photo,
                'caption' => $caption,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
                'disable_notification' => $options['silent'] ?? false
            ];

            if (isset($options['keyboard'])) {
                $payload['reply_markup'] = json_encode($options['keyboard']);
            }

            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/sendPhoto', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'message_id' => $data['result']['message_id'],
                        'chat_id' => $data['result']['chat']['id'],
                        'provider_response' => $data
                    ];
                }
            }

            $errorData = $response->json();
            return [
                'success' => false,
                'error' => 'TELEGRAM_API_ERROR',
                'message' => $errorData['description'] ?? 'Failed to send photo'
            ];

        } catch (Exception $e) {
            Log::error('Telegram photo send failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'PHOTO_SEND_FAILED',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send document
     */
    public function sendDocument(string $chatId, string $document, string $caption = '', array $options = []): array
    {
        try {
            $payload = [
                'chat_id' => $chatId,
                'document' => $document,
                'caption' => $caption,
                'parse_mode' => $options['parse_mode'] ?? 'HTML',
                'disable_notification' => $options['silent'] ?? false
            ];

            $response = Http::timeout(60)->post($this->baseUrl . $this->botToken . '/sendDocument', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'message_id' => $data['result']['message_id'],
                        'chat_id' => $data['result']['chat']['id'],
                        'provider_response' => $data
                    ];
                }
            }

            $errorData = $response->json();
            return [
                'success' => false,
                'error' => 'TELEGRAM_API_ERROR',
                'message' => $errorData['description'] ?? 'Failed to send document'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'DOCUMENT_SEND_FAILED',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send message with inline keyboard
     */
    public function sendMessageWithButtons(string $chatId, string $message, array $buttons, array $options = []): array
    {
        $keyboard = [
            'inline_keyboard' => $this->formatInlineKeyboard($buttons)
        ];

        $options['keyboard'] = $keyboard;
        return $this->sendMessage($chatId, $message, $options);
    }

    /**
     * Send message to multiple chats
     */
    public function sendToMultipleChats(array $chatIds, string $message, array $options = []): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($chatIds as $chatId) {
            $result = $this->sendMessage($chatId, $message, $options);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$chatId] = $result;
            
            // Small delay to avoid hitting rate limits
            usleep(100000); // 0.1 second
        }

        return $results;
    }

    /**
     * Get chat information
     */
    public function getChat(string $chatId): array
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/getChat', [
                'chat_id' => $chatId
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'chat' => $data['result']
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'CHAT_NOT_FOUND',
                'message' => 'Chat not found or bot not added to chat'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'GET_CHAT_FAILED',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get bot information
     */
    public function getBotInfo(): array
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/getMe');

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'bot' => $data['result']
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'BOT_INFO_FAILED',
                'message' => 'Failed to get bot information'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'BOT_INFO_ERROR',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Set webhook for receiving updates
     */
    public function setWebhook(string $url, array $options = []): array
    {
        try {
            $payload = [
                'url' => $url,
                'max_connections' => $options['max_connections'] ?? 40,
                'allowed_updates' => $options['allowed_updates'] ?? ['message', 'callback_query']
            ];

            if (isset($options['secret_token'])) {
                $payload['secret_token'] = $options['secret_token'];
            }

            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/setWebhook', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'description' => $data['description']
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'WEBHOOK_SETUP_FAILED',
                'message' => 'Failed to set webhook'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'WEBHOOK_ERROR',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(): array
    {
        try {
            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/deleteWebhook');

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true,
                        'description' => $data['description']
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'WEBHOOK_DELETE_FAILED',
                'message' => 'Failed to delete webhook'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'WEBHOOK_DELETE_ERROR',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Answer callback query (for inline keyboards)
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', array $options = []): array
    {
        try {
            $payload = [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $options['show_alert'] ?? false,
                'cache_time' => $options['cache_time'] ?? 0
            ];

            if (isset($options['url'])) {
                $payload['url'] = $options['url'];
            }

            $response = Http::timeout(30)->post($this->baseUrl . $this->botToken . '/answerCallbackQuery', $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['ok']) {
                    return [
                        'success' => true
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'CALLBACK_ANSWER_FAILED',
                'message' => 'Failed to answer callback query'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'CALLBACK_ERROR',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Format inline keyboard buttons
     */
    private function formatInlineKeyboard(array $buttons): array
    {
        $keyboard = [];
        
        foreach ($buttons as $row) {
            $keyboardRow = [];
            
            if (is_array($row)) {
                foreach ($row as $button) {
                    $keyboardRow[] = $this->formatButton($button);
                }
            } else {
                $keyboardRow[] = $this->formatButton($row);
            }
            
            $keyboard[] = $keyboardRow;
        }
        
        return $keyboard;
    }

    /**
     * Format individual button
     */
    private function formatButton(array $button): array
    {
        $formattedButton = [
            'text' => $button['text']
        ];

        if (isset($button['url'])) {
            $formattedButton['url'] = $button['url'];
        } elseif (isset($button['callback_data'])) {
            $formattedButton['callback_data'] = $button['callback_data'];
        } elseif (isset($button['switch_inline_query'])) {
            $formattedButton['switch_inline_query'] = $button['switch_inline_query'];
        }

        return $formattedButton;
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(string $chatId): bool
    {
        $key = "telegram_rate_limit:{$chatId}";
        $count = Cache::get($key, 0);
        
        // Allow 30 messages per minute per chat
        return $count < 30;
    }

    /**
     * Update rate limiting
     */
    private function updateRateLimit(string $chatId): void
    {
        $key = "telegram_rate_limit:{$chatId}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addMinute());
    }

    /**
     * Validate chat ID format
     */
    public function isValidChatId(string $chatId): bool
    {
        // Chat ID can be numeric (user/group ID) or string (channel username)
        return preg_match('/^(-?\d+|@\w+)$/', $chatId);
    }

    /**
     * Parse Telegram entities (mentions, hashtags, etc.)
     */
    public function parseEntities(string $text, array $entities = []): array
    {
        $parsed = [
            'mentions' => [],
            'hashtags' => [],
            'urls' => [],
            'bot_commands' => []
        ];

        foreach ($entities as $entity) {
            $entityText = substr($text, $entity['offset'], $entity['length']);
            
            match ($entity['type']) {
                'mention' => $parsed['mentions'][] = $entityText,
                'hashtag' => $parsed['hashtags'][] = $entityText,
                'url' => $parsed['urls'][] = $entityText,
                'bot_command' => $parsed['bot_commands'][] = $entityText,
                default => null
            };
        }

        return $parsed;
    }

    /**
     * Escape HTML characters for Telegram
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape Markdown characters for Telegram
     */
    public function escapeMarkdown(string $text): string
    {
        $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        
        foreach ($chars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        
        return $text;
    }
}
