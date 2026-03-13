<?php

namespace App\Http\Controllers;

use App\Models\PaymentWebhook;
use App\Services\PaymentService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentService$paymentService,
        private WebhookService$webhookService
    ) {
    }

    /**
     * Handle Stripe webhook events.
     */
    public function handleStripe(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Create webhook record
            $webhook = $this->createWebhookRecord('stripe', $request);
            
            // Verify Stripe signature
            $isVerified = $this->webhookService->verifyStripeSignature(
                $request->getContent(),
                $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );

            $webhook->update([
                'signature_verified' => $isVerified,
                'signature_verified_at' => $isVerified ? now() : null,
            ]);

            if (!$isVerified) {
                $webhook->update([
                    'status' => 'failed',
                    'processing_errors' => ['error' => 'Invalid signature'],
                    'processed_at' => now(),
                ]);

                Log::warning('Stripe webhook signature verification failed', [
                    'webhook_id' => $webhook->webhook_id,
                    'source_ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Parse webhook data
            $eventData = json_decode($request->getContent(), true);
            $webhook->update([
                'event_type' => $eventData['type'] ?? 'unknown',
                'event_id' => $eventData['id'] ?? null,
                'parsed_data' => $eventData,
                'is_test_event' => $eventData['livemode'] === false,
            ]);

            // Process the webhook
            $result = $this->processStripeWebhook($webhook, $eventData);
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            $webhook->update([
                'status' => $result['success'] ? 'processed' : 'failed',
                'processed_at' => now(),
                'processing_time_ms' => round($processingTime),
                'processing_result' => $result['message'] ?? null,
                'processing_errors' => $result['success'] ? null : $result['errors'],
                'actions_taken' => $result['actions'] ?? [],
            ]);

            return response()->json([
                'received' => true,
                'webhook_id' => $webhook->webhook_id,
                'status' => $webhook->status,
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            if (isset($webhook)) {
                $webhook->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'processing_errors' => ['exception' => $e->getMessage()],
                ]);
            }

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle PayPal webhook events.
     */
    public function handlePaypal(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Create webhook record
            $webhook = $this->createWebhookRecord('paypal', $request);
            
            // Verify PayPal signature
            $isVerified = $this->webhookService->verifyPayPalSignature(
                $request->getContent(),
                $request->headers->all(),
                config('services.paypal.webhook_id')
            );

            $webhook->update([
                'signature_verified' => $isVerified,
                'signature_verified_at' => $isVerified ? now() : null,
            ]);

            if (!$isVerified) {
                $webhook->update([
                    'status' => 'failed',
                    'processing_errors' => ['error' => 'Invalid signature'],
                    'processed_at' => now(),
                ]);

                Log::warning('PayPal webhook signature verification failed', [
                    'webhook_id' => $webhook->webhook_id,
                    'source_ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Parse webhook data
            $eventData = json_decode($request->getContent(), true);
            $webhook->update([
                'event_type' => $eventData['event_type'] ?? 'unknown',
                'event_id' => $eventData['id'] ?? null,
                'parsed_data' => $eventData,
                'is_test_event' => str_contains($eventData['id'] ?? '', 'sandbox'),
            ]);

            // Process the webhook
            $result = $this->processPayPalWebhook($webhook, $eventData);
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            $webhook->update([
                'status' => $result['success'] ? 'processed' : 'failed',
                'processed_at' => now(),
                'processing_time_ms' => round($processingTime),
                'processing_result' => $result['message'] ?? null,
                'processing_errors' => $result['success'] ? null : $result['errors'],
                'actions_taken' => $result['actions'] ?? [],
            ]);

            return response()->json([
                'received' => true,
                'webhook_id' => $webhook->webhook_id,
                'status' => $webhook->status,
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            if (isset($webhook)) {
                $webhook->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'processing_errors' => ['exception' => $e->getMessage()],
                ]);
            }

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Razorpay webhook events.
     */
    public function handleRazorpay(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Create webhook record
            $webhook = $this->createWebhookRecord('razorpay', $request);
            
            // Verify Razorpay signature
            $isVerified = $this->webhookService->verifyRazorpaySignature(
                $request->getContent(),
                $request->header('X-Razorpay-Signature'),
                config('services.razorpay.webhook_secret')
            );

            $webhook->update([
                'signature_verified' => $isVerified,
                'signature_verified_at' => $isVerified ? now() : null,
            ]);

            if (!$isVerified) {
                $webhook->update([
                    'status' => 'failed',
                    'processing_errors' => ['error' => 'Invalid signature'],
                    'processed_at' => now(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Parse webhook data
            $eventData = json_decode($request->getContent(), true);
            $webhook->update([
                'event_type' => $eventData['event'] ?? 'unknown',
                'event_id' => $eventData['payload']['payment']['entity']['id'] ?? null,
                'parsed_data' => $eventData,
                'is_test_event' => false, // Razorpay doesn't have explicit test mode indicator
            ]);

            // Process the webhook
            $result = $this->processRazorpayWebhook($webhook, $eventData);
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            $webhook->update([
                'status' => $result['success'] ? 'processed' : 'failed',
                'processed_at' => now(),
                'processing_time_ms' => round($processingTime),
                'processing_result' => $result['message'] ?? null,
                'processing_errors' => $result['success'] ? null : $result['errors'],
                'actions_taken' => $result['actions'] ?? [],
            ]);

            return response()->json([
                'received' => true,
                'webhook_id' => $webhook->webhook_id,
                'status' => $webhook->status,
            ], $result['success'] ? 200 : 200); // Razorpay expects 200 even for processing errors

        } catch (\Exception $e) {
            Log::error('Razorpay webhook processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            if (isset($webhook)) {
                $webhook->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'processing_errors' => ['exception' => $e->getMessage()],
                ]);
            }

            return response()->json(['received' => true], 200); // Always return 200 for Razorpay
        }
    }

    /**
     * Handle Square webhook events.
     */
    public function handleSquare(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        
        try {
            // Create webhook record
            $webhook = $this->createWebhookRecord('square', $request);
            
            // Verify Square signature
            $isVerified = $this->webhookService->verifySquareSignature(
                $request->getContent(),
                $request->header('X-Square-Signature'),
                config('services.square.webhook_signature_key'),
                $request->url()
            );

            $webhook->update([
                'signature_verified' => $isVerified,
                'signature_verified_at' => $isVerified ? now() : null,
            ]);

            if (!$isVerified) {
                $webhook->update([
                    'status' => 'failed',
                    'processing_errors' => ['error' => 'Invalid signature'],
                    'processed_at' => now(),
                ]);

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            // Parse webhook data
            $eventData = json_decode($request->getContent(), true);
            $webhook->update([
                'event_type' => $eventData['type'] ?? 'unknown',
                'event_id' => $eventData['event_id'] ?? null,
                'parsed_data' => $eventData,
                'is_test_event' => str_contains(config('services.square.environment'), 'sandbox'),
            ]);

            // Process the webhook
            $result = $this->processSquareWebhook($webhook, $eventData);
            
            $processingTime = (microtime(true) - $startTime) * 1000;
            $webhook->update([
                'status' => $result['success'] ? 'processed' : 'failed',
                'processed_at' => now(),
                'processing_time_ms' => round($processingTime),
                'processing_result' => $result['message'] ?? null,
                'processing_errors' => $result['success'] ? null : $result['errors'],
                'actions_taken' => $result['actions'] ?? [],
            ]);

            return response()->json([
                'received' => true,
                'webhook_id' => $webhook->webhook_id,
                'status' => $webhook->status,
            ], $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            Log::error('Square webhook processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            if (isset($webhook)) {
                $webhook->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                    'processing_errors' => ['exception' => $e->getMessage()],
                ]);
            }

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Create webhook record for audit and processing.
     */
    private function createWebhookRecord(string $provider, Request $request): PaymentWebhook
    {
        $webhookId = 'WHK-' . strtoupper(Str::random(12));
        $contentHash = hash('sha256', $request->getContent());

        // Check for duplicate webhooks
        $existingWebhook = PaymentWebhook::where('content_hash', $contentHash)
            ->where('provider', $provider)
            ->where('created_at', '>', now()->subHours(24))
            ->first();

        if ($existingWebhook) {
            // Mark as duplicate
            return PaymentWebhook::create([
                'webhook_id' => $webhookId,
                'provider' => $provider,
                'headers' => $request->headers->all(),
                'payload' => $request->getContent(),
                'signature' => $request->header('Stripe-Signature') ?? 
                              $request->header('X-Razorpay-Signature') ?? 
                              $request->header('X-Square-Signature') ?? 
                              $request->header('PayPal-Transmission-Sig'),
                'status' => 'duplicate',
                'received_at' => now(),
                'processed_at' => now(),
                'source_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'content_hash' => $contentHash,
                'duplicate_of_webhook_id' => $existingWebhook->id,
                'processing_result' => 'Duplicate webhook ignored',
            ]);
        }

        return PaymentWebhook::create([
            'webhook_id' => $webhookId,
            'provider' => $provider,
            'headers' => $request->headers->all(),
            'payload' => $request->getContent(),
            'signature' => $request->header('Stripe-Signature') ?? 
                          $request->header('X-Razorpay-Signature') ?? 
                          $request->header('X-Square-Signature') ?? 
                          $request->header('PayPal-Transmission-Sig'),
            'status' => 'pending',
            'received_at' => now(),
            'source_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_hash' => $contentHash,
        ]);
    }

    /**
     * Process Stripe webhook event.
     */
    private function processStripeWebhook(PaymentWebhook $webhook, array $eventData): array
    {
        try {
            $eventType = $eventData['type'];
            $actions = [];

            [$result, $action] = match ($eventType) {
                'payment_intent.succeeded', 'charge.succeeded' => [
                    $this->handlePaymentSucceeded($webhook, $eventData, 'stripe'),
                    'payment_completed'
                ],
                'payment_intent.payment_failed', 'charge.failed' => [
                    $this->handlePaymentFailed($webhook, $eventData, 'stripe'),
                    'payment_failed'
                ],
                'charge.refunded' => [
                    $this->handleRefundProcessed($webhook, $eventData, 'stripe'),
                    'refund_processed'
                ],
                'payment_intent.requires_action' => [
                    $this->handlePaymentRequiresAction($webhook, $eventData, 'stripe'),
                    'action_required'
                ],
                default => [
                    ['success' => true, 'message' => 'Event type not handled'],
                    'ignored'
                ]
            };
            $actions[] = $action;

            return array_merge($result, ['actions' => $actions]);

        } catch (\Exception $e) {
            Log::error('Stripe webhook event processing failed', [
                'webhook_id' => $webhook->webhook_id,
                'event_type' => $eventData['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Event processing failed',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Process PayPal webhook event.
     */
    private function processPayPalWebhook(PaymentWebhook $webhook, array $eventData): array
    {
        try {
            $eventType = $eventData['event_type'];
            $actions = [];

            [$result, $action] = match ($eventType) {
                'CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED' => [
                    $this->handlePaymentSucceeded($webhook, $eventData, 'paypal'),
                    'payment_completed'
                ],
                'PAYMENT.CAPTURE.DENIED', 'CHECKOUT.ORDER.VOIDED' => [
                    $this->handlePaymentFailed($webhook, $eventData, 'paypal'),
                    'payment_failed'
                ],
                'PAYMENT.CAPTURE.REFUNDED' => [
                    $this->handleRefundProcessed($webhook, $eventData, 'paypal'),
                    'refund_processed'
                ],
                default => [
                    ['success' => true, 'message' => 'Event type not handled'],
                    'ignored'
                ]
            };
            $actions[] = $action;

            return array_merge($result, ['actions' => $actions]);

        } catch (\Exception $e) {
            Log::error('PayPal webhook event processing failed', [
                'webhook_id' => $webhook->webhook_id,
                'event_type' => $eventData['event_type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Event processing failed',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Process Razorpay webhook event.
     */
    private function processRazorpayWebhook(PaymentWebhook $webhook, array $eventData): array
    {
        try {
            $eventType = $eventData['event'];
            $actions = [];

            [$result, $action] = match ($eventType) {
                'payment.captured', 'payment.authorized' => [
                    $this->handlePaymentSucceeded($webhook, $eventData, 'razorpay'),
                    'payment_completed'
                ],
                'payment.failed' => [
                    $this->handlePaymentFailed($webhook, $eventData, 'razorpay'),
                    'payment_failed'
                ],
                'refund.processed' => [
                    $this->handleRefundProcessed($webhook, $eventData, 'razorpay'),
                    'refund_processed'
                ],
                default => [
                    ['success' => true, 'message' => 'Event type not handled'],
                    'ignored'
                ]
            };
            $actions[] = $action;

            return array_merge($result, ['actions' => $actions]);

        } catch (\Exception $e) {
            Log::error('Razorpay webhook event processing failed', [
                'webhook_id' => $webhook->webhook_id,
                'event_type' => $eventData['event'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Event processing failed',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Process Square webhook event.
     */
    private function processSquareWebhook(PaymentWebhook $webhook, array $eventData): array
    {
        try {
            $eventType = $eventData['type'];
            $actions = [];

            [$result, $action] = match ($eventType) {
                'payment.updated' => (function() use ($webhook, $eventData) {
                    // Check if payment was completed
                    $paymentStatus = $eventData['data']['object']['status'] ?? null;
                    if ($paymentStatus === 'COMPLETED') {
                        return [
                            $this->handlePaymentSucceeded($webhook, $eventData, 'square'),
                            'payment_completed'
                        ];
                    } else {
                        return [
                            ['success' => true, 'message' => 'Payment status updated'],
                            'status_updated'
                        ];
                    }
                })(),
                'refund.updated' => (function() use ($webhook, $eventData) {
                    $refundStatus = $eventData['data']['object']['status'] ?? null;
                    if ($refundStatus === 'COMPLETED') {
                        return [
                            $this->handleRefundProcessed($webhook, $eventData, 'square'),
                            'refund_processed'
                        ];
                    } else {
                        return [
                            ['success' => true, 'message' => 'Refund status updated'],
                            'status_updated'
                        ];
                    }
                })(),
                default => [
                    ['success' => true, 'message' => 'Event type not handled'],
                    'ignored'
                ]
            };
            $actions[] = $action;

            return array_merge($result, ['actions' => $actions]);

        } catch (\Exception $e) {
            Log::error('Square webhook event processing failed', [
                'webhook_id' => $webhook->webhook_id,
                'event_type' => $eventData['type'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Event processing failed',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Handle payment succeeded event.
     */
    private function handlePaymentSucceeded(PaymentWebhook $webhook, array $eventData, string $provider): array
    {
        try {
            // Extract payment reference based on provider
            $paymentReference = $this->extractPaymentReference($provider, $eventData);
            
            if (!$paymentReference) {
                return [
                    'success' => false,
                    'message' => 'Could not extract payment reference',
                    'errors' => ['missing_reference' => 'Payment reference not found in webhook data'],
                ];
            }

            $webhook->update(['payment_reference' => $paymentReference]);

            // Use existing PaymentService webhook handler
            $payment = $this->paymentService->handleWebhook($provider, $eventData);
            
            if ($payment) {
                $webhook->update([
                    'payment_id' => $payment->id,
                    'external_transaction_id' => $this->extractTransactionId($provider, $eventData),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Payment completed successfully',
                'payment_reference' => $paymentReference,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process payment success',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Handle payment failed event.
     */
    private function handlePaymentFailed(PaymentWebhook $webhook, array $eventData, string $provider): array
    {
        try {
            $paymentReference = $this->extractPaymentReference($provider, $eventData);
            
            if (!$paymentReference) {
                return [
                    'success' => false,
                    'message' => 'Could not extract payment reference',
                    'errors' => ['missing_reference' => 'Payment reference not found in webhook data'],
                ];
            }

            $webhook->update(['payment_reference' => $paymentReference]);

            // Use existing PaymentService webhook handler
            $payment = $this->paymentService->handleWebhook($provider, $eventData);
            
            if ($payment) {
                $webhook->update([
                    'payment_id' => $payment->id,
                    'external_transaction_id' => $this->extractTransactionId($provider, $eventData),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Payment failure processed',
                'payment_reference' => $paymentReference,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process payment failure',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Handle refund processed event.
     */
    private function handleRefundProcessed(PaymentWebhook $webhook, array $eventData, string $provider): array
    {
        try {
            $paymentReference = $this->extractPaymentReference($provider, $eventData);
            
            if (!$paymentReference) {
                return [
                    'success' => false,
                    'message' => 'Could not extract payment reference',
                    'errors' => ['missing_reference' => 'Payment reference not found in webhook data'],
                ];
            }

            $webhook->update(['payment_reference' => $paymentReference]);

            // Use existing PaymentService webhook handler
            $payment = $this->paymentService->handleWebhook($provider, $eventData);
            
            if ($payment) {
                $webhook->update([
                    'payment_id' => $payment->id,
                    'external_transaction_id' => $this->extractTransactionId($provider, $eventData),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'payment_reference' => $paymentReference,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process refund',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Handle payment requires action event.
     */
    private function handlePaymentRequiresAction(PaymentWebhook $webhook, array $eventData, string $provider): array
    {
        try {
            $paymentReference = $this->extractPaymentReference($provider, $eventData);
            
            if ($paymentReference) {
                $webhook->update(['payment_reference' => $paymentReference]);
            }

            // Log the action requirement for manual review
            Log::info('Payment requires action', [
                'provider' => $provider,
                'payment_reference' => $paymentReference,
                'webhook_id' => $webhook->webhook_id,
                'event_data' => $eventData,
            ]);

            $webhook->update(['requires_manual_review' => true]);

            return [
                'success' => true,
                'message' => 'Payment requires action - flagged for review',
                'payment_reference' => $paymentReference,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process action requirement',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Extract payment reference from webhook data.
     */
    private function extractPaymentReference(string $provider, array $eventData): ?string
    {
        return match ($provider) {
            'stripe' => $eventData['data']['object']['metadata']['payment_reference'] ?? null,
            'paypal' => $eventData['resource']['custom_id'] ?? null,
            'razorpay' => $eventData['payload']['payment']['entity']['notes']['payment_reference'] ?? null,
            'square' => $eventData['data']['object']['reference_id'] ?? null,
            default => null,
        };
    }

    /**
     * Extract transaction ID from webhook data.
     */
    private function extractTransactionId(string $provider, array $eventData): ?string
    {
        return match ($provider) {
            'stripe' => $eventData['data']['object']['id'] ?? null,
            'paypal' => $eventData['resource']['id'] ?? null,
            'razorpay' => $eventData['payload']['payment']['entity']['id'] ?? null,
            'square' => $eventData['data']['object']['id'] ?? null,
            default => null,
        };
    }
}
