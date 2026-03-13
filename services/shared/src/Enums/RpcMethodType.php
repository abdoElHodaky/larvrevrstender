<?php

namespace Shared\Enums;

/**
 * RPC Method Types Enum (PHP 8.3)
 * 
 * Defines the types of RPC methods available in the system
 */
enum RpcMethodType: string
{
    case READ = 'read';
    case WRITE = 'write';
    case DELETE = 'delete';
    case BATCH = 'batch';
    case STREAM = 'stream';
    case HEALTH_CHECK = 'health_check';
    case SYSTEM = 'system';

    /**
     * Get cacheable method types
     */
    public static function getCacheableTypes(): array
    {
        return [
            self::READ,
            self::HEALTH_CHECK,
            self::SYSTEM
        ];
    }

    /**
     * Get methods that require authentication
     */
    public static function getAuthRequiredTypes(): array
    {
        return [
            self::READ,
            self::WRITE,
            self::DELETE,
            self::BATCH,
            self::STREAM
        ];
    }

    /**
     * Get methods that support batch operations
     */
    public static function getBatchSupportedTypes(): array
    {
        return [
            self::READ,
            self::WRITE,
            self::DELETE
        ];
    }

    /**
     * Check if method type is cacheable
     */
    public function isCacheable(): bool
    {
        return in_array($this, self::getCacheableTypes());
    }

    /**
     * Check if method type requires authentication
     */
    public function requiresAuth(): bool
    {
        return in_array($this, self::getAuthRequiredTypes());
    }

    /**
     * Check if method type supports batching
     */
    public function supportsBatch(): bool
    {
        return in_array($this, self::getBatchSupportedTypes());
    }

    /**
     * Get default cache TTL for method type
     */
    public function getDefaultCacheTtl(): int
    {
        return match($this) {
            self::READ => 300,        // 5 minutes
            self::HEALTH_CHECK => 60, // 1 minute
            self::SYSTEM => 1800,     // 30 minutes
            default => 0              // No caching
        };
    }

    /**
     * Get method type description
     */
    public function getDescription(): string
    {
        return match($this) {
            self::READ => 'Read operation that retrieves data',
            self::WRITE => 'Write operation that modifies data',
            self::DELETE => 'Delete operation that removes data',
            self::BATCH => 'Batch operation that processes multiple requests',
            self::STREAM => 'Streaming operation for real-time data',
            self::HEALTH_CHECK => 'Health check operation for service monitoring',
            self::SYSTEM => 'System operation for configuration and metadata'
        };
    }
}
