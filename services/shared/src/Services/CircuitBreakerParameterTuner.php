<?php

namespace Shared\Services;

use Illuminate\Support\Facades\Cache;
use Shared\Facades\SharedLog;
use Shared\Services\DatabaseTopologyMapper;

/**
 * CircuitBreakerParameterTuner
 * 
 * Fine-tunes circuit breaker parameters based on real-time performance metrics,
 * historical failure patterns, and system load characteristics.
 * 
 * This service continuously monitors circuit breaker performance and adjusts
 * parameters to optimize protection while minimizing false positives.
 */
class CircuitBreakerParameterTuner
{
    /**
     * Performance metrics cache key prefix
     */
    private const METRICS_CACHE_PREFIX = 'circuit_breaker_metrics_';
    
    /**
     * Tuning history cache key prefix
     */
    private const TUNING_HISTORY_PREFIX = 'tuning_history_';
    
    /**
     * Maximum tuning adjustments per session
     */
    private const MAX_ADJUSTMENTS_PER_SESSION = 5;

    /**
     * Minimum observation period before tuning (minutes)
     */
    private const MIN_OBSERVATION_PERIOD = 30;

    /**
     * Performance thresholds for tuning decisions
     */
    private const PERFORMANCE_THRESHOLDS = [
        'false_positive_rate' => 0.15, // 15% false positive threshold
        'recovery_success_rate' => 0.80, // 80% recovery success threshold
        'average_response_time_ms' => 500, // 500ms response time threshold
        'failure_rate' => 0.10, // 10% failure rate threshold
    ];

    /**
     * Tuning adjustment factors
     */
    private const ADJUSTMENT_FACTORS = [
        'failure_threshold' => [
            'increase_factor' => 1.2,
            'decrease_factor' => 0.8,
            'min_value' => 1,
            'max_value' => 20
        ],
        'recovery_timeout' => [
            'increase_factor' => 1.3,
            'decrease_factor' => 0.7,
            'min_value' => 5,
            'max_value' => 300
        ],
        'success_threshold' => [
            'increase_factor' => 1.5,
            'decrease_factor' => 0.8,
            'min_value' => 1,
            'max_value' => 10
        ],
        'timeout' => [
            'increase_factor' => 1.2,
            'decrease_factor' => 0.9,
            'min_value' => 3,
            'max_value' => 60
        ]
    ];

    private DatabaseTopologyMapper $topologyMapper;

    public function __construct(DatabaseTopologyMapper $topologyMapper)
    {
        $this->topologyMapper = $topologyMapper;
    }

    /**
     * Analyze and tune circuit breaker parameters
     *
     * @param array $connections Connections to analyze
     * @return array Tuning results
     */
    public function tuneParameters(array $connections = []): array
    {
        $tuningId = $this->generateTuningId();
        
        SharedLog::databaseFailover('circuit_breaker_parameter_tuning_started', [
            'tuning_id' => $tuningId,
            'connections_count' => count($connections),
            'timestamp' => now()->toISOString()
        ]);

        try {
            // If no connections specified, get all from topology
            if (empty($connections)) {
                $topology = $this->topologyMapper->mapTopology();
                $connections = array_keys($topology['connections']);
            }

            $tuningResults = [];
            $totalAdjustments = 0;

            foreach ($connections as $connectionName) {
                if ($totalAdjustments >= self::MAX_ADJUSTMENTS_PER_SESSION) {
                    SharedLog::databaseFailover('circuit_breaker_tuning_limit_reached', [
                        'tuning_id' => $tuningId,
                        'max_adjustments' => self::MAX_ADJUSTMENTS_PER_SESSION,
                        'connection_name' => $connectionName
                    ]);
                    break;
                }

                $result = $this->tuneConnectionParameters($connectionName, $tuningId);
                $tuningResults[$connectionName] = $result;
                
                if ($result['adjustments_made'] > 0) {
                    $totalAdjustments += $result['adjustments_made'];
                }
            }

            SharedLog::databaseFailover('circuit_breaker_parameter_tuning_completed', [
                'tuning_id' => $tuningId,
                'connections_tuned' => count($tuningResults),
                'total_adjustments' => $totalAdjustments,
                'tuning_summary' => $this->generateTuningSummary($tuningResults)
            ]);

            return [
                'tuning_id' => $tuningId,
                'results' => $tuningResults,
                'total_adjustments' => $totalAdjustments,
                'completed_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            SharedLog::databaseFailover('circuit_breaker_parameter_tuning_failed', [
                'tuning_id' => $tuningId,
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e)
            ]);

