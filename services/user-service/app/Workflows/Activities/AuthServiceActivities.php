<?php

namespace App\Workflows\Activities;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Workflow\Activity;

class AuthServiceActivities extends Activity
{
    private string $authServiceUrl;

    public function __construct()
    {
        $this->authServiceUrl = config('services.auth_service.url', 'http://auth-service:8001');
    }

    /**
     * Notify auth-service about new user creation.
     */
    public function notifyUserCreated(array $userData): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->post("{$this->authServiceUrl}/api/internal/users/created", $userData);

            if ($response->successful()) {
                Log::info('Auth service notified of user creation', [
                    'user_id' => $userData['user_id'],
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Auth service notified successfully'
                ];
            }

            Log::warning('Auth service notification failed', [
                'user_id' => $userData['user_id'],
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Auth service notification failed',
                'status' => $response->status(),
                'response' => $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Auth service communication error', [
                'user_id' => $userData['user_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => 'Communication error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Sync user updates with auth-service.
     */
    public function syncUserUpdate(array $updateData): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->put("{$this->authServiceUrl}/api/internal/users/{$updateData['user_id']}/sync", $updateData);

            if ($response->successful()) {
                Log::info('Auth service synced user update', [
                    'user_id' => $updateData['user_id'],
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Auth service synced successfully'
                ];
            }

            Log::warning('Auth service sync failed', [
                'user_id' => $updateData['user_id'],
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Auth service sync failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Auth service sync error', [
                'user_id' => $updateData['user_id'],
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Sync error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Revoke all user tokens in auth-service.
     */
    public function revokeAllUserTokens(int $userId): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->delete("{$this->authServiceUrl}/api/internal/users/{$userId}/tokens");

            if ($response->successful()) {
                Log::info('All user tokens revoked', [
                    'user_id' => $userId,
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'All tokens revoked successfully'
                ];
            }

            Log::warning('Token revocation failed', [
                'user_id' => $userId,
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'error' => 'Token revocation failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Token revocation error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Token revocation error: ' . $e->getMessage()
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
     * Invalidate user tokens due to permission/role changes.
     */
    public function invalidateUserTokens(int $userId, string $reason): array
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.auth_service.token'),
                    'Content-Type' => 'application/json',
                    'X-Service-Name' => 'user-service'
                ])
                ->post("{$this->authServiceUrl}/api/internal/users/{$userId}/tokens/invalidate", [
                    'reason' => $reason,
                    'timestamp' => now()->toISOString()
                ]);

            if ($response->successful()) {
                Log::info('User tokens invalidated', [
                    'user_id' => $userId,
                    'reason' => $reason,
                    'response' => $response->json()
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'message' => 'Tokens invalidated successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'Token invalidation failed',
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error('Token invalidation error', [
                'user_id' => $userId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Token invalidation error: ' . $e->getMessage()
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
