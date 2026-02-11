<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private PaymentService $paymentService;
    private PaymentGatewayService $gatewayService;

    public function __construct(PaymentService $paymentService, PaymentGatewayService $gatewayService)
    {
        $this->paymentService = $paymentService;
        $this->gatewayService = $gatewayService;
    }

    /**
     * List payments for authenticated user (REST API).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $filters = $request->only(['status', 'payment_method', 'date_from', 'date_to']);
            
            $payments = $this->paymentService->getCustomerPayments($user->id, $filters);
            
            return response()->json([
                'success' => true,
                'data' => $payments,
                'meta' => [
                    'total' => $payments->count(),
                    'filters_applied' => $filters,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list payments', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to retrieve payments',
                'message' => 'An error occurred while fetching your payments.',
            ], 500);
        }
    }

    /**
     * Create a new payment (REST API).
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'payment_method' => 'required|string|in:card,bank_transfer,wallet,cash',
                'payment_provider' => 'required|string|in:stripe,paypal,mada,stc_pay',
                'card_last_four' => 'required_if:payment_method,card|string|size:4',
                'card_brand' => 'required_if:payment_method,card|string',
                'card_token' => 'nullable|string',
                'mobile_number' => 'required_if:payment_provider,stc_pay|string',
                'return_url' => 'nullable|url',
                'metadata' => 'nullable|array',
            ]);

            $payment = $this->paymentService->initiatePayment($validated['invoice_id'], $validated);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'payment_reference' => $payment->payment_reference,
                    'status' => $payment->status,
                    'requires_3ds' => $payment->requires_3ds,
                ],
                'message' => 'Payment initiated successfully',
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create payment', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'payment_creation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Show payment details (REST API).
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Ensure user can only access their own payments
            if ($payment->customer_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => 'You are not authorized to view this payment.',
                ], 403);
            }

            $paymentStats = $this->paymentService->getPaymentStats($payment->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment->load(['invoice']),
                    'stats' => $paymentStats,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to show payment', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'payment_retrieval_failed',
                'message' => 'Failed to retrieve payment details.',
            ], 500);
        }
    }

    /**
     * Confirm/authorize payment (REST API).
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Ensure user can only confirm their own payments
            if ($payment->customer_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => 'You are not authorized to confirm this payment.',
                ], 403);
            }

            $validated = $request->validate([
                'payment_method_id' => 'nullable|string', // For Stripe
                'otp' => 'nullable|string', // For STC Pay
                'session_id' => 'nullable|string', // For STC Pay
                'confirmation_data' => 'nullable|array',
            ]);

            $processedPayment = $this->paymentService->processPayment($payment->id, $validated);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $processedPayment,
                    'status' => $processedPayment->status,
                    'requires_action' => $processedPayment->isPending(),
                ],
                'message' => 'Payment processed successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_failed',
                'message' => 'The provided data is invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to confirm payment', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'payment_confirmation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel payment (REST API).
     */
    public function cancel(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Ensure user can only cancel their own payments
            if ($payment->customer_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => 'You are not authorized to cancel this payment.',
                ], 403);
            }

            $validated = $request->validate([
                'reason' => 'nullable|string|max:255',
            ]);

            $cancelledPayment = $this->paymentService->cancelPayment(
                $payment->id, 
                $validated['reason'] ?? 'Cancelled by customer'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $cancelledPayment,
                    'status' => $cancelledPayment->status,
                ],
                'message' => 'Payment cancelled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel payment', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'payment_cancellation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment receipt (REST API).
     */
    public function getReceipt(Request $request, Payment $payment): JsonResponse
    {
        try {
            // Ensure user can only access their own payment receipts
            if ($payment->customer_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'unauthorized',
                    'message' => 'You are not authorized to view this receipt.',
                ], 403);
            }

            if (!$payment->isSuccessful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'receipt_not_available',
                    'message' => 'Receipt is only available for successful payments.',
                ], 400);
            }

            $receiptData = [
                'receipt_id' => 'RCP-' . $payment->payment_reference,
                'payment_reference' => $payment->payment_reference,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method_display,
                'status' => $payment->status_display,
                'completed_at' => $payment->completed_at,
                'gateway_fee' => $payment->gateway_fee,
                'platform_fee' => $payment->platform_fee,
                'net_amount' => $payment->net_amount,
                'invoice' => $payment->invoice,
                'masked_card_number' => $payment->masked_card_number,
            ];

            return response()->json([
                'success' => true,
                'data' => $receiptData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate receipt', [
                'payment_id' => $payment->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'receipt_generation_failed',
                'message' => 'Failed to generate payment receipt.',
            ], 500);
        }
    }

    // ========================================
    // Inter-Service RPC Methods (service.auth middleware)
    // ========================================

    /**
     * Process payment for inter-service communication.
     */
    public function processPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'invoice_id' => 'required|integer|exists:invoices,id',
                'payment_data' => 'required|array',
                'gateway_data' => 'nullable|array',
                'workflow_id' => 'nullable|string', // For saga pattern integration
            ]);

            // Initiate payment
            $payment = $this->paymentService->initiatePayment(
                $validated['invoice_id'], 
                $validated['payment_data']
            );

            // Process payment if gateway data provided
            if (isset($validated['gateway_data'])) {
                $payment = $this->paymentService->processPayment($payment->id, $validated['gateway_data']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'requires_3ds' => $payment->requires_3ds,
                    'workflow_id' => $validated['workflow_id'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('RPC payment processing failed', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'rpc_payment_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get payment status for inter-service communication.
     */
    public function getPaymentStatus(Request $request, int $paymentId): JsonResponse
    {
        try {
            $payment = $this->paymentService->getPayment($paymentId);
            $stats = $this->paymentService->getPaymentStats($paymentId);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'stats' => $stats,
                ],
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'payment_not_found',
                'message' => 'Payment not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('RPC payment status retrieval failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'rpc_status_failed',
                'message' => 'Failed to retrieve payment status.',
            ], 500);
        }
    }

    /**
     * Refund payment for inter-service communication.
     */
    public function refundPayment(Request $request, int $paymentId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'nullable|string|max:255',
                'workflow_id' => 'nullable|string',
            ]);

            $refund = $this->paymentService->processRefund(
                $paymentId,
                $validated['amount'],
                $validated['reason'] ?? 'Refund requested via RPC'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'refund_id' => $refund->id,
                    'refund_reference' => $refund->payment_reference,
                    'amount' => $refund->amount,
                    'status' => $refund->status,
                    'workflow_id' => $validated['workflow_id'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('RPC payment refund failed', [
                'payment_id' => $paymentId,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'rpc_refund_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Capture/settle payment for inter-service communication.
     */
    public function capturePayment(Request $request, int $paymentId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount' => 'nullable|numeric|min:0.01',
                'workflow_id' => 'nullable|string',
            ]);

            $payment = $this->paymentService->getPayment($paymentId);

            if (!$payment->isPending()) {
                return response()->json([
                    'success' => false,
                    'error' => 'invalid_payment_status',
                    'message' => 'Only pending payments can be captured.',
                ], 400);
            }

            // Mark as completed (capture)
            $payment->markAsCompleted();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'status' => $payment->status,
                    'captured_amount' => $payment->amount,
                    'workflow_id' => $validated['workflow_id'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('RPC payment capture failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'rpc_capture_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel payment for inter-service communication.
     */
    public function cancelPayment(Request $request, int $paymentId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:255',
                'workflow_id' => 'nullable|string',
            ]);

            $cancelledPayment = $this->paymentService->cancelPayment(
                $paymentId,
                $validated['reason'] ?? 'Cancelled via RPC'
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $cancelledPayment->id,
                    'payment_reference' => $cancelledPayment->payment_reference,
                    'status' => $cancelledPayment->status,
                    'workflow_id' => $validated['workflow_id'] ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('RPC payment cancellation failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'rpc_cancellation_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Validate payment data for inter-service communication.
     */
    public function validatePayment(Request $request): JsonResponse
    {
        try {
            $paymentData = $request->validate([
                'payment_method' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
                'card_last_four' => 'required_if:payment_method,card|string|size:4',
                'card_brand' => 'required_if:payment_method,card|string',
            ]);

            // Use existing validation logic from PaymentService
            $validation = $this->paymentService->validatePaymentData($paymentData);

            return response()->json([
                'success' => $validation['valid'],
                'data' => [
                    'valid' => $validation['valid'],
                    'errors' => $validation['errors'] ?? [],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify payment method for inter-service communication.
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_reference' => 'required|string',
                'expected_amount' => 'nullable|numeric',
                'expected_status' => 'nullable|string',
            ]);

            $payment = $this->paymentService->getPaymentByReference($validated['payment_reference']);

            $verification = [
                'payment_exists' => true,
                'payment_reference' => $payment->payment_reference,
                'current_status' => $payment->status,
                'current_amount' => $payment->amount,
                'is_successful' => $payment->isSuccessful(),
                'verification_passed' => true,
            ];

            // Check expected amount if provided
            if (isset($validated['expected_amount'])) {
                $amountMatches = abs($payment->amount - $validated['expected_amount']) < 0.01;
                $verification['amount_matches'] = $amountMatches;
                $verification['verification_passed'] = $verification['verification_passed'] && $amountMatches;
            }

            // Check expected status if provided
            if (isset($validated['expected_status'])) {
                $statusMatches = $payment->status === $validated['expected_status'];
                $verification['status_matches'] = $statusMatches;
                $verification['verification_passed'] = $verification['verification_passed'] && $statusMatches;
            }

            return response()->json([
                'success' => true,
                'data' => $verification,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => true,
                'data' => [
                    'payment_exists' => false,
                    'verification_passed' => false,
                    'error' => 'payment_not_found',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('RPC payment verification failed', [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'verification_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
