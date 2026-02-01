<?php

namespace Shared\Http\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseServiceClient
{
    protected Client $client;
    protected string $baseUrl;
    protected int $timeout;
    protected array $defaultHeaders;
    protected int $retryAttempts;
    protected int $retryDelay;

    public function __construct(string $baseUrl, int $timeout = 30, int $retryAttempts = 3, int $retryDelay = 1000)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->retryAttempts = $retryAttempts;
        $this->retryDelay = $retryDelay;
        $this->defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Service-Name' => config('app.name', 'Unknown Service'),
            'X-Request-ID' => $this->generateRequestId(),
        ];

        $this->client = $this->createGuzzleClient();
    }

    /**
     * Create configured Guzzle HTTP client.
     */
    protected function createGuzzleClient(): Client
    {
        $stack = HandlerStack::create();
        
        // Add retry middleware
        $stack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, RequestException $exception = null) {
                return $retries < $this->retryAttempts && (
                    $exception !== null || 
                    ($response && $response->getStatusCode() >= 500)
                );
            },
            function ($retries) {
                return $this->retryDelay * $retries; // Exponential backoff
            }
        ));

        // Add logging middleware
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            $this->logRequest($request);
            return $request;
        }));

        $stack->push(Middleware::mapResponse(function (ResponseInterface $response) {
            $this->logResponse($response);
            return $response;
        }));

        return new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
            'headers' => $this->defaultHeaders,
            'handler' => $stack,
            'http_errors' => false, // Don't throw exceptions on HTTP errors
        ]);
    }

    /**
     * Make a GET request to the service.
     */
    protected function get(string $endpoint, array $query = []): ResponseInterface
    {
        return $this->makeRequest('GET', $endpoint, [
            'query' => $query,
        ]);
    }

    /**
     * Make a POST request to the service.
     */
    protected function post(string $endpoint, array $data = []): ResponseInterface
    {
        return $this->makeRequest('POST', $endpoint, [
            'json' => $data,
        ]);
    }

    /**
     * Make a PUT request to the service.
     */
    protected function put(string $endpoint, array $data = []): ResponseInterface
    {
        return $this->makeRequest('PUT', $endpoint, [
            'json' => $data,
        ]);
    }

    /**
     * Make a DELETE request to the service.
     */
    protected function delete(string $endpoint): ResponseInterface
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    /**
     * Make an HTTP request to the service.
     */
    protected function makeRequest(string $method, string $endpoint, array $options = []): ResponseInterface
    {
        $url = '/'.ltrim($endpoint, '/');
        $startTime = microtime(true);

        try {
            $response = $this->client->request($method, $url, $options);
            $duration = microtime(true) - $startTime;

            $this->logRequestCompletion($method, $url, $options, $response, $duration);

            return $response;
        } catch (GuzzleException $e) {
            $duration = microtime(true) - $startTime;
            $this->logError($method, $url, $options, $e, $duration);
            throw $e;
        }
    }

    /**
     * Log outgoing requests.
     */
    protected function logRequest(RequestInterface $request): void
    {
        Log::info('Service request initiated', [
            'service' => static::class,
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
            'request_id' => $this->defaultHeaders['X-Request-ID'],
        ]);
    }

    /**
     * Log incoming responses.
     */
    protected function logResponse(ResponseInterface $response): void
    {
        Log::info('Service response received', [
            'service' => static::class,
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
            'request_id' => $this->defaultHeaders['X-Request-ID'],
        ]);
    }

    /**
     * Log completed requests with timing.
     */
    protected function logRequestCompletion(string $method, string $url, array $options, ResponseInterface $response, float $duration): void
    {
        Log::info('Service request completed', [
            'service' => static::class,
            'method' => $method,
            'url' => $this->baseUrl . $url,
            'status' => $response->getStatusCode(),
            'duration' => round($duration * 1000, 2).'ms',
            'request_id' => $this->defaultHeaders['X-Request-ID'],
        ]);
    }

    /**
     * Log failed requests.
     */
    protected function logError(string $method, string $url, array $options, GuzzleException $e, float $duration): void
    {
        Log::error('Service request failed', [
            'service' => static::class,
            'method' => $method,
            'url' => $this->baseUrl . $url,
            'error' => $e->getMessage(),
            'duration' => round($duration * 1000, 2).'ms',
            'request_id' => $this->defaultHeaders['X-Request-ID'],
        ]);
    }

    /**
     * Generate a unique request ID for tracing.
     */
    protected function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * Check if the service is healthy.
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->get('/health');
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    /**
     * Get service information.
     */
    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->get('/info');
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $body = $response->getBody()->getContents();
                return json_decode($body, true);
            }
            return null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Helper method to decode JSON response.
     */
    protected function decodeJsonResponse(ResponseInterface $response): ?array
    {
        try {
            $body = $response->getBody()->getContents();
            return json_decode($body, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Helper method to check if response is successful.
     */
    protected function isSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
