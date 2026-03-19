<?php

namespace App\Jobs;

use App\Models\BusinessMetric;
use App\Models\UserAnalytic;
use App\Services\AnalyticsService;
use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Business Reports Generation Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Generates comprehensive business reports from aggregated analytics data.
 * This is critical for business intelligence, executive dashboards, and
 * strategic decision-making across the platform.
 */
class GenerateBusinessReportsJob extends BaseQueueJob
{
    public array $reportTypes;
    public Carbon $startDate;
    public Carbon $endDate;
    public array $reportOptions;
    public string $outputFormat;
    public int $tries = 3;
    public int $timeout = 1200; // 20 minutes for report generation

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $reportTypes,
        Carbon $startDate,
        Carbon $endDate,
        array $reportOptions = [],
        string $outputFormat = 'json'
    ) {
        // Initialize parent with circuit breaker configuration
        parent::__construct();
        
        $this->reportTypes = $reportTypes ?: $this->getDefaultReportTypes();
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->reportOptions = array_merge($this->getDefaultReportOptions(), $reportOptions);
        $this->outputFormat = $outputFormat;
        
        // Set queue based on report complexity
        $this->onQueue($this->getQueueForReportComplexity($reportTypes));
        
        // Configure circuit breaker for business report generation
        $this->configureCircuitBreaker([
            'service_name' => 'business_report_generation',
            'failure_threshold' => 30, // 30% failure rate triggers circuit breaker
            'timeout' => 600, // 10 minutes timeout for report generation
            'recovery_timeout' => 900, // 15 minutes before attempting recovery
            'tags' => [
                'service' => 'analytics-service',
                'job_type' => 'report_generation',
                'operation' => 'business_reports',
                'priority' => 'medium'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(AnalyticsService $analyticsService): void
    {
        Log::info('Starting business report generation with circuit breaker protection', [
            'report_types' => $this->reportTypes,
            'date_range' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString()
            ],
            'output_format' => $this->outputFormat,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'business_report_generation'
        ]);

        // Execute with circuit breaker protection
        $this->executeWithCircuitBreaker(function() use ($analyticsService) {
            $results = [
                'reports_generated' => 0,
                'reports_failed' => 0,
                'total_data_points' => 0,
                'output_files' => [],
                'errors' => []
            ];

            collect($this->reportTypes)->each(function($reportType) use (&$results, $analyticsService) {
                try {
                    $reportResult = $this->generateReport($reportType, $analyticsService);
                    
                    $results['reports_generated']++;
                    $results['total_data_points'] += $reportResult['data_points'];
                    $results['output_files'][] = $reportResult['file_path'];
                    
                    Log::info("Successfully generated {$reportType} report", [
                        'data_points' => $reportResult['data_points'],
                        'file_path' => $reportResult['file_path'],
                        'generation_time' => $reportResult['generation_time']
                    ]);
                    
                } catch (\Exception $e) {
                    $results['reports_failed']++;
                    $results['errors'][] = [
                        'report_type' => $reportType,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error("Failed to generate {$reportType} report", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            });

            Log::info('Business report generation completed', $results);
            
            // Store summary results for monitoring
            $this->storeSummaryResults($results);
        });
    }

    /**
     * Generate individual report by type
     */
    private function generateReport(string $reportType, AnalyticsService $analyticsService): array
    {
        $startTime = microtime(true);
        
        $data = match ($reportType) {
            'revenue_analysis' => $this->generateRevenueAnalysis($analyticsService),
            'user_engagement' => $this->generateUserEngagementReport($analyticsService),
            'conversion_funnel' => $this->generateConversionFunnelReport($analyticsService),
            'auction_performance' => $this->generateAuctionPerformanceReport($analyticsService),
            'geographic_distribution' => $this->generateGeographicReport($analyticsService),
            'retention_cohorts' => $this->generateRetentionCohortsReport($analyticsService),
            'feature_adoption' => $this->generateFeatureAdoptionReport($analyticsService),
            default => throw new \InvalidArgumentException("Unknown report type: {$reportType}")
        };
        
        $generationTime = microtime(true) - $startTime;
        
        // Save report to storage
        $filePath = $this->saveReportToStorage($reportType, $data);
        
        return [
            'data_points' => count($data),
            'file_path' => $filePath,
            'generation_time' => round($generationTime, 3)
        ];
    }

    /**
     * Generate revenue analysis report
     */
    private function generateRevenueAnalysis(AnalyticsService $analyticsService): array
    {
        return DB::table('business_metrics')
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN metric_type = "revenue" THEN metric_value ELSE 0 END) as total_revenue'),
                DB::raw('SUM(CASE WHEN metric_type = "transactions" THEN metric_value ELSE 0 END) as total_transactions'),
                DB::raw('AVG(CASE WHEN metric_type = "avg_order_value" THEN metric_value ELSE NULL END) as avg_order_value'),
                DB::raw('COUNT(DISTINCT user_id) as unique_customers')
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('metric_type', ['revenue', 'transactions', 'avg_order_value'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Generate user engagement report
     */
    private function generateUserEngagementReport(AnalyticsService $analyticsService): array
    {
        return DB::table('user_analytics')
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT user_id) as daily_active_users'),
                DB::raw('AVG(session_duration) as avg_session_duration'),
                DB::raw('SUM(page_views) as total_page_views'),
                DB::raw('AVG(page_views) as avg_page_views_per_user'),
                DB::raw('COUNT(*) as total_sessions')
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Generate conversion funnel report
     */
    private function generateConversionFunnelReport(AnalyticsService $analyticsService): array
    {
        $funnelSteps = [
            'landing_page_view',
            'product_view',
            'add_to_cart',
            'checkout_start',
            'payment_complete'
        ];

        return collect($funnelSteps)->map(function($step, $index) {
            $count = DB::table('user_analytics')
                ->where('event_type', $step)
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->count();

            return [
                'step' => $step,
                'step_number' => $index + 1,
                'count' => $count,
                'conversion_rate' => $index === 0 ? 100 : null // Calculate in post-processing
            ];
        })->toArray();
    }

    /**
     * Generate auction performance report
     */
    private function generateAuctionPerformanceReport(AnalyticsService $analyticsService): array
    {
        return DB::table('business_metrics')
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN metric_type = "auctions_created" THEN metric_value ELSE 0 END) as auctions_created'),
                DB::raw('SUM(CASE WHEN metric_type = "auctions_completed" THEN metric_value ELSE 0 END) as auctions_completed'),
                DB::raw('SUM(CASE WHEN metric_type = "total_bids" THEN metric_value ELSE 0 END) as total_bids'),
                DB::raw('AVG(CASE WHEN metric_type = "avg_bid_amount" THEN metric_value ELSE NULL END) as avg_bid_amount'),
                DB::raw('SUM(CASE WHEN metric_type = "auction_revenue" THEN metric_value ELSE 0 END) as auction_revenue')
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('metric_type', ['auctions_created', 'auctions_completed', 'total_bids', 'avg_bid_amount', 'auction_revenue'])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Generate geographic distribution report
     */
    private function generateGeographicReport(AnalyticsService $analyticsService): array
    {
        return DB::table('user_analytics')
            ->select([
                'country',
                'region',
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(*) as total_sessions'),
                DB::raw('AVG(session_duration) as avg_session_duration'),
                DB::raw('SUM(page_views) as total_page_views')
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereNotNull('country')
            ->groupBy(['country', 'region'])
            ->orderBy('unique_users', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Generate retention cohorts report
     */
    private function generateRetentionCohortsReport(AnalyticsService $analyticsService): array
    {
        // Simplified cohort analysis - in production, this would be more sophisticated
        return DB::table('user_analytics')
            ->select([
                DB::raw('DATE_FORMAT(MIN(created_at), "%Y-%m") as cohort_month'),
                DB::raw('COUNT(DISTINCT user_id) as cohort_size'),
                DB::raw('AVG(session_duration) as avg_session_duration'),
                DB::raw('SUM(page_views) as total_page_views')
            ])
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->groupBy(DB::raw('DATE_FORMAT(MIN(created_at), "%Y-%m")'))
            ->orderBy('cohort_month')
            ->get()
            ->toArray();
    }

    /**
     * Generate feature adoption report
     */
    private function generateFeatureAdoptionReport(AnalyticsService $analyticsService): array
    {
        $features = [
            'search_filters',
            'saved_searches',
            'bid_alerts',
            'auto_bidding',
            'mobile_app',
            'social_sharing'
        ];

        return collect($features)->map(function($feature) {
            $adoptionData = DB::table('user_analytics')
                ->select([
                    DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                    DB::raw('COUNT(*) as total_usage'),
                    DB::raw('AVG(session_duration) as avg_session_duration')
                ])
                ->where('event_type', "feature_used_{$feature}")
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->first();

            return [
                'feature' => $feature,
                'unique_users' => $adoptionData->unique_users ?? 0,
                'total_usage' => $adoptionData->total_usage ?? 0,
                'avg_session_duration' => $adoptionData->avg_session_duration ?? 0
            ];
        })->toArray();
    }

    /**
     * Save report to storage
     */
    private function saveReportToStorage(string $reportType, array $data): string
    {
        $fileName = sprintf(
            'business_reports/%s/%s_%s_%s.%s',
            $this->startDate->format('Y/m'),
            $reportType,
            $this->startDate->format('Y-m-d'),
            $this->endDate->format('Y-m-d'),
            $this->outputFormat
        );

        $content = match ($this->outputFormat) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'csv' => $this->convertToCsv($data),
            default => json_encode($data, JSON_PRETTY_PRINT)
        };

        Storage::disk('local')->put($fileName, $content);
        
        return $fileName;
    }

    /**
     * Convert data to CSV format
     */
    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys((array) $data[0]));
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, (array) $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Store summary results for monitoring
     */
    private function storeSummaryResults(array $results): void
    {
        DB::table('job_execution_logs')->insert([
            'job_class' => static::class,
            'job_id' => $this->job?->getJobId(),
            'execution_results' => json_encode($results),
            'executed_at' => now(),
            'execution_time' => $this->timeout,
            'status' => $results['reports_failed'] > 0 ? 'partial_success' : 'success'
        ]);
    }

    /**
     * Get default report types
     */
    private function getDefaultReportTypes(): array
    {
        return [
            'revenue_analysis',
            'user_engagement',
            'auction_performance'
        ];
    }

    /**
     * Get default report options
     */
    private function getDefaultReportOptions(): array
    {
        return [
            'include_charts' => false,
            'include_raw_data' => false,
            'aggregate_level' => 'daily',
            'timezone' => config('app.timezone', 'UTC')
        ];
    }

    /**
     * Determine queue based on report complexity
     */
    private function getQueueForReportComplexity(array $reportTypes): string
    {
        $complexReports = ['retention_cohorts', 'geographic_distribution', 'conversion_funnel'];
        
        $hasComplexReports = !empty(array_intersect($reportTypes, $complexReports));
        
        return $hasComplexReports ? 'reports-heavy' : 'reports-standard';
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Business report generation job failed', [
            'report_types' => $this->reportTypes,
            'date_range' => [
                'start' => $this->startDate->toDateString(),
                'end' => $this->endDate->toDateString()
            ],
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

