<?php

namespace App\Procedures;

use Shared\Procedures\CrossServiceProcedure;
use Shared\Procedures\Micro\ValidationProcedure;
use Shared\Procedures\Micro\SecurityProcedure;
use Exception;

/**
 * Gateway Procedure
 * 
 * Main gateway procedure that serves as the entry point for all external requests
 * and links to the cross-service infrastructure for internal operations.
 */
class GatewayProcedure extends CrossServiceProcedure
{
    // Additional gateway-specific procedures using use statements
    use ValidationProcedure;
    use SecurityProcedure;

    /**
     * Route request to appropriate service
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function routeRequest(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service' => ['required' => true, 'type' => 'string'],
                'endpoint' => ['required' => true, 'type' => 'string'],
                'method' => ['required' => true, 'type' => 'string'],
                'data' => ['type' => 'array'],
                'headers' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $service = $params['service'];
            $endpoint = $params['endpoint'];
            $method = strtoupper($params['method']);
            $data = $params['data'] ?? [];
            $headers = $params['headers'] ?? [];

            // Step 1: Validate the request
            $validationResult = $this->validateApiRequest([
                'request_data' => $data,
                'endpoint' => $endpoint,
                'method' => $method
            ], $context);

            if (!$validationResult['success']) {
                return $this->errorResponse('Request validation failed', $validationResult['data']);
            }

            // Step 2: Authenticate if token provided
            if (isset($headers['Authorization'])) {
                $token = str_replace('Bearer ', '', $headers['Authorization']);
                $authResult = $this->authenticateToken([
                    'token' => $token,
                    'service' => $service
                ], $context);

                if (!$authResult['success']) {
                    return $this->errorResponse('Authentication failed', $authResult['data']);
                }

                $context['user'] = $authResult['data'];
            }

            // Step 3: Apply rate limiting
            $identifier = $context['user']['user_id'] ?? $context['ip_address'] ?? 'anonymous';
            $rateLimitResult = $this->applyRateLimit([
                'identifier' => $identifier,
                'action' => "{$service}.{$endpoint}"
            ], $context);

            if (!$rateLimitResult['success']) {
                return $this->errorResponse('Rate limit exceeded', $rateLimitResult['data']);
            }

            // Step 4: Route to target service
            $routingResult = $this->forwardToService($service, $endpoint, $method, $data, $headers, $context);

            // Step 5: Log the request
            $this->logGatewayRequest($service, $endpoint, $method, $routingResult['success'], $context);

            return $routingResult;

        } catch (Exception $e) {
            $this->log('error', 'Gateway routing failed', [
                'error' => $e->getMessage(),
                'service' => $params['service'] ?? null,
                'endpoint' => $params['endpoint'] ?? null
            ]);

            return $this->errorResponse('Gateway routing failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle service discovery
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function discoverService(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['required' => true, 'type' => 'string']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'];

            // Get service information from registry
            $registryResult = $this->getServiceRegistry();
            
            if (!$registryResult['success']) {
                return $this->errorResponse('Service registry unavailable', $registryResult);
            }

            $services = $registryResult['data']['services'] ?? [];
            
            if (!isset($services[$serviceName])) {
                return $this->errorResponse('Service not found', [
                    'service_name' => $serviceName,
                    'available_services' => array_keys($services)
                ]);
            }

            $serviceInfo = $services[$serviceName];

            return $this->successResponse([
                'service_name' => $serviceName,
                'service_info' => $serviceInfo,
                'discovered_at' => now()->toISOString()
            ], 'Service discovered successfully');

        } catch (Exception $e) {
            $this->log('error', 'Service discovery failed', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null
            ]);

            return $this->errorResponse('Service discovery failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle cross-service event publishing through gateway
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function publishGatewayEvent(array $params, array $context = []): array
    {
        try {
            // Add gateway context to the event
            $params['source_service'] = 'gateway-service';
            $params['gateway_context'] = [
                'ip_address' => $context['ip_address'] ?? null,
                'user_agent' => $context['user_agent'] ?? null,
                'user_id' => $context['user']['user_id'] ?? null,
                'timestamp' => now()->toISOString()
            ];

            // Use the inherited event publishing functionality
            return $this->publishEvent($params, $context);

        } catch (Exception $e) {
            $this->log('error', 'Gateway event publishing failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Gateway event publishing failed: ' . $e->getMessage());
        }
    }

    /**
     * Gateway health check with cross-service status
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function gatewayHealthCheck(array $params = [], array $context = []): array
    {
        try {
            // Get cross-service health
            $crossServiceHealth = $this->healthCheck($params, $context);
            
            // Add gateway-specific health checks
            $gatewayChecks = [
                'gateway_service' => [
                    'status' => 'healthy',
                    'version' => '1.0.0',
                    'uptime' => $this->getUptime()
                ],
                'routing' => [
                    'status' => 'healthy',
                    'active_routes' => $this->getActiveRoutes()
                ],
                'load_balancer' => [
                    'status' => 'healthy',
                    'strategy' => 'round_robin'
                ]
            ];

            // Combine health checks
            $healthData = $crossServiceHealth['data'];
            $healthData['checks'] = array_merge($healthData['checks'], $gatewayChecks);
            
            // Determine overall status
            $overallStatus = $crossServiceHealth['data']['status'];
            foreach ($gatewayChecks as $check) {
                if ($check['status'] !== 'healthy') {
                    $overallStatus = 'degraded';
                    break;
                }
            }
            
            $healthData['status'] = $overallStatus;

            return $this->successResponse($healthData, "Gateway and cross-service infrastructure is {$overallStatus}");

        } catch (Exception $e) {
            $this->log('error', 'Gateway health check failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Gateway health check failed: ' . $e->getMessage());
        }
    }

    /**
     * Forward request to target service
     *
     * @param string $service
     * @param string $endpoint
     * @param string $method
     * @param array $data
     * @param array $headers
     * @param array $context
     * @return array
     */
    private function forwardToService(string $service, string $endpoint, string $method, array $data, array $headers, array $context): array
    {
        try {
            // Discover service
            $discoveryResult = $this->discoverService(['service_name' => $service], $context);
            
            if (!$discoveryResult['success']) {
                return $discoveryResult;
            }

            $serviceInfo = $discoveryResult['data']['service_info'];
            $serviceUrl = "{$serviceInfo['protocol']}://{$serviceInfo['host']}:{$serviceInfo['port']}{$endpoint}";

            // Add gateway headers
            $headers['X-Gateway-Request'] = 'true';
            $headers['X-Gateway-Service'] = 'gateway-service';
            $headers['X-Trace-ID'] = $context['trace_id'] ?? $this->generateTraceId();

            // Make HTTP request to service
            $response = $this->makeHttpRequest($serviceUrl, $method, $data, $headers);

            // Record metrics
            $this->recordMetric('gateway_request_forwarded', 1, [
                'service' => $service,
                'endpoint' => $endpoint,
                'method' => $method,
                'success' => $response['success']
            ]);

            return $response;

        } catch (Exception $e) {
            return $this->errorResponse('Service forwarding failed: ' . $e->getMessage());
        }
    }

