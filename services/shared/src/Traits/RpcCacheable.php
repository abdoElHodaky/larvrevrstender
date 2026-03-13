<?php

namespace Shared\Traits;

use Shared\Core\RpcCache;
use Illuminate\Support\Facades\Log;

/**
 * RPC Cacheable Trait
 * 
 * Provides caching functionality for RPC procedures with automatic
 * cache management and invalidation strategies.
 */
trait RpcCacheable
{
    private ?RpcCache $rpcCache = null;
    private array $cacheConfig = [];

    /**
     * Initialize RPC cache
     */
    protected function initializeRpcCache(array $config = []): void
    {
        $this->cacheConfig = array_merge([
            'enabled' => true,
            'default_ttl' => 300,
            'cache_read_methods' => [
                'getUser',
                'getUserPermissions',
                'validateToken',
                'getSystemConfig',
                'getActiveRoles'
            ],
            'invalidation_methods' => [
                'updateUser' => ['user.*'],
                'deleteUser' => ['user.*'],
                'updatePermissions' => ['*.getUserPermissions'],
                'updateRole' => ['*.getActiveRoles', '*.getUserPermissions'],
                'logout' => ['auth.validateToken']
            ]
        ], $config);

        if ($this->cacheConfig['enabled']) {
            $this->rpcCache = new RpcCache($config);
        }
    }

    /**
     * Execute RPC method with caching
     */
    protected function executeWithCache(string $method, array $params, callable $callback, array $options = [])
    {
        if (!$this->isCacheEnabled() || !$this->shouldCache($method)) {
            return $callback();
        }

        // Try to get from cache first
        $cached = $this->rpcCache->get($method, $params, $options);
        if ($cached !== null) {
            Log::debug('RPC method served from cache', [
                'method' => $method,
                'cache_hit' => true
            ]);
            return $cached;
        }

        // Execute method and cache result
        $result = $callback();
        
        if ($this->shouldCacheResult($method, $result)) {
            $this->rpcCache->put($method, $params, $result, $options);
        }

        return $result;
    }

    /**
     * Invalidate cache after method execution
     */
    protected function invalidateCacheAfterMethod(string $method, array $params = []): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $invalidationRules = $this->cacheConfig['invalidation_methods'];
        
        if (isset($invalidationRules[$method])) {
            $patterns = $invalidationRules[$method];
            
            foreach ($patterns as $pattern) {
                if (str_contains($pattern, '*')) {
                    $this->rpcCache->invalidateByPattern($pattern);
                } else {
                    $this->rpcCache->invalidate($pattern, $params);
                }
            }

            Log::info('Cache invalidated after method execution', [
                'method' => $method,
                'patterns' => $patterns
            ]);
        }
    }

    /**
     * Invalidate user-specific cache
     */
    protected function invalidateUserCache(int $userId): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $tags = ['user:' . $userId];
        $this->rpcCache->invalidateByTags($tags);

        Log::info('User cache invalidated', [
            'user_id' => $userId,
            'tags' => $tags
        ]);
    }

    /**
     * Invalidate service-specific cache
     */
    protected function invalidateServiceCache(string $serviceName): void
    {
        if (!$this->isCacheEnabled()) {
            return;
        }

        $tags = ['service:' . $serviceName];
        $this->rpcCache->invalidateByTags($tags);

        Log::info('Service cache invalidated', [
            'service' => $serviceName,
            'tags' => $tags
        ]);
    }

    /**
     * Cache RPC method result with automatic TTL
     */
    protected function cacheMethodResult(string $method, array $params, $result, ?int $ttl = null): bool
    {
        if (!$this->isCacheEnabled()) {
            return false;
        }

        $options = [];
        if ($ttl !== null) {
            $options['ttl'] = $ttl;
        }

        return $this->rpcCache->put($method, $params, $result, $options);
    }

    /**
     * Get cached method result
     */
    protected function getCachedMethodResult(string $method, array $params, array $options = [])
    {
        if (!$this->isCacheEnabled()) {
            return null;
        }

        return $this->rpcCache->get($method, $params, $options);
    }

    /**
     * Warm up cache for frequently used methods
     */
    protected function warmUpCache(array $methods = []): array
    {
        if (!$this->isCacheEnabled()) {
            return ['status' => 'disabled'];
        }

        $results = [];
        $defaultMethods = [
            'getSystemConfig' => [],
            'getActiveRoles' => [],
        ];

        $methodsToWarm = empty($methods) ? $defaultMethods : $methods;

        foreach ($methodsToWarm as $method => $params) {
            try {
                // Check if method exists in this class
                if (method_exists($this, $method)) {
                    $result = $this->$method($params);
                    $this->cacheMethodResult($method, $params, $result);
                    $results[$method] = 'warmed';
                } else {
                    $results[$method] = 'method_not_found';
                }
            } catch (\Exception $e) {
                $results[$method] = 'error: ' . $e->getMessage();
                Log::warning('Cache warm-up failed for method', [
                    'method' => $method,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Cache warm-up completed', [
            'methods' => array_keys($methodsToWarm),
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Get cache statistics
     */
    protected function getCacheStats(): array
    {
        if (!$this->isCacheEnabled()) {
            return ['status' => 'disabled'];
        }

        return $this->rpcCache->getStats();
    }

    /**
     * Clear all cache for this procedure
     */
    protected function clearProcedureCache(): bool
    {
        if (!$this->isCacheEnabled()) {
            return false;
        }

        return $this->rpcCache->flush();
    }

    /**
     * Check if caching is enabled
     */
    private function isCacheEnabled(): bool
    {
        return $this->rpcCache !== null && $this->cacheConfig['enabled'];
    }

    /**
     * Check if method should be cached
     */
    private function shouldCache(string $method): bool
    {
        $readMethods = $this->cacheConfig['cache_read_methods'];
        
        // Check exact method name
        if (in_array($method, $readMethods)) {
            return true;
        }

        // Check method patterns
        foreach ($readMethods as $pattern) {
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('*', '.*', preg_quote($pattern, '/')) . '$/';
                if (preg_match($regex, $method)) {
                    return true;
                }
            }
        }

        // Check if method name suggests it's a read operation
        $readPrefixes = ['get', 'list', 'find', 'search', 'validate', 'check'];
        foreach ($readPrefixes as $prefix) {
            if (str_starts_with(strtolower($method), $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if result should be cached
     */
    private function shouldCacheResult(string $method, $result): bool
    {
        // Don't cache null or false results
        if ($result === null || $result === false) {
            return false;
        }

        // Don't cache error responses
        if (is_array($result) && isset($result['success']) && !$result['success']) {
            return false;
        }

        // Don't cache empty arrays (might be temporary state)
        if (is_array($result) && empty($result)) {
            return false;
        }

        return true;
    }

    /**
     * Configure cache for specific method
     */
    protected function configureCacheForMethod(string $method, array $config): void
    {
        if (!isset($this->cacheConfig['method_configs'])) {
            $this->cacheConfig['method_configs'] = [];
        }

        $this->cacheConfig['method_configs'][$method] = $config;

        Log::debug('Cache configuration updated for method', [
            'method' => $method,
            'config' => $config
        ]);
    }

    /**
     * Add cache invalidation rule
     */
    protected function addCacheInvalidationRule(string $method, array $patterns): void
    {
        if (!isset($this->cacheConfig['invalidation_methods'])) {
            $this->cacheConfig['invalidation_methods'] = [];
        }

        $this->cacheConfig['invalidation_methods'][$method] = $patterns;

        Log::debug('Cache invalidation rule added', [
            'method' => $method,
            'patterns' => $patterns
        ]);
    }
}
