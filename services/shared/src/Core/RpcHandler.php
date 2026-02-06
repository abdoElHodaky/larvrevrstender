<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * RPC Handler for Cross-Service Procedures
 * 
 * Handles RPC requests with high-performance binary protocols,
 * load balancing, and service discovery for internal microservice communication.
 */
class RpcHandler
{
    private ProcedureEngine $engine;
    private array $config;
    private array $serviceRegistry = [];

    public function __construct(ProcedureEngine $engine, array $config = [])
    {
        $this->engine = $engine;
        $this->config = array_merge([
            'protocol' => 'json-rpc', // json-rpc, msgpack, protobuf
            'timeout' => 30,
            'max_connections' => 100,
            'enable_compression' => true,
            'compression_threshold' => 1024, // bytes
            'enable_load_balancing' => true,
            'load_balancing_strategy' => 'round_robin', // round_robin, least_connections, weighted
            'enable_circuit_breaker' => true,
            'circuit_breaker_threshold' => 5, // failures before opening circuit
            'circuit_breaker_timeout' => 60, // seconds
            'enable_service_discovery' => true,
            'heartbeat_interval' => 30, // seconds
        ], $config);
    }

    /**
     * Handle RPC request
     *
     * @param array $request
     * @param array $context
     * @return array
     */
    public function handle(array $request, array $context = []): array
    {
        $startTime = microtime(true);
        $traceId = $context['trace_id'] ?? $this->generateTraceId();

        try {
            // Validate RPC request format
            $validation = $this->validateRpcRequest($request);
            if (!$validation['success']) {
                return $this->formatRpcError(
                    -32600, // Invalid Request
                    $validation['message'],
                    $request['id'] ?? null,
                    $traceId
                );
            }

            $procedure = $request['method'] ?? '';
            $params = $request['params'] ?? [];
            $requestId = $request['id'] ?? null;

            // Parse procedure and method
            $procedureInfo = $this->parseProcedureMethod($procedure);
            if (!$procedureInfo['success']) {
                return $this->formatRpcError(
                    -32601, // Method not found
                    $procedureInfo['message'],
                    $requestId,
                    $traceId
                );
            }

            // Prepare context for execution
            $executionContext = array_merge($context, [
                'trace_id' => $traceId,
                'protocol' => 'rpc',
                'request_id' => $requestId,
                'timestamp' => now()->toISOString()
            ]);

            // Execute procedure
            $result = $this->engine->execute(
                $procedureInfo['procedure'],
                $procedureInfo['method'],
                $params,
                $executionContext
            );

            $executionTime = (microtime(true) - $startTime) * 1000;

            // Log RPC request
            Log::info('RPC request processed', [
                'procedure' => $procedureInfo['procedure'],
                'method' => $procedureInfo['method'],
                'request_id' => $requestId,
                'execution_time_ms' => $executionTime,
                'success' => $result['success'],
                'trace_id' => $traceId
            ]);

            // Format response
            if ($result['success']) {
                return $this->formatRpcSuccess(
                    $result['data'],
                    $requestId,
                    $traceId,
                    $executionTime
                );
            } else {
                return $this->formatRpcError(
                    -32603, // Internal error
                    $result['error'] ?? 'Procedure execution failed',
                    $requestId,
                    $traceId,
                    $result['metadata'] ?? null
                );
            }

        } catch (Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('RPC request failed', [
                'request' => $request,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time_ms' => $executionTime,
                'trace_id' => $traceId
            ]);

            return $this->formatRpcError(
                -32603, // Internal error
                'Internal server error',
                $request['id'] ?? null,
                $traceId,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Handle batch RPC requests
     *
     * @param array $requests
     * @param array $context
     * @return array
     */
    public function handleBatch(array $requests, array $context = []): array
    {
        if (empty($requests)) {
            return $this->formatRpcError(-32600, 'Invalid Request', null);
        }

        $responses = [];
        $batchTraceId = $context['trace_id'] ?? $this->generateTraceId();

        foreach ($requests as $index => $request) {
            $requestContext = array_merge($context, [
                'batch_trace_id' => $batchTraceId,
                'batch_index' => $index,
                'trace_id' => $this->generateTraceId()
            ]);

            $response = $this->handle($request, $requestContext);
            
            // Only include response if request has an ID (not a notification)
            if (isset($request['id'])) {
                $responses[] = $response;
            }
        }

        return $responses;
    }

    /**
     * Validate RPC request format
     *
     * @param array $request
     * @return array
     */
    private function validateRpcRequest(array $request): array
    {
        // Check for required fields
        if (!isset($request['method'])) {
            return [
                'success' => false,
                'message' => 'Missing required field: method'
            ];
        }

        // Validate JSON-RPC version if specified
        if (isset($request['jsonrpc']) && $request['jsonrpc'] !== '2.0') {
            return [
                'success' => false,
                'message' => 'Unsupported JSON-RPC version'
            ];
        }

        // Validate method name format
        if (!is_string($request['method']) || empty($request['method'])) {
            return [
                'success' => false,
                'message' => 'Method must be a non-empty string'
            ];
        }

        // Validate params if present
        if (isset($request['params']) && !is_array($request['params'])) {
            return [
                'success' => false,
                'message' => 'Params must be an array'
            ];
        }

        return ['success' => true];
    }

    /**
     * Parse procedure and method from RPC method string
     *
     * @param string $method
     * @return array
     */
    private function parseProcedureMethod(string $method): array
    {
        // Expected format: procedure.method or procedure/method
        $separators = ['.', '/'];
        $parts = null;

        foreach ($separators as $separator) {
            if (strpos($method, $separator) !== false) {
                $parts = explode($separator, $method, 2);
                break;
            }
        }

        if (!$parts || count($parts) !== 2) {
            return [
                'success' => false,
                'message' => "Invalid method format. Expected: 'procedure.method' or 'procedure/method'"
            ];
        }

        return [
            'success' => true,
            'procedure' => $parts[0],
            'method' => $parts[1]
        ];
    }

    /**
     * Format RPC success response
     *
     * @param mixed $result
     * @param mixed $id
     * @param string $traceId
     * @param float|null $executionTime
     * @return array
     */
    private function formatRpcSuccess($result, $id, string $traceId, ?float $executionTime = null): array
    {
        $response = [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
            'meta' => [
                'trace_id' => $traceId,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($executionTime !== null) {
            $response['meta']['execution_time_ms'] = round($executionTime, 2);
        }

        return $response;
    }

    /**
     * Format RPC error response
     *
     * @param int $code
     * @param string $message
     * @param mixed $id
     * @param string|null $traceId
     * @param mixed $data
     * @return array
     */
    private function formatRpcError(int $code, string $message, $id, ?string $traceId = null, $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        $response = [
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => $id
        ];

        if ($traceId) {
            $response['meta'] = [
                'trace_id' => $traceId,
                'timestamp' => now()->toISOString()
            ];
        }

        return $response;
    }

    /**
     * Register a service in the service registry
     *
     * @param string $serviceName
     * @param array $serviceInfo
     * @return void
     */
    public function registerService(string $serviceName, array $serviceInfo): void
    {
        $this->serviceRegistry[$serviceName] = array_merge([
            'host' => 'localhost',
            'port' => 8080,
            'protocol' => 'http',
            'health_check_url' => '/health',
            'weight' => 1,
            'max_connections' => 100,
            'registered_at' => now()->toISOString(),
            'last_heartbeat' => now()->toISOString(),
            'status' => 'healthy'
        ], $serviceInfo);

        Log::info('Service registered', [
            'service' => $serviceName,
            'info' => $this->serviceRegistry[$serviceName]
        ]);
    }

    /**
     * Unregister a service from the service registry
     *
     * @param string $serviceName
     * @return void
     */
    public function unregisterService(string $serviceName): void
    {
        if (isset($this->serviceRegistry[$serviceName])) {
            unset($this->serviceRegistry[$serviceName]);
            
            Log::info('Service unregistered', [
                'service' => $serviceName
            ]);
        }
    }

    /**
     * Get service information from registry
     *
     * @param string $serviceName
     * @return array|null
     */
    public function getService(string $serviceName): ?array
    {
        return $this->serviceRegistry[$serviceName] ?? null;
    }

    /**
     * Get all registered services
     *
     * @return array
     */
    public function getAllServices(): array
    {
        return $this->serviceRegistry;
    }

    /**
     * Update service heartbeat
     *
     * @param string $serviceName
     * @return bool
     */
    public function updateHeartbeat(string $serviceName): bool
    {
        if (isset($this->serviceRegistry[$serviceName])) {
            $this->serviceRegistry[$serviceName]['last_heartbeat'] = now()->toISOString();
            $this->serviceRegistry[$serviceName]['status'] = 'healthy';
            return true;
        }
        return false;
    }

    /**
     * Check service health and update status
     *
     * @param string $serviceName
     * @return array
     */
    public function checkServiceHealth(string $serviceName): array
    {
        if (!isset($this->serviceRegistry[$serviceName])) {
            return [
                'status' => 'not_found',
                'message' => 'Service not registered'
            ];
        }

        $service = $this->serviceRegistry[$serviceName];
        $lastHeartbeat = strtotime($service['last_heartbeat']);
        $heartbeatAge = time() - $lastHeartbeat;

        if ($heartbeatAge > $this->config['heartbeat_interval'] * 2) {
            $this->serviceRegistry[$serviceName]['status'] = 'unhealthy';
            return [
                'status' => 'unhealthy',
                'message' => 'Service heartbeat timeout',
                'last_heartbeat_age' => $heartbeatAge
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Service is healthy',
            'last_heartbeat_age' => $heartbeatAge
        ];
    }

    /**
     * Generate trace ID
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return 'rpc_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Get handler configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update handler configuration
     *
     * @param array $config
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get service registry statistics
     *
     * @return array
     */
    public function getRegistryStats(): array
    {
        $stats = [
            'total_services' => count($this->serviceRegistry),
            'healthy_services' => 0,
            'unhealthy_services' => 0,
            'services' => []
        ];

        foreach ($this->serviceRegistry as $name => $service) {
            $healthCheck = $this->checkServiceHealth($name);
            $stats['services'][$name] = [
                'status' => $healthCheck['status'],
                'last_heartbeat' => $service['last_heartbeat'],
                'registered_at' => $service['registered_at']
            ];

            if ($healthCheck['status'] === 'healthy') {
                $stats['healthy_services']++;
            } else {
                $stats['unhealthy_services']++;
            }
        }

        return $stats;
    }
}
