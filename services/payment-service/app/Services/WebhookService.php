<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Verify Stripe webhook signature.
     */
    public function verifyStripeSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature || !$secret) {
            return false;
        }

        try {
            $elements = explode(',', $signature);
            $signatureHash = null;
            $timestamp = null;

            foreach ($elements as $element) {
                [$key, $value] = explode('=', $element, 2);
                if ($key === 'v1') {
                    $signatureHash = $value;
                } elseif ($key === 't') {
                    $timestamp = $value;
                }
            }

            if (!$signatureHash || !$timestamp) {
                return false;
            }

            // Check timestamp (reject if older than 5 minutes)
            if (abs(time() - $timestamp) > 300) {
                Log::warning('Stripe webhook timestamp too old', [
                    'timestamp' => $timestamp,
                    'current_time' => time(),
                    'age_seconds' => abs(time() - $timestamp),
                ]);
                return false;
            }

            // Verify signature
            $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
            
            return hash_equals($expectedSignature, $signatureHash);

        } catch (\Exception $e) {
            Log::error('Stripe signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);
            return false;
        }
    }

    /**
     * Verify PayPal webhook signature.
     */
    public function verifyPayPalSignature(string $payload, array $headers, string $webhookId): bool
    {
        try {
            // PayPal signature verification requires multiple headers
            $requiredHeaders = [
                'paypal-transmission-id',
                'paypal-cert-id', 
                'paypal-transmission-time',
                'paypal-transmission-sig'
            ];

            foreach ($requiredHeaders as $header) {
                if (!isset($headers[$header])) {
                    Log::warning('PayPal webhook missing required header', [
                        'missing_header' => $header,
                        'available_headers' => array_keys($headers),
                    ]);
                    return false;
                }
            }

            // For now, we'll implement a simplified verification
            // In production, you would use PayPal's SDK to verify the signature
            // This involves downloading PayPal's certificate and verifying the signature
            
            $transmissionId = $headers['paypal-transmission-id'][0] ?? '';
            $certId = $headers['paypal-cert-id'][0] ?? '';
            $transmissionTime = $headers['paypal-transmission-time'][0] ?? '';
            $signature = $headers['paypal-transmission-sig'][0] ?? '';

            // Basic validation
            if (empty($transmissionId) || empty($certId) || empty($transmissionTime) || empty($signature)) {
                return false;
            }

            // Check timestamp (reject if older than 5 minutes)
            if (abs(time() - $transmissionTime) > 300) {
                Log::warning('PayPal webhook timestamp too old', [
                    'transmission_time' => $transmissionTime,
                    'current_time' => time(),
                    'age_seconds' => abs(time() - $transmissionTime),
                ]);
                return false;
            }

            // TODO: Implement full PayPal signature verification using their SDK
            // For now, return true if all required headers are present
            return true;

        } catch (\Exception $e) {
            Log::error('PayPal signature verification failed', [
                'error' => $e->getMessage(),
                'headers' => $headers,
            ]);
            return false;
        }
    }

    /**
     * Verify Razorpay webhook signature.
     */
    public function verifyRazorpaySignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature || !$secret) {
            return false;
        }

        try {
            // Razorpay uses HMAC SHA256
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            
            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Razorpay signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);
            return false;
        }
    }

    /**
     * Verify Square webhook signature.
     */
    public function verifySquareSignature(string $payload, ?string $signature, string $signatureKey, string $url): bool
    {
        if (!$signature || !$signatureKey) {
            return false;
        }

        try {
            // Square webhook signature verification
            // The signature is created by combining the request URL and body, then hashing with the signature key
            $stringToSign = $url . $payload;
            $expectedSignature = base64_encode(hash_hmac('sha256', $stringToSign, $signatureKey, true));
            
            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Square signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
                'url' => $url,
            ]);
            return false;
        }
    }

    /**
     * Verify Mada webhook signature (Saudi Arabia).
     */
    public function verifyMadaSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature || !$secret) {
            return false;
        }

        try {
            // Mada typically uses HMAC SHA256 (implementation may vary by provider)
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            
            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Mada signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);
            return false;
        }
    }

    /**
     * Verify STC Pay webhook signature (Saudi Arabia).
     */
    public function verifyStcPaySignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature || !$secret) {
            return false;
        }

        try {
            // STC Pay signature verification (implementation may vary)
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            
            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('STC Pay signature verification failed', [
                'error' => $e->getMessage(),
                'signature' => $signature,
            ]);
            return false;
        }
    }

    /**
     * Generate webhook signature for outgoing webhooks.
     */
    public function generateWebhookSignature(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac($algorithm, $signedPayload, $secret);
        
        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Validate webhook timestamp to prevent replay attacks.
     */
    public function isTimestampValid(int $timestamp, int $toleranceSeconds = 300): bool
    {
        $currentTime = time();
        $timeDifference = abs($currentTime - $timestamp);
        
        return $timeDifference <= $toleranceSeconds;
    }

    /**
     * Extract timestamp from webhook signature.
     */
    public function extractTimestamp(string $signature): ?int
    {
        try {
            $elements = explode(',', $signature);
            
            foreach ($elements as $element) {
                [$key, $value] = explode('=', $element, 2);
                if ($key === 't') {
                    return (int) $value;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Sanitize webhook payload for logging.
     */
    public function sanitizePayloadForLogging(array $payload): array
    {
        $sensitiveFields = [
            'card_number',
            'cvv',
            'card_token',
            'bank_account',
            'routing_number',
            'ssn',
            'tax_id',
            'password',
            'secret',
            'key',
            'token',
        ];

        return $this->recursiveSanitize($payload, $sensitiveFields);
    }

    /**
     * Recursively sanitize sensitive data from arrays.
     */
    private function recursiveSanitize(array $data, array $sensitiveFields): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveFields);
            } elseif (is_string($value) && $this->isSensitiveField($key, $sensitiveFields)) {
                $data[$key] = $this->maskSensitiveValue($value);
            }
        }

        return $data;
    }

    /**
     * Check if field is sensitive.
     */
    private function isSensitiveField(string $fieldName, array $sensitiveFields): bool
    {
        $fieldName = strtolower($fieldName);
        
        foreach ($sensitiveFields as $sensitiveField) {
            if (str_contains($fieldName, strtolower($sensitiveField))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mask sensitive value for logging.
     */
    private function maskSensitiveValue(string $value): string
    {
        $length = strlen($value);
        
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        
        // Show first 2 and last 2 characters
        return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
    }

    /**
     * Validate webhook payload structure.
     */
    public function validateWebhookPayload(array $payload, string $provider): array
    {
        $errors = [];
        
        match ($provider) {
            'stripe' => [
                !isset($payload['type']) ? $errors[] = 'Missing event type' : null,
                !isset($payload['data']['object']) ? $errors[] = 'Missing event data object' : null
            ],
            'paypal' => [
                !isset($payload['event_type']) ? $errors[] = 'Missing event type' : null,
                !isset($payload['resource']) ? $errors[] = 'Missing resource data' : null
            ],
            'razorpay' => [
                !isset($payload['event']) ? $errors[] = 'Missing event type' : null,
                !isset($payload['payload']) ? $errors[] = 'Missing payload data' : null
            ],
            'square' => [
                !isset($payload['type']) ? $errors[] = 'Missing event type' : null,
                !isset($payload['data']) ? $errors[] = 'Missing event data' : null
            ],
            default => null
        };

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get webhook retry configuration.
     */
    public function getRetryConfiguration(string $provider): array
    {
        $defaultConfig = [
            'max_retries' => 3,
            'retry_delays' => [60, 300, 900], // 1 min, 5 min, 15 min
            'exponential_backoff' => true,
        ];

        $providerConfigs = [
            'stripe' => [
                'max_retries' => 5,
                'retry_delays' => [30, 60, 300, 900, 1800], // 30s, 1m, 5m, 15m, 30m
            ],
            'paypal' => [
                'max_retries' => 3,
                'retry_delays' => [60, 300, 900],
            ],
            'razorpay' => [
                'max_retries' => 4,
                'retry_delays' => [60, 180, 600, 1800],
            ],
            'square' => [
                'max_retries' => 3,
                'retry_delays' => [120, 600, 1800],
            ],
        ];

        return array_merge($defaultConfig, $providerConfigs[$provider] ?? []);
    }

    /**
     * Calculate next retry time.
     */
    public function calculateNextRetryTime(int $retryCount, array $retryDelays): ?\DateTime
    {
        if ($retryCount >= count($retryDelays)) {
            return null; // No more retries
        }

        $delay = $retryDelays[$retryCount];
        return new \DateTime('+' . $delay . ' seconds');
    }

    /**
     * Check if webhook should be retried.
     */
    public function shouldRetryWebhook(string $provider, int $currentRetryCount, ?\DateTime $lastRetryAt): bool
    {
        $config = $this->getRetryConfiguration($provider);
        
        // Check if we've exceeded max retries
        if ($currentRetryCount >= $config['max_retries']) {
            return false;
        }

        // Check if enough time has passed since last retry
        if ($lastRetryAt && $lastRetryAt > new \DateTime()) {
            return false;
        }

        return true;
    }
}
