<?php

namespace App\Services\Contracts;

/**
 * Payment Service Contract
 * 
 * Defines the interface for payment processing services
 */
interface PaymentServiceInterface
{
    /**
     * Process payment
     */
    public function processPayment(array $paymentData): array;

    /**
     * Refund payment
     */
    public function refundPayment(string $paymentId, float $amount = null, string $reason = null): array;

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $paymentId): array;

    /**
     * Get payment history for user
     */
    public function getPaymentHistory(int $userId, array $filters = []): array;

    /**
     * Validate payment data
     */
    public function validatePaymentData(array $paymentData): array;

    /**
     * Cancel payment
     */
    public function cancelPayment(string $paymentId, string $reason = null): array;

    /**
     * Capture authorized payment
     */
    public function capturePayment(string $paymentId, float $amount = null): array;

    /**
     * Get supported payment methods
     */
    public function getSupportedPaymentMethods(): array;

    /**
     * Calculate payment fees
     */
    public function calculatePaymentFees(float $amount, string $paymentMethod): array;

    /**
     * Verify payment webhook
     */
    public function verifyPaymentWebhook(array $webhookData): array;
}
