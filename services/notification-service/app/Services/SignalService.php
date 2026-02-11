<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Exception;

/**
 * Signal Service
 * 
 * Supports Signal messaging via multiple methods:
 * - Signal CLI (local installation)
 * - Signal API Gateway (third-party service)
 * - Signal Bot API (if available)
 * 
 * Note: Signal doesn't have an official business API like WhatsApp,
 * so this uses community solutions and Signal CLI.
 */
class SignalService
{
    private string $method;
    private array $config;

    public function __construct()
    {
        $this->method = config('services.signal.method', 'cli');
        $this->config = config('services.signal', []);
    }

    /**
     * Send Signal message
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

            // Send based on method
            $result = match ($this->method) {
                'cli' => $this->sendViaCli($to, $message, $options),
                'api_gateway' => $this->sendViaApiGateway($to, $message, $options),
                'webhook' => $this->sendViaWebhook($to, $message, $options),
                default => throw new Exception("Unsupported Signal method: {$this->method}")
            };

            // Update rate limiting
            if ($result['success']) {
                $this->updateRateLimit($to);
            }

            Log::info('Signal message sent', [
                'method' => $this->method,
                'to' => $to,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Signal message failed', [
                'method' => $this->method,
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'SEND_FAILED',
                'message' => 'Failed to send Signal message: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send message with attachment
     */
    public function sendMessageWithAttachment(string $to, string $message, string $attachmentPath, array $options = []): array
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

            // Check if attachment exists
            if (!file_exists($attachmentPath)) {
                return [
                    'success' => false,
                    'error' => 'ATTACHMENT_NOT_FOUND',
                    'message' => 'Attachment file not found'
                ];
            }

            $result = match ($this->method) {
                'cli' => $this->sendAttachmentViaCli($to, $message, $attachmentPath, $options),
                'api_gateway' => $this->sendAttachmentViaApiGateway($to, $message, $attachmentPath, $options),
                default => [
                    'success' => false,
                    'error' => 'METHOD_NOT_SUPPORTED',
                    'message' => 'Attachment sending not supported for this method'
                ]
            };

