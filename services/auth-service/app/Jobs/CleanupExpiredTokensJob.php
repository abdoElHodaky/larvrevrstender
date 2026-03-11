<?php

namespace App\Jobs;

use App\Models\PersonalAccessToken;
use App\Models\PasswordResetToken;
use App\Models\ActivityLog;
use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup Expired Tokens Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Cleans up expired authentication tokens, password reset tokens, and inactive sessions
 * to maintain database performance and security. This is critical for preventing database
 * bloat and reducing the attack surface by removing expired credentials.
 */
class CleanupExpiredTokensJob extends BaseQueueJob
{
    public array $cleanupTypes;
    public array $retentionPeriods;
    public int $batchSize;
    public int $tries = 3;
    public int $timeout = 600; // 10 minutes for cleanup operations

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $cleanupTypes = [],
        array $retentionPeriods = [],
        int $batchSize = 1000
    ) {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->cleanupTypes = $cleanupTypes ?: $this->getDefaultCleanupTypes();
        $this->retentionPeriods = array_merge($this->getDefaultRetentionPeriods(), $retentionPeriods);
        $this->batchSize = $batchSize;
        
        // Set queue for maintenance operations
        $this->onQueue('auth-maintenance');
        
        // Configure circuit breaker for token cleanup
        $this->configureCircuitBreaker([
            'service_name' => 'auth_token_cleanup',
            'failure_threshold' => 25, // 25% failure rate triggers circuit breaker
            'timeout' => 300, // 5 minutes timeout for cleanup operations
            'recovery_timeout' => 900, // 15 minutes before attempting recovery
            'tags' => [
                'service' => 'auth-service',
                'job_type' => 'maintenance',
                'operation' => 'token_cleanup',
                'priority' => 'medium'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting token cleanup with circuit breaker protection', [
            'cleanup_types' => $this->cleanupTypes,
            'retention_periods' => $this->retentionPeriods,
            'batch_size' => $this->batchSize,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'auth_token_cleanup'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() {
            $results = [];
            
            foreach ($this->cleanupTypes as $cleanupType) {
                $result = $this->performCleanup($cleanupType);
                $results[$cleanupType] = $result;
                
                Log::debug('Completed cleanup type', [
                    'cleanup_type' => $cleanupType,
                    'records_deleted' => $result['deleted'],
                    'batches_processed' => $result['batches']
                ]);
            }

            Log::info('Token cleanup completed successfully', [
                'total_deleted' => array_sum(array_column($results, 'deleted')),
                'total_batches' => array_sum(array_column($results, 'batches')),
                'cleanup_results' => $results,
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            // Circuit breaker failure handler
            Log::error('Token cleanup failed with circuit breaker protection', [
                'cleanup_types' => $this->cleanupTypes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Perform cleanup for a specific type
     */
    private function performCleanup(string $cleanupType): array
    {
        $startTime = microtime(true);
        
        Log::debug('Starting cleanup for type', [
            'cleanup_type' => $cleanupType,
            'retention_period' => $this->retentionPeriods[$cleanupType] ?? 'default'
        ]);

        return match ($cleanupType) {
            'personal_access_tokens' => $this->cleanupPersonalAccessTokens(),
            'password_reset_tokens' => $this->cleanupPasswordResetTokens(),
            'expired_sessions' => $this->cleanupExpiredSessions(),
            'inactive_sessions' => $this->cleanupInactiveSessions(),
            'activity_logs' => $this->cleanupOldActivityLogs(),
            default => (function() use ($cleanupType) {
                Log::warning('Unknown cleanup type', ['cleanup_type' => $cleanupType]);
                return ['deleted' => 0, 'batches' => 0, 'duration_ms' => 0];
            })()
        };
    }

    /**
     * Cleanup expired personal access tokens using Eloquent (Laravel 12)
     */
    private function cleanupPersonalAccessTokens(): array
    {
        $startTime = microtime(true);
        $totalDeleted = 0;
        $batchCount = 0;

        // Delete expired tokens using model method (PHP 8.3 + Laravel 12)
        do {
            $deleted = PersonalAccessToken::cleanupExpired($this->batchSize);
            
            $totalDeleted += $deleted;
            $batchCount++;
            
            if ($deleted > 0) {
                Log::debug('Deleted expired personal access tokens batch', [
                    'batch' => $batchCount,
                    'deleted_in_batch' => $deleted,
                    'total_deleted' => $totalDeleted
                ]);
            }
        } while ($deleted > 0);

        // Delete unused tokens using model method (PHP 8.3 + Laravel 12)
        $retentionDays = $this->retentionPeriods['personal_access_tokens'];
        
        do {
            $deleted = PersonalAccessToken::cleanupUnused($retentionDays, $this->batchSize);
            
            $totalDeleted += $deleted;
            $batchCount++;
            
            if ($deleted > 0) {
                Log::debug('Deleted unused personal access tokens batch', [
                    'batch' => $batchCount,
                    'deleted_in_batch' => $deleted,
                    'total_deleted' => $totalDeleted,
                    'retention_days' => $retentionDays
                ]);
            }
        } while ($deleted > 0);

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'deleted' => $totalDeleted,
            'batches' => $batchCount,
            'duration_ms' => round($duration)
        ];
    }

    /**
     * Cleanup expired password reset tokens
     */
    private function cleanupPasswordResetTokens(): array
    {
        $startTime = microtime(true);
        $totalDeleted = 0;
        $batchCount = 0;

        // Password reset tokens are typically valid for 1 hour by default
        $expirationTime = now()->subHours($this->retentionPeriods['password_reset_tokens']);

        do {
            $deleted = PasswordResetToken::expired($expirationTime)
                ->limit($this->batchSize)
                ->delete();
            
            $totalDeleted += $deleted;
            $batchCount++;
            
            if ($deleted > 0) {
                Log::debug('Deleted expired password reset tokens batch', [
                    'batch' => $batchCount,
                    'deleted_in_batch' => $deleted,
                    'total_deleted' => $totalDeleted,
                    'expiration_time' => $expirationTime->toDateTimeString()
                ]);
            }
        } while ($deleted > 0);

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'deleted' => $totalDeleted,
            'batches' => $batchCount,
            'duration_ms' => round($duration)
        ];
    }

    /**
     * Cleanup expired sessions using Eloquent (Laravel 12)
     */
    private function cleanupExpiredSessions(): array
    {
        $startTime = microtime(true);
        $totalDeleted = 0;
        $batchCount = 0;

        // Use Laravel's built-in session cleanup (PHP 8.3 + Laravel 12)
        $sessionLifetime = config('session.lifetime', 120);
        $expiredThreshold = now()->subMinutes($sessionLifetime)->timestamp;
        
        do {
            $deleted = DB::table('sessions')
                ->where('last_activity', '<', $expiredThreshold)
                ->limit($this->batchSize)
                ->delete();
            
            $totalDeleted += $deleted;
            $batchCount++;
            
            if ($deleted > 0) {
                Log::debug('Deleted expired sessions batch', [
                    'batch' => $batchCount,
                    'deleted_in_batch' => $deleted,
                    'total_deleted' => $totalDeleted,
                    'session_lifetime_minutes' => config('session.lifetime', 120)
                ]);
            }
        } while ($deleted > 0);

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'deleted' => $totalDeleted,
            'batches' => $batchCount,
            'duration_ms' => round($duration)
        ];
    }

    /**
     * Cleanup inactive sessions (sessions that haven't been active for extended period)
     */
    private function cleanupInactiveSessions(): array
    {
        $startTime = microtime(true);
        $totalDeleted = 0;
        $batchCount = 0;

        // Remove sessions inactive for longer than retention period
        $inactivityThreshold = now()->subDays($this->retentionPeriods['inactive_sessions'])->timestamp;

        do {
            $deleted = DB::table('sessions')
                ->where('last_activity', '<', $inactivityThreshold)
                ->limit($this->batchSize)
                ->delete();
            
            $totalDeleted += $deleted;
            $batchCount++;
            
            if ($deleted > 0) {
                Log::debug('Deleted inactive sessions batch', [
                    'batch' => $batchCount,
                    'deleted_in_batch' => $deleted,
                    'total_deleted' => $totalDeleted,
                    'inactivity_threshold' => $inactivityThreshold,
                    'retention_days' => $this->retentionPeriods['inactive_sessions']
                ]);
            }
        } while ($deleted > 0);

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'deleted' => $totalDeleted,
            'batches' => $batchCount,
            'duration_ms' => round($duration)
        ];
    }

    /**
     * Cleanup old activity logs
     */
    private function cleanupOldActivityLogs(): array
    {
        $startTime = microtime(true);
        $totalDeleted = 0;
        $batchCount = 0;

        $retentionDate = now()->subDays($this->retentionPeriods['activity_logs']);

        do {
            $deleted = ActivityLog::where('created_at', '<', $retentionDate)
                ->limit($this->batchSize)
                ->delete();
            
            $totalDeleted += $deleted;
            $batchCount++;
            
            if ($deleted > 0) {
                Log::debug('Deleted old activity logs batch', [
                    'batch' => $batchCount,
                    'deleted_in_batch' => $deleted,
                    'total_deleted' => $totalDeleted,
                    'retention_date' => $retentionDate->toDateString()
                ]);
            }
        } while ($deleted > 0);

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'deleted' => $totalDeleted,
            'batches' => $batchCount,
            'duration_ms' => round($duration)
        ];
    }

    /**
     * Get default cleanup types
     */
    private function getDefaultCleanupTypes(): array
    {
        return [
            'personal_access_tokens',
            'password_reset_tokens',
            'expired_sessions',
            'inactive_sessions',
            'activity_logs'
        ];
    }

    /**
     * Get default retention periods
     */
    private function getDefaultRetentionPeriods(): array
    {
        return [
            'personal_access_tokens' => 90, // 90 days for unused tokens
            'password_reset_tokens' => 2, // 2 hours for password reset tokens
            'expired_sessions' => 1, // 1 day for expired sessions (already handled by session lifetime)
            'inactive_sessions' => 30, // 30 days for inactive sessions
            'activity_logs' => 365, // 1 year for activity logs
        ];
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Token cleanup job failed permanently', [
            'cleanup_types' => $this->cleanupTypes,
            'retention_periods' => $this->retentionPeriods,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\Auth\TokenCleanupFailed(...));
    }
}
