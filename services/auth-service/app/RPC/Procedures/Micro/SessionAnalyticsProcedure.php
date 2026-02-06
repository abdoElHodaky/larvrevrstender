<?php

namespace App\RPC\Procedures\Micro;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Session Analytics Micro Procedure
 *
 * Provides session analytics and reporting methods that can be imported into service procedures
 * via use statements for modular session management.
 */
trait SessionAnalyticsProcedure
{
    /**
     * Get session statistics for a user
     */
    public function getSessionStats(array $params): array
    {
        try {
            $userId = $params['user_id'] ?? null;
            $days = $params['days'] ?? 30;

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'data' => null,
                ];
            }

            $startDate = now()->subDays($days)->startOfDay();

            // Get session statistics
            $stats = [
                'total_sessions' => $this->getTotalSessions($userId, $startDate),
                'active_sessions' => $this->getActiveSessionsCount($userId),
                'average_session_duration' => $this->getAverageSessionDuration($userId, $startDate),
                'most_used_devices' => $this->getMostUsedDevices($userId, $startDate),
                'login_frequency' => $this->getLoginFrequency($userId, $startDate),
                'security_incidents' => $this->getSecurityIncidents($userId, $startDate),
                'geographic_distribution' => $this->getGeographicDistribution($userId, $startDate),
            ];

