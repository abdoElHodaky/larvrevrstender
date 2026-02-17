<?php

namespace Shared\Idempotency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Idempotency Manager
 * 
 * Provides idempotency key generation, validation, and result caching
 * to prevent duplicate operations in saga activities and compensations.
 */
class IdempotencyManager
{
    private const CACHE_PREFIX = 'idempotency:';
    private const DEFAULT_TTL = 3600; // 1 hour
    private const RESULT_TTL = 86400; // 24 hours for completed operations

    /**
     * Generate an idempotency key for an activity
     *
     * @param string $activityClass Activity class name
     * @param array $input Activity input data
     * @param string|null $sagaId Saga ID for context
     * @return string Idempotency key
     */
    public static function generateKey(string $activityClass, array $input, ?string $sagaId = null): string
    {
        // Validate input parameters
        if (empty($activityClass) || !is_string($activityClass)) {
            throw new \InvalidArgumentException('Activity class must be a non-empty string');
        }
        
        if ($sagaId !== null && (!is_string($sagaId) || empty($sagaId))) {
            throw new \InvalidArgumentException('Saga ID must be null or a non-empty string');
        }
        
        // Create a deterministic key based on activity class, input, and saga context
        $keyData = [
            'activity' => $activityClass,
            'saga_id' => $sagaId,
            'input_hash' => self::hashInput($input),
            'timestamp_bucket' => self::getTimestampBucket() // Prevents indefinite caching
        ];

        return self::CACHE_PREFIX . hash('sha256', json_encode($keyData, JSON_THROW_ON_ERROR));
    }

