<?php

namespace Shared\Exceptions;

use Exception;
use Throwable;

/**
 * Standardized RPC Exception (PHP 8.3)
 * 
 * JSON-RPC 2.0 compliant exception handling for RPC procedures
 */
class RpcException extends Exception
{
    // JSON-RPC 2.0 Standard Error Codes
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    // Application-specific Error Codes
    public const AUTHENTICATION_FAILED = -32001;
    public const AUTHORIZATION_FAILED = -32002;
    public const RATE_LIMIT_EXCEEDED = -32003;
    public const SERVICE_UNAVAILABLE = -32004;
    public const VALIDATION_FAILED = -32005;
    public const RESOURCE_NOT_FOUND = -32006;
    public const RESOURCE_CONFLICT = -32007;
    public const CIRCUIT_BREAKER_OPEN = -32008;
    public const TIMEOUT_ERROR = -32009;
    public const CACHE_ERROR = -32010;

    private readonly array $context;
    private readonly ?string $correlationId;
    private readonly ?string $serviceName;

    public function __construct(
        string $message,
        int $code = self::INTERNAL_ERROR,
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->context = $context;
        $this->correlationId = $correlationId;
        $this->serviceName = $serviceName;
    }

    /**
     * Create authentication failed exception
     */
    public static function authenticationFailed(
        string $message = 'Authentication failed',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::AUTHENTICATION_FAILED, $context, $correlationId, $serviceName);
    }

    /**
     * Create authorization failed exception
     */
    public static function authorizationFailed(
        string $message = 'Authorization failed',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::AUTHORIZATION_FAILED, $context, $correlationId, $serviceName);
    }

    /**
     * Create rate limit exceeded exception
     */
    public static function rateLimitExceeded(
        string $message = 'Rate limit exceeded',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::RATE_LIMIT_EXCEEDED, $context, $correlationId, $serviceName);
    }

    /**
     * Create service unavailable exception
     */
    public static function serviceUnavailable(
        string $message = 'Service temporarily unavailable',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::SERVICE_UNAVAILABLE, $context, $correlationId, $serviceName);
    }

    /**
     * Create validation failed exception
     */
    public static function validationFailed(
        string $message = 'Validation failed',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::VALIDATION_FAILED, $context, $correlationId, $serviceName);
    }

    /**
     * Create resource not found exception
     */
    public static function resourceNotFound(
        string $message = 'Resource not found',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::RESOURCE_NOT_FOUND, $context, $correlationId, $serviceName);
    }

    /**
     * Create resource conflict exception
     */
    public static function resourceConflict(
        string $message = 'Resource conflict',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::RESOURCE_CONFLICT, $context, $correlationId, $serviceName);
    }

    /**
     * Create circuit breaker open exception
     */
    public static function circuitBreakerOpen(
        string $message = 'Circuit breaker is open',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::CIRCUIT_BREAKER_OPEN, $context, $correlationId, $serviceName);
    }

    /**
     * Create timeout error exception
     */
    public static function timeoutError(
        string $message = 'Request timeout',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::TIMEOUT_ERROR, $context, $correlationId, $serviceName);
    }

    /**
     * Create invalid parameters exception
     */
    public static function invalidParams(
        string $message = 'Invalid parameters',
        array $context = [],
        ?string $correlationId = null,
        ?string $serviceName = null
    ): self {
        return new self($message, self::INVALID_PARAMS, $context, $correlationId, $serviceName);
    }

    /**
     * Get error context
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get correlation ID
     */
    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    /**
     * Get service name
     */
    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    /**
     * Get error severity level
     */
    public function getSeverity(): string
    {
        return match($this->getCode()) {
            self::PARSE_ERROR, self::INVALID_REQUEST, self::METHOD_NOT_FOUND => 'error',
            self::INVALID_PARAMS, self::VALIDATION_FAILED => 'warning',
            self::AUTHENTICATION_FAILED, self::AUTHORIZATION_FAILED => 'warning',
            self::RATE_LIMIT_EXCEEDED => 'warning',
            self::RESOURCE_NOT_FOUND => 'info',
            self::RESOURCE_CONFLICT => 'warning',
            self::SERVICE_UNAVAILABLE, self::CIRCUIT_BREAKER_OPEN, self::TIMEOUT_ERROR => 'error',
            self::CACHE_ERROR => 'warning',
            default => 'error'
        };
    }

    /**
     * Check if error is retryable
     */
    public function isRetryable(): bool
    {
        return match($this->getCode()) {
            self::SERVICE_UNAVAILABLE, self::TIMEOUT_ERROR, self::CACHE_ERROR => true,
            self::CIRCUIT_BREAKER_OPEN => false, // Circuit breaker handles retries
            self::RATE_LIMIT_EXCEEDED => false,  // Should wait before retry
            default => false
        };
    }

    /**
     * Get retry delay in milliseconds
     */
    public function getRetryDelay(): int
    {
        return match($this->getCode()) {
            self::SERVICE_UNAVAILABLE => 1000,  // 1 second
            self::TIMEOUT_ERROR => 2000,        // 2 seconds
            self::CACHE_ERROR => 500,           // 500ms
            default => 0
        };
    }

    /**
     * Convert to JSON-RPC 2.0 error response
     */
    public function toJsonRpcError(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'data' => array_merge($this->context, [
                'correlation_id' => $this->correlationId,
                'service_name' => $this->serviceName,
                'severity' => $this->getSeverity(),
                'retryable' => $this->isRetryable(),
                'retry_delay_ms' => $this->getRetryDelay(),
                'timestamp' => now()->toISOString(),
                'trace' => $this->getTraceAsString()
            ])
        ];
    }

    /**
     * Convert to array for logging
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->getCode(),
            'error_message' => $this->getMessage(),
            'correlation_id' => $this->correlationId,
            'service_name' => $this->serviceName,
            'context' => $this->context,
            'severity' => $this->getSeverity(),
            'retryable' => $this->isRetryable(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get error code name
     */
    public function getCodeName(): string
    {
        return match($this->getCode()) {
            self::PARSE_ERROR => 'PARSE_ERROR',
            self::INVALID_REQUEST => 'INVALID_REQUEST',
            self::METHOD_NOT_FOUND => 'METHOD_NOT_FOUND',
            self::INVALID_PARAMS => 'INVALID_PARAMS',
            self::INTERNAL_ERROR => 'INTERNAL_ERROR',
            self::AUTHENTICATION_FAILED => 'AUTHENTICATION_FAILED',
            self::AUTHORIZATION_FAILED => 'AUTHORIZATION_FAILED',
            self::RATE_LIMIT_EXCEEDED => 'RATE_LIMIT_EXCEEDED',
            self::SERVICE_UNAVAILABLE => 'SERVICE_UNAVAILABLE',
            self::VALIDATION_FAILED => 'VALIDATION_FAILED',
            self::RESOURCE_NOT_FOUND => 'RESOURCE_NOT_FOUND',
            self::RESOURCE_CONFLICT => 'RESOURCE_CONFLICT',
            self::CIRCUIT_BREAKER_OPEN => 'CIRCUIT_BREAKER_OPEN',
            self::TIMEOUT_ERROR => 'TIMEOUT_ERROR',
            self::CACHE_ERROR => 'CACHE_ERROR',
            default => 'UNKNOWN_ERROR'
        };
    }
}
