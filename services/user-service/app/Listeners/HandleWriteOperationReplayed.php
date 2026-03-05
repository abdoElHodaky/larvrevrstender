<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Shared\Events\WriteOperationReplayedEvent;
use Shared\Services\DatabaseFailoverAlertManager;

class HandleWriteOperationReplayed implements ShouldQueue
{
    use InteractsWithQueue;

    private DatabaseFailoverAlertManager $alertManager;

    public function __construct()
    {
        $this->alertManager = new DatabaseFailoverAlertManager();
    }

    /**
     * Handle the write operation replayed event for User Service.
     */
    public function handle(WriteOperationReplayedEvent $event): void
    {
        Log::channel('write-operations')->info('User Service: Authentication operation successfully replayed', [
            'service' => 'user-service',
            'operation_id' => $event->operationId,
            'operation_type' => $event->operationType,
            'table' => $event->table,
            'replayed_at' => $event->replayedAt,
            'original_buffered_at' => $event->originalBufferedAt,
            'replay_duration_seconds' => $event->replayDurationSeconds,
            'correlation_id' => $event->correlationId,
            'user_experience_impact' => 'Authentication operation replay completed',
            'auth_continuity_status' => 'User authentication continuity maintained',
        ]);

        // User service handles authentication-critical operation replay monitoring
        $this->handleUserReplayMonitoring($event);
    }

    /**
     * Handle user-specific write operation replay monitoring.
     */
    private function handleUserReplayMonitoring(WriteOperationReplayedEvent $event): void
    {
        // Update metrics for successfully replayed authentication operations
        cache()->increment('user_replayed_operations_count');
        cache()->decrement('user_buffered_operations_count'); // Reduce buffer count
        cache()->put('user_last_replayed_operation', now(), 3600);

        // Track operation type for authentication recovery monitoring
        $operationType = $event->operationType;
        cache()->increment("user_replayed_operations_{$operationType}");

        // Calculate user experience impact recovery and authentication metrics
        $userExperienceRecovered = $this->calculateUserExperienceRecovered($operationType, $event);
        $authImpactReduction = $this->assessAuthImpactReduction($event);
        $authContinuityStatus = $this->validateAuthContinuityStatus($event);

        // Log for monitoring dashboard with user experience recovery context
        Log::info('User Service: Authentication operation replay completed - user experience recovery', [
            'service' => 'user-service',
            'operation_id' => $event->operationId,
            'operation_type' => $operationType,
            'table' => $event->table,
            'replay_duration' => $event->replayDurationSeconds,
            'user_experience_recovered' => $userExperienceRecovered,
            'auth_impact_reduction' => $authImpactReduction,
            'auth_continuity_status' => $authContinuityStatus,
            'remaining_buffer_size' => cache()->get('user_buffered_operations_count', 0),
        ]);

        // Warning alert for slow authentication operation replay
        if ($event->replayDurationSeconds > 45) {
            Log::warning('User Service: SLOW AUTHENTICATION OPERATION REPLAY DETECTED', [
                'service' => 'user-service',
                'operation_type' => $operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'user_experience_impact' => 'User authentication experience may be degraded',
                'auth_continuity_risk' => 'MEDIUM - Authentication continuity delayed',
                'recommended_action' => 'Monitor authentication performance and user support channels',
            ]);

            // Send user experience degradation alert
            $this->sendUserExperienceDegradationAlert($event);
        }

        // Monitor authentication replay success rate and user experience continuity
        $this->monitorAuthReplaySuccessRate($operationType);
        $this->assessUserExperienceContinuity($event);

        // Update user service health metrics
        $this->updateUserServiceHealthMetrics($event);

        // Check for complete authentication buffer recovery
        $remainingBufferSize = cache()->get('user_buffered_operations_count', 0);
        if ($remainingBufferSize === 0) {
            $this->handleCompleteAuthBufferRecovery();
        }

        // Notify user support team and dependent services
        $this->notifyUserSupportTeamOfRecovery($event);
        $this->notifyDependentServicesOfAuthRecovery($event);

        // Update user experience metrics
        $this->updateUserExperienceMetrics($event);
    }

