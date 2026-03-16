<?php

declare(strict_types=1);

namespace Shared\RPC\Exceptions;

use Exception;
use Shared\RPC\Enums\ServiceType;
use Throwable;

/**
 * RPC Exception - PHP 8.3 Implementation
 * 
 * Standardized exception for RPC communication errors
 * with detailed context and service information.
 */
class RpcException extends Exception
{
    public function __construct(
        string $message,
        public readonly ServiceType $serviceType,
        public readonly string $endpoint,
        public readonly ?string $correlationId = null,
        public readonly ?array $context = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for connection timeout
     */
    public static function timeout(
        ServiceType $serviceType,
        string $endpoint,
        int $timeoutSeconds,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "RPC call to {$serviceType->getDisplayName()} timed out after {$timeoutSeconds} seconds",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['timeout_seconds' => $timeoutSeconds],
            code: 408
        );
    }

    /**
     * Create exception for connection failure
     */
    public static function connectionFailed(
        ServiceType $serviceType,
        string $endpoint,
        string $reason,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "Failed to connect to {$serviceType->getDisplayName()}: {$reason}",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['connection_error' => $reason],
            code: 503
        );
    }

    /**
     * Create exception for authentication failure
     */
    public static function authenticationFailed(
        ServiceType $serviceType,
        string $endpoint,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "Authentication failed for {$serviceType->getDisplayName()}",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['auth_error' => true],
            code: 401
        );
    }

    /**
     * Create exception for service unavailable
     */
    public static function serviceUnavailable(
        ServiceType $serviceType,
        string $endpoint,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "{$serviceType->getDisplayName()} is currently unavailable",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['service_unavailable' => true],
            code: 503
        );
    }

    /**
     * Create exception for invalid response
     */
    public static function invalidResponse(
        ServiceType $serviceType,
        string $endpoint,
        string $reason,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "Invalid response from {$serviceType->getDisplayName()}: {$reason}",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['invalid_response' => $reason],
            code: 502
        );
    }

    /**
     * Create exception for rate limiting
     */
    public static function rateLimited(
        ServiceType $serviceType,
        string $endpoint,
        int $retryAfterSeconds = 60,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "Rate limited by {$serviceType->getDisplayName()}. Retry after {$retryAfterSeconds} seconds",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['retry_after_seconds' => $retryAfterSeconds],
            code: 429
        );
    }

    /**
     * Create exception for circuit breaker open
     */
    public static function circuitBreakerOpen(
        ServiceType $serviceType,
        string $endpoint,
        ?string $correlationId = null
    ): self {
        return new self(
            message: "Circuit breaker is open for {$serviceType->getDisplayName()}",
            serviceType: $serviceType,
            endpoint: $endpoint,
            correlationId: $correlationId,
            context: ['circuit_breaker_open' => true],
            code: 503
        );
    }

    /**
     * Get the service display name
     */
    public function getServiceName(): string
    {
        return $this->serviceType->getDisplayName();
    }

    /**
     * Get the service value
     */
    public function getServiceValue(): string
    {
        return $this->serviceType->value;
    }

    /**
     * Check if the error is retryable
     */
    public function isRetryable(): bool
    {
        return in_array($this->code, [408, 429, 502, 503, 504]);
    }

    /**
     * Get exception context for logging
     */
    public function getLoggingContext(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'service_type' => $this->serviceType->value,
            'service_name' => $this->serviceType->getDisplayName(),
            'endpoint' => $this->endpoint,
            'correlation_id' => $this->correlationId,
            'code' => $this->code,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    /**
     * Convert to array for API responses
     */
    public function toArray(): array
    {
        return [
            'error' => $this->getMessage(),
            'service' => $this->serviceType->value,
            'endpoint' => $this->endpoint,
            'correlation_id' => $this->correlationId,
            'code' => $this->code,
            'retryable' => $this->isRetryable(),
        ];
    }
}
