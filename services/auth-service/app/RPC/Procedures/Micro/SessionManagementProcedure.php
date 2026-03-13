<?php

namespace App\RPC\Procedures\Micro;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use App\Models\Session as SessionModel;

/**
 * Session Management Micro Procedure
 *
 * Provides session CRUD operations that can be imported into service procedures
 * via use statements for modular session management.
 */
trait SessionManagementProcedure
{
    /**
     * Create a new Laravel session
     */
    public function createLaravelSession(array $params): array
    {
        try {
            $userId = $params['user_id'] ?? null;
            $ipAddress = $params['ip_address'] ?? null;
            $userAgent = $params['user_agent'] ?? null;
            $sessionData = $params['session_data'] ?? [];

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'data' => null,
                ];
            }

            // Generate unique session ID
            $sessionId = Str::random(40);

            // Prepare session payload
            $payload = array_merge([
                '_token' => Str::random(40),
                'user_id' => $userId,
                'login_time' => now()->timestamp,
                'device_info' => $this->extractDeviceInfo($userAgent),
            ], $sessionData);

            // Encode payload
            $encodedPayload = base64_encode(serialize($payload));

            // Insert session into database using Eloquent model (Laravel 12)
            SessionModel::create([
                'id' => $sessionId,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'payload' => $payload, // Model handles encoding automatically
                'last_activity' => now()->timestamp,
            ]);

            // Update user's last login information
            $this->updateUserLastLogin($userId, $ipAddress);

