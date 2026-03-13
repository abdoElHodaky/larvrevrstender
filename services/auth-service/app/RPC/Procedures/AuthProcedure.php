<?php

namespace App\RPC\Procedures;

use App\Http\Controllers\AuthController;
use App\Services\Shared\ActivityRpcService;
use Shared\Core\RpcClient;
use App\RPC\BaseProcedure;
use App\RPC\Procedures\Micro\SessionAnalyticsProcedure;
use App\RPC\Procedures\Micro\SessionManagementProcedure;
use App\RPC\Procedures\Micro\SessionSecurityProcedure;
use App\RPC\Procedures\Micro\SessionValidationProcedure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\AuthUser;
use App\Models\AuthRole;
use App\Models\AuthUserRole;
use App\Models\AuthPermission;
use App\Models\TokenInvalidation;
use Shared\Traits\RpcMonitoring;
use Shared\Traits\RpcCacheable;
use Shared\Enums\RpcMethodType;

class AuthProcedure extends BaseProcedure
{
    use SessionAnalyticsProcedure, SessionManagementProcedure, SessionSecurityProcedure, SessionValidationProcedure;
    use RpcMonitoring, RpcCacheable;

    public function __construct(
        private ActivityRpcService $activityRpcService,
        private RpcClient $rpcClient
    ) {}

