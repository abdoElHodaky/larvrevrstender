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
     * Generate and send OTP
     */
    public function sendOtp(string $phoneNumber): array
    {
        try {
            $otp = $this->generateOtp();
            $key = $this->getCacheKey($phoneNumber);
            
            // Store OTP in cache for 5 minutes
            Cache::put($key, $otp, now()->addMinutes(5));
            
            // Send SMS
            $message = $this->twilioClient->messages->create(
                $phoneNumber,
                [
                    'from' => $this->fromNumber,
                    'body' => "Your verification code is: {$otp}. Valid for 5 minutes."
                ]
            );

            Log::info('OTP sent successfully', [
                'phone' => $phoneNumber,
                'message_sid' => $message->sid
            ]);

            return [
                'success' => true,
                'message' => 'OTP sent successfully',
                'expires_at' => now()->addMinutes(5)->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send OTP', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP'
            ];
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(string $phoneNumber, string $otp): bool
    {
        $key = $this->getCacheKey($phoneNumber);
        $storedOtp = Cache::get($key);

        if (!$storedOtp) {
            return false;
        }

        if ($storedOtp === $otp) {
            Cache::forget($key);
            return true;
        }

        return false;
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get cache key for phone number
     */
    private function getCacheKey(string $phoneNumber): string
    {
        return 'otp:' . md5($phoneNumber);
    }
}