            Log::info('Laravel session created', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ]);

            return [
                'success' => true,
                'message' => 'Session created successfully',
                'data' => [
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'expires_at' => now()->addMinutes(config('session.lifetime', 1440))->toISOString(),
                    'device_info' => $payload['device_info'],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Session creation failed', [
                'user_id' => $params['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Session creation failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get session data by session ID
     */
    public function getSessionData(array $params): array
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

            // Validate session first
            $validation = $this->validateLaravelSession(['session_id' => $sessionId]);

            if (! $validation['success']) {
                return $validation;
            }

            return [
                'success' => true,
                'message' => 'Session data retrieved successfully',
                'data' => $validation['data'],
            ];

        } catch (\Exception $e) {
            Log::error('Get session data failed', [
                'session_id' => $params['session_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Get session data failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Refresh Laravel session (extend lifetime)
     */
    public function refreshLaravelSession(array $params): array
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

            // Validate session first
            $validation = $this->validateLaravelSession(['session_id' => $sessionId]);

            if (! $validation['success']) {
                return $validation;
            }

            // Update last activity using Eloquent model (Laravel 12)
            $updated = SessionModel::where('id', $sessionId)
                ->update(['last_activity' => now()->timestamp]);

            if (! $updated) {
                return [
                    'success' => false,
                    'message' => 'Session not found or could not be refreshed',
                    'data' => null,
                ];
            }

            Log::info('Session refreshed', [
                'session_id' => $sessionId,
                'user_id' => $validation['data']['user_id'],
            ]);

            return [
                'success' => true,
                'message' => 'Session refreshed successfully',
                'data' => [
                    'session_id' => $sessionId,
                    'new_expires_at' => now()->addMinutes(config('session.lifetime', 1440))->toISOString(),
                    'last_activity' => now()->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Session refresh failed', [
                'session_id' => $params['session_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Session refresh failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Revoke Laravel session
     */
    public function revokeLaravelSession(array $params): array
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

            // Get session info before deletion for logging
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

            // Delete session
            $deleted = DB::table('sessions')
                ->where('id', $sessionId)
                ->delete();

            if (! $deleted) {
                return [
                    'success' => false,
                    'message' => 'Session could not be revoked',
                    'data' => null,
                ];
            }

            Log::info('Session revoked', [
                'session_id' => $sessionId,
                'user_id' => $sessionData->user_id,
            ]);

            return [
                'success' => true,
                'message' => 'Session revoked successfully',
                'data' => [
                    'session_id' => $sessionId,
                    'revoked_at' => now()->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Session revocation failed', [
                'session_id' => $params['session_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Session revocation failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get active user sessions
     */
    public function getActiveUserSessions(array $params): array
    {
        try {
            $userId = $params['user_id'] ?? null;
            $limit = $params['limit'] ?? 10;
            $offset = $params['offset'] ?? 0;

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'data' => null,
                ];
            }

            // Get active sessions for user using Eloquent model (Laravel 12)
            $sessions = SessionModel::forUser($userId)
                ->orderBy('last_activity', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get();

            $sessionLifetime = config('session.lifetime', 1440);

            // Use Laravel collection methods with arrow functions (PHP 8.3)
            $activeSessions = collect($sessions)
                ->map(fn($session) => [
                    'session' => $session,
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity)
                ])
                ->partition(fn($item) => !$item['last_activity']->addMinutes($sessionLifetime)->isPast())
                ->pipe(function ($partitioned) use ($sessionLifetime) {
                    [$active, $expired] = $partitioned;
                    
                    // Clean up expired sessions using Eloquent model (Laravel 12)
                    $expired->each(fn($item) => 
                        SessionModel::where('id', $item['session']->id)->delete()
                    );
                    
                    // Transform active sessions
                    return $active->map(function ($item) use ($sessionLifetime) {
                        $session = $item['session'];
                        $lastActivity = $item['last_activity'];
                        $payload = unserialize(base64_decode($session->payload));
                        
                        return [
                            'session_id' => $session->id,
                            'ip_address' => $session->ip_address,
                            'user_agent' => $session->user_agent,
                            'last_activity' => $lastActivity->toISOString(),
                            'device_info' => $payload['device_info'] ?? null,
                            'expires_at' => $lastActivity->addMinutes($sessionLifetime)->toISOString(),
                        ];
                    })->values()->toArray();
                });

            return [
                'success' => true,
                'message' => 'Active sessions retrieved successfully',
                'data' => [
                    'sessions' => $activeSessions,
                    'total_active' => count($activeSessions),
                    'user_id' => $userId,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Get active sessions failed', [
                'user_id' => $params['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Get active sessions failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Revoke all user sessions
     */
    public function revokeUserSessions(array $params): array
    {
        try {
            $userId = $params['user_id'] ?? null;
            $excludeSessionId = $params['exclude_session_id'] ?? null;

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'data' => null,
                ];
            }

            // Build query using Eloquent model (Laravel 12)
            $query = SessionModel::forUser($userId);

            // Exclude current session if specified
            if ($excludeSessionId) {
                $query->where('id', '!=', $excludeSessionId);
            }

            // Count sessions before deletion
            $sessionCount = $query->count();

            // Delete sessions
            $deleted = $query->delete();

            Log::info('User sessions revoked', [
                'user_id' => $userId,
                'sessions_revoked' => $deleted,
                'excluded_session' => $excludeSessionId,
            ]);

            return [
                'success' => true,
                'message' => 'User sessions revoked successfully',
                'data' => [
                    'user_id' => $userId,
                    'sessions_revoked' => $deleted,
                    'revoked_at' => now()->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Revoke user sessions failed', [
                'user_id' => $params['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Revoke user sessions failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Extract device information from user agent
     */
    private function extractDeviceInfo(?string $userAgent): array
    {
        if (! $userAgent) {
            return [
                'device_type' => 'unknown',
                'platform' => 'unknown',
                'browser' => 'unknown',
            ];
        }

        // Basic device detection (can be enhanced with a proper library)
        $deviceType = 'desktop';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/Tablet|iPad/', $userAgent)) {
            $deviceType = 'tablet';
        }

        $platform = 'unknown';
        if (preg_match('/Windows/', $userAgent)) {
            $platform = 'Windows';
        } elseif (preg_match('/Mac OS X/', $userAgent)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/Android/', $userAgent)) {
            $platform = 'Android';
        } elseif (preg_match('/iPhone|iPad/', $userAgent)) {
            $platform = 'iOS';
        }

        $browser = 'unknown';
        if (preg_match('/Chrome/', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/', $userAgent)) {
            $browser = 'Edge';
        }

        return [
            'device_type' => $deviceType,
            'platform' => $platform,
            'browser' => $browser,
            'user_agent' => $userAgent,
        ];
    }

    /**
     * Update user's last login information
     */
    private function updateUserLastLogin(int $userId, ?string $ipAddress): void
    {
        try {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $ipAddress,
                    'login_count' => DB::raw('login_count + 1'),
                    'updated_at' => now(),
                ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update user last login', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
