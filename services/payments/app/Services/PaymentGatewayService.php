<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    /**
     * Process payment with Stripe.
     */
    public function processStripePayment(Payment $payment, array $paymentData): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.stripe.secret'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $payment->amount * 100, // Convert to cents
                'currency' => strtolower($payment->currency),
                'payment_method' => $paymentData['payment_method_id'],
                'confirmation_method' => 'manual',
                'confirm' => true,
                'metadata' => [
                    'payment_reference' => $payment->payment_reference,
                    'invoice_id' => $payment->invoice_id,
                    'order_id' => $payment->order_id,
                ],
            ]);

            $result = $response->json();

            if ($response->successful() && $result['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'transaction_id' => $result['id'],
                    'gateway_reference' => $result['id'],
                    'status' => $result['status'],
                    'charges' => $result['charges']['data'][0] ?? null,
                ];
            }

            return [
                'success' => false,
                'error_code' => $result['error']['code'] ?? 'unknown_error',
                'error_message' => $result['error']['message'] ?? 'Payment failed',
                'gateway_reference' => $result['id'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Stripe payment processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'Payment processing failed',
            ];
        }
    }

    /**
     * Process payment with PayPal.
     */
    public function processPayPalPayment(Payment $payment, array $paymentData): array
    {
        try {
            // Get PayPal access token
            $tokenResponse = Http::withBasicAuth(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )->asForm()->post(config('services.paypal.base_url').'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

            if (! $tokenResponse->successful()) {
                throw new \Exception('Failed to get PayPal access token');
            }

            $accessToken = $tokenResponse->json()['access_token'];

            // Create payment
            $paymentResponse = Http::withToken($accessToken)
                ->post(config('services.paypal.base_url').'/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $payment->payment_reference,
                            'amount' => [
                                'currency_code' => $payment->currency,
                                'value' => number_format($payment->amount, 2, '.', ''),
                            ],
                            'custom_id' => $payment->payment_reference,
                        ],
                    ],
                    'payment_source' => [
                        'paypal' => [
                            'experience_context' => [
                                'return_url' => config('app.url').'/payment/success',
                                'cancel_url' => config('app.url').'/payment/cancel',
                            ],
                        ],
                    ],
                ]);

            $result = $paymentResponse->json();

            if ($paymentResponse->successful()) {
                return [
                    'success' => true,
                    'transaction_id' => $result['id'],
                    'gateway_reference' => $result['id'],
                    'status' => $result['status'],
                    'approval_url' => $result['links'][1]['href'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error_code' => $result['name'] ?? 'unknown_error',
                'error_message' => $result['message'] ?? 'Payment failed',
            ];

        } catch (\Exception $e) {
            Log::error('PayPal payment processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'Payment processing failed',
            ];
        }
    }

    /**
     * Process payment with Mada (Saudi Arabia).
     */
    public function processMadaPayment(Payment $payment, array $paymentData): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.mada.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.mada.endpoint').'/payments', [
                'merchant_id' => config('services.mada.merchant_id'),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->payment_reference,
                'card_number' => $paymentData['card_number'],
                'expiry_month' => $paymentData['expiry_month'],
                'expiry_year' => $paymentData['expiry_year'],
                'cvv' => $paymentData['cvv'],
                'cardholder_name' => $paymentData['cardholder_name'],
                'callback_url' => config('app.url').'/webhooks/mada',
            ]);

            $result = $response->json();

            if ($response->successful() && $result['status'] === 'success') {
                return [
                    'success' => true,
                    'transaction_id' => $result['transaction_id'],
                    'gateway_reference' => $result['reference'],
                    'status' => $result['payment_status'],
                    'auth_code' => $result['auth_code'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error_code' => $result['error_code'] ?? 'unknown_error',
                'error_message' => $result['error_message'] ?? 'Payment failed',
                'gateway_reference' => $result['reference'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Mada payment processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'Payment processing failed',
            ];
        }
    }

    /**
     * Process payment with STC Pay (Saudi Arabia).
     */
    public function processStcPayPayment(Payment $payment, array $paymentData): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.stc_pay.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.stc_pay.endpoint').'/payment/request', [
                'merchant_id' => config('services.stc_pay.merchant_id'),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference_number' => $payment->payment_reference,
                'mobile_number' => $paymentData['mobile_number'],
                'description' => 'Reverse Tender Platform Payment',
                'callback_url' => config('app.url').'/webhooks/stc-pay',
            ]);

            $result = $response->json();

            if ($response->successful() && $result['status'] === 'PENDING') {
                return [
                    'success' => true,
                    'transaction_id' => $result['transaction_id'],
                    'gateway_reference' => $result['reference_number'],
                    'status' => $result['status'],
                    'otp_required' => true,
                    'session_id' => $result['session_id'],
                ];
            }

            return [
                'success' => false,
                'error_code' => $result['error_code'] ?? 'unknown_error',
                'error_message' => $result['error_message'] ?? 'Payment failed',
            ];

        } catch (\Exception $e) {
            Log::error('STC Pay payment processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'Payment processing failed',
            ];
        }
    }

    /**
     * Verify STC Pay OTP.
     */
    public function verifyStcPayOtp(Payment $payment, string $otp, string $sessionId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.stc_pay.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.stc_pay.endpoint').'/payment/confirm', [
                'session_id' => $sessionId,
                'otp' => $otp,
            ]);

            $result = $response->json();

            if ($response->successful() && $result['status'] === 'SUCCESS') {
                return [
                    'success' => true,
                    'transaction_id' => $result['transaction_id'],
                    'gateway_reference' => $result['reference_number'],
                    'status' => $result['status'],
                ];
            }

            return [
                'success' => false,
                'error_code' => $result['error_code'] ?? 'otp_verification_failed',
                'error_message' => $result['error_message'] ?? 'OTP verification failed',
            ];

        } catch (\Exception $e) {
            Log::error('STC Pay OTP verification failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'verification_error',
                'error_message' => 'OTP verification failed',
            ];
        }
    }

    /**
     * Process refund with appropriate gateway.
     */
    public function processRefund(Payment $payment, float $amount, string $reason): array
    {
        switch ($payment->payment_provider) {
            case 'stripe':
                return $this->processStripeRefund($payment, $amount, $reason);
            case 'paypal':
                return $this->processPayPalRefund($payment, $amount, $reason);
            case 'mada':
                return $this->processMadaRefund($payment, $amount, $reason);
            case 'stc_pay':
                return $this->processStcPayRefund($payment, $amount, $reason);
            default:
                return [
                    'success' => false,
                    'error_code' => 'unsupported_provider',
                    'error_message' => 'Refund not supported for this payment provider',
                ];
        }
    }

    /**
     * Process Stripe refund.
     */
    private function processStripeRefund(Payment $payment, float $amount, string $reason): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('services.stripe.secret'),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->post('https://api.stripe.com/v1/refunds', [
                'payment_intent' => $payment->provider_transaction_id,
                'amount' => $amount * 100, // Convert to cents
                'reason' => 'requested_by_customer',
                'metadata' => [
                    'original_payment_reference' => $payment->payment_reference,
                    'refund_reason' => $reason,
                ],
            ]);

            $result = $response->json();

            if ($response->successful() && $result['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'refund_id' => $result['id'],
                    'status' => $result['status'],
                    'amount' => $result['amount'] / 100,
                ];
            }

            return [
                'success' => false,
                'error_code' => $result['error']['code'] ?? 'refund_failed',
                'error_message' => $result['error']['message'] ?? 'Refund processing failed',
            ];

        } catch (\Exception $e) {
            Log::error('Stripe refund processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'Refund processing failed',
            ];
        }
    }

    /**
     * Process PayPal refund.
     */
    private function processPayPalRefund(Payment $payment, float $amount, string $reason): array
    {
        try {
            // Get PayPal access token
            $accessToken = $this->getPayPalAccessToken();
            
            if (!$accessToken) {
                return [
                    'success' => false,
                    'error_code' => 'authentication_failed',
                    'error_message' => 'Failed to authenticate with PayPal',
                ];
            }

            // Prepare refund request
            $refundData = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $payment->currency,
                ],
                'note_to_payer' => $reason,
                'invoice_id' => $payment->payment_reference,
            ];

            // Make refund request to PayPal
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
                'PayPal-Request-Id' => 'REFUND-' . $payment->payment_reference . '-' . time(),
            ])->post(
                config('services.paypal.api_url') . '/v2/payments/captures/' . $payment->gateway_transaction_id . '/refund',
                $refundData
            );

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('PayPal refund processed successfully', [
                    'payment_id' => $payment->id,
                    'refund_id' => $result['id'],
                    'amount' => $amount,
                ]);

                return [
                    'success' => true,
                    'refund_id' => $result['id'],
                    'status' => strtolower($result['status']),
                    'amount' => (float) $result['amount']['value'],
                    'currency' => $result['amount']['currency_code'],
                    'created_at' => $result['create_time'],
                    'raw_response' => $result,
                ];
            }

            // Handle PayPal errors
            $error = $response->json();
            Log::error('PayPal refund failed', [
                'payment_id' => $payment->id,
                'error' => $error,
                'status_code' => $response->status(),
            ]);

            return [
                'success' => false,
                'error_code' => $error['name'] ?? 'refund_failed',
                'error_message' => $error['message'] ?? 'PayPal refund processing failed',
                'details' => $error['details'] ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('PayPal refund processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'PayPal refund processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get PayPal access token.
     */
    private function getPayPalAccessToken(): ?string
    {
        try {
            $clientId = config('services.paypal.client_id');
            $clientSecret = config('services.paypal.client_secret');
            $baseUrl = config('services.paypal.api_url');

            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $result = $response->json();
                return $result['access_token'];
            }

            Log::error('PayPal authentication failed', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('PayPal authentication error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Process Mada refund.
     */
    private function processMadaRefund(Payment $payment, float $amount, string $reason): array
    {
        try {
            // Mada refunds are typically processed through the acquiring bank's API
            // This implementation assumes integration with a Mada-certified payment processor
            
            $refundData = [
                'merchant_id' => config('services.mada.merchant_id'),
                'terminal_id' => config('services.mada.terminal_id'),
                'original_transaction_id' => $payment->gateway_transaction_id,
                'refund_amount' => $amount,
                'currency' => $payment->currency,
                'refund_reason' => $reason,
                'refund_reference' => 'REF-' . $payment->payment_reference . '-' . time(),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ];

            // Generate security hash for Mada API
            $securityHash = $this->generateMadaSecurityHash($refundData);
            $refundData['security_hash'] = $securityHash;

            // Make refund request to Mada gateway
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-Merchant-ID' => config('services.mada.merchant_id'),
                ])
                ->post(config('services.mada.api_url') . '/refund', $refundData);

            if ($response->successful()) {
                $result = $response->json();
                
                // Check if refund was approved
                if (isset($result['status']) && $result['status'] === 'approved') {
                    Log::info('Mada refund processed successfully', [
                        'payment_id' => $payment->id,
                        'refund_id' => $result['refund_id'],
                        'amount' => $amount,
                    ]);

                    return [
                        'success' => true,
                        'refund_id' => $result['refund_id'],
                        'status' => 'completed',
                        'amount' => $amount,
                        'currency' => $payment->currency,
                        'approval_code' => $result['approval_code'] ?? null,
                        'reference_number' => $result['reference_number'] ?? null,
                        'processed_at' => $result['processed_at'] ?? now()->toISOString(),
                        'raw_response' => $result,
                    ];
                } else {
                    // Refund was declined
                    Log::warning('Mada refund declined', [
                        'payment_id' => $payment->id,
                        'reason' => $result['decline_reason'] ?? 'Unknown',
                        'response' => $result,
                    ]);

                    return [
                        'success' => false,
                        'error_code' => $result['error_code'] ?? 'refund_declined',
                        'error_message' => $result['decline_reason'] ?? 'Mada refund was declined',
                        'decline_reason' => $result['decline_reason'] ?? null,
                    ];
                }
            }

            // Handle HTTP errors
            $error = $response->json();
            Log::error('Mada refund API error', [
                'payment_id' => $payment->id,
                'status_code' => $response->status(),
                'error' => $error,
            ]);

            return [
                'success' => false,
                'error_code' => 'api_error',
                'error_message' => $error['message'] ?? 'Mada refund API request failed',
                'api_error_code' => $error['error_code'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Mada refund processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'Mada refund processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate security hash for Mada API requests.
     */
    private function generateMadaSecurityHash(array $data): string
    {
        // Remove security_hash from data if present
        unset($data['security_hash']);
        
        // Sort data by key
        ksort($data);
        
        // Create query string
        $queryString = http_build_query($data);
        
        // Append secret key
        $stringToHash = $queryString . config('services.mada.secret_key');
        
        // Generate SHA-256 hash
        return hash('sha256', $stringToHash);
    }

    /**
     * Process STC Pay refund.
     */
    private function processStcPayRefund(Payment $payment, float $amount, string $reason): array
    {
        try {
            // STC Pay refund implementation
            // STC Pay typically requires merchant authentication and specific request format
            
            $refundData = [
                'MerchantId' => config('services.stc_pay.merchant_id'),
                'BranchId' => config('services.stc_pay.branch_id'),
                'TellerId' => config('services.stc_pay.teller_id'),
                'DeviceId' => config('services.stc_pay.device_id'),
                'RefundAmount' => $amount,
                'Currency' => $payment->currency,
                'OriginalTransactionId' => $payment->gateway_transaction_id,
                'RefundReason' => $reason,
                'RefundReference' => 'STC-REF-' . $payment->payment_reference . '-' . time(),
                'RequestDateTime' => now()->format('Y-m-d\TH:i:s'),
                'MobileNumber' => $payment->mobile_number, // Required for STC Pay
            ];

            // Generate STC Pay authentication signature
            $signature = $this->generateStcPaySignature($refundData);
            $refundData['Signature'] = $signature;

            // Make refund request to STC Pay API
            $response = Http::timeout(45) // STC Pay may take longer
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->getStcPayAccessToken(),
                    'X-Merchant-ID' => config('services.stc_pay.merchant_id'),
                ])
                ->post(config('services.stc_pay.api_url') . '/api/refund', $refundData);

            if ($response->successful()) {
                $result = $response->json();
                
                // Check STC Pay response status
                if (isset($result['StatusCode']) && $result['StatusCode'] === '0000') {
                    Log::info('STC Pay refund processed successfully', [
                        'payment_id' => $payment->id,
                        'refund_id' => $result['RefundId'],
                        'amount' => $amount,
                        'stc_reference' => $result['STCReference'] ?? null,
                    ]);

                    return [
                        'success' => true,
                        'refund_id' => $result['RefundId'],
                        'status' => $this->mapStcPayRefundStatus($result['RefundStatus'] ?? 'completed'),
                        'amount' => $amount,
                        'currency' => $payment->currency,
                        'stc_reference' => $result['STCReference'] ?? null,
                        'approval_code' => $result['ApprovalCode'] ?? null,
                        'processed_at' => $result['ProcessedDateTime'] ?? now()->toISOString(),
                        'raw_response' => $result,
                    ];
                } else {
                    // STC Pay returned an error
                    $errorMessage = $result['StatusDescription'] ?? 'STC Pay refund failed';
                    
                    Log::warning('STC Pay refund failed', [
                        'payment_id' => $payment->id,
                        'status_code' => $result['StatusCode'] ?? 'unknown',
                        'error_message' => $errorMessage,
                        'response' => $result,
                    ]);

                    return [
                        'success' => false,
                        'error_code' => $result['StatusCode'] ?? 'refund_failed',
                        'error_message' => $errorMessage,
                        'stc_error_details' => $result['ErrorDetails'] ?? null,
                    ];
                }
            }

            // Handle HTTP errors
            $error = $response->json();
            Log::error('STC Pay refund API error', [
                'payment_id' => $payment->id,
                'status_code' => $response->status(),
                'error' => $error,
            ]);

            return [
                'success' => false,
                'error_code' => 'api_error',
                'error_message' => $error['Message'] ?? 'STC Pay refund API request failed',
                'api_status_code' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('STC Pay refund processing failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error_code' => 'processing_error',
                'error_message' => 'STC Pay refund processing failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate STC Pay authentication signature.
     */
    private function generateStcPaySignature(array $data): string
    {
        // Remove signature from data if present
        unset($data['Signature']);
        
        // Sort data by key (case-sensitive for STC Pay)
        ksort($data);
        
        // Create concatenated string
        $stringToSign = '';
        foreach ($data as $key => $value) {
            $stringToSign .= $key . '=' . $value . '&';
        }
        
        // Remove trailing &
        $stringToSign = rtrim($stringToSign, '&');
        
        // Append secret key
        $stringToSign .= config('services.stc_pay.secret_key');
        
        // Generate SHA-256 hash and encode in base64
        return base64_encode(hash('sha256', $stringToSign, true));
    }

    /**
     * Get STC Pay access token.
     */
    private function getStcPayAccessToken(): ?string
    {
        try {
            $credentials = [
                'grant_type' => 'client_credentials',
                'client_id' => config('services.stc_pay.client_id'),
                'client_secret' => config('services.stc_pay.client_secret'),
                'scope' => 'payment_refund',
            ];

            $response = Http::asForm()
                ->timeout(30)
                ->post(config('services.stc_pay.auth_url') . '/oauth/token', $credentials);

            if ($response->successful()) {
                $result = $response->json();
                return $result['access_token'];
            }

            Log::error('STC Pay authentication failed', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('STC Pay authentication error', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Map STC Pay refund status to standard status.
     */
    private function mapStcPayRefundStatus(string $stcStatus): string
    {
        return match (strtolower($stcStatus)) {
            'completed', 'success', 'approved' => 'completed',
            'pending', 'processing' => 'pending',
            'failed', 'declined', 'rejected' => 'failed',
            default => 'pending',
        };
    }
}
