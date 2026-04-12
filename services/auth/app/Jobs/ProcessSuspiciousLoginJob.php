<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Suspicious Login Processing Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Analyzes login patterns and detects suspicious authentication activities.
 * Critical for security monitoring, fraud prevention, and user account protection.
 */
class ProcessSuspiciousLoginJob extends BaseQueueJob
{
    public array $loginAttempts;
    public array $analysisTypes;
    public array $securityRules;
    public int $batchSize;
    public int $tries = 3;
    public int $timeout = 600; // 10 minutes for security analysis

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $loginAttempts,
        array $analysisTypes = [],
        array $securityRules = [],
        int $batchSize = 100
    ) {
        parent::__construct();
        
        $this->loginAttempts = $loginAttempts;
        $this->analysisTypes = $analysisTypes ?: $this->getDefaultAnalysisTypes();
        $this->securityRules = array_merge($this->getDefaultSecurityRules(), $securityRules);
        $this->batchSize = $batchSize;
        
        $this->onQueue($this->getQueueForBatchSize($batchSize));
        
        // Configure circuit breaker for suspicious login processing
        $this->configureCircuitBreaker([
            'service_name' => 'suspicious_login_processing',
            'failure_threshold' => 20, // 20% failure rate triggers circuit breaker
            'timeout' => 300, // 5 minutes timeout for security operations
            'recovery_timeout' => 600, // 10 minutes before attempting recovery
            'tags' => [
                'service' => 'auth-service',
                'job_type' => 'security',
                'operation' => 'login_analysis',
                'priority' => 'critical'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting suspicious login analysis with circuit breaker protection', [
            'login_attempts_count' => count($this->loginAttempts),
            'analysis_types' => $this->analysisTypes,
            'batch_size' => $this->batchSize,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'suspicious_login_processing'
        ]);

        $this->executeWithCircuitBreaker(function() {
            $results = [
                'processed' => 0,
                'suspicious' => 0,
                'blocked' => 0,
                'flagged' => 0,
                'alerts_sent' => 0,
                'errors' => []
            ];

            // Process login attempts in chunks
            $chunks = array_chunk($this->loginAttempts, $this->batchSize);
            
            foreach ($chunks as $chunkIndex => $chunk) {
                Log::debug('Processing suspicious login chunk', [
                    'chunk_index' => $chunkIndex + 1,
                    'chunk_size' => count($chunk),
                    'total_chunks' => count($chunks)
                ]);

                $chunkResults = $this->processLoginChunk($chunk);
                
                // Aggregate results
                $results['processed'] += $chunkResults['processed'];
                $results['suspicious'] += $chunkResults['suspicious'];
                $results['blocked'] += $chunkResults['blocked'];
                $results['flagged'] += $chunkResults['flagged'];
                $results['alerts_sent'] += $chunkResults['alerts_sent'];
                $results['errors'] = array_merge($results['errors'], $chunkResults['errors']);
            }

            Log::info('Suspicious login analysis completed successfully', [
                'total_processed' => $results['processed'],
                'suspicious_detected' => $results['suspicious'],
                'accounts_blocked' => $results['blocked'],
                'accounts_flagged' => $results['flagged'],
                'alerts_sent' => $results['alerts_sent'],
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            Log::error('Suspicious login analysis failed with circuit breaker protection', [
                'login_attempts_count' => count($this->loginAttempts),
                'analysis_types' => $this->analysisTypes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Process a chunk of login attempts
     */
    private function processLoginChunk(array $loginAttempts): array
    {
        $results = [
            'processed' => 0,
            'suspicious' => 0,
            'blocked' => 0,
            'flagged' => 0,
            'alerts_sent' => 0,
            'errors' => []
        ];

        foreach ($loginAttempts as $attempt) {
            try {
                $analysisResult = $this->analyzeLoginAttempt($attempt);
                
                $results['processed']++;
                
                if ($analysisResult['is_suspicious']) {
                    $results['suspicious']++;
                    
                    // Take security actions based on risk level
                    $actionResult = $this->takeSecurityAction($attempt, $analysisResult);
                    
                    if ($actionResult['blocked']) {
                        $results['blocked']++;
                    }
                    
                    if ($actionResult['flagged']) {
                        $results['flagged']++;
                    }
                    
                    if ($actionResult['alert_sent']) {
                        $results['alerts_sent']++;
                    }
                }

            } catch (\Exception $e) {
                $results['processed']++;
                $results['errors'][] = [
                    'attempt_id' => $attempt['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];

                Log::error('Failed to analyze login attempt', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $results;
    }

    /**
     * Analyze a single login attempt for suspicious activity
     */
    private function analyzeLoginAttempt(array $attempt): array
    {
        $startTime = microtime(true);
        $suspiciousFactors = [];
        $riskScore = 0;

        Log::debug('Analyzing login attempt', [
            'user_id' => $attempt['user_id'] ?? null,
            'ip_address' => $attempt['ip_address'] ?? null,
            'analysis_types' => $this->analysisTypes
        ]);

        foreach ($this->analysisTypes as $analysisType) {
            $result = $this->performAnalysisType($attempt, $analysisType);
            
            if ($result['suspicious']) {
                $suspiciousFactors[] = $result['factor'];
                $riskScore += $result['risk_score'];
            }
        }

        $processingTime = (microtime(true) - $startTime) * 1000;
        $isSuspicious = $riskScore >= $this->securityRules['risk_threshold'];

        // Log suspicious activity
        if ($isSuspicious) {
            $this->logSuspiciousActivity($attempt, $suspiciousFactors, $riskScore);
        }

        return [
            'is_suspicious' => $isSuspicious,
            'risk_score' => $riskScore,
            'suspicious_factors' => $suspiciousFactors,
            'processing_time_ms' => round($processingTime)
        ];
    }

    /**
     * Perform specific analysis type
     */
    private function performAnalysisType(array $attempt, string $analysisType): array
    {
        switch ($analysisType) {
            case 'ip_reputation':
                return $this->analyzeIpReputation($attempt);
            
            case 'geolocation_anomaly':
                return $this->analyzeGeolocationAnomaly($attempt);
            
            case 'device_fingerprint':
                return $this->analyzeDeviceFingerprint($attempt);
            
            case 'login_frequency':
                return $this->analyzeLoginFrequency($attempt);
            
            case 'failed_attempts':
                return $this->analyzeFailedAttempts($attempt);
            
            case 'time_pattern':
                return $this->analyzeTimePattern($attempt);
            
            default:
                return [
                    'suspicious' => false,
                    'factor' => "Unknown analysis type: {$analysisType}",
                    'risk_score' => 0
                ];
        }
    }

    /**
     * Analysis methods
     */
    private function analyzeIpReputation(array $attempt): array
    {
        $ipAddress = $attempt['ip_address'] ?? '';
        
        // Check against known malicious IPs (simplified)
        $maliciousIps = $this->securityRules['malicious_ips'] ?? [];
        $isMalicious = in_array($ipAddress, $maliciousIps);
        
        // Check for VPN/Proxy indicators
        $isProxy = $this->isProxyIp($ipAddress);
        
        return [
            'suspicious' => $isMalicious || $isProxy,
            'factor' => $isMalicious ? 'Malicious IP detected' : ($isProxy ? 'VPN/Proxy detected' : ''),
            'risk_score' => $isMalicious ? 50 : ($isProxy ? 20 : 0)
        ];
    }

    private function analyzeGeolocationAnomaly(array $attempt): array
    {
        $userId = $attempt['user_id'] ?? null;
        $currentLocation = $attempt['location'] ?? null;
        
        if (!$userId || !$currentLocation) {
            return ['suspicious' => false, 'factor' => '', 'risk_score' => 0];
        }
        
        // Get user's typical locations from recent logins
        $recentLocations = $this->getUserRecentLocations($userId);
        $isAnomalous = $this->isLocationAnomalous($currentLocation, $recentLocations);
        
        return [
            'suspicious' => $isAnomalous,
            'factor' => $isAnomalous ? 'Unusual login location' : '',
            'risk_score' => $isAnomalous ? 30 : 0
        ];
    }

    private function analyzeDeviceFingerprint(array $attempt): array
    {
        $userId = $attempt['user_id'] ?? null;
        $deviceFingerprint = $attempt['device_fingerprint'] ?? null;
        
        if (!$userId || !$deviceFingerprint) {
            return ['suspicious' => false, 'factor' => '', 'risk_score' => 0];
        }
        
        $knownDevices = $this->getUserKnownDevices($userId);
        $isNewDevice = !in_array($deviceFingerprint, $knownDevices);
        
        return [
            'suspicious' => $isNewDevice,
            'factor' => $isNewDevice ? 'New device detected' : '',
            'risk_score' => $isNewDevice ? 25 : 0
        ];
    }

    private function analyzeLoginFrequency(array $attempt): array
    {
        $userId = $attempt['user_id'] ?? null;
        $timestamp = $attempt['timestamp'] ?? now();
        
        if (!$userId) {
            return ['suspicious' => false, 'factor' => '', 'risk_score' => 0];
        }
        
        $recentLogins = $this->getUserRecentLogins($userId, 60); // Last 60 minutes
        $isHighFrequency = count($recentLogins) > $this->securityRules['max_logins_per_hour'];
        
        return [
            'suspicious' => $isHighFrequency,
            'factor' => $isHighFrequency ? 'High login frequency detected' : '',
            'risk_score' => $isHighFrequency ? 35 : 0
        ];
    }

    private function analyzeFailedAttempts(array $attempt): array
    {
        $userId = $attempt['user_id'] ?? null;
        $ipAddress = $attempt['ip_address'] ?? null;
        
        if (!$userId && !$ipAddress) {
            return ['suspicious' => false, 'factor' => '', 'risk_score' => 0];
        }
        
        $failedAttempts = $this->getRecentFailedAttempts($userId, $ipAddress);
        $isHighFailureRate = $failedAttempts > $this->securityRules['max_failed_attempts'];
        
        return [
            'suspicious' => $isHighFailureRate,
            'factor' => $isHighFailureRate ? 'Multiple failed login attempts' : '',
            'risk_score' => $isHighFailureRate ? 40 : 0
        ];
    }

    private function analyzeTimePattern(array $attempt): array
    {
        $timestamp = Carbon::parse($attempt['timestamp'] ?? now());
        $hour = $timestamp->hour;
        
        // Check if login is during unusual hours (2 AM - 6 AM)
        $isUnusualTime = $hour >= 2 && $hour <= 6;
        
        return [
            'suspicious' => $isUnusualTime,
            'factor' => $isUnusualTime ? 'Login during unusual hours' : '',
            'risk_score' => $isUnusualTime ? 15 : 0
        ];
    }

    /**
     * Helper methods
     */
    private function isProxyIp(string $ipAddress): bool
    {
        // Simplified proxy detection - in production, use proper IP intelligence service
        $proxyRanges = $this->securityRules['proxy_ranges'] ?? [];
        
        foreach ($proxyRanges as $range) {
            if ($this->ipInRange($ipAddress, $range)) {
                return true;
            }
        }
        
        return false;
    }

    private function ipInRange(string $ip, string $range): bool
    {
        // Simplified IP range check
        return strpos($ip, substr($range, 0, strpos($range, '/'))) === 0;
    }

    private function getUserRecentLocations(int $userId): array
    {
        // Get user's recent login locations from database
        return DB::table('login_logs')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'success')
            ->pluck('location')
            ->unique()
            ->toArray();
    }

    private function isLocationAnomalous(string $currentLocation, array $recentLocations): bool
    {
        // Simple location anomaly detection
        return !in_array($currentLocation, $recentLocations) && count($recentLocations) > 0;
    }

    private function getUserKnownDevices(int $userId): array
    {
        return DB::table('login_logs')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subDays(90))
            ->where('status', 'success')
            ->pluck('device_fingerprint')
            ->unique()
            ->filter()
            ->toArray();
    }

    private function getUserRecentLogins(int $userId, int $minutes): array
    {
        return DB::table('login_logs')
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->get()
            ->toArray();
    }

    private function getRecentFailedAttempts(?int $userId, ?string $ipAddress): int
    {
        $query = DB::table('login_logs')
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(1));
        
        if ($userId) {
            $query->where('user_id', $userId);
        }
        
        if ($ipAddress) {
            $query->orWhere('ip_address', $ipAddress);
        }
        
        return $query->count();
    }

    private function logSuspiciousActivity(array $attempt, array $factors, int $riskScore): void
    {
        DB::table('suspicious_activities')->insert([
            'user_id' => $attempt['user_id'] ?? null,
            'ip_address' => $attempt['ip_address'] ?? null,
            'suspicious_factors' => json_encode($factors),
            'risk_score' => $riskScore,
            'attempt_data' => json_encode($attempt),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function takeSecurityAction(array $attempt, array $analysisResult): array
    {
        $riskScore = $analysisResult['risk_score'];
        $userId = $attempt['user_id'] ?? null;
        $ipAddress = $attempt['ip_address'] ?? null;
        
        $result = [
            'blocked' => false,
            'flagged' => false,
            'alert_sent' => false
        ];
        
        // High risk - block account
        if ($riskScore >= $this->securityRules['block_threshold']) {
            if ($userId) {
                $this->blockUserAccount($userId);
                $result['blocked'] = true;
            }
        }
        
        // Medium risk - flag for review
        if ($riskScore >= $this->securityRules['flag_threshold']) {
            if ($userId) {
                $this->flagUserAccount($userId);
                $result['flagged'] = true;
            }
        }
        
        // Send security alert
        if ($riskScore >= $this->securityRules['alert_threshold']) {
            $this->sendSecurityAlert($attempt, $analysisResult);
            $result['alert_sent'] = true;
        }
        
        return $result;
    }

    private function blockUserAccount(int $userId): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'status' => 'blocked',
                'blocked_at' => now(),
                'blocked_reason' => 'Suspicious login activity detected'
            ]);
    }

    private function flagUserAccount(int $userId): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'security_flag' => true,
                'flagged_at' => now(),
                'flag_reason' => 'Suspicious login activity detected'
            ]);
    }

    private function sendSecurityAlert(array $attempt, array $analysisResult): void
    {
        // In production, this would send alerts via notification service
        Log::warning('Security alert: Suspicious login detected', [
            'user_id' => $attempt['user_id'] ?? null,
            'ip_address' => $attempt['ip_address'] ?? null,
            'risk_score' => $analysisResult['risk_score'],
            'factors' => $analysisResult['suspicious_factors']
        ]);
    }

    /**
     * Configuration methods
     */
    private function getDefaultAnalysisTypes(): array
    {
        return [
            'ip_reputation',
            'geolocation_anomaly',
            'device_fingerprint',
            'login_frequency',
            'failed_attempts',
            'time_pattern'
        ];
    }

    private function getDefaultSecurityRules(): array
    {
        return [
            'risk_threshold' => 50,
            'block_threshold' => 80,
            'flag_threshold' => 60,
            'alert_threshold' => 40,
            'max_logins_per_hour' => 10,
            'max_failed_attempts' => 5,
            'malicious_ips' => [],
            'proxy_ranges' => []
        ];
    }

    private function getQueueForBatchSize(int $batchSize): string
    {
        return match (true) {
            $batchSize >= 500 => 'security-analysis-large',
            $batchSize >= 200 => 'security-analysis-medium',
            $batchSize >= 50 => 'security-analysis-small',
            default => 'security-analysis-default',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Suspicious login processing job failed permanently', [
            'login_attempts_count' => count($this->loginAttempts),
            'analysis_types' => $this->analysisTypes,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);
    }
}

