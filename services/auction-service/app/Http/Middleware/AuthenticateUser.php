<?php

namespace App\Http\Middleware;

use App\RPC\Adapters\AuthServiceAdapter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthenticateUser
{
    protected AuthServiceAdapter $authService;

    public function __construct(AuthServiceAdapter $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$guards): mixed
    {
        // Extract token from Authorization header
        $token = $this->extractToken($request);

        if (!$token) {
            return $this->unauthorizedResponse('Missing authentication token');
        }

        // Validate token with auth service
        $user = $this->authService->validateJwtToken($token);

        if (!$user) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        // Add user information to request
        $request->merge(['authenticated_user' => $user]);
        $request->attributes->set('user_id', $user['id']);
        $request->attributes->set('user', $user);

        return $next($request);
    }

    /**
     * Extract token from request headers.
     */
    protected function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return null;
        }

        // Support both "Bearer token" and "token" formats
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $authHeader;
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ], 401);
    }
}