    /**
     * Make HTTP request to service
     *
     * @param string $url
     * @param string $method
     * @param array $data
     * @param array $headers
     * @return array
     */
    private function makeHttpRequest(string $url, string $method, array $data, array $headers): array
    {
        // This would use a proper HTTP client like Guzzle
        // For now, return a mock response
        return $this->successResponse([
            'url' => $url,
            'method' => $method,
            'data' => $data,
            'headers' => $headers,
            'response' => 'Mock service response',
            'forwarded_at' => now()->toISOString()
        ], 'Request forwarded successfully');
    }

    /**
     * Log gateway request
     *
     * @param string $service
     * @param string $endpoint
     * @param string $method
     * @param bool $success
     * @param array $context
     * @return void
     */
    private function logGatewayRequest(string $service, string $endpoint, string $method, bool $success, array $context): void
    {
        $this->log('info', 'Gateway request processed', [
            'service' => $service,
            'endpoint' => $endpoint,
            'method' => $method,
            'success' => $success,
            'user_id' => $context['user']['user_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'trace_id' => $context['trace_id'] ?? null
        ]);
    }

    /**
     * Generate trace ID
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return 'gateway_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Get active routes
     *
     * @return array
     */
    private function getActiveRoutes(): array
    {
        return [
            'auth-service' => '/api/auth/*',
            'user-service' => '/api/users/*',
            'order-service' => '/api/orders/*',
            'payment-service' => '/api/payments/*',
            'notification-service' => '/api/notifications/*'
        ];
    }

    /**
     * Get uptime (placeholder)
     *
     * @return string
     */
    private function getUptime(): string
    {
        return 'Unknown';
    }
}

