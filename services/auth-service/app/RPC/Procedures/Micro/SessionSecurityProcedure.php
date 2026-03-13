<?php

namespace App\RPC\Procedures\Micro;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Session;
use Carbon\Carbon;

/**
 * Session Security Micro Procedure
 *
 * Provides session security methods that can be imported into service procedures
 * via use statements for modular session management.
 */
trait SessionSecurityProcedure
{
    /**
     * Detect suspicious session activity
     */
    public function detectSuspiciousActivity(array $params): array
    {
        try {
            $sessionId = $params['session_id'] ?? null;
            $currentIp = $params['current_ip'] ?? null;
            $currentUserAgent = $params['current_user_agent'] ?? null;

            if (! $sessionId) {
                return [
                    'success' => false,
                    'message' => 'Session ID is required',
                    'data' => null,
                ];
            }

            // Get session data
            $sessionValidation = $this->validateLaravelSession(['session_id' => $sessionId]);

            if (! $sessionValidation['success']) {
                return $sessionValidation;
            }

            $sessionData = $sessionValidation['data'];
            $suspiciousFlags = [];

            // Check for IP address changes
            if ($currentIp && $sessionData['ip_address'] !== $currentIp) {
                $suspiciousFlags[] = [
                    'type' => 'ip_change',
                    'message' => 'IP address changed during session',
                    'original_ip' => $sessionData['ip_address'],
                    'current_ip' => $currentIp,
                    'severity' => 'medium',
                ];
            }

            // Check for user agent changes
            if ($currentUserAgent && $sessionData['user_agent'] !== $currentUserAgent) {
                $suspiciousFlags[] = [
                    'type' => 'user_agent_change',
                    'message' => 'User agent changed during session',
                    'severity' => 'high',
                ];
            }

            // Check for rapid location changes (if geolocation data available)
            $locationSuspicion = $this->checkLocationSuspicion($sessionData['user_id'], $currentIp);
            if ($locationSuspicion) {
                $suspiciousFlags[] = $locationSuspicion;
            }

            // Check for concurrent sessions from different locations
            $concurrentSuspicion = $this->checkConcurrentSessionSuspicion($sessionData['user_id'], $currentIp);
            if ($concurrentSuspicion) {
                $suspiciousFlags[] = $concurrentSuspicion;
            }

            $isSuspicious = ! empty($suspiciousFlags);

            // Log suspicious activity
            if ($isSuspicious) {
                Log::warning('Suspicious session activity detected', [
                    'session_id' => $sessionId,
                    'user_id' => $sessionData['user_id'],
                    'flags' => $suspiciousFlags,
                ]);

                // Store suspicious activity record
                $this->recordSuspiciousActivity($sessionId, $sessionData['user_id'], $suspiciousFlags);
            }

            return [
                'success' => true,
                'message' => $isSuspicious ? 'Suspicious activity detected' : 'No suspicious activity detected',
                'data' => [
                    'session_id' => $sessionId,
                    'is_suspicious' => $isSuspicious,
                    'flags' => $suspiciousFlags,
                    'risk_level' => $this->calculateRiskLevel($suspiciousFlags),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Suspicious activity detection failed', [
                'session_id' => $params['session_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Suspicious activity detection failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Enforce concurrent session limits
     */
    public function enforceConcurrentSessionLimits(array $params): array
    {
        try {
            $userId = $params['user_id'] ?? null;
            $maxSessions = $params['max_sessions'] ?? 5;
            $currentSessionId = $params['current_session_id'] ?? null;

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'data' => null,
                ];
            }

            // Get active sessions for user
            $activeSessionsResult = $this->getActiveUserSessions(['user_id' => $userId]);

            if (! $activeSessionsResult['success']) {
                return $activeSessionsResult;
            }

            $activeSessions = $activeSessionsResult['data']['sessions'];
            $sessionCount = count($activeSessions);

            if ($sessionCount <= $maxSessions) {
                return [
                    'success' => true,
                    'message' => 'Session limit not exceeded',
                    'data' => [
                        'user_id' => $userId,
                        'active_sessions' => $sessionCount,
                        'max_sessions' => $maxSessions,
                        'action_taken' => 'none',
                    ],
                ];
            }

            // Determine sessions to revoke (oldest first, excluding current session)
            $sessionsToRevoke = [];
            $sortedSessions = collect($activeSessions)
                ->sortBy('last_activity')
                ->values()
                ->toArray();

            $sessionsToRemove = $sessionCount - $maxSessions;
            $revokedCount = 0;

            // Use collection methods with arrow functions (PHP 8.3)
            $sessionsToRevoke = collect($sortedSessions)
                ->filter(fn($session) => !$currentSessionId || $session['session_id'] !== $currentSessionId)
                ->take($sessionsToRemove)
                ->pluck('session_id')
                ->toArray();

            // Revoke excess sessions using collection methods (PHP 8.3)
            $totalRevoked = collect($sessionsToRevoke)
                ->map(fn($sessionId) => $this->revokeLaravelSession(['session_id' => $sessionId]))
                ->filter(fn($result) => $result['success'])
                ->count();

            Log::info('Concurrent session limit enforced', [
                'user_id' => $userId,
                'sessions_revoked' => $totalRevoked,
                'max_sessions' => $maxSessions,
            ]);

            return [
                'success' => true,
                'message' => 'Session limit enforced successfully',
                'data' => [
                    'user_id' => $userId,
                    'sessions_revoked' => $totalRevoked,
                    'remaining_sessions' => $sessionCount - $totalRevoked,
                    'max_sessions' => $maxSessions,
                    'action_taken' => 'revoked_oldest_sessions',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Session limit enforcement failed', [
                'user_id' => $params['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Session limit enforcement failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Validate device fingerprint
     */
    public function validateDevice(array $params): array
    {
        try {
            $sessionId = $params['session_id'] ?? null;
            $currentUserAgent = $params['current_user_agent'] ?? null;
            $deviceFingerprint = $params['device_fingerprint'] ?? null;

            if (! $sessionId) {
                return [
                    'success' => false,
                    'message' => 'Session ID is required',
                    'data' => null,
                ];
            }

            // Get session data
            $sessionValidation = $this->validateLaravelSession(['session_id' => $sessionId]);

            if (! $sessionValidation['success']) {
                return $sessionValidation;
            }

            $sessionData = $sessionValidation['data'];
            $originalUserAgent = $sessionData['user_agent'];

            $validationResults = [
                'user_agent_match' => $originalUserAgent === $currentUserAgent,
                'device_fingerprint_match' => true, // Default to true if no fingerprint provided
            ];

            // Validate device fingerprint if provided
            if ($deviceFingerprint && isset($sessionData['payload']['device_fingerprint'])) {
                $originalFingerprint = $sessionData['payload']['device_fingerprint'];
                $validationResults['device_fingerprint_match'] = $originalFingerprint === $deviceFingerprint;
            }

            $isValid = $validationResults['user_agent_match'] && $validationResults['device_fingerprint_match'];

            if (! $isValid) {
                Log::warning('Device validation failed', [
                    'session_id' => $sessionId,
                    'user_id' => $sessionData['user_id'],
                    'validation_results' => $validationResults,
                ]);
            }

            return [
                'success' => true,
                'message' => $isValid ? 'Device validation passed' : 'Device validation failed',
                'data' => [
                    'session_id' => $sessionId,
                    'is_valid' => $isValid,
                    'validation_results' => $validationResults,
                    'risk_level' => $isValid ? 'low' : 'high',
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Device validation failed', [
                'session_id' => $params['session_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Device validation failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Clean up expired and suspicious sessions
     */
    public function cleanupSuspiciousSessions(array $params): array
    {
        try {
            $maxAge = $params['max_age_hours'] ?? 24;
            $riskThreshold = $params['risk_threshold'] ?? 'high';

            $cutoffTime = Carbon::now()->subHours($maxAge)->timestamp;

            // Get sessions that are either expired or flagged as suspicious using Eloquent (Laravel 12)
            $suspiciousSessions = Session::where(function ($query) use ($cutoffTime) {
                    $query->where('last_activity', '<', $cutoffTime)
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('session_security_logs')
                                ->whereColumn('session_security_logs.session_id', 'sessions.id')
                                ->where('risk_level', 'high');
                        });
                })
                ->get();

            // Use collection methods for session cleanup with Eloquent (PHP 8.3 + Laravel 12)
            $cleanedCount = collect($suspiciousSessions)
                ->map(fn($session) => $session->delete())
                ->filter(fn($deleted) => $deleted)
                ->count();

            // Also clean up old security logs
            $logsCleaned = DB::table('session_security_logs')
                ->where('created_at', '<', Carbon::now()->subDays(30))
                ->delete();

            Log::info('Suspicious sessions cleaned up', [
                'sessions_cleaned' => $cleanedCount,
                'security_logs_cleaned' => $logsCleaned,
                'max_age_hours' => $maxAge,
            ]);

            return [
                'success' => true,
                'message' => 'Suspicious sessions cleaned up successfully',
                'data' => [
                    'sessions_cleaned' => $cleanedCount,
                    'security_logs_cleaned' => $logsCleaned,
                    'cleanup_criteria' => [
                        'max_age_hours' => $maxAge,
                        'risk_threshold' => $riskThreshold,
                    ],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Suspicious session cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Suspicious session cleanup failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Check for suspicious location changes
     */
    private function checkLocationSuspicion(int $userId, ?string $currentIp): ?array
    {
        if (! $currentIp) {
            return null;
        }

        // Get recent sessions for this user using Eloquent (Laravel 12)
        $recentSessions = Session::forUser($userId)
            ->where('last_activity', '>', Carbon::now()->subHours(1)->timestamp)
            ->orderBy('last_activity', 'desc')
            ->limit(5)
            ->get();

        if ($recentSessions->count() < 2) {
            return null;
        }

        // Check for rapid IP changes (simplified - in production, use geolocation service)
        $ipAddresses = $recentSessions->pluck('ip_address')->unique();

        if ($ipAddresses->count() > 2) {
            return [
                'type' => 'rapid_location_change',
                'message' => 'Multiple IP addresses detected in short time period',
                'severity' => 'high',
                'ip_count' => $ipAddresses->count(),
            ];
        }

        return null;
    }

    /**
     * Check for concurrent sessions from different locations
     */
    private function checkConcurrentSessionSuspicion(int $userId, ?string $currentIp): ?array
    {
        if (! $currentIp) {
            return null;
        }

        // Get active sessions for user
        $activeSessions = $this->getActiveUserSessions(['user_id' => $userId]);

        if (! $activeSessions['success'] || count($activeSessions['data']['sessions']) < 2) {
            return null;
        }

        $sessions = $activeSessions['data']['sessions'];
        $ipAddresses = collect($sessions)->pluck('ip_address')->unique();

        // If there are sessions from different IPs active simultaneously
        if ($ipAddresses->count() > 1) {
            return [
                'type' => 'concurrent_different_locations',
                'message' => 'Active sessions from multiple IP addresses',
                'severity' => 'medium',
                'concurrent_ips' => $ipAddresses->count(),
            ];
        }

        return null;
    }

    /**
     * Record suspicious activity in security log
     */
    private function recordSuspiciousActivity(string $sessionId, int $userId, array $flags): void
    {
        try {
            // Create security logs table if it doesn't exist (in production, this should be a migration)
            DB::statement('
                CREATE TABLE IF NOT EXISTS session_security_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(255) NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    risk_level ENUM("low", "medium", "high") NOT NULL,
                    flags JSON NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_session_id (session_id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_risk_level (risk_level)
                )
            ');

            $riskLevel = $this->calculateRiskLevel($flags);

            DB::table('session_security_logs')->insert([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'risk_level' => $riskLevel,
                'flags' => json_encode($flags),
                'created_at' => Carbon::now(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record suspicious activity', [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calculate risk level based on suspicious flags
     */
    private function calculateRiskLevel(array $flags): string
    {
        if (empty($flags)) {
            return 'low';
        }

        $highRiskTypes = ['user_agent_change', 'rapid_location_change'];
        $mediumRiskTypes = ['ip_change', 'concurrent_different_locations'];

        // Use collection methods for risk level calculation (PHP 8.3)
        if (collect($flags)->some(fn($flag) => in_array($flag['type'], $highRiskTypes) || $flag['severity'] === 'high')) {
            return 'high';
        }

        if (collect($flags)->some(fn($flag) => in_array($flag['type'], $mediumRiskTypes) || $flag['severity'] === 'medium')) {
            return 'medium';
        }

        return 'low';
    }
}