            return $result;

        } catch (Exception $e) {
            Log::error('Signal attachment send failed', [
                'method' => $this->method,
                'to' => $to,
                'attachment' => $attachmentPath,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'ATTACHMENT_SEND_FAILED',
                'message' => 'Failed to send Signal attachment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send to multiple recipients
     */
    public function sendToMultiple(array $recipients, string $message, array $options = []): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($recipients as $recipient) {
            $result = $this->sendMessage($recipient, $message, $options);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
            
            $results['details'][$recipient] = $result;
            
            // Delay to avoid rate limits
            usleep(500000); // 0.5 second
        }

        return $results;
    }

    /**
     * Send via Signal CLI
     */
    private function sendViaCli(string $to, string $message, array $options = []): array
    {
        try {
            $cliPath = $this->config['cli_path'] ?? '/usr/local/bin/signal-cli';
            $account = $this->config['account'] ?? '';

            if (empty($account)) {
                return [
                    'success' => false,
                    'error' => 'ACCOUNT_NOT_CONFIGURED',
                    'message' => 'Signal account not configured'
                ];
            }

            // Build command
            $command = [
                $cliPath,
                '-a', $account,
                'send',
                '-m', $message,
                $to
            ];

            // Execute command
            $result = Process::run(implode(' ', array_map('escapeshellarg', $command)));

            if ($result->successful()) {
                return [
                    'success' => true,
                    'message_id' => uniqid('signal_'),
                    'method' => 'cli',
                    'output' => $result->output()
                ];
            }

            return [
                'success' => false,
                'error' => 'CLI_EXECUTION_FAILED',
                'message' => 'Signal CLI execution failed: ' . $result->errorOutput(),
                'exit_code' => $result->exitCode()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'CLI_ERROR',
                'message' => 'Signal CLI error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send attachment via Signal CLI
     */
    private function sendAttachmentViaCli(string $to, string $message, string $attachmentPath, array $options = []): array
    {
        try {
            $cliPath = $this->config['cli_path'] ?? '/usr/local/bin/signal-cli';
            $account = $this->config['account'] ?? '';

            $command = [
                $cliPath,
                '-a', $account,
                'send',
                '-m', $message,
                '-a', $attachmentPath,
                $to
            ];

            $result = Process::run(implode(' ', array_map('escapeshellarg', $command)));

            if ($result->successful()) {
                return [
                    'success' => true,
                    'message_id' => uniqid('signal_att_'),
                    'method' => 'cli',
                    'attachment_sent' => true,
                    'output' => $result->output()
                ];
            }

            return [
                'success' => false,
                'error' => 'CLI_ATTACHMENT_FAILED',
                'message' => 'Signal CLI attachment send failed: ' . $result->errorOutput()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'CLI_ATTACHMENT_ERROR',
                'message' => 'Signal CLI attachment error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send via API Gateway (third-party service)
     */
    private function sendViaApiGateway(string $to, string $message, array $options = []): array
    {
        try {
            $apiUrl = $this->config['api_gateway']['url'] ?? '';
            $apiKey = $this->config['api_gateway']['api_key'] ?? '';

            if (empty($apiUrl) || empty($apiKey)) {
                return [
                    'success' => false,
                    'error' => 'API_GATEWAY_NOT_CONFIGURED',
                    'message' => 'Signal API Gateway not configured'
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(30)->post($apiUrl . '/send', [
                'number' => $this->config['account'],
                'recipients' => [$to],
                'message' => $message,
                'base64_attachments' => $options['attachments'] ?? []
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['timestamp'] ?? uniqid('signal_gw_'),
                    'method' => 'api_gateway',
                    'provider_response' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'API_GATEWAY_ERROR',
                'message' => 'Signal API Gateway error: ' . $response->body(),
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'API_GATEWAY_EXCEPTION',
                'message' => 'Signal API Gateway exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send attachment via API Gateway
     */
    private function sendAttachmentViaApiGateway(string $to, string $message, string $attachmentPath, array $options = []): array
    {
        try {
            // Convert attachment to base64
            $attachmentData = base64_encode(file_get_contents($attachmentPath));
            $attachmentName = basename($attachmentPath);
            $mimeType = mime_content_type($attachmentPath);

            $attachment = [
                'filename' => $attachmentName,
                'data' => $attachmentData,
                'contentType' => $mimeType
            ];

            $options['attachments'] = [$attachment];
            
            return $this->sendViaApiGateway($to, $message, $options);

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'ATTACHMENT_ENCODING_FAILED',
                'message' => 'Failed to encode attachment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send via webhook (custom implementation)
     */
    private function sendViaWebhook(string $to, string $message, array $options = []): array
    {
        try {
            $webhookUrl = $this->config['webhook']['url'] ?? '';
            $webhookSecret = $this->config['webhook']['secret'] ?? '';

            if (empty($webhookUrl)) {
                return [
                    'success' => false,
                    'error' => 'WEBHOOK_NOT_CONFIGURED',
                    'message' => 'Signal webhook not configured'
                ];
            }

            $payload = [
                'to' => $to,
                'message' => $message,
                'timestamp' => time(),
                'options' => $options
            ];

            $headers = [
                'Content-Type' => 'application/json'
            ];

            // Add signature if secret is configured
            if (!empty($webhookSecret)) {
                $signature = hash_hmac('sha256', json_encode($payload), $webhookSecret);
                $headers['X-Signal-Signature'] = 'sha256=' . $signature;
            }

            $response = Http::withHeaders($headers)->timeout(30)->post($webhookUrl, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? true,
                    'message_id' => $data['message_id'] ?? uniqid('signal_wh_'),
                    'method' => 'webhook',
                    'provider_response' => $data
                ];
            }

            return [
                'success' => false,
                'error' => 'WEBHOOK_ERROR',
                'message' => 'Signal webhook error: ' . $response->body(),
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'WEBHOOK_EXCEPTION',
                'message' => 'Signal webhook exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Signal CLI status
     */
    public function getCliStatus(): array
    {
        try {
            $cliPath = $this->config['cli_path'] ?? '/usr/local/bin/signal-cli';
            $account = $this->config['account'] ?? '';

            if (empty($account)) {
                return [
                    'success' => false,
                    'error' => 'ACCOUNT_NOT_CONFIGURED',
                    'message' => 'Signal account not configured'
                ];
            }

            // Check if CLI is available
            $result = Process::run([$cliPath, '--version']);

            if (!$result->successful()) {
                return [
                    'success' => false,
                    'error' => 'CLI_NOT_AVAILABLE',
                    'message' => 'Signal CLI not available or not installed'
                ];
            }

            // Check account status
            $accountResult = Process::run([$cliPath, '-a', $account, 'listIdentities']);

            return [
                'success' => true,
                'cli_version' => trim($result->output()),
                'account_configured' => $accountResult->successful(),
                'account' => $account
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'CLI_STATUS_ERROR',
                'message' => 'Failed to get CLI status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Register Signal account (CLI method)
     */
    public function registerAccount(string $phoneNumber, array $options = []): array
    {
        try {
            $cliPath = $this->config['cli_path'] ?? '/usr/local/bin/signal-cli';

            // Start registration
            $result = Process::run([$cliPath, '-a', $phoneNumber, 'register']);

            if ($result->successful()) {
                return [
                    'success' => true,
                    'message' => 'Registration started. Check your phone for verification code.',
                    'phone_number' => $phoneNumber,
                    'next_step' => 'verify_code'
                ];
            }

            return [
                'success' => false,
                'error' => 'REGISTRATION_FAILED',
                'message' => 'Registration failed: ' . $result->errorOutput()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'REGISTRATION_ERROR',
                'message' => 'Registration error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify registration code
     */
    public function verifyRegistration(string $phoneNumber, string $verificationCode): array
    {
        try {
            $cliPath = $this->config['cli_path'] ?? '/usr/local/bin/signal-cli';

            $result = Process::run([$cliPath, '-a', $phoneNumber, 'verify', $verificationCode]);

            if ($result->successful()) {
                return [
                    'success' => true,
                    'message' => 'Account verified successfully',
                    'phone_number' => $phoneNumber
                ];
            }

            return [
                'success' => false,
                'error' => 'VERIFICATION_FAILED',
                'message' => 'Verification failed: ' . $result->errorOutput()
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'VERIFICATION_ERROR',
                'message' => 'Verification error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalize phone number to international format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add + prefix for international format
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }

    /**
     * Validate phone number format
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // Basic validation for international phone numbers
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit(string $phone): bool
    {
        $key = "signal_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        
        // Allow 20 messages per hour per number (Signal has stricter limits)
        return $count < 20;
    }

    /**
     * Update rate limiting
     */
    private function updateRateLimit(string $phone): void
    {
        $key = "signal_rate_limit:{$phone}";
        $count = Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    /**
     * Get supported attachment types
     */
    public function getSupportedAttachmentTypes(): array
    {
        return [
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'video' => ['mp4', 'mov', 'avi', 'mkv'],
            'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
            'document' => ['pdf', 'doc', 'docx', 'txt', 'rtf']
        ];
    }

    /**
     * Validate attachment
     */
    public function validateAttachment(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'valid' => false,
                'error' => 'FILE_NOT_FOUND',
                'message' => 'Attachment file not found'
            ];
        }

        $fileSize = filesize($filePath);
        $maxSize = $this->config['max_attachment_size'] ?? 100 * 1024 * 1024; // 100MB default

        if ($fileSize > $maxSize) {
            return [
                'valid' => false,
                'error' => 'FILE_TOO_LARGE',
                'message' => 'Attachment file is too large',
                'max_size' => $maxSize,
                'actual_size' => $fileSize
            ];
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $supportedTypes = $this->getSupportedAttachmentTypes();
        $allExtensions = array_merge(...array_values($supportedTypes));

        if (!in_array($extension, $allExtensions)) {
            return [
                'valid' => false,
                'error' => 'UNSUPPORTED_FILE_TYPE',
                'message' => 'Unsupported file type',
                'supported_types' => $supportedTypes
            ];
        }

        return [
            'valid' => true,
            'file_size' => $fileSize,
            'file_type' => $extension,
            'mime_type' => mime_content_type($filePath)
        ];
    }
}
