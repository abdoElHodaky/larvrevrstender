<?php

namespace Shared\Core;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * REST API Handler for Cross-Service Procedures
 * 
 * Handles REST API requests and routes them to appropriate procedures
 * with standardized request/response formatting and error handling.
 */
class RestHandler
{
    private ProcedureEngine $engine;
    private array $config;

    public function __construct(ProcedureEngine $engine, array $config = [])
    {
        $this->engine = $engine;
        $this->config = array_merge([
            'enable_cors' => true,
            'cors_origins' => ['*'],
            'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'cors_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'rate_limit' => 1000, // requests per minute
            'enable_compression' => true,
            'max_request_size' => 10485760, // 10MB
        ], $config);
    }

    /**
     * Handle REST API request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $traceId = $request->header('X-Trace-ID') ?? $this->generateTraceId();

        try {
            // Add CORS headers if enabled
            $headers = [];
            if ($this->config['enable_cors']) {
                $headers = $this->getCorsHeaders($request);
            }

            // Handle preflight OPTIONS request
            if ($request->getMethod() === 'OPTIONS') {
                return response()->json(['status' => 'ok'], 200, $headers);
            }

            // Validate request size
            if ($this->exceedsMaxRequestSize($request)) {
                return $this->errorResponse(
                    'Request payload too large',
                    ['max_size' => $this->config['max_request_size']],
                    413,
                    $headers,
                    $traceId
                );
            }

            // Parse route to extract procedure and method
            $routeInfo = $this->parseRoute($request);
            if (!$routeInfo['success']) {
                return $this->errorResponse(
                    $routeInfo['message'],
                    null,
                    404,
                    $headers,
                    $traceId
                );
            }

            // Prepare parameters from request
            $params = $this->extractParameters($request);
            
            // Prepare context
            $context = [
                'trace_id' => $traceId,
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'headers' => $request->headers->all(),
                'timestamp' => now()->toISOString()
            ];

            // Execute procedure
            $result = $this->engine->execute(
                $routeInfo['procedure'],
                $routeInfo['method'],
                $params,
                $context
            );

            $executionTime = (microtime(true) - $startTime) * 1000;

            // Log request
            Log::info('REST API request processed', [
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'procedure' => $routeInfo['procedure'],
                'procedure_method' => $routeInfo['method'],
                'execution_time_ms' => $executionTime,
                'success' => $result['success'],
                'trace_id' => $traceId
            ]);

            // Return response
            if ($result['success']) {
                return $this->successResponse(
                    $result['data'],
                    $result['metadata']['message'] ?? 'Request processed successfully',
                    200,
                    $headers,
                    $traceId,
                    $executionTime
                );
            } else {
                return $this->errorResponse(
                    $result['error'] ?? 'Procedure execution failed',
                    $result['metadata'] ?? null,
                    500,
                    $headers,
                    $traceId
                );
            }

        } catch (Exception $e) {
            $executionTime = (microtime(true) - $startTime) * 1000;

            Log::error('REST API request failed', [
                'method' => $request->getMethod(),
                'uri' => $request->getRequestUri(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time_ms' => $executionTime,
                'trace_id' => $traceId
            ]);

            return $this->errorResponse(
                'Internal server error',
                ['error' => $e->getMessage()],
                500,
                $headers ?? [],
                $traceId
            );
        }
    }

    /**
     * Parse REST route to extract procedure and method
     *
     * @param Request $request
     * @return array
     */
    private function parseRoute(Request $request): array
    {
        $path = trim($request->getPathInfo(), '/');
        $segments = explode('/', $path);

        // Expected format: /api/v1/{procedure}/{method}
        // or: /api/{procedure}/{method}
        if (count($segments) < 2) {
            return [
                'success' => false,
                'message' => 'Invalid route format. Expected: /api/{procedure}/{method}'
            ];
        }

        // Remove 'api' and version if present
        if ($segments[0] === 'api') {
            array_shift($segments);
            
            // Remove version if present (v1, v2, etc.)
            if (!empty($segments) && preg_match('/^v\d+$/', $segments[0])) {
                array_shift($segments);
            }
        }

        if (count($segments) < 2) {
            return [
                'success' => false,
                'message' => 'Invalid route format. Expected: /api/{procedure}/{method}'
            ];
        }

        $procedure = $segments[0];
        $method = $segments[1];

        // Convert HTTP method to procedure method if needed
        $httpMethod = $request->getMethod();
        $procedureMethod = $this->mapHttpMethodToProcedureMethod($httpMethod, $method);

        return [
            'success' => true,
            'procedure' => $procedure,
            'method' => $procedureMethod,
            'original_method' => $method
        ];
    }

