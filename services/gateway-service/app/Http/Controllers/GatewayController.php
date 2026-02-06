<?php

namespace App\Http\Controllers;

use App\Procedures\GatewayProcedure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Gateway Controller
 * 
 * Main controller that handles all gateway operations and routes requests
 * to appropriate services through the cross-service infrastructure.
 */
class GatewayController extends Controller
{
    private GatewayProcedure $gateway;

    public function __construct()
    {
        $this->gateway = new GatewayProcedure();
    }

    /**
     * Route request to appropriate service
     *
     * @param Request $request
     * @param string $service
     * @param string $path
     * @return JsonResponse
     */
    public function routeToService(Request $request, string $service, string $path = ''): JsonResponse
    {
        $context = $this->buildContext($request);
        
        $result = $this->gateway->routeRequest([
            'service' => $service,
            'endpoint' => '/' . ltrim($path, '/'),
            'method' => $request->getMethod(),
            'data' => $request->all(),
            'headers' => $request->headers->all()
        ], $context);

        return $this->formatResponse($result);
    }

    /**
     * Gateway health check
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function health(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        $result = $this->gateway->gatewayHealthCheck([], $context);
        
        return $this->formatResponse($result);
    }

    /**
     * Discover service
     *
     * @param Request $request
     * @param string $serviceName
     * @return JsonResponse
     */
    public function discoverService(Request $request, string $serviceName): JsonResponse
    {
        $context = $this->buildContext($request);
        
        $result = $this->gateway->discoverService([
            'service_name' => $serviceName
        ], $context);

        return $this->formatResponse($result);
    }

    /**
     * Publish event through gateway
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function publishEvent(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        
        $result = $this->gateway->publishGatewayEvent($request->all(), $context);

        return $this->formatResponse($result);
    }

    /**
     * Validate request data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validateRequest(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        
        $result = $this->gateway->validateApiRequest([
            'request_data' => $request->all(),
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod()
        ], $context);

        return $this->formatResponse($result);
    }

    /**
     * Authenticate token
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        $token = $request->bearerToken() ?? $request->input('token');
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Token is required'
            ], 400);
        }

        $result = $this->gateway->authenticateToken([
            'token' => $token,
            'service' => 'gateway-service'
        ], $context);

        return $this->formatResponse($result);
    }

    /**
     * Get service registry
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getServiceRegistry(Request $request): JsonResponse
    {
        $context = $this->buildContext($request);
        $result = $this->gateway->getServiceRegistry([], $context);
        
        return $this->formatResponse($result);
    }

    /**
     * Cache operations through gateway
     *
     * @param Request $request
     * @param string $operation
     * @return JsonResponse
     */
    public function cacheOperation(Request $request, string $operation): JsonResponse
    {
        $context = $this->buildContext($request);
        
        switch ($operation) {
            case 'set':
                $result = $this->gateway->cacheSet($request->all(), $context);
                break;
            case 'get':
                $result = $this->gateway->cacheGet($request->all(), $context);
                break;
            case 'delete':
                $result = $this->gateway->cacheDelete($request->all(), $context);
                break;
            case 'exists':
                $result = $this->gateway->cacheExists($request->all(), $context);
                break;
            case 'stats':
                $result = $this->gateway->cacheStats($request->all(), $context);
                break;
            case 'flush':
                $result = $this->gateway->cacheFlush($request->all(), $context);
                break;
            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid cache operation'
                ], 400);
        }

        return $this->formatResponse($result);
    }

    /**
     * Build request context
     *
     * @param Request $request
     * @return array
     */
    private function buildContext(Request $request): array
    {
        return [
            'trace_id' => $request->header('X-Trace-ID') ?? $this->generateTraceId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Format response
     *
     * @param array $result
     * @return JsonResponse
     */
    private function formatResponse(array $result): JsonResponse
    {
        $statusCode = $result['success'] ? 200 : 400;
        
        // Handle specific error cases
        if (!$result['success']) {
            $message = $result['message'] ?? 'Unknown error';
            
            if (strpos($message, 'Authentication failed') !== false) {
                $statusCode = 401;
            } elseif (strpos($message, 'Access denied') !== false || strpos($message, 'Authorization') !== false) {
                $statusCode = 403;
            } elseif (strpos($message, 'not found') !== false) {
                $statusCode = 404;
            } elseif (strpos($message, 'Rate limit') !== false) {
                $statusCode = 429;
            } elseif (strpos($message, 'Internal') !== false || strpos($message, 'failed') !== false) {
                $statusCode = 500;
            }
        }

        return response()->json($result, $statusCode);
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
}

