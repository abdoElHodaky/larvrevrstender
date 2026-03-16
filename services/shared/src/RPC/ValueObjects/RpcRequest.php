<?php

declare(strict_types=1);

namespace Shared\RPC\ValueObjects;

use JsonSerializable;

/**
 * RPC Request Value Object - PHP 8.3 Implementation
 * 
 * Immutable value object representing an RPC request
 * with type safety and modern PHP features.
 */
readonly class RpcRequest implements JsonSerializable
{
    public function __construct(
        public string $method,
        public string $endpoint,
        public array $data = [],
        public array $headers = [],
        public ?string $correlationId = null,
        public int $timeoutSeconds = 30,
    ) {}

    /**
     * Create a GET request
     */
    public static function get(string $endpoint, array $queryParams = [], array $headers = []): self
    {
        return new self(
            method: 'GET',
            endpoint: $endpoint,
            data: $queryParams,
            headers: $headers
        );
    }

    /**
     * Create a POST request
     */
    public static function post(string $endpoint, array $data = [], array $headers = []): self
    {
        return new self(
            method: 'POST',
            endpoint: $endpoint,
            data: $data,
            headers: $headers
        );
    }

    /**
     * Create a PUT request
     */
    public static function put(string $endpoint, array $data = [], array $headers = []): self
    {
        return new self(
            method: 'PUT',
            endpoint: $endpoint,
            data: $data,
            headers: $headers
        );
    }

    /**
     * Create a DELETE request
     */
    public static function delete(string $endpoint, array $data = [], array $headers = []): self
    {
        return new self(
            method: 'DELETE',
            endpoint: $endpoint,
            data: $data,
            headers: $headers
        );
    }

    /**
     * Create a new request with additional headers
     */
    public function withHeaders(array $additionalHeaders): self
    {
        return new self(
            method: $this->method,
            endpoint: $this->endpoint,
            data: $this->data,
            headers: array_merge($this->headers, $additionalHeaders),
            correlationId: $this->correlationId,
            timeoutSeconds: $this->timeoutSeconds
        );
    }

    /**
     * Create a new request with correlation ID
     */
    public function withCorrelationId(string $correlationId): self
    {
        return new self(
            method: $this->method,
            endpoint: $this->endpoint,
            data: $this->data,
            headers: $this->headers,
            correlationId: $correlationId,
            timeoutSeconds: $this->timeoutSeconds
        );
    }

    /**
     * Create a new request with timeout
     */
    public function withTimeout(int $timeoutSeconds): self
    {
        return new self(
            method: $this->method,
            endpoint: $this->endpoint,
            data: $this->data,
            headers: $this->headers,
            correlationId: $this->correlationId,
            timeoutSeconds: $timeoutSeconds
        );
    }

    /**
     * Get the full URL with query parameters for GET requests
     */
    public function getFullUrl(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($this->endpoint, '/');
        
        if ($this->method === 'GET' && !empty($this->data)) {
            $url .= '?' . http_build_query($this->data);
        }
        
        return $url;
    }

    /**
     * Get request body for non-GET requests
     */
    public function getBody(): ?string
    {
        if ($this->method === 'GET' || empty($this->data)) {
            return null;
        }
        
        return json_encode($this->data);
    }

    /**
     * Get all headers including correlation ID
     */
    public function getAllHeaders(): array
    {
        $headers = $this->headers;
        
        if ($this->correlationId) {
            $headers['X-Correlation-ID'] = $this->correlationId;
        }
        
        if ($this->method !== 'GET' && !empty($this->data)) {
            $headers['Content-Type'] = 'application/json';
        }
        
        return $headers;
    }

    /**
     * JSON serialization for logging and debugging
     */
    public function jsonSerialize(): array
    {
        return [
            'method' => $this->method,
            'endpoint' => $this->endpoint,
            'data_size' => count($this->data),
            'headers_count' => count($this->headers),
            'correlation_id' => $this->correlationId,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }
}