    /**
     * Check if an operation has already been executed
     *
     * @param string $idempotencyKey Idempotency key
     * @return array|null Previous result if exists, null otherwise
     */
    public static function getExistingResult(string $idempotencyKey): ?array
    {
        try {
            $cached = Cache::get($idempotencyKey);
            
            if ($cached && isset($cached['status'], $cached['result'])) {
                Log::info('Idempotency: Found existing result', [
                    'key' => $idempotencyKey,
                    'status' => $cached['status'],
                    'created_at' => $cached['created_at'] ?? null
                ]);
                
                return $cached;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::warning('Idempotency: Failed to retrieve existing result', [
                'key' => substr($idempotencyKey, 0, 16) . '...', // Only log partial key for security
                'error_type' => get_class($e)
            ]);
            
            return null;
        }
    }

    /**
     * Mark an operation as in progress
     *
     * @param string $idempotencyKey Idempotency key
     * @param array $metadata Optional metadata
     * @return bool True if successfully marked, false if already in progress
     */
    public static function markInProgress(string $idempotencyKey, array $metadata = []): bool
    {
        try {
            $existing = Cache::get($idempotencyKey);
            
            if ($existing) {
                Log::info('Idempotency: Operation already in progress or completed', [
                    'key' => $idempotencyKey,
                    'existing_status' => $existing['status'] ?? 'unknown'
                ]);
                return false;
            }

            $progressData = [
                'status' => 'in_progress',
                'started_at' => now()->toISOString(),
                'metadata' => $metadata
            ];

            Cache::put($idempotencyKey, $progressData, self::DEFAULT_TTL);
            
            Log::info('Idempotency: Marked operation as in progress', [
                'key' => $idempotencyKey,
                'metadata' => $metadata
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Idempotency: Failed to mark operation as in progress', [
                'key' => substr($idempotencyKey, 0, 16) . '...',
                'error_type' => get_class($e)
            ]);
            
            return false;
        }
    }

    /**
     * Store the result of a completed operation
     *
     * @param string $idempotencyKey Idempotency key
     * @param array $result Operation result
     * @param bool $success Whether the operation was successful
     * @return void
     */
    public static function storeResult(string $idempotencyKey, array $result, bool $success = true): void
    {
        try {
            $resultData = [
                'status' => $success ? 'completed' : 'failed',
                'result' => $result,
                'completed_at' => now()->toISOString(),
                'created_at' => Cache::get($idempotencyKey)['started_at'] ?? now()->toISOString()
            ];

            Cache::put($idempotencyKey, $resultData, self::RESULT_TTL);
            
            Log::info('Idempotency: Stored operation result', [
                'key' => $idempotencyKey,
                'status' => $resultData['status'],
                'result_size' => strlen(json_encode($result))
            ]);
        } catch (\Exception $e) {
            Log::error('Idempotency: Failed to store operation result', [
                'key' => substr($idempotencyKey, 0, 16) . '...',
                'error_type' => get_class($e)
            ]);
        }
    }

    /**
     * Clear an idempotency key (for testing or manual intervention)
     *
     * @param string $idempotencyKey Idempotency key
     * @return bool True if cleared successfully
     */
    public static function clear(string $idempotencyKey): bool
    {
        try {
            Cache::forget($idempotencyKey);
            
            Log::info('Idempotency: Cleared key', [
                'key' => $idempotencyKey
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Idempotency: Failed to clear key', [
                'key' => substr($idempotencyKey, 0, 16) . '...',
                'error_type' => get_class($e)
            ]);
            
            return false;
        }
    }

    /**
     * Execute an operation with idempotency protection
     *
     * @param string $activityClass Activity class name
     * @param array $input Activity input
     * @param callable $operation Operation to execute
     * @param string|null $sagaId Saga ID for context
     * @return array Operation result
     */
    public static function executeWithIdempotency(
        string $activityClass,
        array $input,
        callable $operation,
        ?string $sagaId = null
    ): array {
        $idempotencyKey = self::generateKey($activityClass, $input, $sagaId);
        
        // Check for existing result
        $existingResult = self::getExistingResult($idempotencyKey);
        if ($existingResult) {
            if ($existingResult['status'] === 'completed') {
                return $existingResult['result'];
            } elseif ($existingResult['status'] === 'in_progress') {
                throw new IdempotencyException(
                    "Operation already in progress",
                    ['idempotency_key' => $idempotencyKey]
                );
            } elseif ($existingResult['status'] === 'failed') {
                // For failed operations, we allow retry after some time
                $failedAt = $existingResult['completed_at'] ?? null;
                if ($failedAt && now()->diffInMinutes($failedAt) < 5) {
                    throw new IdempotencyException(
                        "Operation recently failed, retry not allowed yet",
                        ['idempotency_key' => $idempotencyKey, 'failed_at' => $failedAt]
                    );
                }
            }
        }

        // Mark as in progress
        if (!self::markInProgress($idempotencyKey, [
            'activity_class' => $activityClass,
            'saga_id' => $sagaId
        ])) {
            throw new IdempotencyException(
                "Failed to mark operation as in progress",
                ['idempotency_key' => $idempotencyKey]
            );
        }

        try {
            // Execute the operation
            $result = $operation();
            
            // Store successful result
            self::storeResult($idempotencyKey, $result, true);
            
            return $result;
        } catch (\Exception $e) {
            // Store failed result
            self::storeResult($idempotencyKey, [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e)
            ], false);
            
            throw $e;
        }
    }

    /**
     * Create a hash of input data for idempotency key generation
     *
     * @param array $input Input data
     * @return string Hash of input data
     */
    private static function hashInput(array $input): string
    {
        // Remove non-deterministic fields that shouldn't affect idempotency
        $filteredInput = array_filter($input, function ($key) {
            return !in_array($key, [
                'timestamp',
                'created_at',
                'updated_at',
                'workflow_id',
                'activity_id',
                'attempt_number',
                'password',
                'token',
                'secret',
                'api_key'
            ]);
        }, ARRAY_FILTER_USE_KEY);

        // Sort to ensure consistent hashing
        ksort($filteredInput);
        
        // Use a more secure approach with salt to prevent hash collisions
        $jsonData = json_encode($filteredInput, JSON_THROW_ON_ERROR);
        $salt = config('app.key', 'default-salt');
        
        return hash_hmac('sha256', $jsonData, $salt);
    }

    /**
     * Get timestamp bucket for cache key generation
     * This prevents indefinite caching by bucketing operations by hour
     *
     * @return string Timestamp bucket
     */
    private static function getTimestampBucket(): string
    {
        return now()->format('Y-m-d-H'); // Bucket by hour
    }
}
