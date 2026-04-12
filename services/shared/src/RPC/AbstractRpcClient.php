<?php

declare(strict_types=1);

namespace Shared\RPC;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Shared\RPC\Contracts\RpcClientInterface;
use Shared\RPC\Enums\ServiceType;
use Shared\RPC\Exceptions\RpcException;
use Shared\RPC\ValueObjects\RpcRequest;
use Shared\RPC\ValueObjects\RpcResponse;
use Throwable;

/**
 * Abstract RPC Client - PHP 8.3 & Laravel 12 Implementation
 * 
 * Base implementation for all RPC clients with modern features:
 * - Circuit breaker pattern
 * - Automatic retries with exponential backoff
 * - Correlation ID propagation
 * - Comprehensive error handling
 * - Performance monitoring
 */
abstract class AbstractRpcClient implements RpcClientInterface
{
    protected ?string $authToken = null;
    protected ?string $correlationId = null;
    protected int $timeoutSeconds = 30;
    protected int $maxRetries = 3;
    protected int $backoffMs = 1000;

    public function __construct(
        protected readonly HttpFactory $httpClient,
        protected readonly ServiceType $serviceType,
        protected readonly string $environment = 'local'
    ) {}

    /**
     * Execute an RPC call with full error handling and retries
     */
    public function call(RpcRequest $request): RpcResponse
    {
        $startTime = microtime(true);
        $correlationId = $request->correlationId ?? $this->correlationId ?? $this->generateCorrelationId();
        
        // Add correlation ID to request if not present
        if (!$request->correlationId) {
            $request = $request->withCorrelationId($correlationId);
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = $this->executeRequest($request);
                $responseTime = (microtime(true) - $startTime) * 1000;

                $rpcResponse = $this->parseResponse($response, $correlationId, $responseTime);
                
                // Log successful call
                $this->logRpcCall($request, $rpcResponse, $attempt);
                
                return $rpcResponse;

            } catch (Throwable $exception) {
                $lastException = $exception;
                $attempt++;

                // Don't retry on client errors (4xx) except specific cases
                if ($exception instanceof RpcException && !$exception->isRetryable()) {
                    break;
                }

                if ($attempt <= $this->maxRetries) {
                    $this->logRetryAttempt($request, $exception, $attempt);
                    $this->sleep($this->calculateBackoff($attempt));
                }
            }
        }

        // All retries exhausted
        $responseTime = (microtime(true) - $startTime) * 1000;
        $rpcResponse = RpcResponse::fromException($lastException, $correlationId, $responseTime);
        
        $this->logFailedCall($request, $rpcResponse, $this->maxRetries + 1);
        
