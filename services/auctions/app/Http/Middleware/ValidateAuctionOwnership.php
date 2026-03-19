<?php

namespace App\Http\Middleware;

use App\RPC\Adapters\AuthServiceAdapter;
use App\Models\Auction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ValidateAuctionOwnership
{
    protected AuthServiceAdapter $authService;

    public function __construct(AuthServiceAdapter $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $action = 'modify'): mixed
    {
        // Get user from request (set by AuthenticateUser middleware)
        $user = $request->attributes->get('user');
        $userId = $request->attributes->get('user_id');

        if (!$user || !$userId) {
            return $this->forbiddenResponse('User authentication required');
        }

        // Get auction ID from route parameters
        $auctionId = $request->route('id') ?? $request->route('auctionId');

        if (!$auctionId) {
            return $this->badRequestResponse('Auction ID is required');
        }

        try {
            // Find the auction
            $auction = Auction::findOrFail($auctionId);

            // Check if user is the auction owner
            if ($auction->created_by !== $userId) {
                // Check if user has admin permissions for override
                if (!$this->hasAdminOverride($userId, $action)) {
                    return $this->forbiddenResponse('You do not have permission to perform this action on this auction');
                }
            }

            // Check auction access permissions via auth service
            if (!$this->authService->canAccessAuction($userId, $auctionId, $action)) {
                return $this->forbiddenResponse('Access denied for this auction');
            }

            // Add auction to request for use in controller
            $request->attributes->set('auction', $auction);

            // Log the activity
            $this->authService->logAuctionActivity($userId, "auction.{$action}", [
                'auction_id' => $auctionId,
                'auction_title' => $auction->title,
                'action' => $action
            ]);

            return $next($request);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Auction not found');
        } catch (\Exception $e) {
            \Log::error('Error in auction ownership validation', [
                'user_id' => $userId,
                'auction_id' => $auctionId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);

            return $this->serverErrorResponse('Failed to validate auction access');
        }
    }

    /**
     * Check if user has admin override permissions.
     */
    protected function hasAdminOverride(int $userId, string $action): bool
    {
        // Check for admin or moderator roles
        $adminRoles = ['admin', 'moderator', 'auction_manager'];
        
        if ($this->authService->hasRole($userId, $adminRoles)) {
            return true;
        }

        // Check for specific auction management permissions
        $permissions = [
            'auction.manage_all',
            'auction.override_ownership',
            "auction.{$action}_any"
        ];

        foreach ($permissions as $permission) {
            if ($this->authService->hasPermission($userId, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return forbidden response.
     */
    protected function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ], 403);
    }

    /**
     * Return bad request response.
     */
    protected function badRequestResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'BAD_REQUEST'
        ], 400);
    }

    /**
     * Return not found response.
     */
    protected function notFoundResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'NOT_FOUND'
        ], 404);
    }

    /**
     * Return server error response.
     */
    protected function serverErrorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'SERVER_ERROR'
        ], 500);
    }
}
