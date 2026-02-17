<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Payment Service
 * 
 * Provides RPC-based communication with the payment service for
 * fund reservations, payments, refunds, and financial operations.
 */
class PaymentServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('payment-service', [
            'timeout' => 45, // Payment operations may take longer
            'retries' => 2,  // Fewer retries for financial operations
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Reserve funds for a bid
     *
     * @param array $reservationData Reservation data (user_id, amount, auction_id, etc.)
     * @return array RPC response with reservation details
     */
    public function reserveFunds(array $reservationData): array
    {
        return $this->call('payment.reserveFunds', $reservationData);
    }
    
    /**
     * Release reserved funds
     *
     * @param string $reservationId Reservation ID
     * @param string|null $reason Optional reason for release
     * @return array RPC response
     */
    public function releaseFunds(string $reservationId, ?string $reason = null): array
    {
        $params = ['reservation_id' => $reservationId];
        
        if ($reason) {
            $params['reason'] = $reason;
        }
        
        return $this->call('payment.releaseFunds', $params);
    }
    
    /**
     * Capture reserved funds (convert reservation to payment)
     *
     * @param string $reservationId Reservation ID
     * @param array $captureData Capture data (final_amount, fees, etc.)
     * @return array RPC response with payment details
     */
    public function captureFunds(string $reservationId, array $captureData = []): array
    {
        return $this->call('payment.captureFunds', [
            'reservation_id' => $reservationId,
            'capture_data' => $captureData,
        ]);
    }
    
    /**
     * Process payment for auction winner
     *
     * @param array $paymentData Payment data (user_id, amount, auction_id, etc.)
     * @return array RPC response with payment result
     */
    public function processPayment(array $paymentData): array
    {
        return $this->call('payment.processPayment', $paymentData);
    }
    
    /**
     * Issue refund
     *
     * @param array $refundData Refund data (payment_id, amount, reason, etc.)
     * @return array RPC response with refund details
     */
    public function issueRefund(array $refundData): array
    {
        return $this->call('payment.issueRefund', $refundData);
    }
    
    /**
     * Get payment status
     *
     * @param string $paymentId Payment ID
     * @return array RPC response with payment status
     */
    public function getPaymentStatus(string $paymentId): array
    {
        return $this->call('payment.getStatus', [
            'payment_id' => $paymentId,
        ]);
    }
    
    /**
     * Get reservation status
     *
     * @param string $reservationId Reservation ID
     * @return array RPC response with reservation status
     */
    public function getReservationStatus(string $reservationId): array
    {
        return $this->call('payment.getReservationStatus', [
            'reservation_id' => $reservationId,
        ]);
    }
    
    /**
     * Get user's payment methods
     *
     * @param int $userId User ID
     * @return array RPC response with payment methods
     */
    public function getUserPaymentMethods(int $userId): array
    {
        return $this->call('payment.getUserPaymentMethods', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Validate payment method
     *
     * @param int $userId User ID
     * @param string $paymentMethodId Payment method ID
     * @return array RPC response with validation result
     */
    public function validatePaymentMethod(int $userId, string $paymentMethodId): array
    {
        return $this->call('payment.validatePaymentMethod', [
            'user_id' => $userId,
            'payment_method_id' => $paymentMethodId,
        ]);
    }
    
    /**
     * Check user's available balance
     *
     * @param int $userId User ID
     * @return array RPC response with balance information
     */
    public function getUserBalance(int $userId): array
    {
        return $this->call('payment.getUserBalance', [
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get transaction history for user
     *
     * @param int $userId User ID
     * @param int $limit Number of records to return
     * @param int $offset Offset for pagination
     * @param array $filters Optional filters (type, status, date_range, etc.)
     * @return array RPC response with transaction history
     */
    public function getUserTransactionHistory(
        int $userId, 
        int $limit = 50, 
        int $offset = 0,
        array $filters = []
    ): array {
        return $this->call('payment.getUserTransactionHistory', [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset,
            'filters' => $filters,
        ]);
    }
    
    /**
     * Get auction-related payments
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction payments
     */
    public function getAuctionPayments(int $auctionId): array
    {
        return $this->call('payment.getAuctionPayments', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Calculate fees for payment
     *
     * @param float $amount Payment amount
     * @param string $paymentType Payment type (bid_deposit, final_payment, etc.)
     * @param array $context Additional context for fee calculation
     * @return array RPC response with fee breakdown
     */
    public function calculateFees(float $amount, string $paymentType, array $context = []): array
    {
        return $this->call('payment.calculateFees', [
            'amount' => $amount,
            'payment_type' => $paymentType,
            'context' => $context,
        ]);
    }
    
    /**
     * Verify payment webhook
     *
     * @param array $webhookData Webhook payload data
     * @param string $signature Webhook signature
     * @return array RPC response with verification result
     */
    public function verifyWebhook(array $webhookData, string $signature): array
    {
        return $this->call('payment.verifyWebhook', [
            'webhook_data' => $webhookData,
            'signature' => $signature,
        ]);
    }
    
    /**
     * Get payment analytics for auction
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with payment analytics
     */
    public function getAuctionPaymentAnalytics(int $auctionId): array
    {
        return $this->call('payment.getAuctionAnalytics', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Batch operation: Reserve funds for multiple bids
     *
     * @param array $reservations Array of reservation data
     * @return array Array of RPC responses
     */
    public function batchReserveFunds(array $reservations): array
    {
        $calls = [];
        foreach ($reservations as $reservation) {
            $calls[] = [
                'method' => 'payment.reserveFunds',
                'params' => $reservation,
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Release multiple fund reservations
     *
     * @param array $reservationIds Array of reservation IDs
     * @param string|null $reason Optional reason for release
     * @return array Array of RPC responses
     */
    public function batchReleaseFunds(array $reservationIds, ?string $reason = null): array
    {
        $calls = [];
        foreach ($reservationIds as $reservationId) {
            $params = ['reservation_id' => $reservationId];
            if ($reason) {
                $params['reason'] = $reason;
            }
            
            $calls[] = [
                'method' => 'payment.releaseFunds',
                'params' => $params,
            ];
        }
        
        return $this->batchCall($calls);
    }
    
    /**
     * Batch operation: Get status of multiple payments
     *
     * @param array $paymentIds Array of payment IDs
     * @return array Array of RPC responses
     */
    public function batchGetPaymentStatus(array $paymentIds): array
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

