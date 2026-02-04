<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Circuit Breaker Service - Prevents cascading failures
 */
class CircuitBreakerService
{
    private const CACHE_KEY_PREFIX = 'circuit_breaker:';
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $expectedExceptionThreshold;

    public function __construct()
    {
        $this->failureThreshold = config('gateway.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeout = config('gateway.circuit_breaker.recovery_timeout', 60);
        $this->expectedExceptionThreshold = config('gateway.circuit_breaker.expected_exception_threshold', 10);
    }

    /**
     * Check if service can execute requests
     */
    public function canExecute(string $serviceName): bool
    {
        if (!config('gateway.circuit_breaker.enabled', true)) {
            return true;
        }

        $state = $this->getState($serviceName);
        
        switch ($state['status']) {
            case self::STATE_CLOSED:
                return true;
                
            case self::STATE_OPEN:
                // Check if recovery timeout has passed
                if (time() - $state['last_failure_time'] >= $this->recoveryTimeout) {
                    $this->setState($serviceName, self::STATE_HALF_OPEN);
                    Log::info("Circuit breaker transitioning to half-open", ['service' => $serviceName]);
                    return true;
                }
                return false;
                
            case self::STATE_HALF_OPEN:
                return true;
                
            default:
                return true;
        }
    }

    /**
     * Record successful request
     */
    public function recordSuccess(string $serviceName): void
    {
        if (!config('gateway.circuit_breaker.enabled', true)) {
            return;
        }

        $state = $this->getState($serviceName);
        
        if ($state['status'] === self::STATE_HALF_OPEN) {
            // Transition back to closed state
            $this->setState($serviceName, self::STATE_CLOSED, [
                'failure_count' => 0,
                'success_count' => $state['success_count'] + 1,
            ]);
            
            Log::info("Circuit breaker closed after successful recovery", ['service' => $serviceName]);
        } else {
            // Reset failure count on success
            $this->setState($serviceName, self::STATE_CLOSED, [
                'failure_count' => 0,
                'success_count' => $state['success_count'] + 1,
            ]);
        }
    }

    /**
     * Record failed request
     */
    public function recordFailure(string $serviceName): void
    {
        if (!config('gateway.circuit_breaker.enabled', true)) {
            return;
        }

        $state = $this->getState($serviceName);
        $newFailureCount = $state['failure_count'] + 1;
        
        if ($state['status'] === self::STATE_HALF_OPEN) {
            // Transition back to open state
            $this->setState($serviceName, self::STATE_OPEN, [
                'failure_count' => $newFailureCount,
                'last_failure_time' => time(),
            ]);
            
            Log::warning("Circuit breaker opened after failure in half-open state", [
                'service' => $serviceName,
                'failure_count' => $newFailureCount,
            ]);
        } elseif ($newFailureCount >= $this->failureThreshold) {
            // Transition to open state
            $this->setState($serviceName, self::STATE_OPEN, [
                'failure_count' => $newFailureCount,
                'last_failure_time' => time(),
            ]);
            
            Log::error("Circuit breaker opened due to failure threshold", [
                'service' => $serviceName,
                'failure_count' => $newFailureCount,
                'threshold' => $this->failureThreshold,
            ]);
        } else {
            // Increment failure count
            $this->setState($serviceName, $state['status'], [
                'failure_count' => $newFailureCount,
                'last_failure_time' => time(),
            ]);
        }
    }

    /**
     * Get current state of circuit breaker for service
     */
    public function getState(string $serviceName): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $serviceName;
        
        return Cache::get($cacheKey, [
            'status' => self::STATE_CLOSED,
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure_time' => 0,
            'created_at' => time(),
        ]);
    }

    /**
     * Set state of circuit breaker for service
     */
    private function setState(string $serviceName, string $status, array $additionalData = []): void
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $serviceName;
        $currentState = $this->getState($serviceName);
        
        $newState = array_merge($currentState, $additionalData, [
            'status' => $status,
            'updated_at' => time(),
        ]);
        
        // Cache for 1 hour (circuit breaker states should persist)
        Cache::put($cacheKey, $newState, 3600);
    }

    /**
     * Get all circuit breaker states
     */
    public function getAllStates(): array
    {
        $services = [
            'auth', 'user', 'analytics', 'order', 'payment', 
            'bidding', 'notification', 'vin-ocr', 'shared'
        ];
        
        $states = [];
        foreach ($services as $service) {
            $states[$service] = $this->getState($service);
        }
        
        return $states;
    }

    /**
     * Manually reset circuit breaker for service
     */
    public function reset(string $serviceName): void
    {
        $this->setState($serviceName, self::STATE_CLOSED, [
            'failure_count' => 0,
            'success_count' => 0,
            'last_failure_time' => 0,
        ]);
        
        Log::info("Circuit breaker manually reset", ['service' => $serviceName]);
    }

    /**
     * Manually open circuit breaker for service (for maintenance)
     */
    public function open(string $serviceName): void
    {
        $this->setState($serviceName, self::STATE_OPEN, [
            'last_failure_time' => time(),
        ]);
        
        Log::info("Circuit breaker manually opened", ['service' => $serviceName]);
    }

    /**
     * Get circuit breaker statistics
     */
    public function getStatistics(): array
    {
        $states = $this->getAllStates();
        $stats = [
            'total_services' => count($states),
            'closed' => 0,
            'open' => 0,
            'half_open' => 0,
            'total_failures' => 0,
            'total_successes' => 0,
        ];
        
        foreach ($states as $service => $state) {
            $stats[$state['status']]++;
            $stats['total_failures'] += $state['failure_count'];
            $stats['total_successes'] += $state['success_count'];
        }
        
        return $stats;
    }

    /**
     * Check if circuit breaker is healthy (not too many open circuits)
     */
    public function isHealthy(): bool
    {
        $stats = $this->getStatistics();
        $openPercentage = $stats['total_services'] > 0 
            ? ($stats['open'] / $stats['total_services']) * 100 
            : 0;
            
        // Consider unhealthy if more than 50% of services have open circuits
        return $openPercentage <= 50;
    }
}
