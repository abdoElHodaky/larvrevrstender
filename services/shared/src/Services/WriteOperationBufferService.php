<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Crypt;
use Shared\Events\WriteOperationBufferedEvent;
use Shared\Events\WriteOperationReplayedEvent;
use Shared\Jobs\ReplayBufferedWriteOperationsJob;
use Carbon\Carbon;

/**
 * Write Operation Buffer Service
 * 
 * Handles buffering of write operations during database failover scenarios.
 * Implements write-behind pattern with Redis persistence and replay capabilities.
 * Ensures business continuity while maintaining data consistency.
 */
class WriteOperationBufferService
{
    private array $config;
    private string $serviceName;
    private Redis $redis;

    public function __construct(string $serviceName = null)
    {
        $this->serviceName = $serviceName ?? config('app.name', 'unknown-service');
        $this->config = config('database-failover.services.' . $this->serviceName, []);
        $this->redis = Redis::connection($this->config['write_buffer_config']['queue_connection'] ?? 'default');
    }

    /**
     * Buffer a write operation for later replay
     *
     * @param string $operation The operation type (e.g., 'bid_placement', 'payment_processing')
     * @param array $data The operation data
     * @param string|null $idempotencyKey Unique key to prevent duplicate operations
     * @return string Buffer ID for tracking
     */
    public function bufferWriteOperation(string $operation, array $data, ?string $idempotencyKey = null): string
    {
        $bufferId = $this->generateBufferId();
        $queueName = $this->getQueueName();
        
        // Check if operation is configured for buffering
        if (!$this->isOperationBufferable($operation)) {
            throw new \InvalidArgumentException("Operation '{$operation}' is not configured for buffering");
        }

        // Check idempotency if enabled
        if ($idempotencyKey && $this->config['write_buffer_config']['enable_idempotency'] ?? false) {
            if ($this->isDuplicateOperation($idempotencyKey)) {
                Log::info("Duplicate write operation detected", [
                    'service' => $this->serviceName,
                    'operation' => $operation,
                    'idempotency_key' => $idempotencyKey,
                ]);
                return $this->getExistingBufferId($idempotencyKey);
            }
        }

        // Check buffer capacity
        $currentSize = $this->redis->llen($queueName);
        $maxSize = $this->config['write_buffer_config']['max_buffer_size'] ?? 10000;
        
        if ($currentSize >= $maxSize) {
            throw new \RuntimeException("Write operation buffer is full. Current size: {$currentSize}, Max: {$maxSize}");
        }

        // Prepare operation data
        $operationData = [
            'buffer_id' => $bufferId,
            'service' => $this->serviceName,
            'operation' => $operation,
            'data' => $this->encryptDataIfRequired($data),
            'idempotency_key' => $idempotencyKey,
            'priority' => $this->getOperationPriority($operation),
            'consistency_level' => $this->getOperationConsistencyLevel($operation),
            'max_delay_seconds' => $this->getOperationMaxDelay($operation),
            'created_at' => Carbon::now()->toISOString(),
            'expires_at' => Carbon::now()->addSeconds($this->config['write_buffer_config']['buffer_timeout'] ?? 300)->toISOString(),
            'retry_count' => 0,
            'status' => 'buffered',
        ];

        // Store in Redis queue based on priority
        if ($operationData['priority'] === 'critical') {
            $this->redis->lpush($queueName . ':critical', json_encode($operationData));
        } else {
            $this->redis->rpush($queueName, json_encode($operationData));
        }

        // Store idempotency mapping if enabled
        if ($idempotencyKey && $this->config['write_buffer_config']['enable_idempotency'] ?? false) {
            $this->redis->setex(
                "idempotency:{$this->serviceName}:{$idempotencyKey}",
                $this->config['write_buffer_config']['buffer_timeout'] ?? 300,
                $bufferId
            );
        }

        // Set expiration for the operation
        $this->redis->expire($queueName, $this->config['write_buffer_config']['buffer_timeout'] ?? 300);

        // Dispatch event
        Event::dispatch(new WriteOperationBufferedEvent(
            $this->serviceName,
            $operation,
            $bufferId,
            $operationData['priority'],
            $currentSize + 1
        ));

        Log::info("Write operation buffered successfully", [
            'service' => $this->serviceName,
            'operation' => $operation,
            'buffer_id' => $bufferId,
            'priority' => $operationData['priority'],
            'queue_size' => $currentSize + 1,
        ]);

        return $bufferId;
    }

