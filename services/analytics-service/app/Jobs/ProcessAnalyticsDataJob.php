<?php

namespace App\Jobs;

use App\Models\BusinessMetric;
use App\Models\UserAnalytic;
use App\Services\AnalyticsService;
use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Analytics Data Processing Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Aggregates raw user analytics data into business metrics for reporting and dashboards.
 * This is the foundation job for business intelligence - processes daily/hourly metrics
 * from user events and creates aggregated business metrics for efficient querying.
 */
class ProcessAnalyticsDataJob extends BaseQueueJob
{
    public string $aggregationType;
    public ?Carbon $targetDate;
    public array $metricTypes;
    public int $tries = 3;
    public int $timeout = 900; // 15 minutes for data processing

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $aggregationType = 'daily',
        ?Carbon $targetDate = null,
        array $metricTypes = []
    ) {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->aggregationType = $aggregationType;
        $this->targetDate = $targetDate ?? now()->subDay(); // Default to yesterday
        $this->metricTypes = $metricTypes ?: $this->getDefaultMetricTypes();
        
        // Set queue based on aggregation type priority
        $this->onQueue($this->getQueueForAggregationType($aggregationType));
        
        // Configure circuit breaker for analytics data processing
        $this->configureCircuitBreaker([
            'service_name' => 'analytics_data_processing',
            'failure_threshold' => 30, // 30% failure rate triggers circuit breaker
            'timeout' => 120, // 2 minutes timeout for data aggregation
            'recovery_timeout' => 300, // 5 minutes before attempting recovery
            'tags' => [
                'service' => 'analytics-service',
                'job_type' => 'data_processing',
                'aggregation_type' => $aggregationType,
                'priority' => 'critical'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        Log::info('Starting analytics data processing with circuit breaker protection', [
            'aggregation_type' => $this->aggregationType,
            'target_date' => $this->targetDate->toDateString(),
            'metric_types' => $this->metricTypes,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'analytics_data_processing'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() use ($analyticsService) {
            $results = [];
            
            $results = collect($this->metricTypes)->mapWithKeys(function ($metricType) use ($analyticsService) {
                $result = $this->processMetricType($metricType, $analyticsService);
                
                Log::debug('Processed metric type', [
                    'metric_type' => $metricType,
                    'records_processed' => $result['records_processed'],
                    'metrics_created' => $result['metrics_created']
                ]);
                
                return [$metricType => $result];
            })->toArray();

            Log::info('Analytics data processing completed successfully', [
                'aggregation_type' => $this->aggregationType,
                'target_date' => $this->targetDate->toDateString(),
                'total_metrics_processed' => array_sum(array_column($results, 'records_processed')),
                'total_metrics_created' => array_sum(array_column($results, 'metrics_created')),
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            // Circuit breaker failure handler
            Log::error('Analytics data processing failed with circuit breaker protection', [
                'aggregation_type' => $this->aggregationType,
                'target_date' => $this->targetDate->toDateString(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Process a specific metric type
     */
    private function processMetricType(string $metricType, AnalyticsService $analyticsService): array
    {
        $startDate = $this->getStartDateForAggregation();
        $endDate = $this->getEndDateForAggregation();
        
        Log::debug('Processing metric type', [
            'metric_type' => $metricType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'aggregation_type' => $this->aggregationType
        ]);

        switch ($metricType) {
            case 'user_events':
                return $this->aggregateUserEvents($startDate, $endDate);
            
            case 'active_users':
                return $this->aggregateActiveUsers($startDate, $endDate);
            
            case 'session_metrics':
                return $this->aggregateSessionMetrics($startDate, $endDate);
            
            case 'conversion_funnel':
                return $this->aggregateConversionMetrics($startDate, $endDate);
            
            case 'event_types':
                return $this->aggregateEventTypes($startDate, $endDate);
            
            default:
                Log::warning('Unknown metric type', ['metric_type' => $metricType]);
                return ['records_processed' => 0, 'metrics_created' => 0];
        }
    }

    /**
     * Aggregate user events data
     */
    private function aggregateUserEvents(Carbon $startDate, Carbon $endDate): array
    {
        $query = UserAnalytic::dateRange($startDate, $endDate);
        
        if ($this->aggregationType === 'hourly') {
            $groupBy = DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as period');
        } else {
            $groupBy = DB::raw('DATE(created_at) as period');
        }

        $aggregatedData = $query
            ->select($groupBy, DB::raw('COUNT(*) as total_events'))
            ->groupBy('period')
            ->get();

        $metricsCreated = 0;
        foreach ($aggregatedData as $data) {
            $metricDate = Carbon::parse($data->period);
            
            BusinessMetric::updateOrCreate([
                'metric_date' => $metricDate,
                'metric_type' => 'user_events_' . $this->aggregationType,
            ], [
                'value' => $data->total_events,
                'breakdown' => [
                    'aggregation_type' => $this->aggregationType,
                    'period' => $data->period,
                    'processed_at' => now()->toISOString()
                ]
            ]);
            
            $metricsCreated++;
        }

        return [
            'records_processed' => $aggregatedData->count(),
            'metrics_created' => $metricsCreated
        ];
    }

    /**
     * Aggregate active users data
     */
    private function aggregateActiveUsers(Carbon $startDate, Carbon $endDate): array
    {
        $query = UserAnalytic::dateRange($startDate, $endDate);
        
        if ($this->aggregationType === 'hourly') {
            $groupBy = DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as period');
        } else {
            $groupBy = DB::raw('DATE(created_at) as period');
        }

        $aggregatedData = $query
            ->select($groupBy, DB::raw('COUNT(DISTINCT user_id) as active_users'))
            ->groupBy('period')
            ->get();

        $metricsCreated = 0;
        foreach ($aggregatedData as $data) {
            $metricDate = Carbon::parse($data->period);
            
            BusinessMetric::updateOrCreate([
                'metric_date' => $metricDate,
                'metric_type' => 'active_users_' . $this->aggregationType,
            ], [
                'value' => $data->active_users,
                'breakdown' => [
                    'aggregation_type' => $this->aggregationType,
                    'period' => $data->period,
                    'processed_at' => now()->toISOString()
                ]
            ]);
            
            $metricsCreated++;
        }

        return [
            'records_processed' => $aggregatedData->count(),
            'metrics_created' => $metricsCreated
        ];
    }

    /**
     * Aggregate session metrics data
     */
    private function aggregateSessionMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $query = UserAnalytic::dateRange($startDate, $endDate)
            ->whereNotNull('session_id');
        
        if ($this->aggregationType === 'hourly') {
            $groupBy = DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as period');
        } else {
            $groupBy = DB::raw('DATE(created_at) as period');
        }

        $aggregatedData = $query
            ->select(
                $groupBy,
                DB::raw('COUNT(DISTINCT session_id) as unique_sessions'),
                DB::raw('COUNT(*) as total_session_events')
            )
            ->groupBy('period')
            ->get();

        $metricsCreated = 0;
        foreach ($aggregatedData as $data) {
            $metricDate = Carbon::parse($data->period);
            
            // Create unique sessions metric
            BusinessMetric::updateOrCreate([
                'metric_date' => $metricDate,
                'metric_type' => 'unique_sessions_' . $this->aggregationType,
            ], [
                'value' => $data->unique_sessions,
                'breakdown' => [
                    'aggregation_type' => $this->aggregationType,
                    'period' => $data->period,
                    'total_session_events' => $data->total_session_events,
                    'processed_at' => now()->toISOString()
                ]
            ]);
            
            $metricsCreated++;
        }

        return [
            'records_processed' => $aggregatedData->count(),
            'metrics_created' => $metricsCreated
        ];
    }

    /**
     * Aggregate conversion metrics data
     */
    private function aggregateConversionMetrics(Carbon $startDate, Carbon $endDate): array
    {
        // Define conversion funnel steps
        $funnelSteps = [
            'page_view',
            'signup_started', 
            'signup_completed',
            'first_action',
            'conversion'
        ];

        $metricsCreated = 0;
        
        foreach ($funnelSteps as $step) {
            $query = UserAnalytic::dateRange($startDate, $endDate)
                ->where('event_type', $step);
            
            if ($this->aggregationType === 'hourly') {
                $groupBy = DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as period');
            } else {
                $groupBy = DB::raw('DATE(created_at) as period');
            }

            $aggregatedData = $query
                ->select(
                    $groupBy,
                    DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                    DB::raw('COUNT(*) as total_events')
                )
                ->groupBy('period')
                ->get();

            foreach ($aggregatedData as $data) {
                $metricDate = Carbon::parse($data->period);
                
                BusinessMetric::updateOrCreate([
                    'metric_date' => $metricDate,
                    'metric_type' => 'conversion_' . $step . '_' . $this->aggregationType,
                ], [
                    'value' => $data->unique_users,
                    'breakdown' => [
                        'aggregation_type' => $this->aggregationType,
                        'funnel_step' => $step,
                        'period' => $data->period,
                        'total_events' => $data->total_events,
                        'processed_at' => now()->toISOString()
                    ]
                ]);
                
                $metricsCreated++;
            }
        }

        return [
            'records_processed' => count($funnelSteps),
            'metrics_created' => $metricsCreated
        ];
    }

    /**
     * Aggregate event types data
     */
    private function aggregateEventTypes(Carbon $startDate, Carbon $endDate): array
    {
        $query = UserAnalytic::dateRange($startDate, $endDate);
        
        if ($this->aggregationType === 'hourly') {
            $groupBy = [
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as period'),
                'event_type'
            ];
        } else {
            $groupBy = [
                DB::raw('DATE(created_at) as period'),
                'event_type'
            ];
        }

        $aggregatedData = $query
            ->select(
                $groupBy[0],
                $groupBy[1],
                DB::raw('COUNT(*) as event_count'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy($groupBy)
            ->get();

        $metricsCreated = 0;
        foreach ($aggregatedData as $data) {
            $metricDate = Carbon::parse($data->period);
            
            BusinessMetric::updateOrCreate([
                'metric_date' => $metricDate,
                'metric_type' => 'event_type_' . $data->event_type . '_' . $this->aggregationType,
            ], [
                'value' => $data->event_count,
                'breakdown' => [
                    'aggregation_type' => $this->aggregationType,
                    'event_type' => $data->event_type,
                    'period' => $data->period,
                    'unique_users' => $data->unique_users,
                    'processed_at' => now()->toISOString()
                ]
            ]);
            
            $metricsCreated++;
        }

        return [
            'records_processed' => $aggregatedData->count(),
            'metrics_created' => $metricsCreated
        ];
    }

    /**
     * Get start date for aggregation based on type
     */
    private function getStartDateForAggregation(): Carbon
    {
        switch ($this->aggregationType) {
            case 'hourly':
                return $this->targetDate->startOfDay();
            case 'daily':
                return $this->targetDate->startOfDay();
            case 'weekly':
                return $this->targetDate->startOfWeek();
            case 'monthly':
                return $this->targetDate->startOfMonth();
            default:
                return $this->targetDate->startOfDay();
        }
    }

    /**
     * Get end date for aggregation based on type
     */
    private function getEndDateForAggregation(): Carbon
    {
        switch ($this->aggregationType) {
            case 'hourly':
                return $this->targetDate->endOfDay();
            case 'daily':
                return $this->targetDate->endOfDay();
            case 'weekly':
                return $this->targetDate->endOfWeek();
            case 'monthly':
                return $this->targetDate->endOfMonth();
            default:
                return $this->targetDate->endOfDay();
        }
    }

    /**
     * Get default metric types to process
     */
    private function getDefaultMetricTypes(): array
    {
        return [
            'user_events',
            'active_users',
            'session_metrics',
            'conversion_funnel',
            'event_types'
        ];
    }

    /**
     * Get queue name based on aggregation type
     */
    private function getQueueForAggregationType(string $aggregationType): string
    {
        return match ($aggregationType) {
            'hourly' => 'analytics-realtime',
            'daily' => 'analytics-daily',
            'weekly' => 'analytics-weekly',
            'monthly' => 'analytics-monthly',
            default => 'analytics-default',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Analytics data processing job failed permanently', [
            'aggregation_type' => $this->aggregationType,
            'target_date' => $this->targetDate->toDateString(),
            'metric_types' => $this->metricTypes,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\Analytics\DataProcessingFailed(...));
    }
}
