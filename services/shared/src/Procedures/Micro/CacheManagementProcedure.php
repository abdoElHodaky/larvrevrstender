<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Exception;

/**
 * Cache Management Micro Procedure
 * 
 * Provides comprehensive caching operations with TTL management, tagging,
 * distributed cache coordination, and performance analytics.
 */
trait CacheManagementProcedure
{
    /**
     * Store data in cache
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cacheSet(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'key' => ['required' => true, 'type' => 'string'],
                'value' => ['required' => true],
                'ttl' => ['type' => 'int'],
                'tags' => ['type' => 'array'],
                'driver' => ['type' => 'string'],
                'compress' => ['type' => 'bool']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $key = $params['key'];
            $value = $params['value'];
            $ttl = $params['ttl'] ?? 3600; // 1 hour default
            $tags = $params['tags'] ?? [];
            $driver = $params['driver'] ?? config('cross_service.caching.default_driver', 'redis');
            $compress = $params['compress'] ?? false;

            // Generate full cache key with namespace
            $fullKey = $this->generateCacheKey($key, $context);

            // Compress value if requested and above threshold
            $originalSize = strlen(serialize($value));
            if ($compress || $this->shouldCompress($originalSize)) {
                $value = $this->compressValue($value);
                $compressed = true;
            } else {
                $compressed = false;
            }

            // Store in cache
            $result = $this->setCacheValue($fullKey, $value, $ttl, $driver, $tags);
            
            if (!$result['success']) {
                return $this->errorResponse('Cache set failed', $result);
            }

            // Record metrics
            $this->recordMetric('cache_set', 1, [
                'driver' => $driver,
                'compressed' => $compressed,
                'size_bytes' => $originalSize,
                'ttl' => $ttl
            ]);

            $this->log('debug', 'Cache set successful', [
                'key' => $key,
                'full_key' => $fullKey,
                'driver' => $driver,
                'ttl' => $ttl,
                'compressed' => $compressed,
                'size_bytes' => $originalSize
            ]);

            return $this->successResponse([
                'key' => $key,
                'full_key' => $fullKey,
                'ttl' => $ttl,
                'compressed' => $compressed,
                'size_bytes' => $originalSize,
                'expires_at' => now()->addSeconds($ttl)->toISOString()
            ], 'Cache set successful');

        } catch (Exception $e) {
            $this->log('error', 'Cache set failed', [
                'error' => $e->getMessage(),
                'key' => $params['key'] ?? null
            ]);

            return $this->errorResponse('Cache set failed: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve data from cache
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cacheGet(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'key' => ['required' => true, 'type' => 'string'],
                'driver' => ['type' => 'string'],
                'default' => []
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $key = $params['key'];
            $driver = $params['driver'] ?? config('cross_service.caching.default_driver', 'redis');
            $default = $params['default'] ?? null;

            // Generate full cache key
            $fullKey = $this->generateCacheKey($key, $context);

            // Get from cache
            $result = $this->getCacheValue($fullKey, $driver);
            
            if (!$result['success']) {
                // Record cache miss
                $this->recordMetric('cache_miss', 1, [
                    'driver' => $driver,
                    'key' => $key
                ]);

                return $this->successResponse([
                    'key' => $key,
                    'found' => false,
                    'value' => $default,
                    'hit' => false
                ], 'Cache miss');
            }

            $value = $result['value'];
            $compressed = false;

            // Decompress if needed
            if ($this->isCompressed($value)) {
                $value = $this->decompressValue($value);
                $compressed = true;
            }

            // Record cache hit
            $this->recordMetric('cache_hit', 1, [
                'driver' => $driver,
                'key' => $key,
                'compressed' => $compressed
            ]);

            $this->log('debug', 'Cache get successful', [
                'key' => $key,
                'full_key' => $fullKey,
                'driver' => $driver,
                'compressed' => $compressed,
                'hit' => true
            ]);

            return $this->successResponse([
                'key' => $key,
                'found' => true,
                'value' => $value,
                'hit' => true,
                'compressed' => $compressed,
                'ttl_remaining' => $result['ttl_remaining'] ?? null
            ], 'Cache hit');

        } catch (Exception $e) {
            $this->log('error', 'Cache get failed', [
                'error' => $e->getMessage(),
                'key' => $params['key'] ?? null
            ]);

            return $this->errorResponse('Cache get failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete data from cache
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cacheDelete(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'key' => ['required' => true, 'type' => 'string'],
                'driver' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $key = $params['key'];
            $driver = $params['driver'] ?? config('cross_service.caching.default_driver', 'redis');

            // Generate full cache key
            $fullKey = $this->generateCacheKey($key, $context);

            // Delete from cache
            $result = $this->deleteCacheValue($fullKey, $driver);

            // Record metrics
            $this->recordMetric('cache_delete', 1, [
                'driver' => $driver,
                'success' => $result['success']
            ]);

            $this->log('debug', 'Cache delete', [
                'key' => $key,
                'full_key' => $fullKey,
                'driver' => $driver,
                'success' => $result['success']
            ]);

            return $this->successResponse([
                'key' => $key,
                'deleted' => $result['success']
            ], $result['success'] ? 'Cache delete successful' : 'Cache key not found');

        } catch (Exception $e) {
            $this->log('error', 'Cache delete failed', [
                'error' => $e->getMessage(),
                'key' => $params['key'] ?? null
            ]);

            return $this->errorResponse('Cache delete failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if cache key exists
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cacheExists(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'key' => ['required' => true, 'type' => 'string'],
                'driver' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $key = $params['key'];
            $driver = $params['driver'] ?? config('cross_service.caching.default_driver', 'redis');

            // Generate full cache key
            $fullKey = $this->generateCacheKey($key, $context);

            // Check existence
            $exists = $this->cacheKeyExists($fullKey, $driver);

            return $this->successResponse([
                'key' => $key,
                'exists' => $exists
            ], $exists ? 'Cache key exists' : 'Cache key does not exist');

        } catch (Exception $e) {
            $this->log('error', 'Cache exists check failed', [
                'error' => $e->getMessage(),
                'key' => $params['key'] ?? null
            ]);

            return $this->errorResponse('Cache exists check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get cache statistics
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cacheStats(array $params, array $context = []): array
    {
        try {
            $driver = $params['driver'] ?? config('cross_service.caching.default_driver', 'redis');

            $stats = $this->getCacheStats($driver);

            return $this->successResponse($stats, 'Cache statistics retrieved');

        } catch (Exception $e) {
            $this->log('error', 'Cache stats failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Cache stats failed: ' . $e->getMessage());
        }
    }

    /**
     * Flush cache by pattern or tags
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function cacheFlush(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'pattern' => ['type' => 'string'],
                'tags' => ['type' => 'array'],
                'driver' => ['type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $pattern = $params['pattern'] ?? null;
            $tags = $params['tags'] ?? [];
            $driver = $params['driver'] ?? config('cross_service.caching.default_driver', 'redis');

            $flushedCount = 0;

            if ($pattern) {
                $flushedCount += $this->flushByPattern($pattern, $driver);
            }

            if (!empty($tags)) {
                $flushedCount += $this->flushByTags($tags, $driver);
            }

            if (!$pattern && empty($tags)) {
                // Flush all cross-service cache
                $flushedCount = $this->flushAllCrossServiceCache($driver);
            }

            $this->recordMetric('cache_flush', $flushedCount, [
                'driver' => $driver,
                'pattern' => $pattern,
                'tags' => $tags
            ]);

            return $this->successResponse([
                'flushed_count' => $flushedCount,
                'pattern' => $pattern,
                'tags' => $tags
            ], "Flushed {$flushedCount} cache entries");

        } catch (Exception $e) {
            $this->log('error', 'Cache flush failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Cache flush failed: ' . $e->getMessage());
        }
    }

    /**
     * Set cache value with driver-specific implementation
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param string $driver
     * @param array $tags
     * @return array
     */
    private function setCacheValue(string $key, $value, int $ttl, string $driver, array $tags = []): array
    {
        try {
            return match ($driver) {
                'redis' => $this->setRedisValue($key, $value, $ttl, $tags),
                'memcached' => $this->setMemcachedValue($key, $value, $ttl, $tags),
                'file' => $this->setFileValue($key, $value, $ttl, $tags),
                default => (function() use ($key, $value, $ttl) {
                    // Use Laravel's default cache
                    Cache::put($key, $value, $ttl);
                    return ['success' => true];
                })()
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get cache value with driver-specific implementation
     *
     * @param string $key
     * @param string $driver
     * @return array
     */
    private function getCacheValue(string $key, string $driver): array
    {
        try {
            return match ($driver) {
                'redis' => $this->getRedisValue($key),
                'memcached' => $this->getMemcachedValue($key),
                'file' => $this->getFileValue($key),
                default => (function() use ($key) {
                    $value = Cache::get($key);
                    return [
                        'success' => $value !== null,
                        'value' => $value
                    ];
                })()
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete cache value with driver-specific implementation
     *
     * @param string $key
     * @param string $driver
     * @return array
     */
    private function deleteCacheValue(string $key, string $driver): array
    {
        try {
            return match ($driver) {
                'redis' => (function() use ($key) {
                    $result = Redis::del($key);
                    return ['success' => $result > 0];
                })(),
                'memcached', 'file', default => (function() use ($key) {
                    $result = Cache::forget($key);
                    return ['success' => $result];
                })()
            };
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if cache key exists
     *
     * @param string $key
     * @param string $driver
     * @return bool
     */
    private function cacheKeyExists(string $key, string $driver): bool
    {
        try {
            return match ($driver) {
                'redis' => Redis::exists($key) > 0,
                default => Cache::has($key)
            };
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Set value in Redis with tags support
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param array $tags
     * @return array
     */
    private function setRedisValue(string $key, $value, int $ttl, array $tags): array
    {
        try {
            // Store the main value
            Redis::setex($key, $ttl, serialize($value));

            // Store tags if provided
            if (!empty($tags)) {
                foreach ($tags as $tag) {
                    $tagKey = "tag:{$tag}";
                    Redis::sadd($tagKey, $key);
                    Redis::expire($tagKey, $ttl + 3600); // Tag expires 1 hour after value
                }
            }

            return ['success' => true];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get value from Redis
     *
     * @param string $key
     * @return array
     */
    private function getRedisValue(string $key): array
    {
        try {
            $value = Redis::get($key);
            if ($value === null) {
                return ['success' => false];
            }

            $ttl = Redis::ttl($key);
            
            return [
                'success' => true,
                'value' => unserialize($value),
                'ttl_remaining' => $ttl > 0 ? $ttl : null
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Set value in Memcached (placeholder)
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param array $tags
     * @return array
     */
    private function setMemcachedValue(string $key, $value, int $ttl, array $tags): array
    {
        // Placeholder for Memcached implementation
        Cache::put($key, $value, $ttl);
        return ['success' => true];
    }

    /**
     * Get value from Memcached (placeholder)
     *
     * @param string $key
     * @return array
     */
    private function getMemcachedValue(string $key): array
    {
        $value = Cache::get($key);
        return [
            'success' => $value !== null,
            'value' => $value
        ];
    }

    /**
     * Set value in file cache (placeholder)
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param array $tags
     * @return array
     */
    private function setFileValue(string $key, $value, int $ttl, array $tags): array
    {
        // Placeholder for file cache implementation
        Cache::put($key, $value, $ttl);
        return ['success' => true];
    }

    /**
     * Get value from file cache (placeholder)
     *
     * @param string $key
     * @return array
     */
    private function getFileValue(string $key): array
    {
        $value = Cache::get($key);
        return [
            'success' => $value !== null,
            'value' => $value
        ];
    }

    /**
     * Generate cache key with namespace
     *
     * @param string $key
     * @param array $context
     * @return string
     */
    private function generateCacheKey(string $key, array $context): string
    {
        $prefix = config('cross_service.caching.drivers.redis.prefix', 'cross_service:');
        $service = $context['source_service'] ?? 'shared';
        
        return "{$prefix}{$service}:{$key}";
    }

    /**
     * Check if value should be compressed
     *
     * @param int $size
     * @return bool
     */
    private function shouldCompress(int $size): bool
    {
        $threshold = config('cross_service.caching.compression_threshold', 1024);
        return $size > $threshold;
    }

    /**
     * Compress value
     *
     * @param mixed $value
     * @return array
     */
    private function compressValue($value): array
    {
        return [
            'compressed' => true,
            'data' => gzcompress(serialize($value))
        ];
    }

    /**
     * Check if value is compressed
     *
     * @param mixed $value
     * @return bool
     */
    private function isCompressed($value): bool
    {
        return is_array($value) && isset($value['compressed']) && $value['compressed'] === true;
    }

    /**
     * Decompress value
     *
     * @param array $value
     * @return mixed
     */
    private function decompressValue(array $value)
    {
        return unserialize(gzuncompress($value['data']));
    }

    /**
     * Get cache statistics
     *
     * @param string $driver
     * @return array
     */
    private function getCacheStats(string $driver): array
    {
        try {
            return match ($driver) {
                'redis' => (function() {
                    $info = Redis::info();
                    return [
                        'driver' => 'redis',
                        'used_memory' => $info['used_memory_human'] ?? 'unknown',
                        'connected_clients' => $info['connected_clients'] ?? 0,
                        'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                        'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                        'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                        'hit_rate' => $this->calculateHitRate($info['keyspace_hits'] ?? 0, $info['keyspace_misses'] ?? 0)
                    ];
                })(),
                default => [
                    'driver' => $driver,
                    'message' => 'Statistics not available for this driver'
                ]
            };
        } catch (Exception $e) {
            return [
                'driver' => $driver,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate cache hit rate
     *
     * @param int $hits
     * @param int $misses
     * @return float
     */
    private function calculateHitRate(int $hits, int $misses): float
    {
        $total = $hits + $misses;
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Flush cache by pattern
     *
     * @param string $pattern
     * @param string $driver
     * @return int
     */
    private function flushByPattern(string $pattern, string $driver): int
    {
        try {
            return match ($driver) {
                'redis' => (function() use ($pattern) {
                    $keys = Redis::keys($pattern);
                    if (!empty($keys)) {
                        return Redis::del($keys);
                    }
                    return 0;
                })(),
                default => 0 // Not supported for other drivers
            };
        } catch (Exception $e) {
            $this->log('error', 'Flush by pattern failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Flush cache by tags
     *
     * @param array $tags
     * @param string $driver
     * @return int
     */
    private function flushByTags(array $tags, string $driver): int
    {
        try {
            $flushedCount = 0;
            
            foreach ($tags as $tag) {
                match ($driver) {
                    'redis' => (function() use ($tag, &$flushedCount) {
                        $tagKey = "tag:{$tag}";
                        $keys = Redis::smembers($tagKey);
                        if (!empty($keys)) {
                            $flushedCount += Redis::del($keys);
                            Redis::del($tagKey); // Remove the tag set itself
                        }
                    })(),
                    default => null
                };
            }
            
            return $flushedCount;
        } catch (Exception $e) {
            $this->log('error', 'Flush by tags failed', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Flush all cross-service cache
     *
     * @param string $driver
     * @return int
     */
    private function flushAllCrossServiceCache(string $driver): int
    {
        $prefix = config('cross_service.caching.drivers.redis.prefix', 'cross_service:');
        return $this->flushByPattern("{$prefix}*", $driver);
    }
}
