<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * WhatsApp Service
 * 
 * Supports multiple MENA-compatible WhatsApp providers:
 * - WhatsApp Business API (Meta)
 * - Infobip (MENA-friendly)
 * - Unifonic (Saudi-based)
 * - Msegat (UAE-based)
 * - Oursms (Egypt-based)
 */
class WhatsAppService
{
    private string $provider;
    private array $config;

    public function __construct()
    {
        $this->provider = config('services.whatsapp.provider', 'infobip');
        $this->config = config('services.whatsapp.providers.' . $this->provider, []);
    }

    /**
     * Send WhatsApp message to a single recipient
     */
    public function sendMessage(string $to, string $message, array $options = []): array
    {
        try {
            // Normalize phone number
            $to = $this->normalizePhoneNumber($to);
            
            // Validate phone number
            if (!$this->isValidPhoneNumber($to)) {
                return [
                    'success' => false,
                    'error' => 'INVALID_PHONE_NUMBER',
                    'message' => 'Invalid phone number format'
                ];
            }

            // Check rate limiting
            if (!$this->checkRateLimit($to)) {
                return [
                    'success' => false,
                    'error' => 'RATE_LIMITED',
                    'message' => 'Rate limit exceeded for this number'
                ];
            }

            // Send based on provider
            $result = match ($this->provider) {
                'infobip' => $this->sendViaInfobip($to, $message, $options),
                'unifonic' => $this->sendViaUnifonic($to, $message, $options),
                'msegat' => $this->sendViaMsegat($to, $message, $options),
                'oursms' => $this->sendViaOursms($to, $message, $options),
                'meta' => $this->sendViaMeta($to, $message, $options),
                default => throw new Exception("Unsupported WhatsApp provider: {$this->provider}")
            };

            // Update rate limiting
            if ($result['success']) {
                $this->updateRateLimit($to);
            }

            Log::info('WhatsApp message sent', [
                'provider' => $this->provider,
                'to' => $to,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('WhatsApp message failed', [
                'provider' => $this->provider,
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'SEND_FAILED',
                'message' => 'Failed to send WhatsApp message: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send WhatsApp template message
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = [], array $options = []): array
    {
        try {
            $to = $this->normalizePhoneNumber($to);
            
            if (!$this->isValidPhoneNumber($to)) {
                return [
                    'success' => false,
                    'error' => 'INVALID_PHONE_NUMBER',
                    'message' => 'Invalid phone number format'
                ];
            }

            $result = match ($this->provider) {
                'infobip' => $this->sendTemplateViaInfobip($to, $templateName, $parameters, $options),
                'unifonic' => $this->sendTemplateViaUnifonic($to, $templateName, $parameters, $options),
                'msegat' => $this->sendTemplateViaMsegat($to, $templateName, $parameters, $options),
                'meta' => $this->sendTemplateViaMeta($to, $templateName, $parameters, $options),
                default => $this->sendMessage($to, $this->renderTemplate($templateName, $parameters), $options)
            };

            return $result;

        } catch (Exception $e) {
            Log::error('WhatsApp template message failed', [
                'provider' => $this->provider,
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'TEMPLATE_SEND_FAILED',
                'message' => 'Failed to send WhatsApp template: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send via Infobip (MENA-friendly, good coverage)
     */
    private function sendViaInfobip(string $to, string $message, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'App ' . $this->config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ])->post($this->config['base_url'] . '/whatsapp/1/message/text', [
            'from' => $this->config['from'],
            'to' => $to,
            'messageId' => uniqid('wa_'),
            'content' => [
                'text' => $message
            ],
            'callbackData' => $options['callback_data'] ?? null
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['messages'][0]['messageId'] ?? null,
                'status' => $data['messages'][0]['status']['name'] ?? 'sent',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'PROVIDER_ERROR',
            'message' => 'Infobip API error: ' . $response->body(),
            'status_code' => $response->status()
        ];
    }

    /**
     * Send via Unifonic (Saudi-based, excellent MENA coverage)
     */
    private function sendViaUnifonic(string $to, string $message, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key'],
            'Content-Type' => 'application/json'
        ])->post($this->config['base_url'] . '/v1/messages', [
            'recipient' => $to,
            'body' => $message,
            'type' => 'text',
            'sender_id' => $this->config['sender_id']
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['data']['message_id'] ?? null,
                'status' => 'sent',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'PROVIDER_ERROR',
            'message' => 'Unifonic API error: ' . $response->body(),
            'status_code' => $response->status()
        ];
    }

    /**
     * Send via Msegat (UAE-based, good Gulf coverage)
     */
    private function sendViaMsegat(string $to, string $message, array $options = []): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json'
        ])->post($this->config['base_url'] . '/gw/sendsms.php', [
            'userName' => $this->config['username'],
            'apiKey' => $this->config['api_key'],
            'numbers' => $to,
            'userSender' => $this->config['sender'],
            'msg' => $message,
            'msgEncoding' => 'UTF8'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => $data['code'] === '1',
                'message_id' => $data['msgID'] ?? null,
                'status' => $data['code'] === '1' ? 'sent' : 'failed',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'PROVIDER_ERROR',
            'message' => 'Msegat API error: ' . $response->body(),
            'status_code' => $response->status()
        ];
    }

    /**
     * Send via Oursms (Egypt-based, good North Africa coverage)
     */
    private function sendViaOursms(string $to, string $message, array $options = []): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->config['api_key']
        ])->post($this->config['base_url'] . '/api/v1/whatsapp/send', [
            'to' => $to,
            'message' => $message,
            'from' => $this->config['from']
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => $data['success'] ?? false,
                'message_id' => $data['message_id'] ?? null,
                'status' => 'sent',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'PROVIDER_ERROR',
            'message' => 'Oursms API error: ' . $response->body(),
            'status_code' => $response->status()
        ];
    }

    /**
     * Send via Meta WhatsApp Business API
     */
    private function sendViaMeta(string $to, string $message, array $options = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['access_token'],
            'Content-Type' => 'application/json'
        ])->post($this->config['base_url'] . '/' . $this->config['phone_number_id'] . '/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'PROVIDER_ERROR',
            'message' => 'Meta API error: ' . $response->body(),
            'status_code' => $response->status()
        ];
    }

    /**
     * Send template via Infobip
     */
    private function sendTemplateViaInfobip(string $to, string $templateName, array $parameters, array $options): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'App ' . $this->config['api_key'],
            'Content-Type' => 'application/json'
        ])->post($this->config['base_url'] . '/whatsapp/1/message/template', [
            'from' => $this->config['from'],
            'to' => $to,
            'messageId' => uniqid('wa_tpl_'),
            'content' => [
                'templateName' => $templateName,
                'templateData' => $parameters,
                'language' => $options['language'] ?? 'en'
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['messages'][0]['messageId'] ?? null,
                'status' => 'sent',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'TEMPLATE_ERROR',
            'message' => 'Template send failed: ' . $response->body()
        ];
    }

    /**
     * Send template via Meta
     */
    private function sendTemplateViaMeta(string $to, string $templateName, array $parameters, array $options): array
    {
        $templateComponents = [];
        if (!empty($parameters)) {
            $templateComponents[] = [
                'type' => 'body',
                'parameters' => array_map(fn($param) => ['type' => 'text', 'text' => $param], $parameters)
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['access_token'],
            'Content-Type' => 'application/json'
        ])->post($this->config['base_url'] . '/' . $this->config['phone_number_id'] . '/messages', [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $options['language'] ?? 'en'
                ],
                'components' => $templateComponents
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['messages'][0]['id'] ?? null,
                'status' => 'sent',
                'provider_response' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'TEMPLATE_ERROR',
            'message' => 'Template send failed: ' . $response->body()
        ];
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if missing (default to Saudi Arabia +966)
        if (!str_starts_with($phone, '966') && !str_starts_with($phone, '+966')) {
            if (str_starts_with($phone, '0')) {
                $phone = '966' . substr($phone, 1);
            } else {
                $phone = '966' . $phone;
            }
        }
        
        // Remove + if present
        $phone = ltrim($phone, '+');
        
        return $phone;
    }

    /**
     * Validate phone number format
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // Basic validation for MENA region numbers
        return preg_match('/^(966|971|973|974|965|968|20|213|212|216|218|249|963|962|964|961|970|972)\d{7,10}$/', $phone);
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(string $phone): bool
    {
        $key = "whatsapp_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        
        // Allow 10 messages per hour per number
        return $count < 10;
    }

    /**
     * Update rate limiting
     */
    private function updateRateLimit(string $phone): void
    {
        $key = "whatsapp_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    /**
     * Render template with parameters
     */
    private function renderTemplate(string $templateName, array $parameters): string
    {
        $template = config("services.whatsapp.templates.{$templateName}", $templateName);
        
        foreach ($parameters as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }
        
        return $template;
    }

    /**
     * Get delivery status
     */
    public function getDeliveryStatus(string $messageId): array
    {
        try {
            $result = match ($this->provider) {
                'infobip' => $this->getInfobipStatus($messageId),
                'unifonic' => $this->getUnifonicStatus($messageId),
                'meta' => $this->getMetaStatus($messageId),
                default => ['status' => 'unknown', 'message' => 'Status check not supported for this provider']
            };

            return [
                'success' => true,
                'message_id' => $messageId,
                'status' => $result['status'],
                'details' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'STATUS_CHECK_FAILED',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get status from Infobip
     */
    private function getInfobipStatus(string $messageId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'App ' . $this->config['api_key']
        ])->get($this->config['base_url'] . '/whatsapp/1/reports', [
            'messageId' => $messageId
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'status' => $data['results'][0]['status']['name'] ?? 'unknown',
                'delivered_at' => $data['results'][0]['doneAt'] ?? null,
                'error' => $data['results'][0]['error'] ?? null
            ];
        }

        return ['status' => 'unknown'];
    }

    /**
     * Get status from Unifonic
     */
    private function getUnifonicStatus(string $messageId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['api_key']
        ])->get($this->config['base_url'] . '/v1/messages/' . $messageId);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'status' => $data['data']['status'] ?? 'unknown',
                'delivered_at' => $data['data']['delivered_at'] ?? null
            ];
        }

        return ['status' => 'unknown'];
    }

    /**
     * Get status from Meta
     */
    private function getMetaStatus(string $messageId): array
    {
        // Meta doesn't provide a direct status API, status comes via webhooks
        return ['status' => 'sent', 'message' => 'Status tracking via webhooks'];
    }
}
