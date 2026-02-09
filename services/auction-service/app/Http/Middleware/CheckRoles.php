<?php

namespace App\Http\Middleware;

use App\Http\Clients\AuthServiceClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckRoles
{
    protected AuthServiceClient $authService;

    public function __construct(AuthServiceClient $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        // Get user from request (set by AuthenticateUser middleware)
        $user = $request->attributes->get('user');
        $userId = $request->attributes->get('user_id');

        if (!$user || !$userId) {
            return $this->forbiddenResponse('User authentication required');
        }

        // Check if user has any of the required roles
        if (!$this->authService->hasRole($userId, $roles)) {
            $rolesList = implode(', ', $roles);
            return $this->forbiddenResponse("One of the following roles is required: {$rolesList}");
        }

        // Log the role check
        $this->authService->logAuctionActivity($userId, 'role.checked', [
            'required_roles' => $roles,
            'granted' => true,
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod()
        ]);

        return $next($request);
    }

    /**
     * Return forbidden response.
     */
    protected function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'INSUFFICIENT_ROLE'
        ], 403);
    }
}