    /**
     * Replay buffered write operations when database recovers
     *
     * @param int $batchSize Number of operations to replay in this batch
     * @return array Results of replay operations
     */
    public function replayBufferedOperations(int $batchSize = null): array
    {
        $batchSize = $batchSize ?? $this->config['write_buffer_config']['replay_batch_size'] ?? 100;
        $queueName = $this->getQueueName();
        $results = [];

        // Process critical operations first
        $criticalOperations = $this->getBufferedOperations($queueName . ':critical', $batchSize);
        foreach ($criticalOperations as $operation) {
            $results[] = $this->replayOperation($operation);
        }

        // Process regular operations
        $remainingBatch = $batchSize - count($criticalOperations);
        if ($remainingBatch > 0) {
            $regularOperations = $this->getBufferedOperations($queueName, $remainingBatch);
            foreach ($regularOperations as $operation) {
                $results[] = $this->replayOperation($operation);
            }
        }

        // Schedule next batch if more operations exist
        if ($this->hasBufferedOperations()) {
            Queue::dispatch(new ReplayBufferedWriteOperationsJob($this->serviceName))
                ->delay(now()->addSeconds(5));
        }

        return $results;
    }

    /**
     * Get the current buffer status
     *
     * @return array Buffer status information
     */
    public function getBufferStatus(): array
    {
        $queueName = $this->getQueueName();
        $regularCount = $this->redis->llen($queueName);
        $criticalCount = $this->redis->llen($queueName . ':critical');
        
        return [
            'service' => $this->serviceName,
            'total_buffered' => $regularCount + $criticalCount,
            'regular_operations' => $regularCount,
            'critical_operations' => $criticalCount,
            'max_buffer_size' => $this->config['write_buffer_config']['max_buffer_size'] ?? 10000,
            'buffer_utilization' => round((($regularCount + $criticalCount) / ($this->config['write_buffer_config']['max_buffer_size'] ?? 10000)) * 100, 2),
            'oldest_operation' => $this->getOldestOperationAge(),
        ];
    }

    /**
     * Clear expired operations from buffer
     *
     * @return int Number of operations cleared
     */
    public function clearExpiredOperations(): int
    {
        $queueName = $this->getQueueName();
        $cleared = 0;
        $now = Carbon::now();

        // Check regular queue
        $cleared += $this->clearExpiredFromQueue($queueName, $now);
        
        // Check critical queue
        $cleared += $this->clearExpiredFromQueue($queueName . ':critical', $now);

        if ($cleared > 0) {
            Log::info("Cleared expired write operations", [
                'service' => $this->serviceName,
                'cleared_count' => $cleared,
            ]);
        }

        return $cleared;
    }

    /**
     * Generate a unique buffer ID
     */
    private function generateBufferId(): string
    {
        return $this->serviceName . '_' . uniqid() . '_' . time();
    }

    /**
     * Get the Redis queue name for this service
     */
    private function getQueueName(): string
    {
        return $this->config['write_buffer_config']['queue_name'] ?? ($this->serviceName . '_write_operations');
    }

    /**
     * Check if an operation is configured for buffering
     */
    private function isOperationBufferable(string $operation): bool
    {
        return isset($this->config['operation_specific_rules'][$operation]['enable_buffering']) &&
               $this->config['operation_specific_rules'][$operation]['enable_buffering'] === true;
    }

    /**
     * Check for duplicate operations using idempotency key
     */
    private function isDuplicateOperation(string $idempotencyKey): bool
    {
        return $this->redis->exists("idempotency:{$this->serviceName}:{$idempotencyKey}");
    }

    /**
     * Get existing buffer ID for duplicate operation
     */
    private function getExistingBufferId(string $idempotencyKey): string
    {
        return $this->redis->get("idempotency:{$this->serviceName}:{$idempotencyKey}");
    }

    /**
     * Encrypt data if encryption is enabled
     */
    private function encryptDataIfRequired(array $data): array
    {
        if ($this->config['write_buffer_config']['enable_encryption'] ?? false) {
            return [
                'encrypted' => true,
                'data' => Crypt::encrypt($data),
            ];
        }
        
        return $data;
    }

    /**
     * Decrypt data if it was encrypted
     */
    private function decryptDataIfRequired(array $data): array
    {
        if (isset($data['encrypted']) && $data['encrypted'] === true) {
            return Crypt::decrypt($data['data']);
        }
        
        return $data;
    }

    /**
     * Get operation priority from configuration
     */
    private function getOperationPriority(string $operation): string
    {
        return $this->config['operation_specific_rules'][$operation]['priority'] ?? 'normal';
    }

    /**
     * Get operation consistency level from configuration
     */
    private function getOperationConsistencyLevel(string $operation): string
    {
        return $this->config['operation_specific_rules'][$operation]['consistency_level'] ?? 'eventual';
    }

    /**
     * Get operation max delay from configuration
     */
    private function getOperationMaxDelay(string $operation): int
    {
        return $this->config['operation_specific_rules'][$operation]['max_delay_seconds'] ?? 300;
    }

