<?php

declare(strict_types=1);

namespace Shared\RPC\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shared\RPC\Clients\AuthServiceClient;

/**
 * RPC Authentication Middleware - PHP 8.3 & Laravel 12 Implementation
 * 
 * Validates RPC tokens for service-to-service communication
 * using the auth service for token verification.
 */
class RpcAuthMiddleware
{
    public function __construct(
        private readonly AuthServiceClient $authClient
    ) {}

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip authentication for health checks
        if ($request->is('health') || $request->is('info')) {
            return $next($request);
        }

        // Get RPC token from header
        $rpcToken = $request->header('X-RPC-Token');
        $serviceId = $request->header('X-Service-Client');

        if (!$rpcToken || !$serviceId) {
            return response()->json([
                'error' => 'Missing RPC authentication headers',
                'required_headers' => ['X-RPC-Token', 'X-Service-Client'],
            ], 401);
        }

        // Validate token with auth service
        try {
            $response = $this->authClient->validateRpcToken($rpcToken, $serviceId);
            
            if (!$response->isSuccessful()) {
                return response()->json([
                    'error' => 'Invalid RPC token',
                    'details' => $response->getErrorMessage(),
                ], 401);
            }

            // Add validated service info to request
            $request->attributes->set('rpc_service_id', $serviceId);
            $request->attributes->set('rpc_token_data', $response->getData());

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'RPC authentication failed',
                'message' => $e->getMessage(),
            ], 500);
        }

        return $next($request);
    }
}
