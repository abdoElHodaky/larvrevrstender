<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * RPC Client for Cross-Service Communication
 * 
 * Provides a unified interface for making RPC calls to other services
 * using service discovery, retry logic, and circuit breaker patterns.
 */
class RpcClient
{
    private ServiceDiscoveryClient $serviceDiscovery;
    private array $config;
    private array $circuitBreakers = [];

    public function __construct(ServiceDiscoveryClient $serviceDiscovery, array $config = [])
    {
        $this->serviceDiscovery = $serviceDiscovery;
        $this->config = array_merge([
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1000, // milliseconds
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_timeout' => 60, // seconds
            'enable_compression' => true,
            'correlation_id_header' => 'X-Correlation-ID'
        ], $config);
    }

    /**
     * Make an RPC call to a service
     *
     * @param string $serviceName The target service name
     * @param string $method The RPC method (e.g., 'user.getUser')
     * @param array $params The method parameters
     * @param array $options Additional options for this call
     * @return array The RPC response
     * @throws Exception
     */
    public function call(string $serviceName, string $method, array $params = [], array $options = []): array
    {
        $correlationId = $options['correlation_id'] ?? $this->generateCorrelationId();
        $timeout = $options['timeout'] ?? $this->config['timeout'];
        
        // Check circuit breaker
        if ($this->isCircuitBreakerOpen($serviceName)) {
            throw new Exception("Circuit breaker is open for service: {$serviceName}");
        }

        $startTime = microtime(true);
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->config['retry_attempts']) {
            $attempt++;
            
            try {
                // Get service endpoint from service discovery
                $serviceInfo = $this->serviceDiscovery->getService($serviceName);
                if (!$serviceInfo) {
                    throw new Exception("Service not found in registry: {$serviceName}");
                }

                // Prepare RPC request
                $request = [
                    'jsonrpc' => '2.0',
                    'method' => $method,
                    'params' => $params,
                    'id' => $correlationId
                ];

                // Make the RPC call
                $response = $this->makeHttpRequest($serviceInfo, $request, $timeout, $correlationId);
                
                // Reset circuit breaker on success
                $this->recordSuccess($serviceName);
                
                // Log successful call
                $executionTime = (microtime(true) - $startTime) * 1000;
                Log::info('RPC call successful', [
                    'service' => $serviceName,
                    'method' => $method,
                    'attempt' => $attempt,
                    'execution_time_ms' => round($executionTime, 2),
                    'correlation_id' => $correlationId
                ]);

                return $this->parseResponse($response);

            } catch (Exception $e) {
                $lastException = $e;
                
                // Record failure for circuit breaker
                $this->recordFailure($serviceName);
                
                Log::warning('RPC call attempt failed', [
                    'service' => $serviceName,
                    'method' => $method,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'correlation_id' => $correlationId
                ]);

                // Don't retry on certain errors
                if ($this->shouldNotRetry($e)) {
                    break;
                }

                // Wait before retry (exponential backoff)
                if ($attempt < $this->config['retry_attempts']) {
                    $delay = $this->config['retry_delay'] * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                }
            }
        }

        // All attempts failed
        $executionTime = (microtime(true) - $startTime) * 1000;
        Log::error('RPC call failed after all attempts', [
            'service' => $serviceName,
            'method' => $method,
            'attempts' => $attempt,
            'execution_time_ms' => round($executionTime, 2),
            'final_error' => $lastException->getMessage(),
            'correlation_id' => $correlationId
        ]);

