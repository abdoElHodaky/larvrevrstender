<?php

namespace App\Http\Clients;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class BaseServiceClient
{
    protected string $baseUrl;

    protected int $timeout;

    protected array $defaultHeaders;

    public function __construct(string $baseUrl, int $timeout = 30)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Service-Name' => config('app.name'),
            'X-Request-ID' => $this->generateRequestId(),
        ];
    }

    protected function get(string $endpoint, array $query = []): Response
    {
        return $this->makeRequest('GET', $endpoint, ['query' => $query]);
    }

    protected function post(string $endpoint, array $data = []): Response
    {
        return $this->makeRequest('POST', $endpoint, ['json' => $data]);
    }

    protected function put(string $endpoint, array $data = []): Response
    {
        return $this->makeRequest('PUT', $endpoint, ['json' => $data]);
    }

    protected function delete(string $endpoint): Response
    {
        return $this->makeRequest('DELETE', $endpoint);
    }

    protected function makeRequest(string $method, string $endpoint, array $options = []): Response
    {
        $url = $this->baseUrl.'/'.ltrim($endpoint, '/');
        $startTime = microtime(true);

        try {
            $response = Http::withHeaders($this->defaultHeaders)
                ->timeout($this->timeout)
                ->retry(3, 1000)
                ->{strtolower($method)}($url, $options['json'] ?? [], $options['query'] ?? []);

            $duration = microtime(true) - $startTime;
            $this->logRequest($method, $url, $options, $response, $duration);

            return $response;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logError($method, $url, $options, $e, $duration);
            throw $e;
        }
    }

    protected function logRequest(string $method, string $url, array $options, Response $response, float $duration): void
    {
        Log::info('Service request completed', [
            'service' => static::class,
            'method' => $method,
            'url' => $url,
            'status' => $response->status(),
            'duration' => round($duration * 1000, 2).'ms',
            'request_id' => $this->defaultHeaders['X-Request-ID'],
        ]);
    }

    protected function logError(string $method, string $url, array $options, \Exception $e, float $duration): void
    {
        Log::error('Service request failed', [
            'service' => static::class,
            'method' => $method,
            'url' => $url,
            'error' => $e->getMessage(),
            'duration' => round($duration * 1000, 2).'ms',
            'request_id' => $this->defaultHeaders['X-Request-ID'],
        ]);
    }

    protected function generateRequestId(): string
    {
        return 'req_' . bin2hex(random_bytes(16));
    }

    public function healthCheck(): bool
    {
        try {
            $response = $this->get('/health');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getServiceInfo(): ?array
    {
        try {
            $response = $this->get('/info');

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
