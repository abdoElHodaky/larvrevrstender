<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;
use Shared\Core\BaseService;
use App\Services\Contracts\TransactionServiceInterface;

class TransactionService extends BaseService
{
    /**
     * Create a new transaction.
     */
    public function createTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            $transaction = Transaction::create($data);

            Log::info('Transaction created', [
                'transaction_id' => $transaction->id,
                'transaction_reference' => $transaction->transaction_reference,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ]);

            return $transaction;
        });
    }

    /**
     * Get transactions with filtering and pagination.
     */
    public function getTransactions(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Transaction::query();

        // Apply filters
        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }

        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }

        if (isset($filters['type'])) {
            $query->byType($filters['type']);
        }

        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        if (isset($filters['reconciled'])) {
            $query->reconciled($filters['reconciled']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->byDateRange(
                new \DateTime($filters['start_date']),
                new \DateTime($filters['end_date'])
            );
        }

        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        if (isset($filters['tags']) && is_array($filters['tags'])) {
            foreach ($filters['tags'] as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Default ordering
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get transaction analytics for a date range.
     */
    public function getTransactionAnalytics(\DateTime $startDate, \DateTime $endDate, array $filters = []): array
    {
        $query = Transaction::byDateRange($startDate, $endDate);

        // Apply additional filters
        if (isset($filters['customer_id'])) {
            $query->byCustomer($filters['customer_id']);
        }

        if (isset($filters['merchant_id'])) {
            $query->byMerchant($filters['merchant_id']);
        }

        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        // Get basic metrics
        $totalTransactions = $query->count();
        $completedTransactions = (clone $query)->completed()->count();
        $failedTransactions = (clone $query)->failed()->count();
        $pendingTransactions = (clone $query)->pending()->count();

        // Revenue metrics
        $totalRevenue = (clone $query)->revenue()->completed()->sum('amount');
        $totalExpenses = (clone $query)->expense()->completed()->sum('amount');
        $netRevenue = $totalRevenue - $totalExpenses;

        // Average transaction value
        $avgTransactionValue = $completedTransactions > 0 
            ? (clone $query)->completed()->avg('amount') 
            : 0;

        // Success rate
        $successRate = $totalTransactions > 0 
            ? ($completedTransactions / $totalTransactions) * 100 
            : 0;

        // Transaction volume by type
        $transactionsByType = (clone $query)->completed()
            ->selectRaw('type, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        // Transaction volume by provider
        $transactionsByProvider = (clone $query)->completed()
            ->selectRaw('payment_provider, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('payment_provider')
            ->get()
            ->keyBy('payment_provider');

        // Daily transaction volume
        $dailyVolume = (clone $query)->completed()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'days' => $startDate->diffInDays($endDate) + 1,
            ],
            'totals' => [
                'transactions' => $totalTransactions,
                'completed' => $completedTransactions,
                'failed' => $failedTransactions,
                'pending' => $pendingTransactions,
                'success_rate' => round($successRate, 2),
            ],
            'financial' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_revenue' => round($netRevenue, 2),
                'avg_transaction_value' => round($avgTransactionValue, 2),
            ],
            'breakdown' => [
                'by_type' => $transactionsByType,
                'by_provider' => $transactionsByProvider,
                'daily_volume' => $dailyVolume,
            ],
        ];
    }

    /**
     * Process refund transaction.
     */
    public function processRefund(Transaction $originalTransaction, float $amount, string $reason): Transaction
    {
        if (!$originalTransaction->canBeRefunded()) {
            throw new \Exception('Transaction cannot be refunded');
        }

        $remainingAmount = $originalTransaction->getRemainingRefundableAmount();
        if ($amount > $remainingAmount) {
            throw new \Exception("Refund amount ({$amount}) exceeds remaining refundable amount ({$remainingAmount})");
        }

        return DB::transaction(function () use ($originalTransaction, $amount, $reason) {
            // Determine refund type
            $refundType = $amount >= $originalTransaction->amount 
                ? Transaction::TYPE_REFUND 
                : Transaction::TYPE_PARTIAL_REFUND;

            // Create refund transaction
            $refundTransaction = $this->createTransaction([
                'parent_transaction_id' => $originalTransaction->id,
                'payment_id' => $originalTransaction->payment_id,
                'customer_id' => $originalTransaction->customer_id,
                'merchant_id' => $originalTransaction->merchant_id,
                'type' => $refundType,
                'status' => Transaction::STATUS_PENDING,
                'amount' => $amount,
                'currency' => $originalTransaction->currency,
                'description' => "Refund: {$reason}",
                'payment_provider' => $originalTransaction->payment_provider,
                'external_reference' => $originalTransaction->external_reference,
                'custom_metadata' => [
                    'refund_reason' => $reason,
                    'original_transaction_id' => $originalTransaction->id,
                    'original_transaction_reference' => $originalTransaction->transaction_reference,
                ],
            ]);

            Log::info('Refund transaction created', [
                'refund_transaction_id' => $refundTransaction->id,
                'original_transaction_id' => $originalTransaction->id,
                'refund_amount' => $amount,
                'refund_type' => $refundType,
                'reason' => $reason,
            ]);

            return $refundTransaction;
        });
    }

    /**
     * Reconcile transactions.
     */
    public function reconcileTransactions(array $transactionIds, string $reconciliationReference): int
    {
        $reconciledCount = 0;

        DB::transaction(function () use ($transactionIds, $reconciliationReference, &$reconciledCount) {
            $transactions = Transaction::whereIn('id', $transactionIds)
                                     ->where('reconciled', false)
                                     ->get();

            foreach ($transactions as $transaction) {
                $transaction->markAsReconciled($reconciliationReference);
                $reconciledCount++;
            }
        });

        Log::info('Transactions reconciled', [
            'reconciliation_reference' => $reconciliationReference,
            'transaction_count' => $reconciledCount,
            'transaction_ids' => $transactionIds,
        ]);

        return $reconciledCount;
    }

    /**
     * Get unreconciled transactions.
     */
    public function getUnreconciledTransactions(array $filters = []): Collection
    {
        $query = Transaction::reconciled(false)->completed();

        // Apply filters
        if (isset($filters['provider'])) {
            $query->byProvider($filters['provider']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->byDateRange(
                new \DateTime($filters['start_date']),
                new \DateTime($filters['end_date'])
            );
        }

        if (isset($filters['amount_min'])) {
            $query->where('amount', '>=', $filters['amount_min']);
        }

        if (isset($filters['amount_max'])) {
            $query->where('amount', '<=', $filters['amount_max']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get transaction summary for a customer.
     */
    public function getCustomerTransactionSummary(int $customerId, \DateTime $startDate = null, \DateTime $endDate = null): array
    {
        $query = Transaction::byCustomer($customerId);

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        $totalTransactions = $query->count();
        $completedTransactions = (clone $query)->completed()->count();
        $totalSpent = (clone $query)->completed()->whereIn('type', [Transaction::TYPE_PAYMENT])->sum('amount');
        $totalRefunded = (clone $query)->completed()->whereIn('type', [Transaction::TYPE_REFUND, Transaction::TYPE_PARTIAL_REFUND])->sum('amount');

        $recentTransactions = (clone $query)->orderBy('created_at', 'desc')->limit(10)->get();

        return [
            'customer_id' => $customerId,
            'period' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
            'summary' => [
                'total_transactions' => $totalTransactions,
                'completed_transactions' => $completedTransactions,
                'total_spent' => round($totalSpent, 2),
                'total_refunded' => round($totalRefunded, 2),
                'net_spent' => round($totalSpent - $totalRefunded, 2),
            ],
            'recent_transactions' => $recentTransactions,
        ];
    }

    /**
     * Get merchant transaction summary.
     */
    public function getMerchantTransactionSummary(int $merchantId, \DateTime $startDate = null, \DateTime $endDate = null): array
    {
        $query = Transaction::byMerchant($merchantId);

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        $totalTransactions = $query->count();
        $completedTransactions = (clone $query)->completed()->count();
        $totalRevenue = (clone $query)->revenue()->completed()->sum('amount');
        $totalFees = (clone $query)->completed()->where('type', Transaction::TYPE_FEE)->sum('amount');
        $totalRefunded = (clone $query)->completed()->whereIn('type', [Transaction::TYPE_REFUND, Transaction::TYPE_PARTIAL_REFUND])->sum('amount');

        $netRevenue = $totalRevenue - $totalFees - $totalRefunded;

        return [
            'merchant_id' => $merchantId,
            'period' => [
                'start_date' => $startDate?->format('Y-m-d'),
                'end_date' => $endDate?->format('Y-m-d'),
            ],
            'summary' => [
                'total_transactions' => $totalTransactions,
                'completed_transactions' => $completedTransactions,
                'total_revenue' => round($totalRevenue, 2),
                'total_fees' => round($totalFees, 2),
                'total_refunded' => round($totalRefunded, 2),
                'net_revenue' => round($netRevenue, 2),
            ],
        ];
    }

    /**
     * Bulk update transaction tags.
     */
    public function bulkUpdateTags(array $transactionIds, array $tagsToAdd = [], array $tagsToRemove = []): int
    {
        $updatedCount = 0;

        DB::transaction(function () use ($transactionIds, $tagsToAdd, $tagsToRemove, &$updatedCount) {
            $transactions = Transaction::whereIn('id', $transactionIds)->get();

            foreach ($transactions as $transaction) {
                foreach ($tagsToAdd as $tag) {
                    $transaction->addTag($tag);
                }

                foreach ($tagsToRemove as $tag) {
                    $transaction->removeTag($tag);
                }

                $updatedCount++;
            }
        });

        Log::info('Bulk tag update completed', [
            'transaction_count' => $updatedCount,
            'tags_added' => $tagsToAdd,
            'tags_removed' => $tagsToRemove,
        ]);

        return $updatedCount;
    }

    /**
     * Get transaction trends.
     */
    public function getTransactionTrends(\DateTime $startDate, \DateTime $endDate, string $interval = 'day'): array
    {
        $dateFormat = match ($interval) {
            'hour' => '%Y-%m-%d %H:00:00',
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $trends = Transaction::byDateRange($startDate, $endDate)
            ->completed()
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as transaction_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('AVG(amount) as avg_amount')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'interval' => $interval,
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'trends' => $trends,
        ];
    }
}
