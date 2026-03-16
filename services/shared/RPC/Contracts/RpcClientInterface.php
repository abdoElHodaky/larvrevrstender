<?php

declare(strict_types=1);

namespace Shared\RPC\Contracts;

use Shared\RPC\Enums\ServiceType;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;

/**
 * Modern RPC Client Interface for PHP 8.3 & Laravel 12
 * 
 * Provides standardized contract for all RPC communication
 * across microservices with type safety and modern PHP features.
 */
interface RpcClientInterface
{
    /**
     * Execute an RPC call with full type safety
     */
    public function call(RpcRequest $request): RpcResponse;

    /**
     * Get the target service type this client communicates with
     */
    public function getServiceType(): ServiceType;

    /**
     * Check if the target service is healthy and reachable
     */
    public function healthCheck(): RpcResponse;

    /**
     * Get service information and capabilities
     */
    public function getServiceInfo(): RpcResponse;

    /**
     * Set authentication token for RPC calls
     */
    public function withToken(string $token): static;

    /**
     * Set correlation ID for distributed tracing
     */
    public function withCorrelationId(string $correlationId): static;

    /**
     * Configure timeout for RPC calls
     */
    public function withTimeout(int $timeoutSeconds): static;

    /**
     * Configure retry policy for failed calls
     */
    public function withRetries(int $maxRetries, int $backoffMs = 1000): static;
}