    /**
     * Calculate user experience value recovered from successful operation replay.
     */
    private function calculateUserExperienceRecovered(string $operationType, WriteOperationReplayedEvent $event): string
    {
        // User experience impact per operation type
        $experienceImpactMap = [
            'user_registration' => 'high_impact',        // New user onboarding
            'password_change' => 'high_impact',          // Security-critical operation
            'email_verification' => 'medium_impact',     // Account verification
            'account_verification' => 'medium_impact',   // Account status
            'profile_update' => 'low_impact',            // Profile information
            'preference_update' => 'low_impact',         // User preferences
            'session_management' => 'minimal_impact',    // Session handling
        ];

        $baseImpact = $experienceImpactMap[$operationType] ?? 'minimal_impact';
        
        // Apply delay factor to user experience recovery
        $delayFactor = $event->replayDurationSeconds;
        
        if ($delayFactor <= 30) {
            return $baseImpact . '_fast_recovery';
        } elseif ($delayFactor <= 120) {
            return $baseImpact . '_normal_recovery';
        } else {
            return $baseImpact . '_slow_recovery';
        }
    }

    /**
     * Assess authentication impact reduction from successful replay.
     */
    private function assessAuthImpactReduction(WriteOperationReplayedEvent $event): string
    {
        $replayDuration = $event->replayDurationSeconds;
        
        // Authentication operations have specific user experience requirements
        if ($replayDuration <= 15) {
            return 'minimal_auth_impact'; // Replayed within 15 seconds
        } elseif ($replayDuration <= 60) {
            return 'low_auth_impact'; // Replayed within 1 minute
        } elseif ($replayDuration <= 300) {
            return 'moderate_auth_impact'; // Replayed within 5 minutes
        } else {
            return 'high_auth_impact'; // Took longer than 5 minutes
        }
    }

    /**
     * Validate authentication continuity status for replayed operation.
     */
    private function validateAuthContinuityStatus(WriteOperationReplayedEvent $event): string
    {
        // Check if operation maintains authentication continuity requirements
        $criticalAuthOperations = [
            'user_registration',
            'password_change',
            'email_verification',
            'account_verification'
        ];

        if (in_array($event->operationType, $criticalAuthOperations)) {
            // Validate authentication continuity requirements
            if ($event->replayDurationSeconds <= 120) { // 2 minutes
                return 'auth_continuity_maintained';
            } else {
                return 'auth_continuity_degraded';
            }
        }

        return 'non_critical_auth_operation';
    }

    /**
     * Monitor authentication replay success rate.
     */
    private function monitorAuthReplaySuccessRate(string $operationType): void
    {
        $totalReplayed = cache()->get('user_replayed_operations_count', 0);
        $totalBuffered = cache()->get('user_total_buffered_operations', 0);
        
        if ($totalBuffered > 0) {
            $successRate = ($totalReplayed / $totalBuffered) * 100;
            
            cache()->put('user_replay_success_rate', $successRate, 3600);
            
            Log::info('User Service: Authentication replay success rate updated', [
                'service' => 'user-service',
                'operation_type' => $operationType,
                'success_rate_percentage' => round($successRate, 2),
                'total_replayed' => $totalReplayed,
                'total_buffered' => $totalBuffered,
                'auth_continuity_status' => 'monitoring_active',
            ]);

            // Alert if authentication success rate is low
            if ($successRate < 96 && $totalBuffered > 8) {
                Log::warning('User Service: LOW AUTHENTICATION REPLAY SUCCESS RATE', [
                    'service' => 'user-service',
                    'success_rate' => $successRate,
                    'user_experience_impact' => 'User authentication experience may be compromised',
                    'auth_continuity_risk' => 'HIGH - Authentication continuity at risk',
                    'recommended_action' => 'Escalate to user support and authentication teams',
                ]);

                // Send authentication continuity alert
                $this->sendAuthContinuityAlert($successRate, $totalBuffered);
            }
        }
    }

