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
                'operation' => 'analytics_aggregation',
                'priority' => 'high'
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
            $results = [
                'metrics_processed' => 0,
                'metrics_failed' => 0,
                'total_records_processed' => 0,
                'processing_time' => 0,
                'errors' => []
            ];

            $startTime = microtime(true);

            collect($this->metricTypes)->each(function($metricType) use (&$results, $analyticsService) {
                try {
                    $processedCount = $this->processMetricType($metricType, $analyticsService);
                    
                    $results['metrics_processed']++;
                    $results['total_records_processed'] += $processedCount;
                    
                    Log::info("Successfully processed {$metricType} metrics", [
                        'records_processed' => $processedCount,
                        'aggregation_type' => $this->aggregationType,
                        'target_date' => $this->targetDate->toDateString()
                    ]);
                    
                } catch (\Exception $e) {
                    $results['metrics_failed']++;
                    $results['errors'][] = [
                        'metric_type' => $metricType,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error("Failed to process {$metricType} metrics", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'aggregation_type' => $this->aggregationType,
                        'target_date' => $this->targetDate->toDateString()
                    ]);
                }
            });

            $results['processing_time'] = round(microtime(true) - $startTime, 3);

            Log::info('Analytics data processing completed', $results);
            
            // Store processing results for monitoring
            $this->storeProcessingResults($results);
        });
    }

    /**
     * Process specific metric type
     */
    private function processMetricType(string $metricType, AnalyticsService $analyticsService): int
    {
        return match ($metricType) {
            'user_engagement' => $this->processUserEngagementMetrics(),
            'revenue_metrics' => $this->processRevenueMetrics(),
            'auction_metrics' => $this->processAuctionMetrics(),
            'conversion_metrics' => $this->processConversionMetrics(),
            'geographic_metrics' => $this->processGeographicMetrics(),
            'device_metrics' => $this->processDeviceMetrics(),
            'feature_usage' => $this->processFeatureUsageMetrics(),
            default => throw new \InvalidArgumentException("Unknown metric type: {$metricType}")
        };
    }

    /**
     * Process user engagement metrics
     */
    private function processUserEngagementMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        $metrics = DB::table('user_analytics')
            ->select([
                DB::raw($this->getDateGroupBy() . ' as metric_date'),
                DB::raw('COUNT(DISTINCT user_id) as daily_active_users'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('AVG(session_duration) as avg_session_duration'),
                DB::raw('SUM(page_views) as total_page_views'),
                DB::raw('AVG(page_views) as avg_page_views_per_session'),
                DB::raw('COUNT(DISTINCT CASE WHEN session_duration > 300 THEN user_id END) as engaged_users')
            ])
            ->whereBetween('created_at', $dateRange)
            ->groupBy(DB::raw($this->getDateGroupBy()))
            ->get();

        $processedCount = 0;

        foreach ($metrics as $metric) {
            // Store individual metrics
            $metricsToStore = [
                'daily_active_users' => $metric->daily_active_users,
                'total_sessions' => $metric->total_sessions,
                'avg_session_duration' => round($metric->avg_session_duration, 2),
                'total_page_views' => $metric->total_page_views,
                'avg_page_views_per_session' => round($metric->avg_page_views_per_session, 2),
                'engaged_users' => $metric->engaged_users
            ];

            foreach ($metricsToStore as $metricName => $value) {
                BusinessMetric::updateOrCreate([
                    'metric_type' => $metricName,
                    'metric_date' => $metric->metric_date,
                    'aggregation_type' => $this->aggregationType
                ], [
                    'metric_value' => $value,
                    'updated_at' => now()
                ]);
                
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Process revenue metrics
     */
    private function processRevenueMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        // This would typically query order/payment tables
        // For now, we'll aggregate from existing business metrics or user analytics
        $metrics = DB::table('user_analytics')
            ->select([
                DB::raw($this->getDateGroupBy() . ' as metric_date'),
                DB::raw('COUNT(DISTINCT CASE WHEN event_type = "purchase_completed" THEN user_id END) as paying_customers'),
                DB::raw('COUNT(CASE WHEN event_type = "purchase_completed" THEN 1 END) as total_transactions'),
                DB::raw('SUM(CASE WHEN event_type = "purchase_completed" THEN CAST(event_data->>"$.amount" AS DECIMAL(10,2)) ELSE 0 END) as total_revenue'),
                DB::raw('AVG(CASE WHEN event_type = "purchase_completed" THEN CAST(event_data->>"$.amount" AS DECIMAL(10,2)) END) as avg_order_value')
            ])
            ->whereBetween('created_at', $dateRange)
            ->whereIn('event_type', ['purchase_completed', 'payment_processed'])
            ->groupBy(DB::raw($this->getDateGroupBy()))
            ->get();

        $processedCount = 0;

        foreach ($metrics as $metric) {
            $metricsToStore = [
                'paying_customers' => $metric->paying_customers,
                'total_transactions' => $metric->total_transactions,
                'total_revenue' => round($metric->total_revenue ?? 0, 2),
                'avg_order_value' => round($metric->avg_order_value ?? 0, 2)
            ];

            foreach ($metricsToStore as $metricName => $value) {
                BusinessMetric::updateOrCreate([
                    'metric_type' => $metricName,
                    'metric_date' => $metric->metric_date,
                    'aggregation_type' => $this->aggregationType
                ], [
                    'metric_value' => $value,
                    'updated_at' => now()
                ]);
                
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Process auction metrics
     */
    private function processAuctionMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        $metrics = DB::table('user_analytics')
            ->select([
                DB::raw($this->getDateGroupBy() . ' as metric_date'),
                DB::raw('COUNT(CASE WHEN event_type = "auction_created" THEN 1 END) as auctions_created'),
                DB::raw('COUNT(CASE WHEN event_type = "auction_completed" THEN 1 END) as auctions_completed'),
                DB::raw('COUNT(CASE WHEN event_type = "bid_placed" THEN 1 END) as total_bids'),
                DB::raw('COUNT(DISTINCT CASE WHEN event_type = "bid_placed" THEN user_id END) as unique_bidders'),
                DB::raw('AVG(CASE WHEN event_type = "bid_placed" THEN CAST(event_data->>"$.amount" AS DECIMAL(10,2)) END) as avg_bid_amount')
            ])
            ->whereBetween('created_at', $dateRange)
            ->whereIn('event_type', ['auction_created', 'auction_completed', 'bid_placed'])
            ->groupBy(DB::raw($this->getDateGroupBy()))
            ->get();

        $processedCount = 0;

        foreach ($metrics as $metric) {
            $metricsToStore = [
                'auctions_created' => $metric->auctions_created,
                'auctions_completed' => $metric->auctions_completed,
                'total_bids' => $metric->total_bids,
                'unique_bidders' => $metric->unique_bidders,
                'avg_bid_amount' => round($metric->avg_bid_amount ?? 0, 2)
            ];

            foreach ($metricsToStore as $metricName => $value) {
                BusinessMetric::updateOrCreate([
                    'metric_type' => $metricName,
                    'metric_date' => $metric->metric_date,
                    'aggregation_type' => $this->aggregationType
                ], [
                    'metric_value' => $value,
                    'updated_at' => now()
                ]);
                
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Process conversion metrics
     */
    private function processConversionMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        $funnelSteps = [
            'landing_page_view' => 'visitors',
            'product_view' => 'product_viewers',
            'add_to_cart' => 'cart_additions',
            'checkout_start' => 'checkout_starts',
            'payment_complete' => 'conversions'
        ];

        $processedCount = 0;

        foreach ($funnelSteps as $eventType => $metricName) {
            $metrics = DB::table('user_analytics')
                ->select([
                    DB::raw($this->getDateGroupBy() . ' as metric_date'),
                    DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                    DB::raw('COUNT(*) as total_events')
                ])
                ->where('event_type', $eventType)
                ->whereBetween('created_at', $dateRange)
                ->groupBy(DB::raw($this->getDateGroupBy()))
                ->get();

            foreach ($metrics as $metric) {
                BusinessMetric::updateOrCreate([
                    'metric_type' => $metricName,
                    'metric_date' => $metric->metric_date,
                    'aggregation_type' => $this->aggregationType
                ], [
                    'metric_value' => $metric->unique_users,
                    'updated_at' => now()
                ]);
                
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Process geographic metrics
     */
    private function processGeographicMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        $metrics = DB::table('user_analytics')
            ->select([
                DB::raw($this->getDateGroupBy() . ' as metric_date'),
                'country',
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('AVG(session_duration) as avg_session_duration')
            ])
            ->whereBetween('created_at', $dateRange)
            ->whereNotNull('country')
            ->groupBy([DB::raw($this->getDateGroupBy()), 'country'])
            ->get();

        $processedCount = 0;

        foreach ($metrics as $metric) {
            BusinessMetric::updateOrCreate([
                'metric_type' => 'geographic_users',
                'metric_date' => $metric->metric_date,
                'aggregation_type' => $this->aggregationType,
                'dimension_value' => $metric->country
            ], [
                'metric_value' => $metric->unique_users,
                'updated_at' => now()
            ]);
            
            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * Process device metrics
     */
    private function processDeviceMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        $metrics = DB::table('user_analytics')
            ->select([
                DB::raw($this->getDateGroupBy() . ' as metric_date'),
                'device_type',
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('AVG(session_duration) as avg_session_duration')
            ])
            ->whereBetween('created_at', $dateRange)
            ->whereNotNull('device_type')
            ->groupBy([DB::raw($this->getDateGroupBy()), 'device_type'])
            ->get();

        $processedCount = 0;

        foreach ($metrics as $metric) {
            BusinessMetric::updateOrCreate([
                'metric_type' => 'device_users',
                'metric_date' => $metric->metric_date,
                'aggregation_type' => $this->aggregationType,
                'dimension_value' => $metric->device_type
            ], [
                'metric_value' => $metric->unique_users,
                'updated_at' => now()
            ]);
            
            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * Process feature usage metrics
     */
    private function processFeatureUsageMetrics(): int
    {
        $dateRange = $this->getDateRangeForAggregation();
        
        $features = [
            'search_filters',
            'saved_searches',
            'bid_alerts',
            'auto_bidding',
            'mobile_app',
            'social_sharing'
        ];

        $processedCount = 0;

        foreach ($features as $feature) {
            $metrics = DB::table('user_analytics')
                ->select([
                    DB::raw($this->getDateGroupBy() . ' as metric_date'),
                    DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                    DB::raw('COUNT(*) as total_usage')
                ])
                ->where('event_type', "feature_used_{$feature}")
                ->whereBetween('created_at', $dateRange)
                ->groupBy(DB::raw($this->getDateGroupBy()))
                ->get();

            foreach ($metrics as $metric) {
                BusinessMetric::updateOrCreate([
                    'metric_type' => 'feature_usage',
                    'metric_date' => $metric->metric_date,
                    'aggregation_type' => $this->aggregationType,
                    'dimension_value' => $feature
                ], [
                    'metric_value' => $metric->unique_users,
                    'updated_at' => now()
                ]);
                
                $processedCount++;
            }
        }

        return $processedCount;
    }

    /**
     * Get date range for aggregation
     */
    private function getDateRangeForAggregation(): array
    {
        return match ($this->aggregationType) {
            'hourly' => [
                $this->targetDate->startOfHour(),
                $this->targetDate->endOfHour()
            ],
            'daily' => [
                $this->targetDate->startOfDay(),
                $this->targetDate->endOfDay()
            ],
            'weekly' => [
                $this->targetDate->startOfWeek(),
                $this->targetDate->endOfWeek()
            ],
            'monthly' => [
                $this->targetDate->startOfMonth(),
                $this->targetDate->endOfMonth()
            ],
            default => [
                $this->targetDate->startOfDay(),
                $this->targetDate->endOfDay()
            ]
        };
    }

    /**
     * Get date grouping SQL for aggregation type
     */
    private function getDateGroupBy(): string
    {
        return match ($this->aggregationType) {
            'hourly' => 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00")',
            'daily' => 'DATE(created_at)',
            'weekly' => 'DATE_FORMAT(created_at, "%Y-%u")',
            'monthly' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default => 'DATE(created_at)'
        };
    }

    /**
     * Store processing results for monitoring
     */
    private function storeProcessingResults(array $results): void
    {
        DB::table('job_execution_logs')->insert([
            'job_class' => static::class,
            'job_id' => $this->job?->getJobId(),
            'execution_results' => json_encode($results),
            'executed_at' => now(),
            'execution_time' => $results['processing_time'],
            'status' => $results['metrics_failed'] > 0 ? 'partial_success' : 'success'
        ]);
    }

    /**
     * Get default metric types
     */
    private function getDefaultMetricTypes(): array
    {
        return [
            'user_engagement',
            'revenue_metrics',
            'auction_metrics',
            'conversion_metrics'
        ];
    }

    /**
     * Determine queue based on aggregation type
     */
    private function getQueueForAggregationType(string $aggregationType): string
    {
        return match ($aggregationType) {
            'hourly' => 'analytics-realtime',
            'daily' => 'analytics-standard',
            'weekly', 'monthly' => 'analytics-batch',
            default => 'analytics-standard'
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Analytics data processing job failed', [
            'aggregation_type' => $this->aggregationType,
            'target_date' => $this->targetDate->toDateString(),
            'metric_types' => $this->metricTypes,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId()
        ]);

        // Store failure information
        DB::table('job_execution_logs')->insert([
            'job_class' => static::class,
            'job_id' => $this->job?->getJobId(),
            'execution_results' => json_encode(['error' => $exception->getMessage()]),
            'executed_at' => now(),
            'status' => 'failed'
        ]);
    }
}

