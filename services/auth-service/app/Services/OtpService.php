<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Shared\Core\BaseService;

class OtpService extends BaseService
{
    private $smsProvider;
    private $smsConfig;

    public function __construct()
    {
        $this->smsProvider = config('services.sms.provider', 'unifonic');
        $this->smsConfig = config('services.sms.providers.' . $this->smsProvider);
    }

    /**
     * Generate and send OTP with type support
     */
    public function sendOtp(string $phoneNumber, string $type = 'verification'): array
    {
        try {
            // Check rate limiting
            $rateLimitKey = "otp_rate_limit:{$phoneNumber}";
            $attempts = Cache::get($rateLimitKey, 0);

            if ($attempts >= 3) {
                return [
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again later.',
                ];
            }

            $otp = $this->generateOtp();
            $key = $this->getCacheKey($phoneNumber, $type);
            $expiryMinutes = $this->getExpiryMinutes($type);

            // Store OTP in cache
            Cache::put($key, [
                'code' => $otp,
                'type' => $type,
                'attempts' => 0,
                'created_at' => now(),
            ], now()->addMinutes($expiryMinutes));

            // Increment rate limit counter
            Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(15));

            // Get message template based on type
            $messageBody = $this->getMessageTemplate($type, $otp, $expiryMinutes);

            // Send SMS via MENA-compatible provider
            $smsResult = $this->sendSmsViaMenaProvider($phoneNumber, $messageBody);

            if (!$smsResult['success']) {
                throw new \Exception($smsResult['message']);
            }

            Log::info('OTP sent successfully', [
                'phone' => $phoneNumber,
                'type' => $type,
                'provider' => $this->smsProvider,
                'message_id' => $smsResult['message_id'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'type' => $type,
                'expires_at' => now()->addMinutes($expiryMinutes)->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'phone' => $phoneNumber,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP',
            ];
        }
    }

    /**
     * Verify OTP with enhanced security
     */
    public function verifyOtp(string $phoneNumber, string $otp, string $type = 'verification'): array
    {
        $key = $this->getCacheKey($phoneNumber, $type);
        $storedData = Cache::get($key);

        if (! $storedData) {
            return [
                'valid' => false,
                'message' => 'OTP not found or expired',
            ];
        }

        // Check attempt limit
        if ($storedData['attempts'] >= 3) {
            Cache::forget($key);

            return [
                'valid' => false,
                'message' => 'Too many verification attempts. Please request a new code.',
            ];
        }

        // Increment attempt counter
        $storedData['attempts']++;
        Cache::put($key, $storedData, now()->addMinutes($this->getExpiryMinutes($type)));

        if ($storedData['code'] === $otp) {
            Cache::forget($key);

            Log::info('OTP verified successfully', [
                'phone' => $phoneNumber,
                'type' => $type,
            ]);

            return [
                'valid' => true,
                'message' => 'OTP verified successfully',
                'type' => $type,
            ];
        }

        Log::warning('Invalid OTP attempt', [
            'phone' => $phoneNumber,
            'type' => $type,
            'attempts' => $storedData['attempts'],
        ]);

        return [
            'valid' => false,
            'message' => 'Invalid OTP code',
            'attempts_remaining' => 3 - $storedData['attempts'],
        ];
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get cache key for phone number and type
     */
    private function getCacheKey(string $phoneNumber, string $type = 'verification'): string
    {
        return "otp:{$type}:".md5($phoneNumber);
    }

    /**
     * Get expiry minutes based on OTP type
     */
    private function getExpiryMinutes(string $type): int
    {
        return match ($type) {
            'registration' => 10,
            'password_reset' => 15,
            'login' => 5,
            'verification' => 5,
            'two_factor' => 3,
            default => 5,
        };
    }

    /**
     * Get message template based on OTP type
     */
    private function getMessageTemplate(string $type, string $otp, int $expiryMinutes): string
    {
        return match ($type) {
            'registration' => "Welcome to Reverse Tender! Your registration code is: {$otp}. Valid for {$expiryMinutes} minutes.",
            'password_reset' => "Your password reset code is: {$otp}. Valid for {$expiryMinutes} minutes. If you didn't request this, please ignore.",
            'login' => "Your login verification code is: {$otp}. Valid for {$expiryMinutes} minutes.",
            'two_factor' => "Your two-factor authentication code is: {$otp}. Valid for {$expiryMinutes} minutes.",
            'verification' => "Your verification code is: {$otp}. Valid for {$expiryMinutes} minutes.",
            default => "Your verification code is: {$otp}. Valid for {$expiryMinutes} minutes.",
        };
    }

    /**
     * Check if OTP exists for phone and type
     */
    public function hasActiveOtp(string $phoneNumber, string $type = 'verification'): bool
    {
        $key = $this->getCacheKey($phoneNumber, $type);

        return Cache::has($key);
    }

    /**
     * Get remaining time for OTP
     */
    public function getOtpRemainingTime(string $phoneNumber, string $type = 'verification'): ?int
    {
        $key = $this->getCacheKey($phoneNumber, $type);
        $storedData = Cache::get($key);

        if (! $storedData) {
            return null;
        }

        $expiryMinutes = $this->getExpiryMinutes($type);
        $expiresAt = $storedData['created_at']->addMinutes($expiryMinutes);

        return max(0, now()->diffInSeconds($expiresAt));
    }

    /**
     * Clear all OTPs for a phone number
     */
    public function clearAllOtps(string $phoneNumber): void
    {
        $types = ['registration', 'password_reset', 'login', 'verification', 'two_factor'];

        foreach ($types as $type) {
            $key = $this->getCacheKey($phoneNumber, $type);
            Cache::forget($key);
        }

        // Also clear rate limit
        Cache::forget("otp_rate_limit:{$phoneNumber}");
    }

    /**
     * Send SMS via MENA-compatible providers
     */
    private function sendSmsViaMenaProvider(string $phoneNumber, string $message): array
    {
        try {
            switch ($this->smsProvider) {
                case 'unifonic':
                    return $this->sendViaUnifonic($phoneNumber, $message);
                
                case 'msegat':
                    return $this->sendViaMsegat($phoneNumber, $message);
                
                case 'oursms':
                    return $this->sendViaOursms($phoneNumber, $message);
                
                case 'infobip':
                    return $this->sendViaInfobip($phoneNumber, $message);
                
                default:
                    return [
                        'success' => false,
                        'message' => 'Unsupported SMS provider: ' . $this->smsProvider
                    ];
            }
        } catch (\Exception $e) {
            Log::error('SMS provider error', [
                'provider' => $this->smsProvider,
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'SMS sending failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send SMS via Unifonic (Saudi Arabia)
     */
    private function sendViaUnifonic(string $phoneNumber, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . base64_encode($this->smsConfig['api_key'] . ':'),
        ])->post($this->smsConfig['base_url'] . '/rest/SMS/messages', [
            'Recipient' => $phoneNumber,
            'Body' => $message,
            'SenderID' => $this->smsConfig['sender_id'],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['data']['MessageID'] ?? null,
                'provider' => 'unifonic'
            ];
        }

        return [
            'success' => false,
            'message' => 'Unifonic API error: ' . $response->body()
        ];
    }

    /**
     * Send SMS via Msegat (UAE)
     */
    private function sendViaMsegat(string $phoneNumber, string $message): array
    {
        $response = Http::post($this->smsConfig['base_url'] . '/gw/sendsms.php', [
            'userName' => $this->smsConfig['username'],
            'apiKey' => $this->smsConfig['api_key'],
            'numbers' => $phoneNumber,
            'userSender' => $this->smsConfig['sender_id'],
            'msg' => $message,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'message_id' => $response->body(),
                'provider' => 'msegat'
            ];
        }

        return [
            'success' => false,
            'message' => 'Msegat API error: ' . $response->body()
        ];
    }

    /**
     * Send SMS via Oursms (Egypt)
     */
    private function sendViaOursms(string $phoneNumber, string $message): array
    {
        $response = Http::post($this->smsConfig['base_url'] . '/api/v1/sms/send', [
            'api_key' => $this->smsConfig['api_key'],
            'to' => $phoneNumber,
            'from' => $this->smsConfig['sender_id'],
            'message' => $message,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['message_id'] ?? null,
                'provider' => 'oursms'
            ];
        }

        return [
            'success' => false,
            'message' => 'Oursms API error: ' . $response->body()
        ];
    }

    /**
     * Send SMS via Infobip (International with MENA focus)
     */
    private function sendViaInfobip(string $phoneNumber, string $message): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'App ' . $this->smsConfig['api_key'],
            'Content-Type' => 'application/json',
        ])->post($this->smsConfig['base_url'] . '/sms/2/text/advanced', [
            'messages' => [
                [
                    'from' => $this->smsConfig['sender_id'],
                    'destinations' => [
                        ['to' => $phoneNumber]
                    ],
                    'text' => $message,
                ]
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return [
                'success' => true,
                'message_id' => $data['messages'][0]['messageId'] ?? null,
                'provider' => 'infobip'
            ];
        }

        return [
            'success' => false,
            'message' => 'Infobip API error: ' . $response->body()
        ];
    }
}
