<?php

namespace App\Http\Middleware;

use App\Http\Clients\AuthServiceClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckPermissions
{
    protected AuthServiceClient $authService;

    public function __construct(AuthServiceClient $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        // Get user from request (set by AuthenticateUser middleware)
        $user = $request->attributes->get('user');
        $userId = $request->attributes->get('user_id');

        if (!$user || !$userId) {
            return $this->forbiddenResponse('User authentication required');
        }

        // Check if user has the required permission
        if (!$this->authService->hasPermission($userId, $permission)) {
            return $this->forbiddenResponse("Permission '{$permission}' is required for this action");
        }

        // Log the permission check
        $this->authService->logAuctionActivity($userId, 'permission.checked', [
            'permission' => $permission,
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
            'error_code' => 'INSUFFICIENT_PERMISSIONS'
        ], 403);
    }
}