        throw new Exception(
            "RPC call to {$serviceName}.{$method} failed after {$attempt} attempts: " . 
            $lastException->getMessage()
        );
    }

    /**
     * Make a batch RPC call to a service
     *
     * @param string $serviceName The target service name
     * @param array $requests Array of RPC requests
     * @param array $options Additional options
     * @return array The batch RPC response
     */
    public function batchCall(string $serviceName, array $requests, array $options = []): array
    {
        $correlationId = $options['correlation_id'] ?? $this->generateCorrelationId();
        
        // Add jsonrpc and id to each request if missing
        $formattedRequests = collect($requests)->map(function ($request, $index) use ($correlationId) {
            return array_merge([
                'jsonrpc' => '2.0',
                'id' => $correlationId . '_' . $index
            ], $request);
        })->toArray();

        return $this->call($serviceName, 'batch', $formattedRequests, $options);
    }

    /**
     * Make HTTP request to service
     */
    private function makeHttpRequest(array $serviceInfo, array $request, int $timeout, string $correlationId): array
    {
        $url = $this->buildServiceUrl($serviceInfo);
        
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            $this->config['correlation_id_header'] => $correlationId
        ];

        // Add compression if enabled
        if ($this->config['enable_compression']) {
            $headers['Accept-Encoding'] = 'gzip, deflate';
        }

        // Use Laravel HTTP client
        $response = \Illuminate\Support\Facades\Http::timeout($timeout)
            ->withHeaders($headers)
            ->post($url, $request);

        if (!$response->successful()) {
            throw new Exception(
                "HTTP request failed with status {$response->status()}: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Parse RPC response and handle errors
     */
    private function parseResponse(array $response): array
    {
        // Handle RPC error response
        if (isset($response['error'])) {
            $error = $response['error'];
            throw new Exception(
                "RPC error [{$error['code']}]: {$error['message']}" . 
                (isset($error['data']) ? ' - ' . json_encode($error['data']) : '')
            );
        }

        // Return successful result
        return $response['result'] ?? [];
    }

    /**
     * Build service URL from service info
     */
    private function buildServiceUrl(array $serviceInfo): string
    {
        $protocol = $serviceInfo['protocol'] ?? 'http';
        $host = $serviceInfo['host'];
        $port = $serviceInfo['port'];
        $path = $serviceInfo['rpc_path'] ?? '/rpc';

        return "{$protocol}://{$host}:{$port}{$path}";
    }

    /**
     * Check if circuit breaker is open for a service
     */
    private function isCircuitBreakerOpen(string $serviceName): bool
    {
        if (!isset($this->circuitBreakers[$serviceName])) {
            return false;
        }

        $breaker = $this->circuitBreakers[$serviceName];
        
        // Check if circuit breaker timeout has passed
        if (time() - $breaker['opened_at'] > $this->config['circuit_breaker_timeout']) {
            // Reset circuit breaker (half-open state)
            unset($this->circuitBreakers[$serviceName]);
            return false;
        }

        return $breaker['failures'] >= $this->config['circuit_breaker_threshold'];
    }

    /**
     * Record successful call for circuit breaker
     */
    private function recordSuccess(string $serviceName): void
    {
        // Reset circuit breaker on success
        if (isset($this->circuitBreakers[$serviceName])) {
            unset($this->circuitBreakers[$serviceName]);
        }
    }

    /**
     * Record failed call for circuit breaker
     */
    private function recordFailure(string $serviceName): void
    {
        if (!isset($this->circuitBreakers[$serviceName])) {
            $this->circuitBreakers[$serviceName] = [
                'failures' => 0,
                'opened_at' => time()
            ];
        }

        $this->circuitBreakers[$serviceName]['failures']++;
        $this->circuitBreakers[$serviceName]['opened_at'] = time();
    }

    /**
     * Check if error should not trigger retry
     */
    private function shouldNotRetry(Exception $e): bool
    {
        $message = $e->getMessage();
        
        // Don't retry on validation errors or method not found
        return str_contains($message, 'RPC error [-32602]') || // Invalid params
               str_contains($message, 'RPC error [-32601]') || // Method not found
               str_contains($message, 'Service not found');
    }

    /**
     * Generate correlation ID
     */
    private function generateCorrelationId(): string
    {
        return 'rpc_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Get circuit breaker status for all services
     */
    public function getCircuitBreakerStatus(): array
    {
        return collect($this->circuitBreakers)->map(function ($breaker, $service) {
            return [
                'service' => $service,
                'failures' => $breaker['failures'],
                'opened_at' => $breaker['opened_at'],
                'is_open' => $this->isCircuitBreakerOpen($service),
                'time_until_reset' => max(0, $this->config['circuit_breaker_timeout'] - (time() - $breaker['opened_at']))
            ];
        })->values()->toArray();
    }

    /**
     * Update client configuration
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
