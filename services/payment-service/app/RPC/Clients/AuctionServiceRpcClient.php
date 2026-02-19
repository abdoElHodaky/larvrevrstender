<?php

namespace App\RPC\Clients;

use Shared\Clients\BaseRpcClient;

/**
 * RPC Client for Auction Service (from Payment Service)
 * 
 * Provides RPC-based communication with the auction service for
 * payment-related auction operations and status updates.
 */
class AuctionServiceRpcClient extends BaseRpcClient
{
    public function __construct()
    {
        parent::__construct('auction-service', [
            'timeout' => 30,
            'retries' => 3,
            'circuit_breaker' => true,
            'trace_requests' => true,
        ]);
    }
    
    /**
     * Get auction details for payment processing
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with auction data
     */
    public function getAuctionForPayment(int $auctionId): array
    {
        return $this->call('auction.getForPayment', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Notify auction of payment completion
     *
     * @param int $auctionId Auction ID
     * @param array $paymentData Payment completion data
     * @return array RPC response
     */
    public function notifyPaymentCompleted(int $auctionId, array $paymentData): array
    {
        return $this->call('auction.notifyPaymentCompleted', [
            'auction_id' => $auctionId,
            'payment_data' => $paymentData,
        ]);
    }
    
    /**
     * Notify auction of payment failure
     *
     * @param int $auctionId Auction ID
     * @param array $failureData Payment failure data
     * @return array RPC response
     */
    public function notifyPaymentFailed(int $auctionId, array $failureData): array
    {
        return $this->call('auction.notifyPaymentFailed', [
            'auction_id' => $auctionId,
            'failure_data' => $failureData,
        ]);
    }
    
    /**
     * Update auction payment status
     *
     * @param int $auctionId Auction ID
     * @param string $paymentStatus Payment status
     * @param array $metadata Additional metadata
     * @return array RPC response
     */
    public function updatePaymentStatus(int $auctionId, string $paymentStatus, array $metadata = []): array
    {
        return $this->call('auction.updatePaymentStatus', [
            'auction_id' => $auctionId,
            'payment_status' => $paymentStatus,
            'metadata' => $metadata,
        ]);
    }
    
    /**
     * Get auction winner information
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with winner data
     */
    public function getAuctionWinner(int $auctionId): array
    {
        return $this->call('auction.getWinner', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Validate auction for payment processing
     *
     * @param int $auctionId Auction ID
     * @param int $userId User ID
     * @return array RPC response with validation result
     */
    public function validateAuctionForPayment(int $auctionId, int $userId): array
    {
        return $this->call('auction.validateForPayment', [
            'auction_id' => $auctionId,
            'user_id' => $userId,
        ]);
    }
    
    /**
     * Get auction payment requirements
     *
     * @param int $auctionId Auction ID
     * @return array RPC response with payment requirements
     */
    public function getPaymentRequirements(int $auctionId): array
    {
        return $this->call('auction.getPaymentRequirements', [
            'auction_id' => $auctionId,
        ]);
    }
    
    /**
     * Request auction closure after payment
     *
     * @param int $auctionId Auction ID
     * @param array $closureData Closure data
     * @return array RPC response
     */
    public function requestAuctionClosure(int $auctionId, array $closureData): array
    {
        return $this->call('auction.requestClosure', [
            'auction_id' => $auctionId,
            'closure_data' => $closureData,
        ]);
    }
}