            throw $e;
        }
    }

    /**
     * Tune parameters for a specific connection
     *
     * @param string $connectionName Connection name
     * @param string $tuningId Tuning session ID
     * @return array Tuning result
     */
    private function tuneConnectionParameters(string $connectionName, string $tuningId): array
    {
        $metrics = $this->collectConnectionMetrics($connectionName);
        
        if (!$this->hasEnoughData($metrics)) {
            return [
                'connection_name' => $connectionName,
                'status' => 'insufficient_data',
                'adjustments_made' => 0,
                'reason' => 'Not enough historical data for tuning'
            ];
        }

        $currentConfig = $this->getCurrentConfig($connectionName);
        $analysis = $this->analyzePerformance($metrics);
        $recommendations = $this->generateRecommendations($analysis, $currentConfig);
        
        if (empty($recommendations)) {
            return [
                'connection_name' => $connectionName,
                'status' => 'no_adjustments_needed',
                'adjustments_made' => 0,
                'analysis' => $analysis
            ];
        }

        $newConfig = $this->applyRecommendations($currentConfig, $recommendations);
        $this->saveConfigurationChanges($connectionName, $currentConfig, $newConfig, $tuningId);

        SharedLog::databaseFailover('connection_circuit_breaker_parameters_tuned', [
            'tuning_id' => $tuningId,
            'connection_name' => $connectionName,
            'old_config' => $currentConfig,
            'new_config' => $newConfig,
            'recommendations' => $recommendations,
            'performance_analysis' => $analysis
        ]);

        return [
            'connection_name' => $connectionName,
            'status' => 'tuned',
            'adjustments_made' => count($recommendations),
            'old_config' => $currentConfig,
            'new_config' => $newConfig,
            'recommendations' => $recommendations,
            'analysis' => $analysis
        ];
    }

    /**
     * Collect performance metrics for a connection
     *
     * @param string $connectionName Connection name
     * @return array Performance metrics
     */
    private function collectConnectionMetrics(string $connectionName): array
    {
        $cacheKey = self::METRICS_CACHE_PREFIX . $connectionName;
        
        // Try to get cached metrics
        $cachedMetrics = Cache::get($cacheKey, []);
        
        // Simulate metrics collection (in real implementation, this would query actual metrics)
        $metrics = array_merge([
            'total_requests' => 0,
            'failed_requests' => 0,
            'circuit_opens' => 0,
            'false_positives' => 0,
            'recovery_attempts' => 0,
            'successful_recoveries' => 0,
            'average_response_time_ms' => 0,
            'p95_response_time_ms' => 0,
            'observation_period_minutes' => 0,
            'last_updated' => now()->subHours(2)->toISOString()
        ], $cachedMetrics);

        // Update with current data (placeholder - would integrate with actual metrics system)
        $metrics['observation_period_minutes'] = now()->diffInMinutes($metrics['last_updated'] ?? now()->subHour());
        
        return $metrics;
    }

    /**
     * Check if there's enough data for tuning
     *
     * @param array $metrics Performance metrics
     * @return bool Whether there's enough data
     */
    private function hasEnoughData(array $metrics): bool
    {
        return $metrics['observation_period_minutes'] >= self::MIN_OBSERVATION_PERIOD
            && $metrics['total_requests'] >= 100; // Minimum request threshold
    }

    /**
     * Get current circuit breaker configuration
     *
     * @param string $connectionName Connection name
     * @return array Current configuration
     */
    private function getCurrentConfig(string $connectionName): array
    {
        // Get from Fuse configuration
        $config = config("fuse.query_connections.{$connectionName}", []);
        
        // Merge with defaults if not found
        if (empty($config)) {
            $config = config('fuse.query_defaults', [
                'failure_threshold' => 5,
                'recovery_timeout' => 30,
                'success_threshold' => 3,
                'timeout' => 10
            ]);
        }

        return $config;
    }

    /**
     * Analyze performance metrics
     *
     * @param array $metrics Performance metrics
     * @return array Performance analysis
     */
    private function analyzePerformance(array $metrics): array
    {
        $analysis = [
            'failure_rate' => $metrics['total_requests'] > 0 
                ? $metrics['failed_requests'] / $metrics['total_requests'] 
                : 0,
            'false_positive_rate' => $metrics['circuit_opens'] > 0 
                ? $metrics['false_positives'] / $metrics['circuit_opens'] 
                : 0,
            'recovery_success_rate' => $metrics['recovery_attempts'] > 0 
                ? $metrics['successful_recoveries'] / $metrics['recovery_attempts'] 
                : 1,
            'average_response_time_ms' => $metrics['average_response_time_ms'],
            'performance_issues' => []
        ];

        // Identify performance issues
        if ($analysis['failure_rate'] > self::PERFORMANCE_THRESHOLDS['failure_rate']) {
            $analysis['performance_issues'][] = 'high_failure_rate';
        }

        if ($analysis['false_positive_rate'] > self::PERFORMANCE_THRESHOLDS['false_positive_rate']) {
            $analysis['performance_issues'][] = 'high_false_positive_rate';
        }

        if ($analysis['recovery_success_rate'] < self::PERFORMANCE_THRESHOLDS['recovery_success_rate']) {
            $analysis['performance_issues'][] = 'low_recovery_success_rate';
        }

        if ($analysis['average_response_time_ms'] > self::PERFORMANCE_THRESHOLDS['average_response_time_ms']) {
            $analysis['performance_issues'][] = 'high_response_time';
        }

        return $analysis;
    }

    /**
     * Generate tuning recommendations
     *
     * @param array $analysis Performance analysis
     * @param array $currentConfig Current configuration
     * @return array Recommendations
     */
    private function generateRecommendations(array $analysis, array $currentConfig): array
    {
        $recommendations = [];

        // High false positive rate - increase failure threshold
        if (in_array('high_false_positive_rate', $analysis['performance_issues'])) {
            $recommendations[] = [
                'parameter' => 'failure_threshold',
                'action' => 'increase',
                'reason' => 'Reduce false positives',
                'current_value' => $currentConfig['failure_threshold'] ?? 5,
                'recommended_value' => $this->calculateAdjustment(
                    $currentConfig['failure_threshold'] ?? 5,
                    'failure_threshold',
                    'increase'
                )
            ];
        }

        // High failure rate - decrease failure threshold for faster protection
        if (in_array('high_failure_rate', $analysis['performance_issues'])) {
            $recommendations[] = [
                'parameter' => 'failure_threshold',
                'action' => 'decrease',
                'reason' => 'Faster failure detection',
                'current_value' => $currentConfig['failure_threshold'] ?? 5,
                'recommended_value' => $this->calculateAdjustment(
                    $currentConfig['failure_threshold'] ?? 5,
                    'failure_threshold',
                    'decrease'
                )
            ];
        }

        // Low recovery success rate - increase recovery timeout
        if (in_array('low_recovery_success_rate', $analysis['performance_issues'])) {
            $recommendations[] = [
                'parameter' => 'recovery_timeout',
                'action' => 'increase',
                'reason' => 'Allow more time for recovery',
                'current_value' => $currentConfig['recovery_timeout'] ?? 30,
                'recommended_value' => $this->calculateAdjustment(
                    $currentConfig['recovery_timeout'] ?? 30,
                    'recovery_timeout',
                    'increase'
                )
            ];
        }

        // High response time - decrease timeout for faster failure detection
        if (in_array('high_response_time', $analysis['performance_issues'])) {
            $recommendations[] = [
                'parameter' => 'timeout',
                'action' => 'decrease',
                'reason' => 'Faster timeout for slow responses',
                'current_value' => $currentConfig['timeout'] ?? 10,
                'recommended_value' => $this->calculateAdjustment(
                    $currentConfig['timeout'] ?? 10,
                    'timeout',
                    'decrease'
                )
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate parameter adjustment
     *
     * @param int|float $currentValue Current parameter value
     * @param string $parameter Parameter name
     * @param string $action Adjustment action (increase/decrease)
     * @return int|float Adjusted value
     */
    private function calculateAdjustment($currentValue, string $parameter, string $action): int
    {
        $factors = self::ADJUSTMENT_FACTORS[$parameter];
        
        if ($action === 'increase') {
            $newValue = $currentValue * $factors['increase_factor'];
        } else {
            $newValue = $currentValue * $factors['decrease_factor'];
        }

        // Apply bounds
        $newValue = max($factors['min_value'], min($factors['max_value'], $newValue));
        
        return (int)round($newValue);
    }

    /**
     * Apply recommendations to configuration
     *
     * @param array $currentConfig Current configuration
     * @param array $recommendations Tuning recommendations
     * @return array New configuration
     */
    private function applyRecommendations(array $currentConfig, array $recommendations): array
    {
        $newConfig = $currentConfig;

        foreach ($recommendations as $recommendation) {
            $newConfig[$recommendation['parameter']] = $recommendation['recommended_value'];
        }

        $newConfig['tuned_at'] = now()->toISOString();
        $newConfig['tuning_version'] = ($currentConfig['tuning_version'] ?? 0) + 1;

        return $newConfig;
    }

    /**
     * Save configuration changes
     *
     * @param string $connectionName Connection name
     * @param array $oldConfig Old configuration
     * @param array $newConfig New configuration
     * @param string $tuningId Tuning session ID
     */
    private function saveConfigurationChanges(string $connectionName, array $oldConfig, array $newConfig, string $tuningId): void
    {
        // Apply to runtime configuration
        config(["fuse.query_connections.{$connectionName}" => $newConfig]);

        // Save tuning history
        $historyKey = self::TUNING_HISTORY_PREFIX . $connectionName;
        $history = Cache::get($historyKey, []);
        
        $history[] = [
            'tuning_id' => $tuningId,
            'timestamp' => now()->toISOString(),
            'old_config' => $oldConfig,
            'new_config' => $newConfig
        ];

        // Keep only last 50 tuning records
        if (count($history) > 50) {
            $history = array_slice($history, -50);
        }

        Cache::put($historyKey, $history, now()->addDays(30));
    }

    /**
     * Generate tuning summary
     *
     * @param array $tuningResults Tuning results
     * @return array Summary
     */
    private function generateTuningSummary(array $tuningResults): array
    {
        $summary = [
            'total_connections' => count($tuningResults),
            'tuned_connections' => 0,
            'insufficient_data_connections' => 0,
            'no_adjustments_needed' => 0,
            'parameters_adjusted' => []
        ];

        foreach ($tuningResults as $result) {
            switch ($result['status']) {
                case 'tuned':
                    $summary['tuned_connections']++;
                    if (isset($result['recommendations'])) {
                        foreach ($result['recommendations'] as $rec) {
                            $param = $rec['parameter'];
                            if (!isset($summary['parameters_adjusted'][$param])) {
                                $summary['parameters_adjusted'][$param] = 0;
                            }
                            $summary['parameters_adjusted'][$param]++;
                        }
                    }
                    break;
                case 'insufficient_data':
                    $summary['insufficient_data_connections']++;
                    break;
                case 'no_adjustments_needed':
                    $summary['no_adjustments_needed']++;
                    break;
            }
        }

        return $summary;
    }

    /**
     * Get tuning history for a connection
     *
     * @param string $connectionName Connection name
     * @return array Tuning history
     */
    public function getTuningHistory(string $connectionName): array
    {
        $historyKey = self::TUNING_HISTORY_PREFIX . $connectionName;
        return Cache::get($historyKey, []);
    }

    /**
     * Reset tuning for a connection (revert to defaults)
     *
     * @param string $connectionName Connection name
     * @return bool Success status
     */
    public function resetTuning(string $connectionName): bool
    {
        $resetId = $this->generateResetId();
        
        try {
            $currentConfig = $this->getCurrentConfig($connectionName);
            $defaultConfig = config('fuse.query_defaults');
            
            // Apply default configuration
            config(["fuse.query_connections.{$connectionName}" => $defaultConfig]);
            
            SharedLog::databaseFailover('circuit_breaker_tuning_reset', [
                'reset_id' => $resetId,
                'connection_name' => $connectionName,
                'old_config' => $currentConfig,
                'default_config' => $defaultConfig,
                'reset_at' => now()->toISOString()
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            SharedLog::databaseFailover('circuit_breaker_tuning_reset_failed', [
                'reset_id' => $resetId,
                'connection_name' => $connectionName,
                'error_message' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Generate unique tuning ID
     *
     * @return string Tuning ID
     */
    private function generateTuningId(): string
    {
        return 'tuning_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }

    /**
     * Generate unique reset ID
     *
     * @return string Reset ID
     */
    private function generateResetId(): string
    {
        return 'reset_' . uniqid() . '_' . substr(md5(microtime()), 0, 8);
    }
}
