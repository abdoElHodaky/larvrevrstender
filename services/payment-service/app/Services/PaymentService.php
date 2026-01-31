<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Events\PaymentInitiated;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PaymentService
{
    private InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Get payment by ID.
     */
    public function getPayment(int $paymentId): Payment
    {
        return Payment::with(['invoice'])->findOrFail($paymentId);
    }

    /**
     * Get payment by reference.
     */
    public function getPaymentByReference(string $paymentReference): Payment
    {
        return Payment::with(['invoice'])
                     ->where('payment_reference', $paymentReference)
                     ->firstOrFail();
    }

    /**
     * Get customer payments.
     */
    public function getCustomerPayments(int $customerId, array $filters = []): Collection
    {
        $query = Payment::with(['invoice'])
                       ->byCustomer($customerId)
                       ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['payment_method'])) {
            $query->byMethod($filters['payment_method']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }

    /**
     * Get merchant payments.
     */
    public function getMerchantPayments(int $merchantId, array $filters = []): Collection
    {
        $query = Payment::with(['invoice'])
                       ->byMerchant($merchantId)
                       ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (isset($filters['payment_method'])) {
            $query->byMethod($filters['payment_method']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        return $query->get();
    }

    /**
     * Initiate payment for invoice.
     */
    public function initiatePayment(int $invoiceId, array $paymentData): Payment
    {
        $invoice = $this->invoiceService->getInvoice($invoiceId);
        
        // Validate invoice can be paid
        if (!$invoice->canBePaid()) {
            throw new \Exception('Invoice cannot be paid in current status');
        }
        
        // Validate payment data
        $validation = $this->validatePaymentData($paymentData);
        if (!$validation['valid']) {
            throw new \Exception('Payment data validation failed: ' . implode(', ', $validation['errors']));
        }
        
        // Check for existing pending payment
        $existingPendingPayment = $invoice->payments()
                                         ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
                                         ->first();
        
        if ($existingPendingPayment) {
            throw new \Exception('Invoice already has a pending payment');
        }
        
        $paymentData = array_merge($paymentData, [
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'customer_id' => $invoice->customer_id,
            'merchant_id' => $invoice->merchant_id,
            'amount' => $invoice->remaining_balance,
            'currency' => $invoice->currency,
            'type' => Payment::TYPE_PAYMENT,
            'status' => Payment::STATUS_PENDING,
            'initiated_at' => now(),
        ]);
        
        // Calculate fees
        $paymentData['gateway_fee'] = $this->calculateGatewayFee($paymentData['amount'], $paymentData['payment_method']);
        $paymentData['platform_fee'] = $this->calculatePlatformFee($paymentData['amount']);
        
        // Risk assessment
        $paymentData['risk_assessment'] = $this->performRiskAssessment($paymentData);
        $paymentData['requires_3ds'] = $this->requires3ds($paymentData);
        
        $payment = Payment::create($paymentData);
        
        event(new PaymentInitiated($payment));
        
        return $payment;
    }

    /**
     * Process payment through gateway.
     */
    public function processPayment(int $paymentId, array $gatewayData = []): Payment
    {
        $payment = $this->getPayment($paymentId);
        
        if (!$payment->isPending()) {
            throw new \Exception('Only pending payments can be processed');
        }
        
        $payment->markAsProcessing();
        
        try {
            // Simulate payment processing with gateway
            $gatewayResponse = $this->processWithGateway($payment, $gatewayData);
            
            if ($gatewayResponse['success']) {
                $payment->markAsCompleted($gatewayResponse);
                event(new PaymentCompleted($payment));
            } else {
                $payment->markAsFailed(
                    $gatewayResponse['error_reason'] ?? 'Payment failed',
                    $gatewayResponse['error_code'] ?? null,
                    $gatewayResponse['error_message'] ?? null
                );
                event(new PaymentFailed($payment, $gatewayResponse));
            }
        } catch (\Exception $e) {
            $payment->markAsFailed('Processing exception', 'EXCEPTION', $e->getMessage());
            event(new PaymentFailed($payment, ['exception' => $e->getMessage()]));
        }
        
        return $payment->fresh();
    }

    /**
     * Simulate payment processing with gateway.
     */
    private function processWithGateway(Payment $payment, array $gatewayData): array
    {
        // In a real implementation, this would integrate with:
        // - Stripe, PayPal, Mada, STC Pay, etc.
        // - Handle 3D Secure authentication
        // - Process card payments, bank transfers, wallets
        
        // Simulate different outcomes based on payment method
        $successRate = match($payment->payment_method) {
            Payment::METHOD_CARD => 0.95,
            Payment::METHOD_BANK_TRANSFER => 0.98,
            Payment::METHOD_WALLET => 0.97,
            Payment::METHOD_CASH => 1.0,
            default => 0.90
        };
        
        $isSuccessful = (rand(1, 100) / 100) <= $successRate;
        
        if ($isSuccessful) {
            return [
                'success' => true,
                'transaction_id' => 'TXN_' . strtoupper(uniqid()),
                'gateway_reference' => 'GW_' . strtoupper(uniqid()),
                'processed_at' => now()->toISOString(),
                'gateway_fee' => $payment->gateway_fee,
                'net_amount' => $payment->amount - $payment->gateway_fee - $payment->platform_fee,
            ];
        } else {
            $errorReasons = [
                'insufficient_funds' => 'Insufficient funds',
                'card_declined' => 'Card declined by issuer',
                'expired_card' => 'Card has expired',
                'invalid_cvv' => 'Invalid CVV code',
                'fraud_detected' => 'Transaction flagged as fraudulent',
                'network_error' => 'Network communication error',
            ];
            
            $errorCode = array_rand($errorReasons);
            
            return [
                'success' => false,
                'error_code' => $errorCode,
                'error_reason' => $errorCode,
                'error_message' => $errorReasons[$errorCode],
                'gateway_reference' => 'ERR_' . strtoupper(uniqid()),
            ];
        }
    }

    /**
     * Cancel payment.
     */
    public function cancelPayment(int $paymentId, string $reason = null): Payment
    {
        $payment = $this->getPayment($paymentId);
        
        $payment->cancel($reason);
        
        return $payment->fresh();
    }

    /**
     * Process refund.
     */
    public function processRefund(int $paymentId, float $amount, string $reason = null): Payment
    {
        $payment = $this->getPayment($paymentId);
        
        $refund = $payment->processRefund($amount, $reason);
        
        event(new PaymentRefunded($payment, $refund, $amount, $reason));
        
        return $refund;
    }

    /**
     * Handle payment webhook.
     */
    public function handleWebhook(string $provider, array $webhookData): ?Payment
    {
        // Extract payment reference from webhook data
        $paymentReference = $this->extractPaymentReference($provider, $webhookData);
        
        if (!$paymentReference) {
            throw new \Exception('Could not extract payment reference from webhook');
        }
        
        try {
            $payment = $this->getPaymentByReference($paymentReference);
        } catch (ModelNotFoundException $e) {
            throw new \Exception('Payment not found for reference: ' . $paymentReference);
        }
        
        // Add webhook data to payment
        $payment->addWebhookData($webhookData);
        
        // Process webhook based on type
        $webhookType = $webhookData['type'] ?? $webhookData['event_type'] ?? 'unknown';
        
        switch ($webhookType) {
            case 'payment.succeeded':
            case 'charge.succeeded':
                if ($payment->isPending()) {
                    $payment->markAsCompleted($webhookData);
                    event(new PaymentCompleted($payment));
                }
                break;
                
            case 'payment.failed':
            case 'charge.failed':
                if ($payment->isPending()) {
                    $payment->markAsFailed(
                        $webhookData['failure_reason'] ?? 'Payment failed',
                        $webhookData['failure_code'] ?? null,
                        $webhookData['failure_message'] ?? null
                    );
                    event(new PaymentFailed($payment, $webhookData));
                }
                break;
                
            case 'payment.refunded':
            case 'charge.refunded':
                // Handle refund webhook
                $refundAmount = $webhookData['refund_amount'] ?? $payment->amount;
                $refundReason = $webhookData['refund_reason'] ?? 'Refunded via webhook';
                
                if ($payment->canBeRefunded()) {
                    $refund = $payment->processRefund($refundAmount, $refundReason);
                    event(new PaymentRefunded($payment, $refund, $refundAmount, $refundReason));
                }
                break;
        }
        
        return $payment->fresh();
    }

    /**
     * Extract payment reference from webhook data.
     */
    private function extractPaymentReference(string $provider, array $webhookData): ?string
    {
        return match($provider) {
            'stripe' => $webhookData['data']['object']['metadata']['payment_reference'] ?? null,
            'paypal' => $webhookData['resource']['custom_id'] ?? null,
            'mada' => $webhookData['merchant_reference'] ?? null,
            'stc_pay' => $webhookData['reference_number'] ?? null,
            default => $webhookData['payment_reference'] ?? $webhookData['reference'] ?? null
        };
    }

    /**
     * Get payment statistics.
     */
    public function getPaymentStats(int $paymentId): array
    {
        $payment = $this->getPayment($paymentId);
        
        return [
            'payment_reference' => $payment->payment_reference,
            'status_display' => $payment->status_display,
            'status_color' => $payment->status_color,
            'payment_method_display' => $payment->payment_method_display,
            'masked_card_number' => $payment->masked_card_number,
            'is_successful' => $payment->isSuccessful(),
            'is_pending' => $payment->isPending(),
            'is_failed' => $payment->isFailed(),
            'can_be_refunded' => $payment->canBeRefunded(),
            'refundable_amount' => $payment->refundable_amount,
            'processing_time' => $payment->processing_time,
            'risk_score' => $payment->risk_score,
            'requires_3ds' => $payment->requires3ds(),
            '3ds_status_display' => $payment->{'3ds_status_display'},
            'gateway_fee' => $payment->gateway_fee,
            'platform_fee' => $payment->platform_fee,
            'net_amount' => $payment->net_amount,
            'is_reconciled' => $payment->reconciled,
        ];
    }

    /**
     * Get customer payment statistics.
     */
    public function getCustomerPaymentStats(int $customerId): array
    {
        $payments = Payment::byCustomer($customerId);
        
        return [
            'total_payments' => $payments->count(),
            'successful_payments' => $payments->successful()->count(),
            'failed_payments' => $payments->failed()->count(),
            'pending_payments' => $payments->pending()->count(),
            'total_amount_paid' => $payments->successful()->sum('amount'),
            'total_refunded' => $payments->where('status', Payment::STATUS_REFUNDED)->sum('amount'),
            'average_payment_amount' => $payments->successful()->avg('amount'),
            'success_rate' => $this->calculateSuccessRate($customerId),
            'preferred_payment_method' => $this->getPreferredPaymentMethod($customerId),
            'average_processing_time' => $this->calculateAverageProcessingTime($customerId),
        ];
    }

    /**
     * Get merchant payment statistics.
     */
    public function getMerchantPaymentStats(int $merchantId): array
    {
        $payments = Payment::byMerchant($merchantId);
        
        return [
            'total_payments' => $payments->count(),
            'successful_payments' => $payments->successful()->count(),
            'total_revenue' => $payments->successful()->sum('net_amount'),
            'total_fees_paid' => $payments->successful()->sum('gateway_fee'),
            'total_platform_fees' => $payments->successful()->sum('platform_fee'),
            'average_transaction_value' => $payments->successful()->avg('amount'),
            'success_rate' => $this->calculateMerchantSuccessRate($merchantId),
            'refund_rate' => $this->calculateRefundRate($merchantId),
            'settlement_pending' => $payments->successful()->unreconciled()->sum('net_amount'),
        ];
    }

    /**
     * Calculate success rate for customer.
     */
    private function calculateSuccessRate(int $customerId): float
    {
        $totalPayments = Payment::byCustomer($customerId)
                               ->whereIn('status', [Payment::STATUS_COMPLETED, Payment::STATUS_FAILED])
                               ->count();
        
        if ($totalPayments === 0) {
            return 0.0;
        }
        
        $successfulPayments = Payment::byCustomer($customerId)
                                    ->successful()
                                    ->count();
        
        return round(($successfulPayments / $totalPayments) * 100, 2);
    }

    /**
     * Calculate success rate for merchant.
     */
    private function calculateMerchantSuccessRate(int $merchantId): float
    {
        $totalPayments = Payment::byMerchant($merchantId)
                               ->whereIn('status', [Payment::STATUS_COMPLETED, Payment::STATUS_FAILED])
                               ->count();
        
        if ($totalPayments === 0) {
            return 0.0;
        }
        
        $successfulPayments = Payment::byMerchant($merchantId)
                                    ->successful()
                                    ->count();
        
        return round(($successfulPayments / $totalPayments) * 100, 2);
    }

    /**
     * Calculate refund rate for merchant.
     */
    private function calculateRefundRate(int $merchantId): float
    {
        $totalSuccessfulPayments = Payment::byMerchant($merchantId)
                                         ->successful()
                                         ->count();
        
        if ($totalSuccessfulPayments === 0) {
            return 0.0;
        }
        
        $refundedPayments = Payment::byMerchant($merchantId)
                                  ->whereIn('status', [Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED])
                                  ->count();
        
        return round(($refundedPayments / $totalSuccessfulPayments) * 100, 2);
    }

    /**
     * Get preferred payment method for customer.
     */
    private function getPreferredPaymentMethod(int $customerId): ?string
    {
        $paymentMethods = Payment::byCustomer($customerId)
                                ->successful()
                                ->selectRaw('payment_method, COUNT(*) as count')
                                ->groupBy('payment_method')
                                ->orderBy('count', 'desc')
                                ->first();
        
        return $paymentMethods->payment_method ?? null;
    }

    /**
     * Calculate average processing time for customer.
     */
    private function calculateAverageProcessingTime(int $customerId): ?float
    {
        $payments = Payment::byCustomer($customerId)
                          ->successful()
                          ->whereNotNull('initiated_at')
                          ->whereNotNull('completed_at')
                          ->get();
        
        if ($payments->isEmpty()) {
            return null;
        }
        
        $totalSeconds = $payments->sum('processing_time');
        
        return round($totalSeconds / $payments->count(), 1);
    }

    /**
     * Calculate gateway fee.
     */
    private function calculateGatewayFee(float $amount, string $paymentMethod): float
    {
        $feeRates = [
            Payment::METHOD_CARD => 0.029, // 2.9%
            Payment::METHOD_BANK_TRANSFER => 0.01, // 1%
            Payment::METHOD_WALLET => 0.025, // 2.5%
            Payment::METHOD_CASH => 0.0, // No fee
        ];
        
        $rate = $feeRates[$paymentMethod] ?? 0.03; // Default 3%
        
        return round($amount * $rate, 2);
    }

    /**
     * Calculate platform fee.
     */
    private function calculatePlatformFee(float $amount): float
    {
        // Platform takes 1% of transaction
        return round($amount * 0.01, 2);
    }

    /**
     * Perform risk assessment.
     */
    private function performRiskAssessment(array $paymentData): array
    {
        // Simplified risk assessment
        $riskScore = 0.0;
        $riskFactors = [];
        
        // Amount-based risk
        if ($paymentData['amount'] > 10000) {
            $riskScore += 0.3;
            $riskFactors[] = 'High transaction amount';
        }
        
        // Payment method risk
        if ($paymentData['payment_method'] === Payment::METHOD_CARD) {
            $riskScore += 0.1;
        }
        
        // Time-based risk (late night transactions)
        $hour = now()->hour;
        if ($hour < 6 || $hour > 22) {
            $riskScore += 0.2;
            $riskFactors[] = 'Off-hours transaction';
        }
        
        return [
            'score' => min($riskScore, 1.0),
            'level' => $riskScore < 0.3 ? 'low' : ($riskScore < 0.7 ? 'medium' : 'high'),
            'factors' => $riskFactors,
            'assessed_at' => now()->toISOString(),
        ];
    }

    /**
     * Check if payment requires 3D Secure.
     */
    private function requires3ds(array $paymentData): bool
    {
        // Require 3DS for high-risk transactions
        $riskScore = $paymentData['risk_assessment']['score'] ?? 0;
        
        return $paymentData['payment_method'] === Payment::METHOD_CARD && 
               ($paymentData['amount'] > 1000 || $riskScore > 0.5);
    }

    /**
     * Validate payment data.
     */
    private function validatePaymentData(array $paymentData): array
    {
        $errors = [];
        
        // Required fields
        if (empty($paymentData['payment_method'])) {
            $errors[] = 'Payment method is required';
        }
        
        // Validate payment method
        $validMethods = [Payment::METHOD_CARD, Payment::METHOD_BANK_TRANSFER, Payment::METHOD_WALLET, Payment::METHOD_CASH];
        if (isset($paymentData['payment_method']) && !in_array($paymentData['payment_method'], $validMethods)) {
            $errors[] = 'Invalid payment method';
        }
        
        // Card-specific validation
        if (isset($paymentData['payment_method']) && $paymentData['payment_method'] === Payment::METHOD_CARD) {
            if (empty($paymentData['card_last_four'])) {
                $errors[] = 'Card last four digits are required';
            }
            
            if (empty($paymentData['card_brand'])) {
                $errors[] = 'Card brand is required';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get pending payments requiring action.
     */
    public function getPendingPayments(): Collection
    {
        return Payment::pending()
                     ->with(['invoice', 'customer', 'merchant'])
                     ->where('initiated_at', '<', now()->subMinutes(30)) // Pending for more than 30 minutes
                     ->orderBy('initiated_at', 'asc')
                     ->get();
    }

    /**
     * Reconcile payments.
     */
    public function reconcilePayments(array $reconciliationData): array
    {
        $results = [
            'reconciled_count' => 0,
            'total_amount' => 0,
            'errors' => []
        ];
        
        foreach ($reconciliationData as $item) {
            try {
                $payment = $this->getPaymentByReference($item['payment_reference']);
                
                if (!$payment->reconciled) {
                    $payment->markAsReconciled($item['reconciliation_reference'] ?? null);
                    $results['reconciled_count']++;
                    $results['total_amount'] += $payment->net_amount;
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'payment_reference' => $item['payment_reference'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

