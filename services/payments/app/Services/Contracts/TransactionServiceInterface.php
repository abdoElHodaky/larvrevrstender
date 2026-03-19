<?php

namespace App\Services\Contracts;

/**
 * Transaction Service Contract
 * 
 * Defines the interface for transaction management services
 */
interface TransactionServiceInterface
{
    /**
     * Create transaction record
     */
    public function createTransaction(array $transactionData): array;

    /**
     * Update transaction status
     */
    public function updateTransactionStatus(string $transactionId, string $status, array $metadata = []): array;

    /**
     * Get transaction details
     */
    public function getTransaction(string $transactionId): array;

    /**
     * Get user transactions
     */
    public function getUserTransactions(int $userId, array $filters = []): array;

    /**
     * Get merchant transactions
     */
    public function getMerchantTransactions(int $merchantId, array $filters = []): array;

    /**
     * Calculate transaction fees
     */
    public function calculateTransactionFees(float $amount, string $transactionType): array;

    /**
     * Reverse transaction
     */
    public function reverseTransaction(string $transactionId, string $reason = null): array;

    /**
     * Get transaction statistics
     */
    public function getTransactionStatistics(array $filters = []): array;

    /**
     * Export transactions
     */
    public function exportTransactions(array $filters = [], string $format = 'csv'): array;

    /**
     * Validate transaction data
     */
    public function validateTransactionData(array $transactionData): array;

    /**
     * Get pending transactions
     */
    public function getPendingTransactions(array $filters = []): array;

    /**
     * Reconcile transactions
     */
    public function reconcileTransactions(array $reconciliationData): array;
}
