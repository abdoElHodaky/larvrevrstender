<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Payment Service
 * 
 * Provides RPC-based communication with the payment service for
 * payment processing, transaction management, and financial operations.
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
     * Process payment for auction
     *
     * @param array $paymentData Payment data (auction_id, user_id, amount, method, etc.)
     * @return array RPC response with payment result
     */
    public function processPayment(array $paymentData): array
    {
        return $this->call('payment.process', $paymentData);
    }
    
    /**
     * Get payment details by ID
     *
     * @param int $paymentId Payment ID
     * @return array RPC response with payment details
     */
    public function getPayment(int $paymentId): array
    {
        return $this->call('payment.get', [
            'payment_id' => $paymentId,
        ]);
    }
    
    /**
     * Get payment status
     *
     * @param int $paymentId Payment ID
     * @return array RPC response with payment status
     */
    public function getPaymentStatus(int $paymentId): array
    {
        return $this->call('payment.getStatus', [
            'payment_id' => $paymentId,
        ]);
    }
    
    /**
     * Refund payment
     *
     * @param int $paymentId Payment ID
     * @param float|null $amount Refund amount (null for full refund)
     * @param string|null $reason Refund reason
     * @return array RPC response with refund result
     */
    public function refundPayment(int $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        $params = ['payment_id' => $paymentId];
        
        if ($amount !== null) {
            $params['amount'] = $amount;
        }
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('payment.refund', $params);
    }
    
    /**
     * Get user's payment history
     *
     * @param int $userId User ID
     * @param array $filters Optional filters
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array RPC response with payment history
     */
    public function getUserPayments(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        return $this->call('payment.getUserPayments', [
            'user_id' => $userId,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
    
    /**
     * Get auction payments
     *
     * @param int $auctionId Auction ID
     * @param array $filters Optional filters
     * @return array RPC response with auction payments
     */
    public function getAuctionPayments(int $auctionId, array $filters = []): array
    {
        return $this->call('payment.getAuctionPayments', [
            'auction_id' => $auctionId,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Validate payment method
     *
     * @param array $paymentMethodData Payment method data
     * @return array RPC response with validation result
     */
    public function validatePaymentMethod(array $paymentMethodData): array
    {
        return $this->call('payment.validateMethod', $paymentMethodData);
    }
    
    /**
     * Create payment intent
     *
     * @param array $intentData Payment intent data
     * @return array RPC response with payment intent
     */
    public function createPaymentIntent(array $intentData): array
    {
        return $this->call('payment.createIntent', $intentData);
    }
    
    /**
     * Confirm payment intent
     *
     * @param string $intentId Payment intent ID
     * @param array $confirmationData Confirmation data
     * @return array RPC response with confirmation result
     */
    public function confirmPaymentIntent(string $intentId, array $confirmationData = []): array
    {
        return $this->call('payment.confirmIntent', [
            'intent_id' => $intentId,
            'confirmation_data' => $confirmationData,
        ]);
    }
    
    /**
     * Cancel payment intent
     *
     * @param string $intentId Payment intent ID
     * @param string|null $reason Cancellation reason
     * @return array RPC response
     */
    public function cancelPaymentIntent(string $intentId, ?string $reason = null): array
    {
        $params = ['intent_id' => $intentId];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('payment.cancelIntent', $params);
    }
    
    /**
     * Get payment methods for user
     *
     * @param int $userId User ID
     * @return array RPC response with payment methods
     */
    public function getUserPaymentMethods(int $userId): array
    {
        return $this->call('payment.getUserMethods', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Add payment method for user
     *
     * @param int $userId User ID
     * @param array $methodData Payment method data
     * @return array RPC response
     */
    public function addPaymentMethod(int $userId, array $methodData): array
    {
        return $this->call('payment.addMethod', [
            'user_id' => $userId,
            'method_data' => $methodData,
        ]);
    }
    
    /**
     * Remove payment method
     *
     * @param int $methodId Payment method ID
     * @return array RPC response
     */
    public function removePaymentMethod(int $methodId): array
    {
        return $this->call('payment.removeMethod', [
            'method_id' => $methodId,
        ]);
    }
    
    /**
     * Get payment statistics
     *
     * @param array $filters Optional filters (date range, user_id, etc.)
     * @return array RPC response with payment statistics
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        return $this->call('payment.getStatistics', [
            'filters' => $filters,
        ]);
    }
    
    /**
     * Process escrow payment
     *
     * @param array $escrowData Escrow payment data
     * @return array RPC response with escrow result
     */
    public function processEscrowPayment(array $escrowData): array
    {
        return $this->call('payment.processEscrow', $escrowData);
    }
    
    /**
     * Release escrow payment
     *
     * @param int $escrowId Escrow ID
     * @param array $releaseData Release data
     * @return array RPC response
     */
    public function releaseEscrowPayment(int $escrowId, array $releaseData = []): array
    {
        return $this->call('payment.releaseEscrow', [
            'escrow_id' => $escrowId,
            'release_data' => $releaseData,
        ]);
    }
    
    /**
     * Batch operation: Get multiple payment statuses
     *
     * @param array $paymentIds Array of payment IDs
     * @return array Array of RPC responses
     */
    public function getMultiplePaymentStatuses(array $paymentIds): array
    {
        $calls = [];
        foreach ($paymentIds as $paymentId) {
            $calls[] = [
                'method' => 'payment.getStatus',
                'params' => ['payment_id' => $paymentId],
            ];
        }
        
        return $this->batchCall($calls);
    }
}

