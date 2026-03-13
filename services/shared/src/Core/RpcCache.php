<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * RPC Cache Manager
 * 
 * Provides caching functionality for RPC procedure responses
 * with intelligent invalidation and TTL management.
 */
class RpcCache
{
    private array $config;
    private array $cacheStats = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_ttl' => 300, // 5 minutes
            'max_ttl' => 3600, // 1 hour
            'key_prefix' => 'rpc_cache',
            'enable_compression' => true,
            'cache_large_responses' => true,
            'max_response_size' => 1024 * 1024, // 1MB
            'invalidation_patterns' => [],
            'cache_tags_enabled' => true
        ], $config);

        $this->initializeStats();
    }

    /**
     * Get cached RPC response
     *
     * @param string $method RPC method name
     * @param array $params Method parameters
     * @param array $options Cache options
     * @return mixed|null Cached response or null if not found
     */
    public function get(string $method, array $params = [], array $options = [])
    {
        $cacheKey = $this->generateCacheKey($method, $params, $options);
        $startTime = microtime(true);

        try {
            $cached = Cache::get($cacheKey);
            $duration = microtime(true) - $startTime;

            if ($cached !== null) {
                $this->recordCacheHit($method, $cacheKey, $duration);
                
                // Decompress if needed
                if ($this->config['enable_compression'] && isset($cached['compressed']) && $cached['compressed']) {
                    $cached['data'] = $this->decompress($cached['data']);
                }

                Log::debug('RPC cache hit', [
                    'method' => $method,
                    'cache_key' => $cacheKey,
                    'duration_ms' => round($duration * 1000, 2)
                ]);

                return $cached['data'];
            }

            $this->recordCacheMiss($method, $cacheKey, $duration);
            return null;

        } catch (\Exception $e) {
            Log::warning('RPC cache get failed', [
                'method' => $method,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);

            $this->recordCacheError($method, $cacheKey);
            return null;
        }
    }

    /**
     * Store RPC response in cache
     *
     * @param string $method RPC method name
     * @param array $params Method parameters
     * @param mixed $response Response to cache
     * @param array $options Cache options
     * @return bool Success status
     */
    public function put(string $method, array $params, $response, array $options = []): bool
    {
        $cacheKey = $this->generateCacheKey($method, $params, $options);
        $ttl = $this->calculateTtl($method, $options);
        $startTime = microtime(true);

        try {
            // Check response size
            $responseSize = strlen(json_encode($response));
            if ($responseSize > $this->config['max_response_size'] && !$this->config['cache_large_responses']) {
                Log::info('RPC response too large to cache', [
                    'method' => $method,
                    'size_bytes' => $responseSize,
                    'max_size' => $this->config['max_response_size']
                ]);
                return false;
            }

            $cacheData = ['data' => $response, 'compressed' => false];

            // Compress large responses
            if ($this->config['enable_compression'] && $responseSize > 1024) {
                $compressed = $this->compress($response);
                if ($compressed !== false && strlen($compressed) < $responseSize * 0.8) {
                    $cacheData = ['data' => $compressed, 'compressed' => true];
                }
            }

            // Store with tags if enabled
            if ($this->config['cache_tags_enabled']) {
                $tags = $this->generateCacheTags($method, $params);
                $success = Cache::tags($tags)->put($cacheKey, $cacheData, $ttl);
            } else {
                $success = Cache::put($cacheKey, $cacheData, $ttl);
            }

            $duration = microtime(true) - $startTime;

            if ($success) {
                $this->recordCacheStore($method, $cacheKey, $responseSize, $duration);
                
                Log::debug('RPC response cached', [
                    'method' => $method,
                    'cache_key' => $cacheKey,
                    'ttl_seconds' => $ttl,
                    'size_bytes' => $responseSize,
                    'compressed' => $cacheData['compressed'],
                    'duration_ms' => round($duration * 1000, 2)
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::warning('RPC cache put failed', [
                'method' => $method,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);

            $this->recordCacheError($method, $cacheKey);
            return false;
        }
    }

    /**
     * Invalidate cache for specific method and parameters
     */
    public function invalidate(string $method, array $params = []): bool
    {
        $cacheKey = $this->generateCacheKey($method, $params);
        
        try {
            $success = Cache::forget($cacheKey);
            
            Log::debug('RPC cache invalidated', [
                'method' => $method,
                'cache_key' => $cacheKey,
                'success' => $success
            ]);

            return $success;

        } catch (\Exception $e) {
            Log::warning('RPC cache invalidation failed', [
                'method' => $method,
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidateByPattern(string $pattern): int
    {
        try {
            // This is a simplified implementation
            // In production, you might want to use Redis SCAN or similar
            $invalidated = 0;
            
            Log::info('RPC cache pattern invalidation', [
                'pattern' => $pattern,
                'invalidated_count' => $invalidated
            ]);

            return $invalidated;

        } catch (\Exception $e) {
            Log::warning('RPC cache pattern invalidation failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);

            return 0;
        }
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateByTags(array $tags): bool
    {
        if (!$this->config['cache_tags_enabled']) {
            return false;
        }

        try {
            Cache::tags($tags)->flush();
            
            Log::info('RPC cache tags invalidated', [
                'tags' => $tags
            ]);

            return true;

        } catch (\Exception $e) {
            Log::warning('RPC cache tags invalidation failed', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Clear all RPC cache
     */
    public function flush(): bool
    {
        try {
            // Clear cache with prefix
            $success = Cache::flush(); // This clears all cache, be careful in production
            
            Log::info('RPC cache flushed', [
                'success' => $success
            ]);

            $this->initializeStats();
            return $success;

        } catch (\Exception $e) {
            Log::warning('RPC cache flush failed', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return array_merge($this->cacheStats, [
            'hit_ratio' => $this->calculateHitRatio(),
            'config' => $this->config,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Generate cache key for method and parameters
     */
    private function generateCacheKey(string $method, array $params = [], array $options = []): string
    {
        // Sort parameters for consistent key generation
        ksort($params);
        
        $keyData = [
            'method' => $method,
            'params' => $params
        ];

        // Include relevant options in key
        if (isset($options['user_id'])) {
            $keyData['user_id'] = $options['user_id'];
        }

        if (isset($options['version'])) {
            $keyData['version'] = $options['version'];
        }

        $hash = hash('sha256', json_encode($keyData));
        return $this->config['key_prefix'] . ':' . $method . ':' . substr($hash, 0, 16);
    }

    /**
     * Calculate TTL for method
     */
    private function calculateTtl(string $method, array $options = []): int
    {
        // Custom TTL from options
        if (isset($options['ttl'])) {
            return min($options['ttl'], $this->config['max_ttl']);
        }

        // Method-specific TTL rules
        $methodTtlRules = [
            'auth.validateToken' => 60, // 1 minute
            'user.getUser' => 300, // 5 minutes
            'user.getUserPermissions' => 600, // 10 minutes
            'system.getConfig' => 1800, // 30 minutes
        ];

        if (isset($methodTtlRules[$method])) {
            return $methodTtlRules[$method];
        }

        // Default TTL
        return $this->config['default_ttl'];
    }

    /**
     * Generate cache tags for method
     */
    private function generateCacheTags(string $method, array $params): array
    {
        $tags = ['rpc_cache'];
        
        // Add method-based tag
        $methodParts = explode('.', $method);
        if (count($methodParts) >= 2) {
            $tags[] = 'service:' . $methodParts[0];
            $tags[] = 'method:' . $method;
        }

        // Add parameter-based tags
        if (isset($params['user_id'])) {
            $tags[] = 'user:' . $params['user_id'];
        }

        return $tags;
    }

    /**
     * Compress data
     */
    private function compress($data): string|false
    {
        return gzcompress(json_encode($data), 6);
    }

    /**
     * Decompress data
     */
    private function decompress(string $compressed)
    {
        $decompressed = gzuncompress($compressed);
        return $decompressed !== false ? json_decode($decompressed, true) : false;
    }

    /**
     * Initialize cache statistics
     */
    private function initializeStats(): void
    {
        $this->cacheStats = [
            'hits' => 0,
            'misses' => 0,
            'stores' => 0,
            'errors' => 0,
            'total_size_bytes' => 0
        ];
    }

    /**
     * Record cache hit
     */
    private function recordCacheHit(string $method, string $key, float $duration): void
    {
        $this->cacheStats['hits']++;
    }

    /**
     * Record cache miss
     */
    private function recordCacheMiss(string $method, string $key, float $duration): void
    {
        $this->cacheStats['misses']++;
    }

    /**
     * Record cache store
     */
    private function recordCacheStore(string $method, string $key, int $size, float $duration): void
    {
        $this->cacheStats['stores']++;
        $this->cacheStats['total_size_bytes'] += $size;
    }

    /**
     * Record cache error
     */
    private function recordCacheError(string $method, string $key): void
    {
        $this->cacheStats['errors']++;
    }

    /**
     * Calculate hit ratio
     */
    private function calculateHitRatio(): float
    {
        $total = $this->cacheStats['hits'] + $this->cacheStats['misses'];
        return $total > 0 ? round($this->cacheStats['hits'] / $total, 4) : 0.0;
    }
}