    /**
     * Assess user experience continuity.
     */
    private function assessUserExperienceContinuity(WriteOperationReplayedEvent $event): void
    {
        $remainingBuffer = cache()->get('user_buffered_operations_count', 0);
        $totalBuffered = cache()->get('user_total_buffered_operations', 1);
        
        $recoveryProgress = (($totalBuffered - $remainingBuffer) / $totalBuffered) * 100;
        
        cache()->put('user_experience_continuity_recovery_progress', $recoveryProgress, 3600);
        
        Log::info('User Service: User experience continuity assessment', [
            'service' => 'user-service',
            'recovery_progress_percentage' => round($recoveryProgress, 2),
            'remaining_operations' => $remainingBuffer,
            'user_experience_status' => $this->getUserExperienceStatus($recoveryProgress),
            'auth_continuity_status' => 'monitoring_active',
        ]);
    }

    /**
     * Get user experience status based on progress.
     */
    private function getUserExperienceStatus(float $recoveryProgress): string
    {
        if ($recoveryProgress >= 100) {
            return 'optimal_user_experience';
        } elseif ($recoveryProgress >= 90) {
            return 'good_user_experience';
        } elseif ($recoveryProgress >= 70) {
            return 'acceptable_user_experience';
        } elseif ($recoveryProgress >= 50) {
            return 'degraded_user_experience';
        } else {
            return 'poor_user_experience';
        }
    }

    /**
     * Update user service health metrics based on replay success.
     */
    private function updateUserServiceHealthMetrics(WriteOperationReplayedEvent $event): void
    {
        $remainingBuffer = cache()->get('user_buffered_operations_count', 0);
        
        // Update service health based on buffer status (user experience focused)
        if ($remainingBuffer === 0) {
            cache()->put('user_service_health', 'healthy', 3600);
            cache()->put('user_service_mode', 'normal_auth_operations', 3600);
            cache()->put('user_service_experience_status', 'optimal', 3600);
        } elseif ($remainingBuffer < 8) {
            cache()->put('user_service_health', 'recovering', 3600);
            cache()->put('user_service_mode', 'auth_recovery', 3600);
            cache()->put('user_service_experience_status', 'good', 3600);
        } else {
            cache()->put('user_service_health', 'degraded', 3600);
            cache()->put('user_service_mode', 'auth_failover_recovery', 3600);
            cache()->put('user_service_experience_status', 'degraded', 3600);
        }

        Log::info('User Service: Health metrics updated after authentication replay', [
            'service' => 'user-service',
            'health_status' => cache()->get('user_service_health'),
            'service_mode' => cache()->get('user_service_mode'),
            'experience_status' => cache()->get('user_service_experience_status'),
            'remaining_buffer' => $remainingBuffer,
        ]);
    }

    /**
     * Handle complete authentication buffer recovery.
     */
    private function handleCompleteAuthBufferRecovery(): void
    {
        Log::info('User Service: COMPLETE AUTHENTICATION BUFFER RECOVERY ACHIEVED', [
            'service' => 'user-service',
            'status' => 'all_auth_operations_replayed',
            'user_experience_impact' => 'Full authentication processing capability restored',
            'auth_continuity_status' => 'Authentication continuity fully restored',
            'service_health' => 'healthy',
        ]);

        // Reset authentication failover-related cache entries
        cache()->forget('user_service_failover_started');
        cache()->forget('user_service_buffer_all_writes');
        cache()->forget('user_service_auth_degraded_mode');
        cache()->put('user_service_auth_recovery_completed', now()->toISOString(), 86400);

        // Send authentication recovery completion alert
        $this->sendAuthRecoveryCompletionAlert();
    }

    /**
     * Notify user support team of recovery progress.
     */
    private function notifyUserSupportTeamOfRecovery(WriteOperationReplayedEvent $event): void
    {
        $userSupportTeam = [
            'User Support Manager',
            'Customer Success Team',
            'Product Team',
            'User Experience Team'
        ];

        foreach ($userSupportTeam as $team) {
            cache()->put("user_service_auth_recovery_notification_{$team}", [
                'status' => 'auth_operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get('user_experience_continuity_recovery_progress', 0),
                'experience_status' => cache()->get('user_service_experience_status', 'unknown'),
                'message' => 'User authentication operation successfully replayed - user experience recovering',
            ], 1800); // 30 minutes
        }
    }

