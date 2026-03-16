<?php

namespace App\Services\Contracts;

/**
 * Escrow Service Contract
 * 
 * Defines the interface for escrow management services
 */
interface EscrowServiceInterface
{
    /**
     * Create escrow account
     */
    public function createEscrowAccount(array $escrowData): array;

    /**
     * Deposit funds to escrow
     */
    public function depositToEscrow(string $escrowId, float $amount, array $paymentData): array;

    /**
     * Release escrow funds
     */
    public function releaseEscrowFunds(string $escrowId, string $recipientId, float $amount = null): array;

    /**
     * Refund escrow funds
     */
    public function refundEscrowFunds(string $escrowId, string $reason = null): array;

    /**
     * Get escrow status
     */
    public function getEscrowStatus(string $escrowId): array;

    /**
     * Get escrow balance
     */
    public function getEscrowBalance(string $escrowId): array;

    /**
     * Get escrow transaction history
     */
    public function getEscrowTransactionHistory(string $escrowId): array;

    /**
     * Update escrow terms
     */
    public function updateEscrowTerms(string $escrowId, array $terms): array;

    /**
     * Cancel escrow
     */
    public function cancelEscrow(string $escrowId, string $reason = null): array;

    /**
     * Dispute escrow
     */
    public function disputeEscrow(string $escrowId, string $reason, array $evidence = []): array;

    /**
     * Resolve escrow dispute
     */
    public function resolveEscrowDispute(string $escrowId, string $resolution, array $resolutionData): array;
}