            return [
                'success' => true,
                'message' => 'Session statistics retrieved successfully',
                'data' => [
                    'user_id' => $userId,
                    'period_days' => $days,
                    'period_start' => $startDate->toISOString(),
                    'period_end' => now()->toISOString(),
                    'statistics' => $stats,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Get session stats failed', [
                'user_id' => $params['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Get session stats failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get login history for a user
     */
    public function getLoginHistory(array $params): array
    {
        try {
            $userId = $params['user_id'] ?? null;
            $limit = $params['limit'] ?? 50;
            $offset = $params['offset'] ?? 0;
            $days = $params['days'] ?? 30;

            if (! $userId) {
                return [
                    'success' => false,
                    'message' => 'User ID is required',
                    'data' => null,
                ];
            }

            $startDate = now()->subDays($days)->timestamp;

            // Get login history from sessions
            $loginHistory = DB::table('sessions')
                ->where('user_id', $userId)
                ->where('last_activity', '>=', $startDate)
                ->orderBy('last_activity', 'desc')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(function ($session) {
                    $payload = unserialize(base64_decode($session->payload));
                    $deviceInfo = $payload['device_info'] ?? [];

                    return [
                        'session_id' => $session->id,
                        'login_time' => isset($payload['login_time'])
                            ? Carbon::createFromTimestamp($payload['login_time'])->toISOString()
                            : Carbon::createFromTimestamp($session->last_activity)->toISOString(),
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent,
                        'device_type' => $deviceInfo['device_type'] ?? 'unknown',
                        'platform' => $deviceInfo['platform'] ?? 'unknown',
                        'browser' => $deviceInfo['browser'] ?? 'unknown',
                        'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toISOString(),
                        'is_active' => $this->isSessionActive($session->last_activity),
                    ];
                });

            // Get total count for pagination
            $totalCount = DB::table('sessions')
                ->where('user_id', $userId)
                ->where('last_activity', '>=', $startDate)
                ->count();

            return [
                'success' => true,
                'message' => 'Login history retrieved successfully',
                'data' => [
                    'user_id' => $userId,
                    'login_history' => $loginHistory->toArray(),
                    'pagination' => [
                        'total' => $totalCount,
                        'limit' => $limit,
                        'offset' => $offset,
                        'has_more' => ($offset + $limit) < $totalCount,
                    ],
                    'period_days' => $days,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Get login history failed', [
                'user_id' => $params['user_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Get login history failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get session analytics for admin dashboard
     */
    public function getSystemSessionAnalytics(array $params): array
    {
        try {
            $days = $params['days'] ?? 7;
            $startDate = now()->subDays($days)->startOfDay();

            // System-wide session analytics
            $analytics = [
                'total_sessions' => $this->getSystemTotalSessions($startDate),
                'active_sessions' => $this->getSystemActiveSessions(),
                'unique_users' => $this->getUniqueActiveUsers($startDate),
                'sessions_by_day' => $this->getSessionsByDay($startDate, $days),
                'device_breakdown' => $this->getSystemDeviceBreakdown($startDate),
                'security_incidents' => $this->getSystemSecurityIncidents($startDate),
                'top_user_agents' => $this->getTopUserAgents($startDate),
                'geographic_stats' => $this->getSystemGeographicStats($startDate),
            ];

            return [
                'success' => true,
                'message' => 'System session analytics retrieved successfully',
                'data' => [
                    'period_days' => $days,
                    'period_start' => $startDate->toISOString(),
                    'period_end' => now()->toISOString(),
                    'analytics' => $analytics,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Get system session analytics failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Get system session analytics failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get session performance metrics
     */
    public function getSessionPerformanceMetrics(array $params): array
    {
        try {
            $days = $params['days'] ?? 7;
            $startDate = now()->subDays($days)->startOfDay();

            $metrics = [
                'average_session_duration' => $this->getSystemAverageSessionDuration($startDate),
                'session_creation_rate' => $this->getSessionCreationRate($startDate, $days),
                'session_cleanup_stats' => $this->getSessionCleanupStats($startDate),
                'concurrent_session_peaks' => $this->getConcurrentSessionPeaks($startDate),
                'database_performance' => $this->getSessionDatabasePerformance(),
            ];

            return [
                'success' => true,
                'message' => 'Session performance metrics retrieved successfully',
                'data' => [
                    'period_days' => $days,
                    'metrics' => $metrics,
                    'generated_at' => now()->toISOString(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Get session performance metrics failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Get session performance metrics failed: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get total sessions for a user in a period
     */
    private function getTotalSessions(int $userId, Carbon $startDate): int
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $startDate->timestamp)
            ->count();
    }

    /**
     * Get active sessions count for a user
     */
    private function getActiveSessionsCount(int $userId): int
    {
        $sessionLifetime = config('session.lifetime', 1440);
        $cutoffTime = now()->subMinutes($sessionLifetime)->timestamp;

        return DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>', $cutoffTime)
            ->count();
    }

    /**
     * Get average session duration for a user
     */
    private function getAverageSessionDuration(int $userId, Carbon $startDate): float
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $startDate->timestamp)
            ->get();

        if ($sessions->isEmpty()) {
            return 0;
        }

        $totalDuration = 0;
        $validSessions = 0;

        foreach ($sessions as $session) {
            $payload = unserialize(base64_decode($session->payload));
            $loginTime = $payload['login_time'] ?? $session->last_activity;

            $duration = $session->last_activity - $loginTime;
            if ($duration > 0) {
                $totalDuration += $duration;
                $validSessions++;
            }
        }

        return $validSessions > 0 ? round($totalDuration / $validSessions / 60, 2) : 0; // Return in minutes
    }

    /**
     * Get most used devices for a user
     */
    private function getMostUsedDevices(int $userId, Carbon $startDate): array
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $startDate->timestamp)
            ->get();

        $deviceStats = [];

        foreach ($sessions as $session) {
            $payload = unserialize(base64_decode($session->payload));
            $deviceInfo = $payload['device_info'] ?? [];

            $deviceKey = ($deviceInfo['device_type'] ?? 'unknown').'|'.
                        ($deviceInfo['platform'] ?? 'unknown').'|'.
                        ($deviceInfo['browser'] ?? 'unknown');

            if (! isset($deviceStats[$deviceKey])) {
                $deviceStats[$deviceKey] = [
                    'device_type' => $deviceInfo['device_type'] ?? 'unknown',
                    'platform' => $deviceInfo['platform'] ?? 'unknown',
                    'browser' => $deviceInfo['browser'] ?? 'unknown',
                    'count' => 0,
                ];
            }

            $deviceStats[$deviceKey]['count']++;
        }

        // Sort by count and return top 5
        uasort($deviceStats, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return array_slice($deviceStats, 0, 5);
    }

    /**
     * Get login frequency for a user
     */
    private function getLoginFrequency(int $userId, Carbon $startDate): array
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $startDate->timestamp)
            ->orderBy('last_activity')
            ->get();

        $dailyLogins = [];

        foreach ($sessions as $session) {
            $payload = unserialize(base64_decode($session->payload));
            $loginTime = $payload['login_time'] ?? $session->last_activity;
            $date = Carbon::createFromTimestamp($loginTime)->format('Y-m-d');

            $dailyLogins[$date] = ($dailyLogins[$date] ?? 0) + 1;
        }

        return $dailyLogins;
    }

    /**
     * Get security incidents for a user
     */
    private function getSecurityIncidents(int $userId, Carbon $startDate): array
    {
        try {
            $incidents = DB::table('session_security_logs')
                ->where('user_id', $userId)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc')
                ->get();

            return $incidents->map(function ($incident) {
                return [
                    'session_id' => $incident->session_id,
                    'risk_level' => $incident->risk_level,
                    'flags' => json_decode($incident->flags, true),
                    'created_at' => Carbon::parse($incident->created_at)->toISOString(),
                ];
            })->toArray();

        } catch (\Exception $e) {
            // Table might not exist yet
            return [];
        }
    }

    /**
     * Get geographic distribution for a user
     */
    private function getGeographicDistribution(int $userId, Carbon $startDate): array
    {
        $sessions = DB::table('sessions')
            ->where('user_id', $userId)
            ->where('last_activity', '>=', $startDate->timestamp)
            ->get();

        $ipStats = [];

        foreach ($sessions as $session) {
            $ip = $session->ip_address;
            $ipStats[$ip] = ($ipStats[$ip] ?? 0) + 1;
        }

        // In production, you would use a geolocation service to convert IPs to locations
        return [
            'unique_ips' => count($ipStats),
            'ip_distribution' => $ipStats,
        ];
    }

    /**
     * Check if session is still active
     */
    private function isSessionActive(int $lastActivity): bool
    {
        $sessionLifetime = config('session.lifetime', 1440);
        $cutoffTime = now()->subMinutes($sessionLifetime)->timestamp;

        return $lastActivity > $cutoffTime;
    }

    /**
     * Get system-wide total sessions
     */
    private function getSystemTotalSessions(Carbon $startDate): int
    {
        return DB::table('sessions')
            ->where('last_activity', '>=', $startDate->timestamp)
            ->count();
    }

    /**
     * Get system-wide active sessions
     */
    private function getSystemActiveSessions(): int
    {
        $sessionLifetime = config('session.lifetime', 1440);
        $cutoffTime = now()->subMinutes($sessionLifetime)->timestamp;

        return DB::table('sessions')
            ->where('last_activity', '>', $cutoffTime)
            ->count();
    }

    /**
     * Get unique active users
     */
    private function getUniqueActiveUsers(Carbon $startDate): int
    {
        return DB::table('sessions')
            ->where('last_activity', '>=', $startDate->timestamp)
            ->distinct('user_id')
            ->count('user_id');
    }

    /**
     * Get sessions by day
     */
    private function getSessionsByDay(Carbon $startDate, int $days): array
    {
        $sessionsByDay = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dayStart = $date->startOfDay()->timestamp;
            $dayEnd = $date->endOfDay()->timestamp;

            $count = DB::table('sessions')
                ->whereBetween('last_activity', [$dayStart, $dayEnd])
                ->count();

            $sessionsByDay[$date->format('Y-m-d')] = $count;
        }

        return $sessionsByDay;
    }

    /**
     * Get system device breakdown
     */
    private function getSystemDeviceBreakdown(Carbon $startDate): array
    {
        $sessions = DB::table('sessions')
            ->where('last_activity', '>=', $startDate->timestamp)
            ->get();

        $deviceStats = [
            'desktop' => 0,
            'mobile' => 0,
            'tablet' => 0,
            'unknown' => 0,
        ];

        foreach ($sessions as $session) {
            $payload = unserialize(base64_decode($session->payload));
            $deviceType = $payload['device_info']['device_type'] ?? 'unknown';

            if (isset($deviceStats[$deviceType])) {
                $deviceStats[$deviceType]++;
            } else {
                $deviceStats['unknown']++;
            }
        }

        return $deviceStats;
    }

    /**
     * Get system security incidents
     */
    private function getSystemSecurityIncidents(Carbon $startDate): array
    {
        try {
            return DB::table('session_security_logs')
                ->where('created_at', '>=', $startDate)
                ->selectRaw('risk_level, COUNT(*) as count')
                ->groupBy('risk_level')
                ->pluck('count', 'risk_level')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get top user agents
     */
    private function getTopUserAgents(Carbon $startDate): array
    {
        return DB::table('sessions')
            ->where('last_activity', '>=', $startDate->timestamp)
            ->selectRaw('user_agent, COUNT(*) as count')
            ->groupBy('user_agent')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'user_agent')
            ->toArray();
    }

    /**
     * Get system geographic stats
     */
    private function getSystemGeographicStats(Carbon $startDate): array
    {
        return DB::table('sessions')
            ->where('last_activity', '>=', $startDate->timestamp)
            ->selectRaw('ip_address, COUNT(*) as count')
            ->groupBy('ip_address')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->pluck('count', 'ip_address')
            ->toArray();
    }

    /**
     * Get system average session duration
     */
    private function getSystemAverageSessionDuration(Carbon $startDate): float
    {
        $sessions = DB::table('sessions')
            ->where('last_activity', '>=', $startDate->timestamp)
            ->get();

        if ($sessions->isEmpty()) {
            return 0;
        }

        $totalDuration = 0;
        $validSessions = 0;

        foreach ($sessions as $session) {
            $payload = unserialize(base64_decode($session->payload));
            $loginTime = $payload['login_time'] ?? $session->last_activity;

            $duration = $session->last_activity - $loginTime;
            if ($duration > 0) {
                $totalDuration += $duration;
                $validSessions++;
            }
        }

        return $validSessions > 0 ? round($totalDuration / $validSessions / 60, 2) : 0;
    }

    /**
     * Get session creation rate
     */
    private function getSessionCreationRate(Carbon $startDate, int $days): float
    {
        $totalSessions = $this->getSystemTotalSessions($startDate);

        return $days > 0 ? round($totalSessions / $days, 2) : 0;
    }

    /**
     * Get session cleanup stats
     */
    private function getSessionCleanupStats(Carbon $startDate): array
    {
        $sessionLifetime = config('session.lifetime', 1440);
        $expiredCutoff = now()->subMinutes($sessionLifetime)->timestamp;

        $totalSessions = DB::table('sessions')->count();
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>', $expiredCutoff)
            ->count();
        $expiredSessions = $totalSessions - $activeSessions;

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'expired_sessions' => $expiredSessions,
            'cleanup_needed' => $expiredSessions > 0,
        ];
    }

    /**
     * Get concurrent session peaks
     */
    private function getConcurrentSessionPeaks(Carbon $startDate): array
    {
        // This is a simplified implementation
        // In production, you'd want to track this with more granular time intervals
        return [
            'peak_concurrent_sessions' => $this->getSystemActiveSessions(),
            'peak_time' => now()->toISOString(),
        ];
    }

    /**
     * Get session database performance metrics
     */
    private function getSessionDatabasePerformance(): array
    {
        try {
            $tableSize = DB::select("
                SELECT 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = 'sessions'
            ");

            return [
                'table_size_mb' => $tableSize[0]->size_mb ?? 0,
                'total_records' => DB::table('sessions')->count(),
            ];
        } catch (\Exception $e) {
            return [
                'table_size_mb' => 0,
                'total_records' => DB::table('sessions')->count(),
            ];
        }
    }
}