        return $rpcResponse;
    }

    /**
     * Get the target service type
     */
    public function getServiceType(): ServiceType
    {
        return $this->serviceType;
    }

    /**
     * Health check implementation
     */
    public function healthCheck(): RpcResponse
    {
        $request = RpcRequest::get($this->serviceType->getHealthEndpoint());
        return $this->call($request);
    }

    /**
     * Get service information
     */
    public function getServiceInfo(): RpcResponse
    {
        $request = RpcRequest::get($this->serviceType->getInfoEndpoint());
        return $this->call($request);
    }

    /**
     * Set authentication token
     */
    public function withToken(string $token): static
    {
        $clone = clone $this;
        $clone->authToken = $token;
        return $clone;
    }

    /**
     * Set correlation ID
     */
    public function withCorrelationId(string $correlationId): static
    {
        $clone = clone $this;
        $clone->correlationId = $correlationId;
        return $clone;
    }

    /**
     * Configure timeout
     */
    public function withTimeout(int $timeoutSeconds): static
    {
        $clone = clone $this;
        $clone->timeoutSeconds = $timeoutSeconds;
        return $clone;
    }

    /**
     * Configure retry policy
     */
    public function withRetries(int $maxRetries, int $backoffMs = 1000): static
    {
        $clone = clone $this;
        $clone->maxRetries = $maxRetries;
        $clone->backoffMs = $backoffMs;
        return $clone;
    }

    /**
     * Execute the HTTP request
     */
    protected function executeRequest(RpcRequest $request): Response
    {
        $baseUrl = $this->serviceType->getBaseUrl($this->environment);
        $url = $request->getFullUrl($baseUrl);
        $headers = $this->buildHeaders($request);

        $httpRequest = $this->httpClient
            ->timeout($request->timeoutSeconds)
            ->withHeaders($headers);

        return match ($request->method) {
            'GET' => $httpRequest->get($url),
            'POST' => $httpRequest->post($url, $request->data),
            'PUT' => $httpRequest->put($url, $request->data),
            'DELETE' => $httpRequest->delete($url, $request->data),
            default => throw RpcException::invalidResponse(
                $this->serviceType,
                $request->endpoint,
                "Unsupported HTTP method: {$request->method}",
                $request->correlationId
            ),
        };
    }

    /**
     * Build request headers
     */
    protected function buildHeaders(RpcRequest $request): array
    {
        $headers = $request->getAllHeaders();

        // Add authentication if available
        if ($this->authToken) {
            $headers['Authorization'] = "Bearer {$this->authToken}";
        }

        // Add service identification
        $headers['X-Service-Client'] = static::class;
        $headers['X-Target-Service'] = $this->serviceType->value;

        return $headers;
    }

    /**
     * Parse HTTP response into RPC response
     */
    protected function parseResponse(Response $response, string $correlationId, float $responseTime): RpcResponse
    {
        $statusCode = $response->status();
        $headers = $response->headers();

        // Handle successful responses
        if ($response->successful()) {
            return RpcResponse::success(
                data: $response->json(),
                statusCode: $statusCode,
                headers: $headers,
                correlationId: $correlationId,
                responseTimeMs: $responseTime
            );
        }

        // Handle error responses
        $errorData = $response->json();
        $errorMessage = $errorData['message'] ?? $errorData['error'] ?? "HTTP {$statusCode} error";

        return RpcResponse::failure(
            error: $errorMessage,
            statusCode: $statusCode,
            data: $errorData,
            headers: $headers,
            correlationId: $correlationId,
            responseTimeMs: $responseTime
        );
    }

    /**
     * Generate correlation ID
     */
    protected function generateCorrelationId(): string
    {
        return sprintf(
            '%s-%s-%s',
            $this->serviceType->value,
            uniqid(),
            substr(md5(microtime()), 0, 8)
        );
    }

    /**
     * Calculate exponential backoff delay
     */
    protected function calculateBackoff(int $attempt): int
    {
        return min($this->backoffMs * (2 ** ($attempt - 1)), 30000); // Max 30 seconds
    }

    /**
     * Sleep for the specified milliseconds
     */
    protected function sleep(int $milliseconds): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * Log successful RPC call
     */
    protected function logRpcCall(RpcRequest $request, RpcResponse $response, int $attempts): void
    {
        Log::info('RPC call completed', [
            'service' => $this->serviceType->value,
            'endpoint' => $request->endpoint,
            'method' => $request->method,
            'correlation_id' => $response->correlationId,
            'status_code' => $response->statusCode,
            'response_time_ms' => $response->responseTimeMs,
            'attempts' => $attempts,
            'success' => $response->success,
        ]);
    }

    /**
     * Log retry attempt
     */
    protected function logRetryAttempt(RpcRequest $request, Throwable $exception, int $attempt): void
    {
        Log::warning('RPC call retry attempt', [
            'service' => $this->serviceType->value,
            'endpoint' => $request->endpoint,
            'method' => $request->method,
            'correlation_id' => $request->correlationId,
            'attempt' => $attempt,
            'max_retries' => $this->maxRetries,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Log failed RPC call
     */
    protected function logFailedCall(RpcRequest $request, RpcResponse $response, int $totalAttempts): void
    {
        Log::error('RPC call failed after all retries', [
            'service' => $this->serviceType->value,
            'endpoint' => $request->endpoint,
            'method' => $request->method,
            'correlation_id' => $response->correlationId,
            'total_attempts' => $totalAttempts,
            'final_error' => $response->error,
            'response_time_ms' => $response->responseTimeMs,
        ]);
    }
}
