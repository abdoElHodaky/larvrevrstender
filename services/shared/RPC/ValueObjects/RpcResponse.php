<?php

declare(strict_types=1);

namespace Shared\RPC\ValueObjects;

use JsonSerializable;
use Throwable;

/**
 * RPC Response Value Object - PHP 8.3 Implementation
 * 
 * Immutable value object representing an RPC response
 * with comprehensive error handling and type safety.
 */
readonly class RpcResponse implements JsonSerializable
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
        public int $statusCode = 200,
        public array $headers = [],
        public ?string $correlationId = null,
        public float $responseTimeMs = 0.0,
    ) {}

    /**
     * Create a successful response
     */
    public static function success(
        mixed $data = null,
        int $statusCode = 200,
        array $headers = [],
        ?string $correlationId = null,
        float $responseTimeMs = 0.0
    ): self {
        return new self(
            success: true,
            data: $data,
            statusCode: $statusCode,
            headers: $headers,
            correlationId: $correlationId,
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Create a failed response
     */
    public static function failure(
        string $error,
        int $statusCode = 500,
        mixed $data = null,
        array $headers = [],
        ?string $correlationId = null,
        float $responseTimeMs = 0.0
    ): self {
        return new self(
            success: false,
            data: $data,
            error: $error,
            statusCode: $statusCode,
            headers: $headers,
            correlationId: $correlationId,
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Create a response from an exception
     */
    public static function fromException(
        Throwable $exception,
        ?string $correlationId = null,
        float $responseTimeMs = 0.0
    ): self {
        return new self(
            success: false,
            error: $exception->getMessage(),
            statusCode: method_exists($exception, 'getStatusCode') 
                ? $exception->getStatusCode() 
                : 500,
            correlationId: $correlationId,
            responseTimeMs: $responseTimeMs
        );
    }

    /**
     * Check if response indicates a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    /**
     * Check if response indicates a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Check if response is successful (2xx)
     */
    public function isSuccessful(): bool
    {
        return $this->success && $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * Check if the request should be retried
     */
    public function shouldRetry(): bool
    {
        // Retry on server errors and specific client errors
        return $this->isServerError() || 
               in_array($this->statusCode, [408, 429, 502, 503, 504]);
    }

    /**
     * Get data with type casting
     */
    public function getData(string $type = 'array'): mixed
    {
        return match ($type) {
            'array' => is_array($this->data) ? $this->data : [],
            'object' => is_object($this->data) ? $this->data : (object)($this->data ?? []),
            'string' => is_string($this->data) ? $this->data : json_encode($this->data),
            'int' => is_numeric($this->data) ? (int)$this->data : 0,
            'float' => is_numeric($this->data) ? (float)$this->data : 0.0,
            'bool' => (bool)$this->data,
            default => $this->data,
        };
    }

    /**
     * Get a specific field from the response data
     */
    public function getField(string $field, mixed $default = null): mixed
    {
        if (!is_array($this->data)) {
            return $default;
        }

        return $this->data[$field] ?? $default;
    }

    /**
     * Get error message with fallback
     */
    public function getErrorMessage(): string
    {
        return $this->error ?? 'Unknown error occurred';
    }

    /**
     * Create a new response with additional data
     */
    public function withData(mixed $additionalData): self
    {
        $newData = is_array($this->data) && is_array($additionalData)
            ? array_merge($this->data, $additionalData)
            : $additionalData;

        return new self(
            success: $this->success,
            data: $newData,
            error: $this->error,
            statusCode: $this->statusCode,
            headers: $this->headers,
            correlationId: $this->correlationId,
            responseTimeMs: $this->responseTimeMs
        );
    }

    /**
     * Create a new response with correlation ID
     */
    public function withCorrelationId(string $correlationId): self
    {
        return new self(
            success: $this->success,
            data: $this->data,
            error: $this->error,
            statusCode: $this->statusCode,
            headers: $this->headers,
            correlationId: $correlationId,
            responseTimeMs: $this->responseTimeMs
        );
    }

    /**
     * JSON serialization for logging and API responses
     */
    public function jsonSerialize(): array
    {
        $result = [
            'success' => $this->success,
            'status_code' => $this->statusCode,
            'response_time_ms' => $this->responseTimeMs,
        ];

        if ($this->success) {
            $result['data'] = $this->data;
        } else {
            $result['error'] = $this->error;
            if ($this->data !== null) {
                $result['error_data'] = $this->data;
            }
        }

        if ($this->correlationId) {
            $result['correlation_id'] = $this->correlationId;
        }

        return $result;
    }

    /**
     * Convert to array for Laravel responses
     */
    public function toArray(): array
    {
        return $this->jsonSerialize();
    }
}
