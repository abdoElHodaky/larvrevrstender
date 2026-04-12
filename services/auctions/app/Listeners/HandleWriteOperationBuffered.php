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
     * Handle the write operation buffered event for Auction Service.
     */
    public function handle(WriteOperationBufferedEvent $event): void
    {
        Log::channel('write-operations')->info('Auction Service: Write operation buffered', [
            'service' => 'auction-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'buffered_at' => $event->bufferedAt,
            'correlation_id' => $event->correlationId,
        ]);

        // Auction service handles auction lifecycle and management operations
        $this->handleAuctionWriteBuffering($event);
    }

    /**
     * Handle auction-specific write operation buffering.
     */
    private function handleAuctionWriteBuffering(WriteOperationBufferedEvent $event): void
    {
        // Update metrics for buffered operations
        cache()->increment('auction_buffered_operations_count');
        cache()->put('auction_last_buffered_operation', now(), 3600);

        // Track operation type for auction lifecycle monitoring
        $operationType = $event->operationType;
        cache()->increment("auction_buffered_operations_{$operationType}");

        // Log for monitoring dashboard with auction lifecycle context
        Log::info('Auction Service: Write operation buffered for replay', [
            'service' => 'auction-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'buffer_size' => cache()->get('auction_buffered_operations_count', 0),
            'auction_impact' => $this->getAuctionLifecycleImpact($operationType),
            'business_criticality' => $this->getBusinessCriticality($operationType),
        ]);

        // Critical alert for auction operations - these affect auction lifecycle
        $bufferSize = cache()->get('auction_buffered_operations_count', 0);
        if ($bufferSize > 75) {
            Log::critical('Auction Service: High number of buffered write operations', [
                'service' => 'auction-service',
                'buffer_size' => $bufferSize,
                'alert' => 'critical_buffer_size',
                'auction_impact' => 'Auction lifecycle operations severely delayed',
                'business_impact' => 'Auction creation and management at risk',
                'bidding_coordination' => 'Coordination with bidding service compromised',
            ]);

            // Send alert for high buffer size affecting auction lifecycle
            $this->sendAuctionLifecycleAlert($bufferSize, $operationType);
        }

        // Special handling for auction lifecycle critical operations
        if ($this->isAuctionLifecycleCritical($operationType)) {
            Log::warning('Auction Service: Auction lifecycle critical operation buffered', [
                'service' => 'auction-service',
                'operation_type' => $operationType,
                'impact' => 'Auction lifecycle may be affected',
                'priority' => 'urgent',
                'coordination_risk' => 'HIGH - Bidding service coordination at risk',
            ]);
        }

        // Determine priority based on operation type and auction lifecycle impact
        $priority = $this->getOperationPriority($operationType);
        $delay = $this->getReplayDelay($priority);
        $batchSize = $this->getBatchSize($priority);

        // Schedule replay job with auction lifecycle appropriate priority
        ReplayBufferedWriteOperationsJob::dispatch('auction-service', $batchSize)
            ->delay(now()->addMinutes($delay))
            ->onQueue('write-operation-replay');

        Log::info('Auction Service: Scheduled write operation replay job', [
            'service' => 'auction-service',
            'operation_type' => $operationType,
            'priority' => $priority,
            'delay_minutes' => $delay,
            'batch_size' => $batchSize,
            'auction_impact' => $this->getAuctionLifecycleImpact($operationType),
        ]);

        // Track replay job scheduling for auction lifecycle monitoring
        cache()->put('auction_last_replay_job_scheduled', now()->toISOString(), 3600);
        cache()->increment('auction_replay_jobs_scheduled');
        
        // Update auction lifecycle metrics
        $this->updateAuctionLifecycleMetrics($operationType);
    }

    /**
     * Get operation priority based on auction lifecycle importance.
     */
    private function getOperationPriority(string $operationType): string
    {
        $priorityMap = [
            'auction_creation' => 'critical',
            'auction_start' => 'critical',
            'auction_close' => 'critical',
            'auction_cancellation' => 'critical',
            'bid_placement' => 'high',
            'auction_update' => 'high',
            'auction_extension' => 'high',
            'auction_status_change' => 'high',
            'auction_metadata_update' => 'medium',
            'auction_view_tracking' => 'low',
            'auction_analytics' => 'low',
        ];

        return $priorityMap[$operationType] ?? 'medium';
    }

    /**
     * Get replay delay based on priority (optimized for auction lifecycle).
     */
    private function getReplayDelay(string $priority): int
    {
        $delayMap = [
            'critical' => 0.5, // 30 seconds for critical auction lifecycle operations
            'high' => 1.5,     // 1.5 minutes for high priority
            'medium' => 4,     // 4 minutes for medium priority
            'low' => 8,        // 8 minutes for low priority
        ];

        return $delayMap[$priority] ?? 2;
    }

    /**
     * Get batch size based on priority (optimized for auction coordination).
     */
    private function getBatchSize(string $priority): int
    {
        $batchSizeMap = [
            'critical' => 8,  // Small batches for critical auction operations
            'high' => 20,     // Medium batches for high priority
            'medium' => 60,   // Larger batches for medium priority
            'low' => 120,     // Large batches for low priority
        ];

        return $batchSizeMap[$priority] ?? 30;
    }

    /**
     * Check if operation is critical for auction lifecycle.
     */
    private function isAuctionLifecycleCritical(string $operationType): bool
    {
        $criticalOperations = [
            'auction_creation',
            'auction_start',
            'auction_close',
            'auction_cancellation',
            'bid_placement'
        ];

        return in_array($operationType, $criticalOperations);
    }

    /**
     * Get auction lifecycle impact level for operation type.
     */
    private function getAuctionLifecycleImpact(string $operationType): string
    {
        $impactMap = [
            'auction_creation' => 'critical',
            'auction_start' => 'critical',
            'auction_close' => 'critical',
            'auction_cancellation' => 'critical',
            'bid_placement' => 'high',
            'auction_update' => 'high',
            'auction_extension' => 'high',
            'auction_status_change' => 'high',
            'auction_metadata_update' => 'medium',
            'auction_view_tracking' => 'low',
            'auction_analytics' => 'low',
        ];

        return $impactMap[$operationType] ?? 'medium';
    }

    /**
     * Get business criticality level for operation type.
     */
    private function getBusinessCriticality(string $operationType): string
    {
        $criticalityMap = [
            'auction_creation' => 'business_critical',
            'auction_start' => 'business_critical',
            'auction_close' => 'business_critical',
            'auction_cancellation' => 'business_critical',
            'bid_placement' => 'revenue_critical',
            'auction_update' => 'operational_critical',
            'auction_extension' => 'operational_critical',
            'auction_status_change' => 'operational_important',
            'auction_metadata_update' => 'operational_standard',
            'auction_view_tracking' => 'analytics_standard',
            'auction_analytics' => 'analytics_standard',
        ];

        return $criticalityMap[$operationType] ?? 'operational_standard';
    }

    /**
     * Update auction lifecycle metrics.
     */
    private function updateAuctionLifecycleMetrics(string $operationType): void
    {
        // Track auction lifecycle operations that might be affected
        $activeAuctions = cache()->get('auction_service_active_auctions', 0);
        $pendingAuctions = cache()->get('auction_service_pending_auctions', 0);
        $scheduledAuctions = cache()->get('auction_service_scheduled_auctions', 0);
        
        // Update auction lifecycle health metrics
        cache()->put('auction_service_lifecycle_health', 'degraded', 3600);
        cache()->put('auction_service_last_operation_buffered', $operationType, 3600);
        
        // Track coordination with bidding service
        cache()->put('auction_bidding_coordination_status', 'at_risk', 3600);
        
        Log::info('Auction Service: Updated auction lifecycle metrics', [
            'service' => 'auction-service',
            'active_auctions' => $activeAuctions,
            'pending_auctions' => $pendingAuctions,
            'scheduled_auctions' => $scheduledAuctions,
            'operation_type' => $operationType,
            'lifecycle_health' => 'degraded',
            'bidding_coordination' => 'at_risk',
        ]);
    }

    /**
     * Send alert for high buffer size affecting auction lifecycle.
     */
    private function sendAuctionLifecycleAlert(int $bufferSize, string $operationType): void
    {
        try {
            // Create alert data for auction lifecycle impact
            $alertData = [
                'alert_id' => 'auction_buffer_critical_' . time(),
                'event_type' => 'critical_auction_buffer_size',
                'title' => '🚨 Auction Service: CRITICAL - Auction Lifecycle Operations at Risk',
                'description' => "Auction service has {$bufferSize} buffered write operations. This severely impacts auction creation, management, and coordination with bidding service.",
                'severity' => 'critical',
                'service' => 'auction-service',
                'connection' => 'auction-database',
                'timestamp' => now()->toISOString(),
                'metadata' => [
                    'buffer_size' => $bufferSize,
                    'operation_type' => $operationType,
                    'auction_impact' => 'Auction lifecycle operations severely delayed',
                    'business_impact' => 'Auction creation and management compromised',
                    'coordination_impact' => 'Bidding service coordination at risk',
                    'recommended_action' => 'URGENT: Restore database connectivity to prevent auction disruption'
                ]
            ];

            Log::critical('Auction Service: Sending critical auction lifecycle alert', $alertData);

        } catch (\Exception $e) {
            Log::error('Auction Service: Failed to send auction lifecycle alert', [
                'error' => $e->getMessage(),
                'buffer_size' => $bufferSize,
                'operation_type' => $operationType,
            ]);
        }
    }
}
