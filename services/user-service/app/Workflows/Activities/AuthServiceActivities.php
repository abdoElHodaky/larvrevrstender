<?php

namespace App\Workflows\Activities;

use Illuminate\Support\Facades\Log;
use Shared\Procedures\CrossServiceProcedure;
use Shared\Core\RpcHandler;
use Shared\Core\ProcedureEngine;
use Workflow\Activity;

class AuthServiceActivities extends Activity
{
    private RpcHandler $rpcHandler;
    private CrossServiceProcedure $crossService;

    public function __construct()
    {
        $engine = new ProcedureEngine();
        $this->rpcHandler = new RpcHandler($engine, [
            'protocol' => 'json-rpc',
            'timeout' => 30,
            'enable_circuit_breaker' => true,
            'enable_service_discovery' => true
        ]);
        $this->crossService = new CrossServiceProcedure();
    }

    /**
     * Notify auth-service about new user creation via RPC.
     */
    public function notifyUserCreated(array $userData): array
    {
        try {
            $rpcRequest = [
                'jsonrpc' => '2.0',
                'method' => 'auth.user.created',
                'params' => $userData,
                'id' => uniqid('user_created_', true)
            ];

            $response = $this->rpcHandler->handle($rpcRequest, [
                'service' => 'auth-service',
                'caller' => 'user-service',
                'trace_id' => uniqid('trace_', true)
            ]);

            if (isset($response['result'])) {
                Log::info('Auth service notified of user creation via RPC', [
                    'user_id' => $userData['user_id'],
                    'response' => $response['result']
                ]);

                return [
                    'success' => true,
                    'data' => $response['result'],
                    'message' => 'Auth service notified successfully via RPC'
                ];
            }

            Log::warning('Auth service RPC notification failed', [
                'user_id' => $userData['user_id'],
                'error' => $response['error'] ?? 'Unknown RPC error'
            ]);

            return [
                'success' => false,
                'error' => 'RPC notification failed',
                'rpc_error' => $response['error'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('Auth service RPC communication error', [
                'user_id' => $userData['user_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'RPC communication error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync user updates with auth-service via RPC.
     */
    public function syncUserUpdate(array $updateData): array
    {
        try {
            $rpcRequest = [
                'jsonrpc' => '2.0',
                'method' => 'auth.user.sync',
                'params' => $updateData,
                'id' => uniqid('user_sync_', true)
            ];

            $response = $this->rpcHandler->handle($rpcRequest, [
                'service' => 'auth-service',
                'caller' => 'user-service',
                'trace_id' => uniqid('trace_', true)
            ]);

            if (isset($response['result'])) {
                Log::info('Auth service synced user update via RPC', [
                    'user_id' => $updateData['user_id'],
                    'response' => $response['result']
                ]);

                return [
                    'success' => true,
                    'data' => $response['result'],
                    'message' => 'Auth service synced successfully via RPC'
                ];
            }

            Log::warning('Auth service RPC sync failed', [
                'user_id' => $updateData['user_id'],
                'error' => $response['error'] ?? 'Unknown RPC error'
            ]);

            return [
                'success' => false,
                'error' => 'RPC sync failed',
                'rpc_error' => $response['error'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('Auth service RPC sync error', [
                'user_id' => $updateData['user_id'],
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'RPC sync error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Revoke all user tokens in auth-service via RPC.
     */
    public function revokeAllUserTokens(int $userId): array
    {
        try {
            $rpcRequest = [
                'jsonrpc' => '2.0',
                'method' => 'auth.tokens.revokeAll',
                'params' => ['user_id' => $userId],
                'id' => uniqid('revoke_tokens_', true)
            ];

            $response = $this->rpcHandler->handle($rpcRequest, [
                'service' => 'auth-service',
                'caller' => 'user-service',
                'trace_id' => uniqid('trace_', true)
            ]);

            if (isset($response['result'])) {
                Log::info('All user tokens revoked via RPC', [
                    'user_id' => $userId,
                    'response' => $response['result']
                ]);

                return [
                    'success' => true,
                    'data' => $response['result'],
                    'message' => 'All tokens revoked successfully via RPC'
                ];
            }

            Log::warning('RPC token revocation failed', [
                'user_id' => $userId,
                'error' => $response['error'] ?? 'Unknown RPC error'
            ]);

            return [
                'success' => false,
                'error' => 'RPC token revocation failed',
                'rpc_error' => $response['error'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('RPC token revocation error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'RPC token revocation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Revoke all user sessions in auth-service.
     */
    public function revokeAllUserSessions(int $userId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->delete("{$this->authServiceUrl}/api/internal/users/{$userId}/sessions");

            if ($response->successful()) {
                Log::info('All user sessions revoked', [
                    'user_id' => $userId,
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'All sessions revoked successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Session revocation failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Session revocation error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Session revocation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Invalidate user tokens due to permission/role changes via RPC.
     */
    public function invalidateUserTokens(int $userId, string $reason): array
    {
        try {
            $rpcRequest = [
                'jsonrpc' => '2.0',
                'method' => 'auth.tokens.invalidate',
                'params' => [
                    'user_id' => $userId,
                    'reason' => $reason,
                    'timestamp' => now()->toISOString()
                ],
                'id' => uniqid('invalidate_tokens_', true)
            ];

            $response = $this->rpcHandler->handle($rpcRequest, [
                'service' => 'auth-service',
                'caller' => 'user-service',
                'trace_id' => uniqid('trace_', true)
            ]);

            if (isset($response['result'])) {
                Log::info('User tokens invalidated via RPC', [
                    'user_id' => $userId,
                    'reason' => $reason,
                    'response' => $response['result']
                ]);

                return [
                    'success' => true,
                    'data' => $response['result'],
                    'message' => 'Tokens invalidated successfully via RPC'
                ];
            }

            Log::warning('RPC token invalidation failed', [
                'user_id' => $userId,
                'reason' => $reason,
                'error' => $response['error'] ?? 'Unknown RPC error'
            ]);

            return [
                'success' => false,
                'error' => 'RPC token invalidation failed',
                'rpc_error' => $response['error'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error('RPC token invalidation error', [
                'user_id' => $userId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'RPC token invalidation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify user authentication status.
     */
    public function verifyUserAuth(int $userId, string $token): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->get("{$this->authServiceUrl}/api/internal/users/{$userId}/verify");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'User authentication verified'
                ];
            }

            return [
                'success' => false,
                'error' => 'Authentication verification failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Auth verification error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Verification error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get user's active sessions from auth-service.
     */
    public function getUserSessions(int $userId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->get("{$this->authServiceUrl}/api/internal/users/{$userId}/sessions");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'User sessions retrieved successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to retrieve user sessions',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Session retrieval error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Session retrieval error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Notify auth-service about user status change.
     */
    public function notifyUserStatusChange(int $userId, string $oldStatus, string $newStatus): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->post("{$this->authServiceUrl}/api/internal/users/{$userId}/status-changed", [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'timestamp' => now()->toISOString()
                ]);

            if ($response->successful()) {
                Log::info('Auth service notified of status change', [
                    'user_id' => $userId,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Status change notification sent successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Status change notification failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Status change notification error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Notification error: ' . $e->getMessage()
            ];
        }
    }
}
