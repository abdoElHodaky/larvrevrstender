<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class OtpService
{
    private $twilioClient;

    private $fromNumber;

    public function __construct()
    {
        $this->twilioClient = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->fromNumber = config('services.twilio.from');
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

            // Send SMS
            $message = $this->twilioClient->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => $messageBody,
                ]
            );

            Log::info('OTP sent successfully', [
                'phone' => $phoneNumber,
                'type' => $type,
                'message_sid' => $message->sid,
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

        if (!$storedData) {
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
        return "otp:{$type}:" . md5($phoneNumber);
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
        
        if (!$storedData) {
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
}
