<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Base Service Class for RPC Ecosystem
 * 
 * Provides common functionality for all domain services including:
 * - Error handling and logging
 * - Transaction management
 * - Cache management
 * - Event dispatching
 * - Validation helpers
 */
abstract class BaseService
{
    /**
     * Service name for logging and identification
     */
    protected string $serviceName;

    /**
     * Cache prefix for this service
     */
    protected string $cachePrefix;

    /**
     * Default cache TTL in seconds
     */
    protected int $cacheTtl = 3600; // 1 hour

    public function __construct()
    {
        $this->serviceName = $this->getServiceName();
        $this->cachePrefix = strtolower($this->serviceName) . ':';
    }

    /**
     * Get the service name for logging
     */
    protected function getServiceName(): string
    {
        $className = class_basename(static::class);
        return str_replace('Service', '', $className);
    }

    /**
     * Execute a database transaction with error handling
     */
    protected function executeInTransaction(callable $callback, string $operation = 'operation')
    {
        try {
            DB::beginTransaction();
            
            $result = $callback();
            
            DB::commit();
            
            $this->logSuccess($operation);
            
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            
            $this->logError($operation, $e);
            
            throw $e;
        }
    }

    /**
     * Execute with logging wrapper
     */
    protected function executeWithLogging(string $operation, array $context, callable $callback)
    {
        $startTime = microtime(true);
        
        try {
            $this->logInfo("Starting {$operation}", $context);
            
            $result = $callback();
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logInfo("Completed {$operation}", array_merge($context, [
                'duration_ms' => $duration,
                'success' => true
            ]));
            
            return $result;
        } catch (Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logError($operation, $e, array_merge($context, [
                'duration_ms' => $duration,
                'success' => false
            ]));
            
            throw $e;
        }
    }

    /**
     * Log info message with service context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info("[{$this->serviceName}] {$message}", $context);
    }

    /**
     * Log error message with service context
     */
    protected function logError(string $operation, Exception $exception, array $context = []): void
    {
        Log::error("[{$this->serviceName}] {$operation} failed", array_merge($context, [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]));
    }

    /**
     * Log success message with service context
     */
    protected function logSuccess(string $operation, array $context = []): void
    {
        Log::info("[{$this->serviceName}] {$operation} completed successfully", $context);
    }

    /**
     * Get cached value or execute callback and cache result
     */
    protected function remember(string $key, callable $callback, ?int $ttl = null)
    {
        $cacheKey = $this->cachePrefix . $key;
        $ttl = $ttl ?? $this->cacheTtl;
        
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Clear cache for specific key
     */
    protected function forgetCache(string $key): void
    {
        $cacheKey = $this->cachePrefix . $key;
        Cache::forget($cacheKey);
    }

    /**
     * Clear all cache for this service
     */
    protected function clearServiceCache(): void
    {
        // Get all cache keys with this service's prefix and clear them
        $pattern = $this->cachePrefix . '*';
        
        // Note: This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar
        Cache::flush(); // For now, we'll flush all cache
    }

    /**
     * Validate array data against rules
     */
    protected function validateData(array $data, array $rules): array
    {
        $validator = validator($data, $rules);
        
        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Validation failed: ' . implode(', ', $validator->errors()->all())
            );
        }
        
        return $validator->validated();
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     */
    protected function sanitizeForLogging(array $data): array
    {
        $sensitiveKeys = [
            'password', 'token', 'secret', 'key', 'api_key', 
            'access_token', 'refresh_token', 'credit_card',
            'card_number', 'cvv', 'ssn', 'social_security'
        ];
        
        $sanitized = $data;
        
        foreach ($sensitiveKeys as $key) {
            if (isset($sanitized[$key])) {
                $sanitized[$key] = '[REDACTED]';
            }
        }
        
        return $sanitized;
    }

    /**
     * Dispatch domain event
     */
    protected function dispatchEvent($event): void
    {
        event($event);
    }

    /**
     * Handle service-specific error formatting
     */
    protected function formatError(Exception $exception, string $operation): array
    {
        return [
            'success' => false,
            'error' => [
                'message' => $exception->getMessage(),
                'operation' => $operation,
                'service' => $this->serviceName,
                'timestamp' => now()->toISOString()
            ]
        ];
    }

    /**
     * Format successful response
     */
    protected function formatSuccess($data, string $operation): array
    {
        return [
            'success' => true,
            'data' => $data,
            'operation' => $operation,
            'service' => $this->serviceName,
            'timestamp' => now()->toISOString()
        ];
    }
}