    /**
     * Get buffered operations from a specific queue
     */
    private function getBufferedOperations(string $queueName, int $count): array
    {
        $operations = [];
        
        for ($i = 0; $i < $count; $i++) {
            $operationJson = $this->redis->lpop($queueName);
            if (!$operationJson) {
                break;
            }
            
            $operation = json_decode($operationJson, true);
            if ($operation) {
                $operations[] = $operation;
            }
        }
        
        return $operations;
    }

    /**
     * Replay a single operation
     */
    private function replayOperation(array $operation): array
    {
        try {
            // Decrypt data if necessary
            $data = $this->decryptDataIfRequired($operation['data']);
            
            // Check if operation has expired
            if (Carbon::parse($operation['expires_at'])->isPast()) {
                Log::warning("Skipping expired write operation", [
                    'service' => $this->serviceName,
                    'buffer_id' => $operation['buffer_id'],
                    'operation' => $operation['operation'],
                    'expired_at' => $operation['expires_at'],
                ]);
                
                return [
                    'buffer_id' => $operation['buffer_id'],
                    'status' => 'expired',
                    'message' => 'Operation expired before replay',
                ];
            }

            // Dispatch event for replay start
            Event::dispatch(new WriteOperationReplayedEvent(
                $this->serviceName,
                $operation['operation'],
                $operation['buffer_id'],
                'started'
            ));

            // Here you would implement the actual database write
            // This is a placeholder - actual implementation would depend on the specific service
            $result = $this->executeWriteOperation($operation['operation'], $data);

            Log::info("Write operation replayed successfully", [
                'service' => $this->serviceName,
                'buffer_id' => $operation['buffer_id'],
                'operation' => $operation['operation'],
            ]);

            // Dispatch success event
            Event::dispatch(new WriteOperationReplayedEvent(
                $this->serviceName,
                $operation['operation'],
                $operation['buffer_id'],
                'completed'
            ));

            return [
                'buffer_id' => $operation['buffer_id'],
                'status' => 'success',
                'result' => $result,
            ];

        } catch (\Exception $e) {
            Log::error("Failed to replay write operation", [
                'service' => $this->serviceName,
                'buffer_id' => $operation['buffer_id'],
                'operation' => $operation['operation'],
                'error' => $e->getMessage(),
            ]);

            // Dispatch failure event
            Event::dispatch(new WriteOperationReplayedEvent(
                $this->serviceName,
                $operation['operation'],
                $operation['buffer_id'],
                'failed',
                $e->getMessage()
            ));

            return [
                'buffer_id' => $operation['buffer_id'],
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute the actual write operation (to be implemented by specific services)
     */
    private function executeWriteOperation(string $operation, array $data): mixed
    {
        // This is a placeholder method that should be overridden by service-specific implementations
        // or use a strategy pattern to delegate to the appropriate handler
        
        throw new \RuntimeException("Write operation execution not implemented for operation: {$operation}");
    }

    /**
     * Check if there are buffered operations
     */
    private function hasBufferedOperations(): bool
    {
        $queueName = $this->getQueueName();
        return $this->redis->llen($queueName) > 0 || $this->redis->llen($queueName . ':critical') > 0;
    }

    /**
     * Get the age of the oldest operation in the buffer
     */
    private function getOldestOperationAge(): ?string
    {
        $queueName = $this->getQueueName();
        
        // Check both queues for oldest operation
        $oldestRegular = $this->getOldestFromQueue($queueName);
        $oldestCritical = $this->getOldestFromQueue($queueName . ':critical');
        
        $oldest = null;
        if ($oldestRegular && $oldestCritical) {
            $oldest = Carbon::parse($oldestRegular)->lt(Carbon::parse($oldestCritical)) ? $oldestRegular : $oldestCritical;
        } elseif ($oldestRegular) {
            $oldest = $oldestRegular;
        } elseif ($oldestCritical) {
            $oldest = $oldestCritical;
        }
        
        return $oldest ? Carbon::parse($oldest)->diffForHumans() : null;
    }

    /**
     * Get oldest operation timestamp from a specific queue
     */
    private function getOldestFromQueue(string $queueName): ?string
    {
        $operationJson = $this->redis->lindex($queueName, -1);
        if (!$operationJson) {
            return null;
        }
        
        $operation = json_decode($operationJson, true);
        return $operation['created_at'] ?? null;
    }

    /**
     * Clear expired operations from a specific queue
     */
    private function clearExpiredFromQueue(string $queueName, Carbon $now): int
    {
        $cleared = 0;
        $queueLength = $this->redis->llen($queueName);
        
        for ($i = 0; $i < $queueLength; $i++) {
            $operationJson = $this->redis->lindex($queueName, $i);
            if (!$operationJson) {
                continue;
            }
            
            $operation = json_decode($operationJson, true);
            if ($operation && isset($operation['expires_at'])) {
                if (Carbon::parse($operation['expires_at'])->lt($now)) {
                    $this->redis->lrem($queueName, 1, $operationJson);
                    $cleared++;
                }
            }
        }
        
        return $cleared;
    }
}
