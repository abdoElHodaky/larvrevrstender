<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationBufferedEvent;
use Shared\Jobs\ReplayBufferedWriteOperationsJob;
use Shared\Services\DatabaseFailoverAlertManager;

class HandleWriteOperationBuffered implements ShouldQueue
{
    use InteractsWithQueue;

    private DatabaseFailoverAlertManager $alertManager;

    public function __construct()
    {
        $this->alertManager = new DatabaseFailoverAlertManager();
    }

    /**
     * Handle the write operation buffered event for Bidding Service.
     */
    public function handle(WriteOperationBufferedEvent $event): void
    {
        Log::channel('write-operations')->info('Bidding Service: Write operation buffered', [
            'service' => 'bidding-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'buffered_at' => $event->bufferedAt,
            'correlation_id' => $event->correlationId,
        ]);

        // Bidding service handles time-sensitive auction operations
        $this->handleBiddingWriteBuffering($event);
    }

    /**
     * Handle bidding-specific write operation buffering.
     */
    private function handleBiddingWriteBuffering(WriteOperationBufferedEvent $event): void
    {
        // Update metrics for buffered operations
        cache()->increment('bidding_buffered_operations_count');
        cache()->put('bidding_last_buffered_operation', now(), 3600);

        // Track operation type for auction integrity monitoring
        $operationType = $event->operationType;
        cache()->increment("bidding_buffered_operations_{$operationType}");

        // Log for monitoring dashboard with auction context
        Log::info('Bidding Service: Write operation buffered for replay', [
            'service' => 'bidding-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'buffer_size' => cache()->get('bidding_buffered_operations_count', 0),
            'auction_impact' => $this->getAuctionImpactLevel($operationType),
            'time_sensitivity' => $this->getTimeSensitivity($operationType),
        ]);

        // Critical alert for bidding operations - these are time-sensitive
        $bufferSize = cache()->get('bidding_buffered_operations_count', 0);
        if ($bufferSize > 100) {
            Log::critical('Bidding Service: High number of buffered write operations', [
                'service' => 'bidding-service',
                'buffer_size' => $bufferSize,
                'alert' => 'critical_buffer_size',
                'auction_impact' => 'Bid processing severely delayed',
                'business_impact' => 'Auction integrity at risk',
                'time_sensitivity' => 'CRITICAL - Bids may expire',
            ]);

            // Send alert for high buffer size affecting auction integrity
            $this->sendAuctionIntegrityAlert($bufferSize, $operationType);
        }

        // Special handling for time-critical bid operations
        if ($this->isTimeCriticalBidOperation($operationType)) {
            Log::warning('Bidding Service: Time-critical bid operation buffered', [
                'service' => 'bidding-service',
                'operation_type' => $operationType,
                'impact' => 'Bid timing may be affected',
                'priority' => 'urgent',
                'auction_risk' => 'HIGH - Bid fairness may be compromised',
            ]);
        }

        // Determine priority based on operation type and time sensitivity
        $priority = $this->getOperationPriority($operationType);
        $delay = $this->getReplayDelay($priority);
        $batchSize = $this->getBatchSize($priority);

        // Schedule replay job with auction-appropriate priority
        ReplayBufferedWriteOperationsJob::dispatch('bidding-service', $batchSize)
            ->delay(now()->addMinutes($delay))
            ->onQueue('write-operation-replay');

        Log::info('Bidding Service: Scheduled write operation replay job', [
            'service' => 'bidding-service',
            'operation_type' => $operationType,
            'priority' => $priority,
            'delay_minutes' => $delay,
            'batch_size' => $batchSize,
            'auction_impact' => $this->getAuctionImpactLevel($operationType),
        ]);

        // Track replay job scheduling for auction monitoring
        cache()->put('bidding_last_replay_job_scheduled', now()->toISOString(), 3600);
        cache()->increment('bidding_replay_jobs_scheduled');
        
        // Update auction-specific metrics
        $this->updateAuctionMetrics($operationType);
    }

    /**
     * Get operation priority based on type and auction timing.
     */
    private function getOperationPriority(string $operationType): string
    {
        $priorityMap = [
            'bid_submission' => 'critical',
            'bid_evaluation' => 'critical',
            'bid_validation' => 'high',
            'bid_ranking' => 'high',
            'auction_close' => 'critical',
            'winner_selection' => 'critical',
            'bid_history_update' => 'medium',
            'bid_notification' => 'medium',
        ];

        return $priorityMap[$operationType] ?? 'high';
    }