    /**
     * Notify dependent services of authentication recovery.
     */
    private function notifyDependentServicesOfAuthRecovery(WriteOperationReplayedEvent $event): void
    {
        $dependentServices = ['order-service', 'payment-service', 'notification-service', 'analytics-service', 'auction-service', 'bidding-service'];

        foreach ($dependentServices as $service) {
            cache()->put("user_service_auth_recovery_notification_{$service}", [
                'status' => 'auth_operation_replayed',
                'operation_type' => $event->operationType,
                'timestamp' => now()->toISOString(),
                'recovery_progress' => cache()->get('user_experience_continuity_recovery_progress', 0),
                'message' => 'User service authentication operation replayed - auth processing recovering',
                'auth_impact' => 'Authentication continuity maintained',
            ], 1800); // 30 minutes
        }
    }

    /**
     * Update user experience metrics.
     */
    private function updateUserExperienceMetrics(WriteOperationReplayedEvent $event): void
    {
        // Track user experience operations that might be affected
        $activeUsers = cache()->get('user_service_active_users', 0);
        $registrationRate = cache()->get('user_service_registration_rate', 0);
        $loginRate = cache()->get('user_service_login_rate', 0);
        
        // Update user experience health metrics
        cache()->put('user_service_experience_health', 'recovering', 3600);
        cache()->put('user_service_last_auth_operation_replayed', $event->operationType, 3600);
        
        Log::info('User Service: Updated user experience metrics', [
            'service' => 'user-service',
            'active_users' => $activeUsers,
            'registration_rate' => $registrationRate,
            'login_rate' => $loginRate,
            'operation_type' => $event->operationType,
            'experience_health' => 'recovering',
        ]);
    }

    /**
     * Send user experience degradation alert.
     */
    private function sendUserExperienceDegradationAlert(WriteOperationReplayedEvent $event): void
    {
        try {
            Log::warning('User Service: USER EXPERIENCE DEGRADATION ALERT', [
                'alert_type' => 'user_experience_degradation',
                'service' => 'user-service',
                'operation_type' => $event->operationType,
                'replay_duration' => $event->replayDurationSeconds,
                'user_experience_impact' => 'User authentication experience may be degraded',
                'auth_continuity_risk' => 'MEDIUM - Authentication continuity delayed',
                'recommended_action' => 'Monitor authentication performance and user support channels',
            ]);

        } catch (\Exception $e) {
            Log::error('User Service: Failed to send user experience degradation alert', [
                'error' => $e->getMessage(),
                'operation_type' => $event->operationType,
            ]);
        }
    }

    /**
     * Send authentication continuity alert.
     */
    private function sendAuthContinuityAlert(float $successRate, int $totalBuffered): void
    {
        try {
            Log::warning('User Service: AUTHENTICATION CONTINUITY ALERT', [
                'alert_type' => 'auth_continuity_risk',
                'service' => 'user-service',
                'success_rate' => $successRate,
                'total_buffered' => $totalBuffered,
                'user_experience_impact' => 'User authentication experience may be compromised',
                'auth_continuity_risk' => 'HIGH - Authentication continuity at risk',
                'recommended_action' => 'Escalate to user support and authentication teams',
            ]);

        } catch (\Exception $e) {
            Log::error('User Service: Failed to send authentication continuity alert', [
                'error' => $e->getMessage(),
                'success_rate' => $successRate,
            ]);
        }
    }

    /**
     * Send authentication recovery completion alert.
     */
    private function sendAuthRecoveryCompletionAlert(): void
    {
        try {
            Log::info('User Service: AUTHENTICATION RECOVERY COMPLETION ALERT', [
                'alert_type' => 'auth_recovery_complete',
                'service' => 'user-service',
                'status' => 'fully_recovered',
                'user_experience_impact' => 'All buffered authentication operations successfully replayed',
                'auth_continuity_status' => 'Authentication continuity fully restored',
                'service_health' => 'healthy',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('User Service: Failed to send authentication recovery completion alert', [
                'error' => $e->getMessage(),
                'service' => 'user-service',
            ]);
        }
    }
}
