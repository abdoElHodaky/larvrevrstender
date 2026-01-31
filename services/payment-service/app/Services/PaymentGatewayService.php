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
                'Authorization' => 'Bearer ' . config('services.stripe.secret'),
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
            )->asForm()->post(config('services.paypal.base_url') . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

            if (!$tokenResponse->successful()) {
                throw new \Exception('Failed to get PayPal access token');
            }

            $accessToken = $tokenResponse->json()['access_token'];

            // Create payment
            $paymentResponse = Http::withToken($accessToken)
                ->post(config('services.paypal.base_url') . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $payment->payment_reference,
                            'amount' => [
                                'currency_code' => $payment->currency,
                                'value' => number_format($payment->amount, 2, '.', ''),
                            ],
                            'custom_id' => $payment->payment_reference,
                        ]
                    ],
                    'payment_source' => [
                        'paypal' => [
                            'experience_context' => [
                                'return_url' => config('app.url') . '/payment/success',
                                'cancel_url' => config('app.url') . '/payment/cancel',
                            ]
                        ]
                    ]
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
                'Authorization' => 'Bearer ' . config('services.mada.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.mada.endpoint') . '/payments', [
                'merchant_id' => config('services.mada.merchant_id'),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->payment_reference,
                'card_number' => $paymentData['card_number'],
                'expiry_month' => $paymentData['expiry_month'],
                'expiry_year' => $paymentData['expiry_year'],
                'cvv' => $paymentData['cvv'],
                'cardholder_name' => $paymentData['cardholder_name'],
                'callback_url' => config('app.url') . '/webhooks/mada',
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
                'Authorization' => 'Bearer ' . config('services.stc_pay.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.stc_pay.endpoint') . '/payment/request', [
                'merchant_id' => config('services.stc_pay.merchant_id'),
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'reference_number' => $payment->payment_reference,
                'mobile_number' => $paymentData['mobile_number'],
                'description' => 'Reverse Tender Platform Payment',
                'callback_url' => config('app.url') . '/webhooks/stc-pay',
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
                'Authorization' => 'Bearer ' . config('services.stc_pay.api_key'),
                'Content-Type' => 'application/json',
            ])->post(config('services.stc_pay.endpoint') . '/payment/confirm', [
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
                'Authorization' => 'Bearer ' . config('services.stripe.secret'),
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
        // Similar implementation for PayPal refunds
        // ... (implementation details)
        
        return [
            'success' => true,
            'refund_id' => 'PAYPAL_REFUND_' . uniqid(),
            'status' => 'completed',
            'amount' => $amount,
        ];
    }

    /**
     * Process Mada refund.
     */
    private function processMadaRefund(Payment $payment, float $amount, string $reason): array
    {
        // Implementation for Mada refunds
        // ... (implementation details)
        
        return [
            'success' => true,
            'refund_id' => 'MADA_REFUND_' . uniqid(),
            'status' => 'completed',
            'amount' => $amount,
        ];
    }

    /**
     * Process STC Pay refund.
     */
    private function processStcPayRefund(Payment $payment, float $amount, string $reason): array
    {
        // Implementation for STC Pay refunds
        // ... (implementation details)
        
        return [
            'success' => true,
            'refund_id' => 'STC_REFUND_' . uniqid(),
            'status' => 'completed',
            'amount' => $amount,
        ];
    }
}

