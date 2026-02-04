<?php

namespace App\Services;

use App\Services\ServiceRegistry;
use App\Services\CircuitBreakerService;
use App\Services\RateLimiterService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\Response;
use Exception;

/**
 * RPC Gateway Service - Core routing and request handling
 */
class RpcGatewayService
{
    private ServiceRegistry $serviceRegistry;
    private CircuitBreakerService $circuitBreaker;
    private RateLimiterService $rateLimiter;

    public function __construct(
        ServiceRegistry $serviceRegistry,
        CircuitBreakerService $circuitBreaker,
        RateLimiterService $rateLimiter
    ) {
        $this->serviceRegistry = $serviceRegistry;
        $this->circuitBreaker = $circuitBreaker;
        $this->rateLimiter = $rateLimiter;
    }

    /**
     * Route RPC request to appropriate service
     */
    public function routeRequest(array $rpcRequest, array $context = []): array
    {
        $startTime = microtime(true);
        $correlationId = $context['correlation_id'] ?? $this->generateCorrelationId();
        
        try {
            // Extract method from RPC request
            $method = $rpcRequest['method'] ?? null;
            if (!$method) {
                return $this->createErrorResponse(-32600, 'Invalid Request: Missing method', $rpcRequest['id'] ?? null);
            }

            // Determine target service
            $serviceName = $this->serviceRegistry->getServiceByRpcMethod($method);
            if (!$serviceName) {
                return $this->createErrorResponse(-32601, 'Method not found: Unable to route method', $rpcRequest['id'] ?? null);
            }

            // Check rate limits
            if (!$this->rateLimiter->checkRateLimit($serviceName, $context)) {
                return $this->createErrorResponse(-32000, 'Rate limit exceeded', $rpcRequest['id'] ?? null);
            }

            // Check circuit breaker
            if (!$this->circuitBreaker->canExecute($serviceName)) {
                return $this->createErrorResponse(-32000, 'Service temporarily unavailable', $rpcRequest['id'] ?? null);
            }

            // Get healthy service instance
            $serviceInstance = $this->serviceRegistry->getHealthyServiceInstance($serviceName);
            if (!$serviceInstance) {
                $this->circuitBreaker->recordFailure($serviceName);
                return $this->createErrorResponse(-32000, 'Service unavailable', $rpcRequest['id'] ?? null);
            }

            // Forward request to service
            $response = $this->forwardRequest($serviceInstance, $rpcRequest, $correlationId);
            
            // Record success
            $this->circuitBreaker->recordSuccess($serviceName);
            
            // Log successful request
            $this->logRequest($serviceName, $method, $correlationId, microtime(true) - $startTime, true);
            
            return $response;

        } catch (Exception $e) {
            // Record failure
            if (isset($serviceName)) {
                $this->circuitBreaker->recordFailure($serviceName);
            }
            
            // Log error
            Log::error('RPC Gateway routing error', [
                'correlation_id' => $correlationId,
                'method' => $method ?? 'unknown',
                'service' => $serviceName ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->createErrorResponse(-32603, 'Internal error: ' . $e->getMessage(), $rpcRequest['id'] ?? null);
        }
    }

    /**
     * Forward request to target service
     */
    private function forwardRequest(array $serviceInstance, array $rpcRequest, string $correlationId): array
    {
        $url = $serviceInstance['url'] . '/rpc';
        $timeout = $serviceInstance['timeout'] ?? 30;
        $retries = $serviceInstance['retries'] ?? 3;

        $headers = [
            'Content-Type' => 'application/json',
            'X-Correlation-ID' => $correlationId,
            'X-Gateway-Request' => 'true',
            'X-Request-ID' => uniqid('gw_', true),
        ];

        $attempt = 0;
        $lastException = null;

        while ($attempt < $retries) {
            $attempt++;
            
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($timeout)
                    ->post($url, $rpcRequest);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    // Validate RPC response format
                    if ($this->isValidRpcResponse($responseData)) {
                        return $responseData;
                    } else {
                        throw new Exception('Invalid RPC response format from service');
                    }
                } else {
                    throw new Exception("HTTP {$response->status()}: {$response->body()}");
                }

            } catch (Exception $e) {
                $lastException = $e;
                
                Log::warning("RPC request attempt {$attempt} failed", [
                    'correlation_id' => $correlationId,
                    'service_url' => $url,
                    'attempt' => $attempt,
                    'max_retries' => $retries,
                    'error' => $e->getMessage(),
                ]);

                // Wait before retry (exponential backoff)
                if ($attempt < $retries) {
                    usleep(pow(2, $attempt - 1) * 100000); // 0.1s, 0.2s, 0.4s, etc.
                }
            }
        }

        // All retries failed
        throw new Exception("All {$retries} attempts failed. Last error: " . $lastException->getMessage());
    }

    /**
     * Validate RPC response format
     */
    private function isValidRpcResponse(array $response): bool
    {
        // Check for required RPC response fields
        return isset($response['jsonrpc']) && 
               $response['jsonrpc'] === '2.0' &&
               (isset($response['result']) || isset($response['error'])) &&
               isset($response['id']);
    }

    /**
     * Create standardized error response
     */
    private function createErrorResponse(int $code, string $message, $id = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
                'data' => [
                    'gateway' => true,
                    'timestamp' => now()->toISOString(),
                ]
            ],
            'id' => $id,
        ];
    }

    /**
     * Generate correlation ID for request tracking
     */
    private function generateCorrelationId(): string
    {
        return 'gw_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Log request details
     */
    private function logRequest(string $service, string $method, string $correlationId, float $duration, bool $success): void
    {
        Log::info('RPC Gateway request', [
            'correlation_id' => $correlationId,
            'service' => $service,
            'method' => $method,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Handle batch RPC requests
     */
    public function routeBatchRequest(array $batchRequest, array $context = []): array
    {
        if (!is_array($batchRequest) || empty($batchRequest)) {
            return $this->createErrorResponse(-32600, 'Invalid Request: Empty batch', null);
        }

        $responses = [];
        $correlationId = $context['correlation_id'] ?? $this->generateCorrelationId();

        foreach ($batchRequest as $index => $request) {
            if (!is_array($request)) {
                $responses[] = $this->createErrorResponse(-32600, 'Invalid Request: Batch item must be object', null);
                continue;
            }

            $requestContext = array_merge($context, [
                'correlation_id' => $correlationId . "_batch_{$index}",
                'batch_index' => $index,
            ]);

            $responses[] = $this->routeRequest($request, $requestContext);
        }

        return $responses;
    }

    /**
     * Get gateway statistics
     */
    public function getGatewayStats(): array
    {
        return [
            'services' => $this->serviceRegistry->getServicesHealthStatus(),
            'circuit_breakers' => $this->circuitBreaker->getAllStates(),
            'rate_limits' => $this->rateLimiter->getCurrentLimits(),
            'uptime' => $this->getUptime(),
            'version' => config('app.version', '1.0.0'),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get gateway uptime
     */
    private function getUptime(): array
    {
        $startTime = Cache::get('gateway_start_time', now());
        $uptime = now()->diffInSeconds($startTime);

        return [
            'started_at' => $startTime->toISOString(),
            'uptime_seconds' => $uptime,
            'uptime_human' => $this->formatUptime($uptime),
        ];
    }

    /**
     * Format uptime in human readable format
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }
}