    /**
     * Validate authentication token
     */
    public function validateToken(array $params): array
    {
        return $this->withCaching(__METHOD__, $params, 60, ['auth', 'token'], function() use ($params) {
            return $this->withMonitoring(__METHOD__, RpcMethodType::READ, $params, function() use ($params) {
                $this->validate($params, [
                    'token' => 'required|string',
                ]);

                $controller = new AuthController;
                $request = new Request(['token' => $params['token']]);

                $result = $controller->validateToken($request);

                return [
                    'valid' => $result->getData()->valid ?? false,
                    'user_id' => $result->getData()->user_id ?? null,
                    'expires_at' => $result->getData()->expires_at ?? null,
                ];
            });
        });
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController;
            $result = $controller->getUserPermissions($params['user_id']);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check specific permission for user
     */
    public function checkPermission(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'permission' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request([
                'user_id' => $params['user_id'],
                'permission' => $params['permission'],
            ]);

            $result = $controller->checkPermission($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'has_permission' => $result->getData()->has_permission ?? false,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user roles
     */
    public function getUserRoles(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController;
            $result = $controller->getUserRoles($params['user_id']);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check specific role for user
     */
    public function checkRole(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'role' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request([
                'user_id' => $params['user_id'],
                'role' => $params['role'],
            ]);

            $result = $controller->checkRole($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'has_role' => $result->getData()->has_role ?? false,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Create session for user
     */
    public function createSession(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'device_info' => 'nullable|array',
                'ip_address' => 'nullable|string',
            ]);

            $controller = new AuthController;
            $request = new Request($params);

            $result = $controller->createSession($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Invalidate session
     */
    public function invalidateSession(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'session_id' => 'required|string',
            ]);

            $controller = new AuthController;
            $result = $controller->invalidateSession($params['session_id']);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'success' => $result->getStatusCode() === 200,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Log user activity
     */
    public function logActivity(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'action' => 'required|string',
                'description' => 'nullable|string',
                'metadata' => 'nullable|array',
            ]);

            $controller = new AuthController;
            $request = new Request($params);

            $result = $controller->logActivity($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'success' => $result->getStatusCode() === 201,
                'activity_id' => $result->getData()->id ?? null,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user information
     */
    public function getUser(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            // Call user-service RPC to get user information using RPC client
            $result = $this->rpcClient->call(
                'user-service',
                'user.getUser',
                ['user_id' => $params['user_id']],
                ['correlation_id' => $this->getCorrelationId()]
            );

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Refresh authentication token
     */
    public function refreshToken(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'refresh_token' => 'required|string',
            ]);

            $controller = new AuthController;
            $request = new Request(['refresh_token' => $params['refresh_token']]);

            $result = $controller->refresh($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result->getData(true);
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Logout user (invalidate all sessions)
     */
    public function logout(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
            ]);

            $controller = new AuthController;
            $request = new Request(['user_id' => $params['user_id']]);

            $result = $controller->logout($request);

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return [
                'success' => $result->getStatusCode() === 200,
            ];
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user activities
     */
    public function getUserActivities(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'limit' => 'nullable|integer|min:1|max:100',
                'offset' => 'nullable|integer|min:0',
            ]);

            $result = $this->activityRpcService->getUserActivities(
                $params['user_id'],
                [],
                $params['limit'] ?? 15,
                1
            );

            $this->logPerformance(__METHOD__, $params, $result, $startTime);

            return $result;
        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle user creation notification from user-service (PHP 8.3 + Laravel 12 optimized).
     */
    public function userCreated(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'email' => 'required|email',
                'name' => 'required|string',
                'status' => 'sometimes|string'
            ]);

            // Extract with named arguments (PHP 8.3)
            $userData = [
                'user_id' => $params['user_id'],
                'email' => $params['email'],
                'name' => $params['name'],
                'status' => $params['status'] ?? 'active'
            ];

            Log::info('User creation notification received via RPC', $userData);

            // Use Eloquent instead of DB::table (Laravel 12)
            $authUser = \App\Models\AuthUser::updateOrCreate(
                ['user_id' => $userData['user_id']],
                $userData
            );

            $result = [
                'success' => true,
                'message' => 'User creation notification processed',
                'user_id' => $userData['user_id'],
                'auth_user_created' => $authUser->wasRecentlyCreated
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle user update synchronization from user-service.
     */
    public function userSync(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'changes' => 'required|array'
            ]);

            $userId = $params['user_id'];
            $changes = $params['changes'];

            Log::info('User sync notification received via RPC', [
                'user_id' => $userId,
                'changes' => $changes
            ]);

            // Update auth user record
            $updated = AuthUser::where('user_id', $userId)
                ->update(array_merge($changes, ['updated_at' => now()]));

            // If email changed, invalidate all tokens for security
            if (isset($changes['email'])) {
                $this->revokeAllUserTokens(['user_id' => $userId]);
            }

            $result = [
                'success' => true,
                'message' => 'User sync completed',
                'user_id' => $userId,
                'updated' => $updated
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllUserTokens(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer'
            ]);

            $userId = $params['user_id'];

            Log::info('Token revocation request received via RPC', [
                'user_id' => $userId
            ]);

            // Revoke all personal access tokens for the user
            $revokedCount = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $userId)
                ->where('tokenable_type', \App\Models\User::class)
                ->delete();

            // Also clear any session data if using database sessions
            \DB::table('sessions')->where('user_id', $userId)->delete();

            $result = [
                'success' => true,
                'message' => 'All user tokens revoked',
                'user_id' => $userId,
                'revoked_tokens' => $revokedCount
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Revoke all sessions for a user.
     */
    public function revokeAllUserSessions(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer'
            ]);

            $userId = $params['user_id'];

            Log::info('Session revocation request received via RPC', [
                'user_id' => $userId
            ]);

            // Clear all sessions for the user
            $revokedCount = \DB::table('sessions')->where('user_id', $userId)->delete();

            // Also clear any remember tokens
            AuthUser::where('user_id', $userId)->update([
                'remember_token' => null,
                'updated_at' => now()
            ]);

            $result = [
                'success' => true,
                'message' => 'All user sessions revoked',
                'user_id' => $userId,
                'revoked_sessions' => $revokedCount
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Invalidate user tokens due to permission/role changes.
     */
    public function invalidateUserTokens(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'reason' => 'required|string',
                'timestamp' => 'sometimes|string'
            ]);

            $userId = $params['user_id'];
            $reason = $params['reason'];
            $timestamp = $params['timestamp'] ?? now()->toISOString();

            Log::info('Token invalidation request received via RPC', [
                'user_id' => $userId,
                'reason' => $reason,
                'timestamp' => $timestamp
            ]);

            // Mark tokens as invalid by updating their abilities or deleting them
            $invalidatedCount = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $userId)
                ->where('tokenable_type', \App\Models\User::class)
                ->update([
                    'abilities' => ['invalidated'],
                    'updated_at' => now()
                ]);

            // Log the invalidation reason
            TokenInvalidation::create([
                'user_id' => $userId,
                'token_type' => 'personal_access_token',
                'reason' => $reason,
                'invalidated_at' => $timestamp,
                'metadata' => [
                    'invalidated_tokens' => $invalidatedCount,
                    'method' => 'invalidateUserTokens'
                ]
            ]);

            $result = [
                'success' => true,
                'message' => 'User tokens invalidated',
                'user_id' => $userId,
                'reason' => $reason,
                'invalidated_tokens' => $invalidatedCount
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Verify user authentication status.
     */
    public function verifyUserAuth(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'token' => 'sometimes|string'
            ]);

            $userId = $params['user_id'];
            $token = $params['token'] ?? null;

            // Check if user exists in auth system
            $authUser = AuthUser::where('user_id', $userId)->first();

            if (!$authUser) {
                return [
                    'success' => false,
                    'error' => 'User not found in auth system'
                ];
            }

            // Check if user is active
            if ($authUser->status !== 'active') {
                return [
                    'success' => false,
                    'error' => 'User account is not active',
                    'status' => $authUser->status
                ];
            }

            // If token provided, verify it
            if ($token) {
                $tokenRecord = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if (!$tokenRecord || $tokenRecord->tokenable_id != $userId) {
                    return [
                        'success' => false,
                        'error' => 'Invalid token'
                    ];
                }
            }

            $result = [
                'success' => true,
                'message' => 'User authentication verified',
                'user_id' => $userId,
                'status' => $authUser->status,
                'verified_at' => now()->toISOString()
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Get user's active sessions.
     */
    public function getUserSessions(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer'
            ]);

            $userId = $params['user_id'];

            // Get active sessions
            $sessions = \DB::table('sessions')
                ->where('user_id', $userId)
                ->select(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->get()
                ->map(function ($session) {
                    return [
                        'session_id' => $session->id,
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent,
                        'last_activity' => date('Y-m-d H:i:s', $session->last_activity)
                    ];
                });

            // Get active tokens
            $tokens = \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $userId)
                ->where('tokenable_type', \App\Models\User::class)
                ->select(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
                ->get()
                ->map(function ($token) {
                    return [
                        'token_id' => $token->id,
                        'name' => $token->name,
                        'abilities' => $token->abilities,
                        'last_used_at' => $token->last_used_at,
                        'created_at' => $token->created_at
                    ];
                });

            $result = [
                'success' => true,
                'message' => 'User sessions retrieved',
                'user_id' => $userId,
                'sessions' => $sessions,
                'tokens' => $tokens
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle user status change notification.
     */
    public function userStatusChanged(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'new_status' => 'required|string',
                'old_status' => 'sometimes|string',
                'timestamp' => 'sometimes|string'
            ]);

            $userId = $params['user_id'];
            $oldStatus = $params['old_status'] ?? null;
            $newStatus = $params['new_status'];
            $timestamp = $params['timestamp'] ?? now()->toISOString();

            Log::info('User status change notification received via RPC', [
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'timestamp' => $timestamp
            ]);

            // Update auth user status
            AuthUser::where('user_id', $userId)->update([
                'status' => $newStatus,
                'updated_at' => now()
            ]);

            // If user is deactivated or suspended, revoke all tokens
            if (in_array($newStatus, ['inactive', 'suspended', 'banned'])) {
                $this->revokeAllUserTokens(['user_id' => $userId]);
                $this->revokeAllUserSessions(['user_id' => $userId]);
            }

            $result = [
                'success' => true,
                'message' => 'User status change processed',
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle role creation notification from user-service.
     */
    public function roleCreated(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'role_id' => 'required|integer',
                'name' => 'required|string',
                'display_name' => 'required|string',
                'permissions' => 'sometimes|array'
            ]);

            $roleId = $params['role_id'];
            $name = $params['name'];
            $displayName = $params['display_name'];
            $permissions = $params['permissions'] ?? [];

            Log::info('Role creation notification received via RPC', [
                'role_id' => $roleId,
                'name' => $name,
                'permissions' => $permissions
            ]);

            // Store role reference for auth operations
            $authRole = AuthRole::updateOrCreate(
                ['role_id' => $roleId],
                [
                    'name' => $name,
                    'display_name' => $displayName,
                    'permissions' => $permissions,
                ]
            );

            $result = [
                'success' => true,
                'message' => 'Role creation notification processed',
                'role_id' => $roleId,
                'auth_role_created' => $authRole
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle role update synchronization from user-service.
     */
    public function roleSync(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'role_id' => 'required|integer',
                'changes' => 'required|array'
            ]);

            $roleId = $params['role_id'];
            $changes = $params['changes'];

            Log::info('Role sync notification received via RPC', [
                'role_id' => $roleId,
                'changes' => $changes
            ]);

            // Update auth role record
            $updated = AuthRole::where('role_id', $roleId)
                ->update(array_merge($changes, ['updated_at' => now()]));

            $result = [
                'success' => true,
                'message' => 'Role sync completed',
                'role_id' => $roleId,
                'updated' => $updated
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle role deletion notification from user-service.
     */
    public function roleDeleted(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'role_id' => 'required|integer',
                'role_name' => 'required|string'
            ]);

            $roleId = $params['role_id'];
            $roleName = $params['role_name'];

            Log::info('Role deletion notification received via RPC', [
                'role_id' => $roleId,
                'role_name' => $roleName
            ]);

            // Remove role from auth system
            $deleted = AuthRole::where('role_id', $roleId)->delete();

            // Invalidate tokens for users who had this role
            $usersWithRole = AuthUserRole::where('role_id', $roleId)->get();
            foreach ($usersWithRole as $userRole) {
                $this->invalidateUserTokens([
                    'user_id' => $userRole->user_id,
                    'reason' => 'role_deleted',
                    'timestamp' => now()->toISOString()
                ]);
            }

            // Remove role assignments
            AuthUserRole::where('role_id', $roleId)->delete();

            $result = [
                'success' => true,
                'message' => 'Role deletion processed',
                'role_id' => $roleId,
                'deleted' => $deleted,
                'affected_users' => count($usersWithRole)
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle role permissions synchronization from user-service (PHP 8.3 + Laravel 12 optimized).
     */
    public function rolePermissionsSync(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'role_id' => 'required|integer',
                'action' => 'required|string|in:assign,revoke,sync',
                'permissions' => 'required|array'
            ]);

            // Extract parameters with destructuring (PHP 8.3)
            ['role_id' => $roleId, 'action' => $action, 'permissions' => $permissions] = $params;

            Log::info('Role permissions sync notification received via RPC', compact('roleId', 'action', 'permissions'));

            // Use Eloquent model instead of DB::table (Laravel 12)
            $authRole = \App\Models\AuthRole::where('role_id', $roleId)->firstOrFail();
            
            // Use model method with match expression (PHP 8.3)
            $newPermissions = $authRole->updatePermissions($action, $permissions);

            // Bulk invalidate tokens using model method (PHP 8.3 + Laravel 12)
            $affectedUsers = $authRole->invalidateUserTokens('role_permissions_changed');

            $result = [
                'success' => true,
                'message' => 'Role permissions sync completed',
                'role_id' => $roleId,
                'action' => $action,
                'new_permissions' => $newPermissions,
                'affected_users' => $affectedUsers
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle permission creation notification from user-service.
     */
    public function permissionCreated(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'permission_id' => 'required|integer',
                'name' => 'required|string',
                'display_name' => 'required|string',
                'category' => 'sometimes|string'
            ]);

            $permissionId = $params['permission_id'];
            $name = $params['name'];
            $displayName = $params['display_name'];
            $category = $params['category'] ?? null;

            Log::info('Permission creation notification received via RPC', [
                'permission_id' => $permissionId,
                'name' => $name,
                'category' => $category
            ]);

            // Store permission reference for auth operations
            $authPermission = AuthPermission::updateOrCreate(
                ['permission_id' => $permissionId],
                [
                    'permission_name' => $name,
                    'description' => $displayName,
                    'category' => $category,
                ]
            );

            $result = [
                'success' => true,
                'message' => 'Permission creation notification processed',
                'permission_id' => $permissionId,
                'auth_permission_created' => $authPermission
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Handle permission deletion notification from user-service.
     */
    public function permissionDeleted(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'permission_id' => 'required|integer',
                'permission_name' => 'required|string'
            ]);

            $permissionId = $params['permission_id'];
            $permissionName = $params['permission_name'];

            Log::info('Permission deletion notification received via RPC', [
                'permission_id' => $permissionId,
                'permission_name' => $permissionName
            ]);

            // Remove permission from auth system
            $deleted = AuthPermission::where('permission_id', $permissionId)->delete();

            // Update roles that had this permission using collection methods (PHP 8.3)
            $rolesWithPermission = AuthRole::get();
            
            $updateResults = collect($rolesWithPermission)
                ->filter(fn($role) => in_array($permissionName, $role->permissions ?? []))
                ->map(function ($role) use ($permissionName) {
                    $newPermissions = array_diff($role->permissions ?? [], [$permissionName]);
                    
                    // Update role permissions
                    AuthRole::where('id', $role->id)->update([
                        'permissions' => $newPermissions,
                        'updated_at' => now()
                    ]);
                    
                    // Get users with this role and invalidate their tokens
                    $usersWithRole = AuthUserRole::where('role_id', $role->role_id)->get();
                    
                    $usersWithRole->each(fn($userRole) => 
                        $this->invalidateUserTokens([
                            'user_id' => $userRole->user_id,
                            'reason' => 'permission_deleted',
                            'timestamp' => now()->toISOString()
                        ])
                    );
                    
                    return [
                        'role_id' => $role->role_id,
                        'affected_users' => $usersWithRole->count()
                    ];
                });
            
            $affectedRoles = $updateResults->count();
            $affectedUsers = $updateResults->sum('affected_users');

            $result = [
                'success' => true,
                'message' => 'Permission deletion processed',
                'permission_id' => $permissionId,
                'deleted' => $deleted,
                'affected_roles' => $affectedRoles,
                'affected_users' => $affectedUsers
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }

    /**
     * Check user permissions via RPC (PHP 8.3 + Laravel 12 optimized).
     */
    public function checkUserPermissions(array $params): array
    {
        $startTime = microtime(true);

        try {
            $this->validate($params, [
                'user_id' => 'required|integer',
                'permissions' => 'required|array'
            ]);

            // Extract with destructuring (PHP 8.3)
            ['user_id' => $userId, 'permissions' => $permissionsToCheck] = $params;

            // Use Eloquent model instead of DB::table + foreach (Laravel 12 + PHP 8.3)
            $authUser = \App\Models\AuthUser::where('user_id', $userId)->first();
            
            if (!$authUser) {
                return [
                    'success' => false,
                    'error' => 'User not found in auth system',
                    'user_id' => $userId
                ];
            }

            // Use model method instead of manual foreach loops (PHP 8.3)
            $permissionResults = $authUser->hasPermissions($permissionsToCheck);
            $allUserPermissions = $authUser->getAllPermissions();

            $result = [
                'success' => true,
                'user_id' => $userId,
                'permissions' => $permissionResults,
                'all_user_permissions' => $allUserPermissions
            ];

            $this->logPerformance(__METHOD__, $params, $result, $startTime);
            return $result;

        } catch (\Exception $e) {
            $this->handleError($e, __METHOD__, $params);
        }
    }
}
