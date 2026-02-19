<?php

namespace App\RPC\Clients;

use Shared\RPC\BaseRpcClient;

/**
 * Payment Service RPC Client for Order Service
 *
 * Handles RPC communication with the Payment Service for order-related
 * payment processing, refunds, escrow management, and payment validation.
 *
 * This client provides comprehensive payment operations needed for
 * order processing workflows including payment creation, status tracking,
 * refund processing, and escrow management.
 */
class PaymentServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('payment-service', [
            'timeout' => 45, // Longer timeout for payment operations
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }

    /**
     * Process payment for an order
     *
     * @param int $orderId Order ID
     * @param array $paymentData Payment information
     * @return array Payment processing result
     */
    public function processOrderPayment(int $orderId, array $paymentData): array
    {
        return $this->call('payment.process_order_payment', [
            'order_id' => $orderId,
            'payment_data' => $paymentData,
        ]);
    }

    /**
     * Get payment status for an order
     *
     * @param int $orderId Order ID
     * @return array Payment status information
     */
    public function getOrderPaymentStatus(int $orderId): array
    {
        return $this->call('payment.get_order_payment_status', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Create escrow payment for order
     *
     * @param int $orderId Order ID
     * @param float $amount Escrow amount
     * @param array $escrowData Escrow configuration
     * @return array Escrow creation result
     */
    public function createOrderEscrow(int $orderId, float $amount, array $escrowData = []): array
    {
        return $this->call('payment.create_order_escrow', [
            'order_id' => $orderId,
            'amount' => $amount,
            'escrow_data' => $escrowData,
        ]);
    }

    /**
     * Release escrow payment for completed order
     *
     * @param int $orderId Order ID
     * @param string $releaseReason Reason for release
     * @return array Escrow release result
     */
    public function releaseOrderEscrow(int $orderId, string $releaseReason = 'order_completed'): array
    {
        return $this->call('payment.release_order_escrow', [
            'order_id' => $orderId,
            'release_reason' => $releaseReason,
        ]);
    }

    /**
     * Refund order payment
     *
     * @param int $orderId Order ID
     * @param float|null $amount Refund amount (null for full refund)
     * @param string $reason Refund reason
     * @return array Refund processing result
     */
    public function refundOrderPayment(int $orderId, ?float $amount = null, string $reason = 'order_cancelled'): array
    {
        return $this->call('payment.refund_order_payment', [
            'order_id' => $orderId,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    /**
     * Validate payment method for order
     *
     * @param int $customerId Customer ID
     * @param array $paymentMethod Payment method details
     * @return array Validation result
     */
    public function validateOrderPaymentMethod(int $customerId, array $paymentMethod): array
    {
        return $this->call('payment.validate_order_payment_method', [
            'customer_id' => $customerId,
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Get payment history for order
     *
     * @param int $orderId Order ID
     * @return array Payment history
     */
    public function getOrderPaymentHistory(int $orderId): array
    {
        return $this->call('payment.get_order_payment_history', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * Calculate payment fees for order
     *
     * @param float $amount Order amount
     * @param string $paymentMethod Payment method
     * @param array $feeData Additional fee calculation data
     * @return array Fee calculation result
     */
    public function calculateOrderPaymentFees(float $amount, string $paymentMethod, array $feeData = []): array
    {
        return $this->call('payment.calculate_order_payment_fees', [
            'amount' => $amount,
            'payment_method' => $paymentMethod,
            'fee_data' => $feeData,
        ]);
    }

    /**
     * Process partial payment for order
     *
     * @param int $orderId Order ID
     * @param float $amount Partial payment amount
     * @param array $paymentData Payment information
     * @return array Partial payment result
     */
    public function processPartialOrderPayment(int $orderId, float $amount, array $paymentData): array
    {
        return $this->call('payment.process_partial_order_payment', [
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_data' => $paymentData,
        ]);
    }

    /**
     * Get payment analytics for orders
     *
     * @param array $orderIds Order IDs
     * @param array $dateRange Date range filter
     * @return array Payment analytics data
     */
    public function getOrderPaymentAnalytics(array $orderIds = [], array $dateRange = []): array
    {
        return $this->call('payment.get_order_payment_analytics', [
            'order_ids' => $orderIds,
            'date_range' => $dateRange,
        ]);
    }

    /**
     * Verify payment completion for order
     *
     * @param int $orderId Order ID
     * @param string $paymentReference Payment reference
     * @return array Verification result
     */
    public function verifyOrderPaymentCompletion(int $orderId, string $paymentReference): array
    {
        return $this->call('payment.verify_order_payment_completion', [
            'order_id' => $orderId,
            'payment_reference' => $paymentReference,
        ]);
    }

    /**
     * Handle payment dispute for order
     *
     * @param int $orderId Order ID
     * @param array $disputeData Dispute information
     * @return array Dispute handling result
     */
    public function handleOrderPaymentDispute(int $orderId, array $disputeData): array
    {
        return $this->call('payment.handle_order_payment_dispute', [
            'order_id' => $orderId,
            'dispute_data' => $disputeData,
        ]);
    }

    /**
     * Get payment methods for customer
     *
     * @param int $customerId Customer ID
     * @return array Available payment methods
     */
    public function getCustomerPaymentMethods(int $customerId): array
    {
        return $this->call('payment.get_customer_payment_methods', [
            'customer_id' => $customerId,
        ]);
    }

    /**
     * Process recurring payment for subscription order
     *
     * @param int $orderId Order ID
     * @param array $recurringData Recurring payment configuration
     * @return array Recurring payment setup result
     */
    public function processRecurringOrderPayment(int $orderId, array $recurringData): array
    {
        return $this->call('payment.process_recurring_order_payment', [
            'order_id' => $orderId,
            'recurring_data' => $recurringData,
        ]);
    }

    /**
     * Cancel pending payment for order
     *
     * @param int $orderId Order ID
     * @param string $cancellationReason Reason for cancellation
     * @return array Cancellation result
     */
    public function cancelOrderPayment(int $orderId, string $cancellationReason): array
    {
        return $this->call('payment.cancel_order_payment', [
            'order_id' => $orderId,
            'cancellation_reason' => $cancellationReason,
        ]);
    }

    /**
     * Get payment receipt for order
     *
     * @param int $orderId Order ID
     * @param string $format Receipt format (pdf, html, json)
     * @return array Payment receipt data
     */
    public function getOrderPaymentReceipt(int $orderId, string $format = 'json'): array
    {
        return $this->call('payment.get_order_payment_receipt', [
            'order_id' => $orderId,
            'format' => $format,
        ]);
    }

    /**
     * Update payment status for order
     *
     * @param int $orderId Order ID
     * @param string $status New payment status
     * @param array $statusData Additional status information
     * @return array Status update result
     */
    public function updateOrderPaymentStatus(int $orderId, string $status, array $statusData = []): array
    {
        return $this->call('payment.update_order_payment_status', [
            'order_id' => $orderId,
            'status' => $status,
            'status_data' => $statusData,
        ]);
    }

    /**
     * Get multiple order payment statuses (batch operation)
     *
     * @param array $orderIds Array of order IDs
     * @return array Batch payment status results
     */
    public function getBatchOrderPaymentStatuses(array $orderIds): array
    {
        $calls = [];
        foreach ($orderIds as $orderId) {
            $calls[] = [
                'method' => 'payment.get_order_payment_status',
                'params' => ['order_id' => $orderId],
                'id' => "order_payment_status_{$orderId}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Process multiple order payments (batch operation)
     *
     * @param array $orderPayments Array of order payment data
     * @return array Batch payment processing results
     */
    public function processBatchOrderPayments(array $orderPayments): array
    {
        $calls = [];
        foreach ($orderPayments as $index => $orderPayment) {
            $calls[] = [
                'method' => 'payment.process_order_payment',
                'params' => $orderPayment,
                'id' => "process_order_payment_{$index}",
            ];
        }

        return $this->batchCall($calls);
    }

    /**
     * Get payment statistics for orders
     *
     * @param array $filters Statistics filters
     * @return array Payment statistics
     */
    public function getOrderPaymentStatistics(array $filters = []): array
    {
        return $this->call('payment.get_order_payment_statistics', [
            'filters' => $filters,
        ]);
    }
}