    /**
     * Map HTTP method to procedure method
     *
     * @param string $httpMethod
     * @param string $baseMethod
     * @return string
     */
    private function mapHttpMethodToProcedureMethod(string $httpMethod, string $baseMethod): string
    {
        return match ($httpMethod) {
            'GET' => 'get' . ucfirst($baseMethod),
            'POST' => 'create' . ucfirst($baseMethod),
            'PUT' => 'update' . ucfirst($baseMethod),
            'DELETE' => 'delete' . ucfirst($baseMethod),
            default => $baseMethod
        };
    }

    /**
     * Extract parameters from request
     *
     * @param Request $request
     * @return array
     */
    private function extractParameters(Request $request): array
    {
        $params = [];

        // Get query parameters
        $params = array_merge($params, $request->query());

        // Get request body
        if ($request->isJson()) {
            $params = array_merge($params, $request->json()->all());
        } else {
            $params = array_merge($params, $request->all());
        }

        // Get route parameters
        $params = array_merge($params, $request->route()->parameters ?? []);

        return $params;
    }

    /**
     * Get CORS headers
     *
     * @param Request $request
     * @return array
     */
    private function getCorsHeaders(Request $request): array
    {
        $headers = [
            'Access-Control-Allow-Methods' => implode(', ', $this->config['cors_methods']),
            'Access-Control-Allow-Headers' => implode(', ', $this->config['cors_headers']),
            'Access-Control-Max-Age' => '86400', // 24 hours
        ];

        $origin = $request->header('Origin');
        if ($origin && (in_array('*', $this->config['cors_origins']) || in_array($origin, $this->config['cors_origins']))) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        return $headers;
    }

    /**
     * Check if request exceeds maximum size
     *
     * @param Request $request
     * @return bool
     */
    private function exceedsMaxRequestSize(Request $request): bool
    {
        $contentLength = $request->header('Content-Length');
        return $contentLength && $contentLength > $this->config['max_request_size'];
    }

    /**
     * Generate trace ID
     *
     * @return string
     */
    private function generateTraceId(): string
    {
        return 'rest_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Format success response
     *
     * @param mixed $data
     * @param string $message
     * @param int $statusCode
     * @param array $headers
     * @param string $traceId
     * @param float|null $executionTime
     * @return JsonResponse
     */
    private function successResponse(
        $data,
        string $message = 'Success',
        int $statusCode = 200,
        array $headers = [],
        string $traceId = null,
        ?float $executionTime = null
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'trace_id' => $traceId,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($executionTime !== null) {
            $response['meta']['execution_time_ms'] = round($executionTime, 2);
        }

        $headers['X-Trace-ID'] = $traceId;

        return response()->json($response, $statusCode, $headers);
    }

    /**
     * Format error response
     *
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @param string $traceId
     * @return JsonResponse
     */
    private function errorResponse(
        string $message,
        $data = null,
        int $statusCode = 400,
        array $headers = [],
        string $traceId = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'error' => $data,
            'meta' => [
                'trace_id' => $traceId,
                'timestamp' => now()->toISOString()
            ]
        ];

        $headers['X-Trace-ID'] = $traceId;

        return response()->json($response, $statusCode, $headers);
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
}
