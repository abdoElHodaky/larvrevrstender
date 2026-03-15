<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * RPC Authentication Middleware
 * 
 * Validates RPC requests using X-RPC-Token header for secure inter-service communication.
 * This middleware should be applied to all RPC endpoints to ensure only authenticated
 * services can make RPC calls.
 */
class RpcAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if this is an RPC request (should have X-RPC-Token header)
        $rpcToken = $request->header('X-RPC-Token');
        $callerService = $request->header('X-Caller-Service');
        $serviceName = $request->header('X-Service-Name');
        $requestId = $request->header('X-Request-ID');
        $traceId = $request->header('X-Trace-ID');

        // If no RPC token is provided, this might not be an RPC request
        if (!$rpcToken) {
            // Log the attempt for security monitoring
            Log::warning('RPC request without token', [
                'caller_service' => $callerService,
                'service_name' => $serviceName,
                'request_id' => $requestId,
                'trace_id' => $traceId,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request',
                    'data' => 'RPC token required for authentication'
                ],
                'id' => null
            ], 401);
        }

        // Validate the RPC token
        if (!$this->validateRpcToken($rpcToken, $callerService)) {
            Log::error('RPC authentication failed', [
                'caller_service' => $callerService,
                'service_name' => $serviceName,
                'request_id' => $requestId,
                'trace_id' => $traceId,
                'token_hash' => hash('sha256', $rpcToken), // Log hash for security
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request',
                    'data' => 'Invalid RPC token'
                ],
                'id' => null
            ], 401);
        }

        // Add RPC context to request attributes for use in controllers
        $request->attributes->set('rpc_authenticated', true);
        $request->attributes->set('caller_service', $callerService);
        $request->attributes->set('service_name', $serviceName);
        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('is_rpc_request', true);

        // Log successful RPC authentication
        Log::info('RPC request authenticated', [
            'caller_service' => $callerService,
            'service_name' => $serviceName,
            'request_id' => $requestId,
            'trace_id' => $traceId,
        ]);

        return $next($request);
    }

    /**
     * Validate RPC token against expected tokens for the calling service
     *
     * @param string $token
     * @param string|null $callerService
     * @return bool
     */
    private function validateRpcToken(string $token, ?string $callerService): bool
    {
        if (!$callerService) {
            return false;
        }

        // Get expected token for the calling service
        $expectedToken = $this->getExpectedTokenForService($callerService);
        
        if (!$expectedToken) {
            Log::warning('No expected token configured for calling service', [
                'caller_service' => $callerService
            ]);
            return false;
        }

        // Use hash_equals for timing-safe comparison
        return hash_equals($expectedToken, $token);
    }

    /**
     * Get expected RPC token for a calling service
     *
     * @param string $callerService
     * @return string|null
     */
    private function getExpectedTokenForService(string $callerService): ?string
    {
        // Normalize service name for environment variable lookup
        $normalizedServiceName = strtoupper(str_replace(['-', ' '], '_', $callerService));
        
        // Try to get from environment variables first
        $envKey = 'RPC_' . $normalizedServiceName . '_TOKEN';
        $token = env($envKey);
        
        if ($token) {
            return $token;
        }

        // Try alternative naming patterns
        $alternativeKeys = [
            'RPC_' . $normalizedServiceName . '_SERVICE_TOKEN',
            $normalizedServiceName . '_RPC_TOKEN',
            $normalizedServiceName . '_SERVICE_TOKEN'
        ];

        foreach ($alternativeKeys as $key) {
            $token = env($key);
            if ($token) {
                return $token;
            }
        }

        // Try to get from config file
        $configKey = strtolower(str_replace(['-', ' '], '_', $callerService));
        $configToken = config("rpc.services.{$configKey}.token");
        
        if ($configToken) {
            return $configToken;
        }

        // Try to get from current service's RPC configuration
        $currentServiceToken = config('rpc.auth.token');
        if ($currentServiceToken) {
            return $currentServiceToken;
        }

        return null;
    }
}
