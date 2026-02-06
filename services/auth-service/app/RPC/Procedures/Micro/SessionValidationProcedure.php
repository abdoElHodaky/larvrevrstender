<?php

namespace App\RPC\Procedures\Micro;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

/**
 * Session Validation Micro Procedure
 *
 * Provides session validation methods that can be imported into service procedures
 * via use statements for modular session management.
 */
trait SessionValidationProcedure
{
    /**
     * Validate Laravel session by session ID
     */
    public function validateLaravelSession(array $params): array
    {
        try {
            $sessionId = $params['session_id'] ?? null;

            if (! $sessionId) {
                return [
                    'success' => false,
                    'message' => 'Session ID is required',
                    'data' => null,
                ];
            }

            // Check if session exists in database
            $sessionData = DB::table('sessions')
                ->where('id', $sessionId)
                ->first();

            if (! $sessionData) {
                return [
                    'success' => false,
                    'message' => 'Session not found',
                    'data' => null,
                ];
            }

            // Check if session is expired
            $lastActivity = Carbon::createFromTimestamp($sessionData->last_activity);
            $sessionLifetime = config('session.lifetime', 1440); // minutes

            if ($lastActivity->addMinutes($sessionLifetime)->isPast()) {
                // Clean up expired session
                DB::table('sessions')->where('id', $sessionId)->delete();

                return [
                    'success' => false,
                    'message' => 'Session expired',
                    'data' => null,
                ];
            }

            // Decode session payload
            $payload = unserialize(base64_decode($sessionData->payload));

            return [
                'success' => true,
                'message' => 'Session is valid',
                'data' => [
                    'session_id' => $sessionId,
                    'user_id' => $sessionData->user_id,
                    'ip_address' => $sessionData->ip_address,
                    'user_agent' => $sessionData->user_agent,
                    'last_activity' => $lastActivity->toISOString(),
                    'payload' => $payload,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Session validation failed', [
                'session_id' => $params['session_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Session validation failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Validate session token for API requests
     */
    public function validateSessionToken(array $params): array
    {
        try {
            $token = $params['token'] ?? null;

            if (! $token) {
                return [
                    'success' => false,
                    'message' => 'Token is required',
                    'data' => null,
                ];
            }

            // For Sanctum tokens, validate through Laravel's built-in mechanism
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

            if (! $personalAccessToken) {
                return [
                    'success' => false,
                    'message' => 'Invalid token',
                    'data' => null,
                ];
            }

            // Check if token is expired
            if ($personalAccessToken->expires_at && $personalAccessToken->expires_at->isPast()) {
                $personalAccessToken->delete();

                return [
                    'success' => false,
                    'message' => 'Token expired',
                    'data' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Token is valid',
                'data' => [
                    'token_id' => $personalAccessToken->id,
                    'user_id' => $personalAccessToken->tokenable_id,
                    'name' => $personalAccessToken->name,
                    'abilities' => $personalAccessToken->abilities,
                    'last_used_at' => $personalAccessToken->last_used_at?->toISOString(),
                    'expires_at' => $personalAccessToken->expires_at?->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Token validation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Token validation failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Check session permissions for specific actions
     */
    public function checkSessionPermissions(array $params): array
    {
        try {
            $sessionId = $params['session_id'] ?? null;
            $permission = $params['permission'] ?? null;

            if (! $sessionId || ! $permission) {
                return [
                    'success' => false,
                    'message' => 'Session ID and permission are required',
                    'data' => null,
                ];
            }

            // Validate session first
            $sessionValidation = $this->validateLaravelSession(['session_id' => $sessionId]);

            if (! $sessionValidation['success']) {
                return $sessionValidation;
            }

            $userId = $sessionValidation['data']['user_id'];

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'No user associated with session',
                    'data' => null,
                ];
            }

            // Get user and check permissions
            $user = \App\Models\User::find($userId);

            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'data' => null,
                ];
            }

            // Check user status
            if ($user->status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'User account is not active',
                    'data' => null,
                ];
            }

            // Basic permission check based on user type and role
            $hasPermission = $this->checkUserPermission($user, $permission);

            return [
                'success' => true,
                'message' => $hasPermission ? 'Permission granted' : 'Permission denied',
                'data' => [
                    'user_id' => $userId,
                    'permission' => $permission,
                    'granted' => $hasPermission,
                    'user_type' => $user->type,
                    'user_role' => $user->role ?? 'user',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Permission check failed', [
                'session_id' => $params['session_id'] ?? null,
                'permission' => $params['permission'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Permission check failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Helper method to check user permissions
     *
     * @param  \App\Models\User  $user
     */
    private function checkUserPermission($user, string $permission): bool
    {
        // Basic permission logic - can be extended based on requirements
        $permissions = [
            'admin' => ['*'], // Admin has all permissions
            'merchant' => ['manage_products', 'view_orders', 'manage_profile'],
            'customer' => ['place_orders', 'view_profile', 'manage_profile'],
        ];

        $userType = $user->type ?? 'customer';
        $userPermissions = $permissions[$userType] ?? [];

        // Check for wildcard permission (admin)
        if (in_array('*', $userPermissions)) {
            return true;
        }

        // Check specific permission
        return in_array($permission, $userPermissions);
    }
}
