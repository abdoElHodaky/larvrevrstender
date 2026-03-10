<?php

namespace App\Jobs;

use App\Services\WorkflowSignalHandler;
use Shared\Jobs\BaseQueueJob;
use Illuminate\Support\Facades\Log;

/**
 * Workflow Signal Processing Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Processes workflow signals asynchronously with circuit breaker protection to prevent
 * cascade failures and ensure reliable workflow orchestration during service outages.
 */
class ProcessWorkflowSignal extends BaseQueueJob
{

    public string $workflowId;
    public string $signalType;
    public array $signalData;
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(string $workflowId, string $signalType, array $signalData)
    {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->workflowId = $workflowId;
        $this->signalType = $signalType;
        $this->signalData = $signalData;
        
        // Set queue based on signal priority
        $priority = $signalData['priority'] ?? 'normal';
        $this->onQueue($this->getQueueForPriority($priority));
        
        // Configure circuit breaker for workflow signal processing
        $this->configureCircuitBreaker([
            'service_name' => 'workflow_signal',
            'failure_threshold' => 35, // 35% failure rate triggers circuit breaker
            'timeout' => 50, // 50 seconds timeout for signal processing
            'recovery_timeout' => 150, // 2.5 minutes before attempting recovery
            'tags' => [
                'service' => 'order-service',
                'job_type' => 'workflow_signal',
                'signal_type' => $signalType,
                'priority' => $priority
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(WorkflowSignalHandler $signalHandler): void
    {
        Log::info('Processing workflow signal with circuit breaker protection', [
            'workflow_id' => $this->workflowId,
            'signal_type' => $this->signalType,
            'signal_data' => $this->signalData,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'workflow_signal'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() use ($signalHandler) {
            match ($this->signalType) {
                'pause' => $this->handlePauseSignal($signalHandler),
                'resume' => $this->handleResumeSignal($signalHandler),
                'manual_intervention' => $this->handleManualInterventionSignal($signalHandler),
                'external_signal' => $this->handleExternalSignal($signalHandler),
                default => throw new \InvalidArgumentException("Unknown signal type: {$this->signalType}")
            };

            Log::info('Workflow signal processed successfully', [
                'workflow_id' => $this->workflowId,
                'signal_type' => $this->signalType,
                'job_id' => $this->job?->getJobId(),
            ]);

            return true;
        }, function(\Exception $e) {
            // Circuit breaker failure handler
            Log::error('Failed to process workflow signal with circuit breaker protection', [
                'workflow_id' => $this->workflowId,
                'signal_type' => $this->signalType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Handle pause signal
     */
    private function handlePauseSignal(WorkflowSignalHandler $signalHandler): void
    {
        $reason = $this->signalData['reason'] ?? 'System pause';
        $userId = $this->signalData['user_id'] ?? 'system';

        $result = $signalHandler->pauseWorkflow($this->workflowId, $reason, $userId);

        // Broadcast pause event for real-time updates
        broadcast(new \App\Events\Workflow\WorkflowPaused(
            $this->workflowId,
            $reason,
            $userId,
            $result
        ));
    }

    /**
     * Handle resume signal
     */
    private function handleResumeSignal(WorkflowSignalHandler $signalHandler): void
    {
        $userId = $this->signalData['user_id'] ?? 'system';

        $result = $signalHandler->resumeWorkflow($this->workflowId, $userId);

        // Broadcast resume event for real-time updates
        broadcast(new \App\Events\Workflow\WorkflowResumed(
            $this->workflowId,
            $userId,
            $result
        ));
    }

    /**
     * Handle manual intervention signal
     */
    private function handleManualInterventionSignal(WorkflowSignalHandler $signalHandler): void
    {
        $reason = $this->signalData['reason'] ?? 'Manual intervention required';
        $priority = $this->signalData['priority'] ?? 'medium';
        $requesterId = $this->signalData['requester_id'] ?? 'system';

        $result = $signalHandler->requestManualIntervention(
            $this->workflowId,
            $reason,
            $priority,
            $requesterId
        );

        // Broadcast intervention request for real-time alerts
        broadcast(new \App\Events\Workflow\ManualInterventionRequested(
            $this->workflowId,
            $result['intervention_id'],
            $reason,
            $priority,
            $requesterId
        ));

        // Send urgent notification for high/critical priority interventions
        if (in_array($priority, ['high', 'critical'])) {
            // TODO: Integrate with notification service for urgent alerts
            Log::alert('Urgent manual intervention required', [
                'workflow_id' => $this->workflowId,
                'intervention_id' => $result['intervention_id'],
                'priority' => $priority,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Handle external signal
     */
    private function handleExternalSignal(WorkflowSignalHandler $signalHandler): void
    {
        $signalName = $this->signalData['signal_name'] ?? 'external_signal';
        $signalPayload = $this->signalData['payload'] ?? [];
        $senderId = $this->signalData['sender_id'] ?? 'external';

        $result = $signalHandler->sendExternalSignal(
            $this->workflowId,
            $signalName,
            $signalPayload,
            $senderId
        );

        // Broadcast external signal for monitoring
        broadcast(new \App\Events\Workflow\ExternalSignalReceived(
            $this->workflowId,
            $signalName,
            $signalPayload,
            $senderId,
            $result
        ));
    }

    /**
     * Get queue name based on priority
     */
    private function getQueueForPriority(string $priority): string
    {
        return match ($priority) {
            'critical', 'urgent' => 'signals-high',
            'high' => 'signals-high',
            'medium' => 'signals-medium',
            'low' => 'signals-low',
            default => 'signals-medium',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Workflow signal job failed permanently', [
            'workflow_id' => $this->workflowId,
            'signal_type' => $this->signalType,
            'signal_data' => $this->signalData,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Broadcast failure event for monitoring
        broadcast(new \App\Events\Workflow\SignalProcessingFailed(
            $this->workflowId,
            $this->signalType,
            $exception->getMessage()
        ));
    }
}
