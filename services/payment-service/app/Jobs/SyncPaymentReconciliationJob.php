<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Payment;
use App\Models\PaymentSettlement;
use App\Models\PaymentRefund;
use App\Models\PaymentChargeback;
use App\Models\PaymentFee;
use App\Models\PaymentReconciliationSummary;

/**
 * Payment Reconciliation Sync Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Synchronizes payment records with external payment gateways and reconciles
 * discrepancies. Critical for financial accuracy, compliance, and preventing
 * revenue leakage across all payment channels.
 */
class SyncPaymentReconciliationJob extends BaseQueueJob
{
    public array $paymentGateways;
    public Carbon $reconciliationDate;
    public array $reconciliationTypes;
    public array $reconciliationOptions;
    public int $tries = 3;
    public int $timeout = 2400; // 40 minutes for reconciliation

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $paymentGateways,
        Carbon $reconciliationDate,
        array $reconciliationTypes = [],
        array $reconciliationOptions = []
    ) {
        parent::__construct();
        
        $this->paymentGateways = $paymentGateways ?: $this->getDefaultPaymentGateways();
        $this->reconciliationDate = $reconciliationDate;
        $this->reconciliationTypes = $reconciliationTypes ?: $this->getDefaultReconciliationTypes();
        $this->reconciliationOptions = array_merge($this->getDefaultReconciliationOptions(), $reconciliationOptions);
        
        // Set queue for financial operations
        $this->onQueue('payment-reconciliation');
        
        // Configure circuit breaker for payment reconciliation
        $this->configureCircuitBreaker([
            'service_name' => 'payment_reconciliation',
            'failure_threshold' => 25, // 25% failure rate triggers circuit breaker (strict for financial data)
            'timeout' => 900, // 15 minutes timeout for reconciliation operations
            'recovery_timeout' => 1200, // 20 minutes before attempting recovery
            'tags' => [
                'service' => 'payment-service',
                'job_type' => 'financial',
                'operation' => 'reconciliation',
                'priority' => 'high'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting payment reconciliation with circuit breaker protection', [
            'payment_gateways' => $this->paymentGateways,
            'reconciliation_date' => $this->reconciliationDate->toDateString(),
            'reconciliation_types' => $this->reconciliationTypes,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'payment_reconciliation'
        ]);

        $this->executeWithCircuitBreaker(function() {
            $results = [
                'gateways_processed' => 0,
                'transactions_reconciled' => 0,
                'discrepancies_found' => 0,
                'discrepancies_resolved' => 0,
                'total_amount_reconciled' => 0,
                'processing_time_ms' => 0,
                'errors' => []
            ];

            $startTime = microtime(true);

            foreach ($this->paymentGateways as $gateway) {
                try {
                    $gatewayResult = $this->reconcilePaymentGateway($gateway);
                    
                    $results['gateways_processed']++;
                    $results['transactions_reconciled'] += $gatewayResult['transactions_reconciled'];
                    $results['discrepancies_found'] += $gatewayResult['discrepancies_found'];
                    $results['discrepancies_resolved'] += $gatewayResult['discrepancies_resolved'];
                    $results['total_amount_reconciled'] += $gatewayResult['amount_reconciled'];
                    
                    Log::debug('Gateway reconciliation completed', [
                        'gateway' => $gateway,
                        'transactions_reconciled' => $gatewayResult['transactions_reconciled'],
                        'discrepancies_found' => $gatewayResult['discrepancies_found'],
                        'amount_reconciled' => $gatewayResult['amount_reconciled']
                    ]);
                    
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'gateway' => $gateway,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to reconcile payment gateway', [
                        'gateway' => $gateway,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $results['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);

            Log::info('Payment reconciliation completed successfully', [
                'gateways_processed' => $results['gateways_processed'],
                'total_transactions_reconciled' => $results['transactions_reconciled'],
                'total_discrepancies_found' => $results['discrepancies_found'],
                'total_amount_reconciled' => $results['total_amount_reconciled'],
                'processing_time_ms' => $results['processing_time_ms'],
                'job_id' => $this->job?->getJobId()
            ]);

            // Store reconciliation summary
            $this->storeReconciliationSummary($results);

            return $results;
        }, function(\Exception $e) {
            Log::error('Payment reconciliation failed with circuit breaker protection', [
                'payment_gateways' => $this->paymentGateways,
                'reconciliation_date' => $this->reconciliationDate->toDateString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Reconcile payments for a specific gateway
     */
    private function reconcilePaymentGateway(string $gateway): array
    {
        $transactionsReconciled = 0;
        $discrepanciesFound = 0;
        $discrepanciesResolved = 0;
        $amountReconciled = 0;

        Log::debug('Starting gateway reconciliation', [
            'gateway' => $gateway,
            'reconciliation_date' => $this->reconciliationDate->toDateString(),
            'reconciliation_types' => $this->reconciliationTypes
        ]);

        foreach ($this->reconciliationTypes as $reconciliationType) {
            $result = $this->performReconciliationType($gateway, $reconciliationType);
            
            $transactionsReconciled += $result['transactions_reconciled'];
            $discrepanciesFound += $result['discrepancies_found'];
            $discrepanciesResolved += $result['discrepancies_resolved'];
            $amountReconciled += $result['amount_reconciled'];
        }

        return [
            'transactions_reconciled' => $transactionsReconciled,
            'discrepancies_found' => $discrepanciesFound,
            'discrepancies_resolved' => $discrepanciesResolved,
            'amount_reconciled' => $amountReconciled
        ];
    }

    /**
     * Perform specific reconciliation type
     */
    private function performReconciliationType(string $gateway, string $reconciliationType): array
    {
        return match ($reconciliationType) {
            'transaction_status' => $this->reconcileTransactionStatus($gateway),
            'settlement_amounts' => $this->reconcileSettlementAmounts($gateway),
            'refund_status' => $this->reconcileRefundStatus($gateway),
            'chargeback_status' => $this->reconcileChargebackStatus($gateway),
            'fee_reconciliation' => $this->reconcileFees($gateway),
            default => throw new \InvalidArgumentException("Unknown reconciliation type: {$reconciliationType}")
        };
    }

    /**
     * Reconcile transaction status with gateway
     */
    private function reconcileTransactionStatus(string $gateway): array
    {
        $transactionsReconciled = 0;
        $discrepanciesFound = 0;
        $discrepanciesResolved = 0;
        $amountReconciled = 0;

        // Get transactions for the reconciliation date
        if (!DB::getSchemaBuilder()->hasTable('payments')) {
            Log::warning('payments table does not exist, skipping transaction reconciliation', [
                'gateway' => $gateway,
                'date' => $this->reconciliationDate->toDateString(),
            ]);
            return ['transactions_reconciled' => 0, 'discrepancies_found' => 0, 'discrepancies_resolved' => 0, 'amount_reconciled' => 0];
        }

        $transactions = Payment::where('gateway', $gateway)
            ->whereDate('created_at', $this->reconciliationDate)
            ->where('status', '!=', 'reconciled')
            ->get();

        foreach ($transactions as $transaction) {
            try {
                // Fetch transaction status from gateway
                $gatewayStatus = $this->fetchGatewayTransactionStatus($gateway, $transaction->gateway_transaction_id);
                
                if ($gatewayStatus) {
                    $localStatus = $transaction->status;
                    $remoteStatus = $gatewayStatus['status'];
                    
                    if ($localStatus !== $remoteStatus) {
                        $discrepanciesFound++;
                        
                        // Resolve discrepancy by updating local status
                        if ($this->resolveStatusDiscrepancy($transaction, $gatewayStatus)) {
                            $discrepanciesResolved++;
                        }
                        
                        Log::warning('Transaction status discrepancy found', [
                            'transaction_id' => $transaction->id,
                            'gateway_transaction_id' => $transaction->gateway_transaction_id,
                            'local_status' => $localStatus,
                            'remote_status' => $remoteStatus,
                            'resolved' => $discrepanciesResolved > 0
                        ]);
                    }
                    
                    $transactionsReconciled++;
                    $amountReconciled += $transaction->amount;
                }

            } catch (\Exception $e) {
                Log::error('Failed to reconcile transaction status', [
                    'transaction_id' => $transaction->id,
                    'gateway' => $gateway,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'transactions_reconciled' => $transactionsReconciled,
            'discrepancies_found' => $discrepanciesFound,
            'discrepancies_resolved' => $discrepanciesResolved,
            'amount_reconciled' => $amountReconciled
        ];
    }

    /**
     * Reconcile settlement amounts with gateway
     */
    private function reconcileSettlementAmounts(string $gateway): array
    {
        $transactionsReconciled = 0;
        $discrepanciesFound = 0;
        $discrepanciesResolved = 0;
        $amountReconciled = 0;

        // Get settled transactions for reconciliation
        if (!DB::getSchemaBuilder()->hasTable('payment_settlements')) {
            Log::warning('payment_settlements table does not exist, skipping settlement reconciliation', [
                'gateway' => $gateway,
                'date' => $this->reconciliationDate->toDateString(),
            ]);
            return ['transactions_reconciled' => 0, 'discrepancies_found' => 0, 'discrepancies_resolved' => 0, 'amount_reconciled' => 0];
        }

        $settlements = PaymentSettlement::forGateway($gateway)
            ->forDate($this->reconciliationDate)
            ->unreconciled()
            ->get();

        foreach ($settlements as $settlement) {
            try {
                // Fetch settlement data from gateway
                $gatewaySettlement = $this->fetchGatewaySettlement($gateway, $settlement->settlement_id);
                
                if ($gatewaySettlement) {
                    $localAmount = $settlement->net_amount;
                    $remoteAmount = $gatewaySettlement['net_amount'];
                    
                    if (abs($localAmount - $remoteAmount) > 0.01) { // Allow for minor rounding differences
                        $discrepanciesFound++;
                        
                        // Resolve amount discrepancy
                        if ($this->resolveAmountDiscrepancy($settlement, $gatewaySettlement)) {
                            $discrepanciesResolved++;
                        }
                        
                        Log::warning('Settlement amount discrepancy found', [
                            'settlement_id' => $settlement->id,
                            'gateway_settlement_id' => $settlement->settlement_id,
                            'local_amount' => $localAmount,
                            'remote_amount' => $remoteAmount,
                            'difference' => $remoteAmount - $localAmount
                        ]);
                    }
                    
                    $transactionsReconciled++;
                    $amountReconciled += $settlement->gross_amount;
                }

            } catch (\Exception $e) {
                Log::error('Failed to reconcile settlement amount', [
                    'settlement_id' => $settlement->id,
                    'gateway' => $gateway,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'transactions_reconciled' => $transactionsReconciled,
            'discrepancies_found' => $discrepanciesFound,
            'discrepancies_resolved' => $discrepanciesResolved,
            'amount_reconciled' => $amountReconciled
        ];
    }

    /**
     * Reconcile refund status with gateway
     */
    private function reconcileRefundStatus(string $gateway): array
    {
        $transactionsReconciled = 0;
        $discrepanciesFound = 0;
        $discrepanciesResolved = 0;
        $amountReconciled = 0;

        // Get refunds for reconciliation
        if (!DB::getSchemaBuilder()->hasTable('payment_refunds')) {
            Log::warning('payment_refunds table does not exist, skipping refund reconciliation', [
                'gateway' => $gateway,
                'date' => $this->reconciliationDate->toDateString(),
            ]);
            return ['transactions_reconciled' => 0, 'discrepancies_found' => 0, 'discrepancies_resolved' => 0, 'amount_reconciled' => 0];
        }

        $refunds = PaymentRefund::forGateway($gateway)
            ->forDate($this->reconciliationDate)
            ->unreconciled()
            ->get();

        foreach ($refunds as $refund) {
            try {
                // Fetch refund status from gateway
                $gatewayRefund = $this->fetchGatewayRefund($gateway, $refund->gateway_refund_id);
                
                if ($gatewayRefund) {
                    $localStatus = $refund->status;
                    $remoteStatus = $gatewayRefund['status'];
                    
                    if ($localStatus !== $remoteStatus) {
                        $discrepanciesFound++;
                        
                        // Resolve refund status discrepancy
                        if ($this->resolveRefundDiscrepancy($refund, $gatewayRefund)) {
                            $discrepanciesResolved++;
                        }
                    }
                    
                    $transactionsReconciled++;
                    $amountReconciled += $refund->amount;
                }

            } catch (\Exception $e) {
                Log::error('Failed to reconcile refund status', [
                    'refund_id' => $refund->id,
                    'gateway' => $gateway,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'transactions_reconciled' => $transactionsReconciled,
            'discrepancies_found' => $discrepanciesFound,
            'discrepancies_resolved' => $discrepanciesResolved,
            'amount_reconciled' => $amountReconciled
        ];
    }

    /**
     * Reconcile chargeback status with gateway
     */
    private function reconcileChargebackStatus(string $gateway): array
    {
        $transactionsReconciled = 0;
        $discrepanciesFound = 0;
        $discrepanciesResolved = 0;
        $amountReconciled = 0;

        // Get chargebacks for reconciliation
        if (!DB::getSchemaBuilder()->hasTable('payment_chargebacks')) {
            Log::warning('payment_chargebacks table does not exist, skipping chargeback reconciliation', [
                'gateway' => $gateway,
                'date' => $this->reconciliationDate->toDateString(),
            ]);
            return ['transactions_reconciled' => 0, 'discrepancies_found' => 0, 'discrepancies_resolved' => 0, 'amount_reconciled' => 0];
        }

        $chargebacks = PaymentChargeback::forGateway($gateway)
            ->forDate($this->reconciliationDate)
            ->unreconciled()
            ->get();

        foreach ($chargebacks as $chargeback) {
            try {
                // Fetch chargeback status from gateway
                $gatewayChargeback = $this->fetchGatewayChargeback($gateway, $chargeback->gateway_chargeback_id);
                
                if ($gatewayChargeback) {
                    $localStatus = $chargeback->status;
                    $remoteStatus = $gatewayChargeback['status'];
                    
                    if ($localStatus !== $remoteStatus) {
                        $discrepanciesFound++;
                        
                        // Resolve chargeback status discrepancy
                        if ($this->resolveChargebackDiscrepancy($chargeback, $gatewayChargeback)) {
                            $discrepanciesResolved++;
                        }
                    }
                    
                    $transactionsReconciled++;
                    $amountReconciled += $chargeback->amount;
                }

            } catch (\Exception $e) {
                Log::error('Failed to reconcile chargeback status', [
                    'chargeback_id' => $chargeback->id,
                    'gateway' => $gateway,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'transactions_reconciled' => $transactionsReconciled,
            'discrepancies_found' => $discrepanciesFound,
            'discrepancies_resolved' => $discrepanciesResolved,
            'amount_reconciled' => $amountReconciled
        ];
    }

    /**
     * Reconcile fees with gateway
     */
    private function reconcileFees(string $gateway): array
    {
        $transactionsReconciled = 0;
        $discrepanciesFound = 0;
        $discrepanciesResolved = 0;
        $amountReconciled = 0;

        // Get fee records for reconciliation
        if (!DB::getSchemaBuilder()->hasTable('payment_fees')) {
            Log::warning('payment_fees table does not exist, skipping fee reconciliation', [
                'gateway' => $gateway,
                'date' => $this->reconciliationDate->toDateString(),
            ]);
            return ['transactions_reconciled' => 0, 'discrepancies_found' => 0, 'discrepancies_resolved' => 0, 'amount_reconciled' => 0];
        }

        $fees = PaymentFee::forGateway($gateway)
            ->forDate($this->reconciliationDate)
            ->unreconciled()
            ->get();

        foreach ($fees as $fee) {
            try {
                // Fetch fee data from gateway
                $gatewayFee = $this->fetchGatewayFee($gateway, $fee->transaction_id);
                
                if ($gatewayFee) {
                    $localFeeAmount = $fee->fee_amount;
                    $remoteFeeAmount = $gatewayFee['fee_amount'];
                    
                    if (abs($localFeeAmount - $remoteFeeAmount) > 0.01) {
                        $discrepanciesFound++;
                        
                        // Resolve fee discrepancy
                        if ($this->resolveFeeDiscrepancy($fee, $gatewayFee)) {
                            $discrepanciesResolved++;
                        }
                    }
                    
                    $transactionsReconciled++;
                    $amountReconciled += $fee->transaction_amount;
                }

            } catch (\Exception $e) {
                Log::error('Failed to reconcile fee', [
                    'fee_id' => $fee->id,
                    'gateway' => $gateway,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'transactions_reconciled' => $transactionsReconciled,
            'discrepancies_found' => $discrepanciesFound,
            'discrepancies_resolved' => $discrepanciesResolved,
            'amount_reconciled' => $amountReconciled
        ];
    }

    /**
     * Gateway API methods (simplified implementations)
     */
    private function fetchGatewayTransactionStatus(string $gateway, string $transactionId): ?array
    {
        // This would make actual API calls to payment gateways
        // For now, return mock data
        return [
            'status' => 'completed',
            'amount' => 100.00,
            'currency' => 'USD',
            'updated_at' => now()->toISOString()
        ];
    }

    private function fetchGatewaySettlement(string $gateway, string $settlementId): ?array
    {
        return [
            'net_amount' => 95.00,
            'gross_amount' => 100.00,
            'fee_amount' => 5.00,
            'settlement_date' => $this->reconciliationDate->toDateString()
        ];
    }

    private function fetchGatewayRefund(string $gateway, string $refundId): ?array
    {
        return [
            'status' => 'completed',
            'amount' => 50.00,
            'processed_at' => now()->toISOString()
        ];
    }

    private function fetchGatewayChargeback(string $gateway, string $chargebackId): ?array
    {
        return [
            'status' => 'pending',
            'amount' => 100.00,
            'reason_code' => 'fraud',
            'created_at' => now()->toISOString()
        ];
    }

    private function fetchGatewayFee(string $gateway, string $transactionId): ?array
    {
        return [
            'fee_amount' => 2.90,
            'fee_type' => 'processing',
            'transaction_amount' => 100.00
        ];
    }

    /**
     * Discrepancy resolution methods
     */
    private function resolveStatusDiscrepancy($transaction, array $gatewayData): bool
    {
        try {
            Payment::where('id', $transaction->id)
                ->update([
                    'status' => $gatewayData['status'],
                    'reconciled_at' => now(),
                    'reconciliation_notes' => 'Status updated from gateway reconciliation'
                ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to resolve status discrepancy', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    private function resolveAmountDiscrepancy($settlement, array $gatewayData): bool
    {
        try {
            PaymentSettlement::where('id', $settlement->id)
                ->update([
                    'net_amount' => $gatewayData['net_amount'],
                    'reconciliation_status' => 'reconciled',
                    'reconciled_at' => now(),
                    'reconciliation_notes' => 'Amount updated from gateway reconciliation'
                ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to resolve amount discrepancy', [
                'settlement_id' => $settlement->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    private function resolveRefundDiscrepancy($refund, array $gatewayData): bool
    {
        try {
            PaymentRefund::where('id', $refund->id)
                ->update([
                    'status' => $gatewayData['status'],
                    'reconciled_at' => now(),
                    'reconciliation_notes' => 'Status updated from gateway reconciliation'
                ]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function resolveChargebackDiscrepancy($chargeback, array $gatewayData): bool
    {
        try {
            PaymentChargeback::where('id', $chargeback->id)
                ->update([
                    'status' => $gatewayData['status'],
                    'reconciled_at' => now(),
                    'reconciliation_notes' => 'Status updated from gateway reconciliation'
                ]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function resolveFeeDiscrepancy($fee, array $gatewayData): bool
    {
        try {
            PaymentFee::where('id', $fee->id)
                ->update([
                    'fee_amount' => $gatewayData['fee_amount'],
                    'reconciliation_status' => 'reconciled',
                    'reconciled_at' => now(),
                    'reconciliation_notes' => 'Fee amount updated from gateway reconciliation'
                ]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Store reconciliation summary
     */
    private function storeReconciliationSummary(array $results): void
    {
        try {
            PaymentReconciliationSummary::create([
                'reconciliation_date' => $this->reconciliationDate,
                'gateways_processed' => $results['gateways_processed'],
                'transactions_reconciled' => $results['transactions_reconciled'],
                'discrepancies_found' => $results['discrepancies_found'],
                'discrepancies_resolved' => $results['discrepancies_resolved'],
                'total_amount_reconciled' => $results['total_amount_reconciled'],
                'processing_time_ms' => $results['processing_time_ms'],
                'job_id' => $this->job?->getJobId(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to store reconciliation summary', [
                'error' => $e->getMessage(),
                'results' => $results
            ]);
        }
    }

    /**
     * Get default payment gateways
     */
    private function getDefaultPaymentGateways(): array
    {
        return [
            'stripe',
            'paypal',
            'square',
            'authorize_net'
        ];
    }

    /**
     * Get default reconciliation types
     */
    private function getDefaultReconciliationTypes(): array
    {
        return [
            'transaction_status',
            'settlement_amounts',
            'refund_status',
            'fee_reconciliation'
        ];
    }

    /**
     * Get default reconciliation options
     */
    private function getDefaultReconciliationOptions(): array
    {
        return [
            'batch_size' => 100,
            'api_timeout_seconds' => 30,
            'max_retries' => 3,
            'amount_tolerance' => 0.01,
            'auto_resolve_discrepancies' => true,
            'notification_threshold' => 10 // Notify if more than 10 discrepancies
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Payment reconciliation job failed permanently', [
            'payment_gateways' => $this->paymentGateways,
            'reconciliation_date' => $this->reconciliationDate->toDateString(),
            'reconciliation_types' => $this->reconciliationTypes,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\Payments\ReconciliationFailed(...));
    }
}