    /**
     * Get replay delay based on priority (shorter for auction operations).
     */
    private function getReplayDelay(string $priority): int
    {
        $delayMap = [
            'critical' => 0.5, // 30 seconds for critical bid operations
            'high' => 1,       // 1 minute for high priority
            'medium' => 3,     // 3 minutes for medium priority
            'low' => 5,        // 5 minutes for low priority
        ];

        return $delayMap[$priority] ?? 1;
    }

    /**
     * Get batch size based on priority (smaller for time-sensitive operations).
     */
    private function getBatchSize(string $priority): int
    {
        $batchSizeMap = [
            'critical' => 5,  // Very small batches for critical bid operations
            'high' => 15,     // Small batches for high priority
            'medium' => 50,   // Medium batches for medium priority
            'low' => 100,     // Large batches for low priority
        ];

        return $batchSizeMap[$priority] ?? 15;
    }

    /**
     * Check if operation is time-critical for bidding.
     */
    private function isTimeCriticalBidOperation(string $operationType): bool
    {
        $timeCriticalOperations = [
            'bid_submission',
            'bid_evaluation',
            'auction_close',
            'winner_selection'
        ];

        return in_array($operationType, $timeCriticalOperations);
    }

    /**
     * Get auction impact level for operation type.
     */
    private function getAuctionImpactLevel(string $operationType): string
    {
        $impactMap = [
            'bid_submission' => 'critical',
            'bid_evaluation' => 'critical',
            'auction_close' => 'critical',
            'winner_selection' => 'critical',
            'bid_validation' => 'high',
            'bid_ranking' => 'high',
            'bid_history_update' => 'medium',
            'bid_notification' => 'low',
        ];

        return $impactMap[$operationType] ?? 'medium';
    }

    /**
     * Get time sensitivity level for operation type.
     */
    private function getTimeSensitivity(string $operationType): string
    {
        $sensitivityMap = [
            'bid_submission' => 'extreme',
            'bid_evaluation' => 'extreme',
            'auction_close' => 'extreme',
            'winner_selection' => 'extreme',
            'bid_validation' => 'high',
            'bid_ranking' => 'high',
            'bid_history_update' => 'medium',
            'bid_notification' => 'low',
        ];

        return $sensitivityMap[$operationType] ?? 'medium';
    }

    /**
     * Update auction-specific metrics.
     */
    private function updateAuctionMetrics(string $operationType): void
    {
        // Track active auctions that might be affected
        $activeAuctions = cache()->get('bidding_active_auctions_count', 0);
        $pendingBids = cache()->get('bidding_pending_bids_count', 0);
        
        // Update auction health metrics
        cache()->put('bidding_auction_health_status', 'degraded', 3600);
        cache()->put('bidding_last_operation_buffered', $operationType, 3600);
        
        Log::info('Bidding Service: Updated auction metrics', [
            'service' => 'bidding-service',
            'active_auctions' => $activeAuctions,
            'pending_bids' => $pendingBids,
            'operation_type' => $operationType,
            'auction_health' => 'degraded',
        ]);
    }

    /**
     * Send alert for high buffer size affecting auction integrity.
     */
    private function sendAuctionIntegrityAlert(int $bufferSize, string $operationType): void
    {
        try {
            // Create alert data for auction integrity impact
            $alertData = [
                'alert_id' => 'bidding_buffer_critical_' . time(),
                'event_type' => 'critical_bidding_buffer_size',
                'title' => '🚨 Bidding Service: CRITICAL - Auction Integrity at Risk',
                'description' => "Bidding service has {$bufferSize} buffered write operations. This severely impacts bid processing and auction fairness.",
                'severity' => 'critical',
                'service' => 'bidding-service',
                'connection' => 'bidding-database',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'buffer_size' => $bufferSize,
                    'operation_type' => $operationType,
                    'auction_impact' => 'Bid processing severely delayed - auction integrity compromised',
                    'business_impact' => 'Auction fairness and timing at risk',
                    'time_sensitivity' => 'EXTREME - Immediate intervention required',
                    'recommended_action' => 'URGENT: Restore database connectivity immediately to prevent auction disruption'
                ]
            ];

            Log::critical('Bidding Service: Sending critical auction integrity alert', $alertData);

        } catch (\Exception $e) {
            Log::error('Bidding Service: Failed to send auction integrity alert', [
                'error' => $e->getMessage(),
                'buffer_size' => $bufferSize,
                'operation_type' => $operationType,
            ]);
        }
    }
}
