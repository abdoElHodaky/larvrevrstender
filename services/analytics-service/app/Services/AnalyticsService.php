<?php

namespace App\Services;

use App\Models\BusinessMetric;
use App\Models\UserAnalytic;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    /**
     * Track a user event
     */
    public function trackEvent(
        int $userId,
        string $eventType,
        array $eventData = [],
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): UserAnalytic {
        try {
            $analytic = UserAnalytic::create([
                'user_id' => $userId,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => now(),
            ]);

            // Update real-time metrics
            $this->updateRealTimeMetrics($eventType);

            Log::info('Event tracked successfully', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'event_id' => $analytic->id,
            ]);

            return $analytic;
        } catch (\Exception $e) {
            Log::error('Failed to track event', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get user analytics for a specific user
     */
    public function getUserAnalytics(
        int $userId,
        Carbon $startDate,
        Carbon $endDate,
        ?array $eventTypes = null
    ): array {
        try {
            $query = UserAnalytic::where('user_id', $userId)
                ->dateRange($startDate, $endDate);

            if ($eventTypes) {
                $query->whereIn('event_type', $eventTypes);
            }

            $analytics = $query->orderBy('created_at', 'desc')->get();

            // Group by event type for summary
            $eventSummary = $analytics->groupBy('event_type')->map(function ($events, $eventType) {
                return [
                    'event_type' => $eventType,
                    'count' => $events->count(),
                    'first_occurrence' => $events->min('created_at'),
                    'last_occurrence' => $events->max('created_at'),
                ];
            })->values();

            // Daily activity breakdown
            $dailyActivity = $analytics->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })->map(function ($events, $date) {
                return [
                    'date' => $date,
                    'event_count' => $events->count(),
                    'unique_sessions' => $events->pluck('session_id')->filter()->unique()->count(),
                ];
            })->values();

            return [
                'user_id' => $userId,
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'total_events' => $analytics->count(),
                'unique_sessions' => $analytics->pluck('session_id')->filter()->unique()->count(),
                'event_summary' => $eventSummary,
                'daily_activity' => $dailyActivity,
                'events' => $analytics->take(100), // Limit to 100 most recent events
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get user analytics', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get business metrics
     */
    public function getBusinessMetrics(
        ?string $metricType = null,
        Carbon $startDate,
        Carbon $endDate,
        string $groupBy = 'day'
    ): array {
        try {
            $query = BusinessMetric::dateRange($startDate, $endDate);

            if ($metricType) {
                $query->metricType($metricType);
            }

            $metrics = $query->orderByDate()->get();

            // Group metrics by the specified period
            $groupedMetrics = $this->groupMetricsByPeriod($metrics, $groupBy);

            // Calculate aggregated statistics
            $totalValue = $metrics->sum('value');
            $averageValue = $metrics->avg('value');
            $metricTypes = $metrics->pluck('metric_type')->unique()->values();

            return [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'group_by' => $groupBy,
                ],
                'summary' => [
                    'total_value' => round($totalValue, 2),
                    'average_value' => round($averageValue, 2),
                    'metric_count' => $metrics->count(),
                    'metric_types' => $metricTypes,
                ],
                'grouped_metrics' => $groupedMetrics,
                'raw_metrics' => $metrics->take(500), // Limit to 500 records
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get business metrics', [
                'metric_type' => $metricType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get dashboard overview
     */
    public function getDashboardOverview(): array
    {
        try {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $lastWeek = Carbon::now()->subWeek();
            $lastMonth = Carbon::now()->subMonth();

            // User analytics overview
            $todayEvents = UserAnalytic::whereDate('created_at', $today)->count();
            $yesterdayEvents = UserAnalytic::whereDate('created_at', $yesterday)->count();
            $weeklyEvents = UserAnalytic::where('created_at', '>=', $lastWeek)->count();
            $monthlyEvents = UserAnalytic::where('created_at', '>=', $lastMonth)->count();

            // Active users
            $todayActiveUsers = UserAnalytic::whereDate('created_at', $today)
                ->distinct('user_id')->count('user_id');
            $weeklyActiveUsers = UserAnalytic::where('created_at', '>=', $lastWeek)
                ->distinct('user_id')->count('user_id');
            $monthlyActiveUsers = UserAnalytic::where('created_at', '>=', $lastMonth)
                ->distinct('user_id')->count('user_id');

            // Top events today
            $topEvents = UserAnalytic::whereDate('created_at', $today)
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get();

            // Business metrics overview
            $latestMetrics = BusinessMetric::whereDate('metric_date', $today)
                ->orWhereDate('metric_date', $yesterday)
                ->orderBy('metric_date', 'desc')
                ->limit(20)
                ->get()
                ->groupBy('metric_type');

            return [
                'overview_date' => $today->toDateString(),
                'events' => [
                    'today' => $todayEvents,
                    'yesterday' => $yesterdayEvents,
                    'weekly' => $weeklyEvents,
                    'monthly' => $monthlyEvents,
                    'daily_change' => $yesterdayEvents > 0 ? 
                        round((($todayEvents - $yesterdayEvents) / $yesterdayEvents) * 100, 2) : 0,
                ],
                'active_users' => [
                    'today' => $todayActiveUsers,
                    'weekly' => $weeklyActiveUsers,
                    'monthly' => $monthlyActiveUsers,
                ],
                'top_events_today' => $topEvents,
                'latest_metrics' => $latestMetrics,
                'real_time_stats' => $this->getRealTimeMetrics(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get dashboard overview', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get conversion funnel analysis
     */
    public function getConversionFunnel(
        Carbon $startDate,
        Carbon $endDate,
        ?string $userType = null
    ): array {
        try {
            // Define funnel steps (customize based on your business logic)
            $funnelSteps = [
                'page_view' => 'Page View',
                'signup_started' => 'Signup Started',
                'signup_completed' => 'Signup Completed',
                'first_action' => 'First Action',
                'conversion' => 'Conversion',
            ];

            $previousStepUsers = null;
            
            $funnelData = collect($funnelSteps)->map(function($stepName, $eventType) use ($startDate, $endDate, $userType, &$previousStepUsers) {
                $query = UserAnalytic::eventType($eventType)
                    ->dateRange($startDate, $endDate);

                // Apply user type filter if specified
                if ($userType) {
                    $query->whereHas('user', function ($q) use ($userType) {
                        $q->where('type', $userType);
                    });
                }

                $stepUsers = $query->distinct('user_id')->pluck('user_id');
                $userCount = $stepUsers->count();

                $conversionRate = $previousStepUsers ? 
                    ($userCount / $previousStepUsers->count()) * 100 : 100;

                $stepData = [
                    'step' => $stepName,
                    'event_type' => $eventType,
                    'users' => $userCount,
                    'conversion_rate' => round($conversionRate, 2),
                    'drop_off_rate' => round(100 - $conversionRate, 2),
                ];

                $previousStepUsers = $stepUsers;
                
                return $stepData;
            })->values()->toArray();

            return [
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'user_type' => $userType,
                'funnel_steps' => $funnelData,
                'overall_conversion' => $funnelData ? 
                    round(($funnelData[count($funnelData) - 1]['users'] / $funnelData[0]['users']) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get conversion funnel', [
                'user_type' => $userType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics(): array
    {
        try {
            $now = Carbon::now();
            $lastHour = $now->copy()->subHour();
            $last5Minutes = $now->copy()->subMinutes(5);

            // Events in the last hour
            $hourlyEvents = UserAnalytic::where('created_at', '>=', $lastHour)->count();
            
            // Events in the last 5 minutes
            $recentEvents = UserAnalytic::where('created_at', '>=', $last5Minutes)->count();

            // Active users in the last hour
            $activeUsers = UserAnalytic::where('created_at', '>=', $lastHour)
                ->distinct('user_id')->count('user_id');

            // Top events in the last hour
            $topEvents = UserAnalytic::where('created_at', '>=', $lastHour)
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get();

            // Events per minute in the last hour
            $eventsPerMinute = UserAnalytic::where('created_at', '>=', $lastHour)
                ->select(DB::raw('DATE_FORMAT(created_at, "%H:%i") as minute'), DB::raw('count(*) as count'))
                ->groupBy('minute')
                ->orderBy('minute')
                ->get();

            return [
                'timestamp' => $now->toISOString(),
                'events' => [
                    'last_hour' => $hourlyEvents,
                    'last_5_minutes' => $recentEvents,
                    'per_minute_avg' => $hourlyEvents > 0 ? round($hourlyEvents / 60, 2) : 0,
                ],
                'active_users_last_hour' => $activeUsers,
                'top_events_last_hour' => $topEvents,
                'events_per_minute' => $eventsPerMinute,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get real-time metrics', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate custom report
     */
    public function generateReport(
        string $reportType,
        Carbon $startDate,
        Carbon $endDate,
        array $filters = [],
        string $format = 'json'
    ): array {
        try {
            $reportData = [];

            $reportData = match ($reportType) {
                'user_activity' => $this->generateUserActivityReport($startDate, $endDate, $filters),
                'event_summary' => $this->generateEventSummaryReport($startDate, $endDate, $filters),
                'business_metrics' => $this->generateBusinessMetricsReport($startDate, $endDate, $filters),
                'conversion_analysis' => $this->generateConversionAnalysisReport($startDate, $endDate, $filters),
                default => throw new \InvalidArgumentException("Unsupported report type: {$reportType}")
            };

            $report = [
                'report_type' => $reportType,
                'generated_at' => now()->toISOString(),
                'period' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                'filters' => $filters,
                'format' => $format,
                'data' => $reportData,
            ];

            // Format the report based on requested format
            if ($format === 'csv') {
                $report['csv_data'] = $this->convertToCSV($reportData);
            }

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to generate report', [
                'report_type' => $reportType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update real-time metrics cache
     */
    private function updateRealTimeMetrics(string $eventType): void
    {
        try {
            // This could be implemented with Redis or another caching mechanism
            // For now, we'll just log the event for real-time processing
            Log::debug('Real-time metric update', [
                'event_type' => $eventType,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to update real-time metrics', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Group metrics by time period
     */
    private function groupMetricsByPeriod($metrics, string $groupBy): array
    {
        return $metrics->groupBy(function ($metric) use ($groupBy) {
            return match ($groupBy) {
                'hour' => $metric->metric_date->format('Y-m-d H:00'),
                'day' => $metric->metric_date->format('Y-m-d'),
                'week' => $metric->metric_date->startOfWeek()->format('Y-m-d'),
                'month' => $metric->metric_date->format('Y-m'),
                'year' => $metric->metric_date->format('Y'),
                default => $metric->metric_date->format('Y-m-d')
            };
        })->map(function ($periodMetrics, $period) {
            return [
                'period' => $period,
                'total_value' => $periodMetrics->sum('value'),
                'average_value' => round($periodMetrics->avg('value'), 2),
                'count' => $periodMetrics->count(),
                'metrics' => $periodMetrics->values(),
            ];
        })->values()->toArray();
    }

    /**
     * Generate user activity report
     */
    private function generateUserActivityReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = UserAnalytic::dateRange($startDate, $endDate);

        if (isset($filters['user_ids'])) {
            $query->whereIn('user_id', $filters['user_ids']);
        }

        if (isset($filters['event_types'])) {
            $query->whereIn('event_type', $filters['event_types']);
        }

        $analytics = $query->get();

        return [
            'total_events' => $analytics->count(),
            'unique_users' => $analytics->pluck('user_id')->unique()->count(),
            'unique_sessions' => $analytics->pluck('session_id')->filter()->unique()->count(),
            'events_by_type' => $analytics->groupBy('event_type')->map->count(),
            'events_by_day' => $analytics->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })->map->count(),
            'top_users' => $analytics->groupBy('user_id')
                ->map->count()
                ->sortDesc()
                ->take(10),
        ];
    }

    /**
     * Generate event summary report
     */
    private function generateEventSummaryReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = UserAnalytic::dateRange($startDate, $endDate);

        if (isset($filters['event_types'])) {
            $query->whereIn('event_type', $filters['event_types']);
        }

        $events = $query->get();

        return [
            'summary' => [
                'total_events' => $events->count(),
                'unique_event_types' => $events->pluck('event_type')->unique()->count(),
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
            ],
            'event_breakdown' => $events->groupBy('event_type')->map(function ($typeEvents, $eventType) {
                return [
                    'event_type' => $eventType,
                    'count' => $typeEvents->count(),
                    'unique_users' => $typeEvents->pluck('user_id')->unique()->count(),
                    'first_occurrence' => $typeEvents->min('created_at'),
                    'last_occurrence' => $typeEvents->max('created_at'),
                ];
            })->values(),
        ];
    }

    /**
     * Generate business metrics report
     */
    private function generateBusinessMetricsReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = BusinessMetric::dateRange($startDate, $endDate);

        if (isset($filters['metric_types'])) {
            $query->whereIn('metric_type', $filters['metric_types']);
        }

        $metrics = $query->get();

        return [
            'summary' => [
                'total_metrics' => $metrics->count(),
                'total_value' => $metrics->sum('value'),
                'average_value' => round($metrics->avg('value'), 2),
                'unique_metric_types' => $metrics->pluck('metric_type')->unique()->count(),
            ],
            'metrics_by_type' => $metrics->groupBy('metric_type')->map(function ($typeMetrics, $metricType) {
                return [
                    'metric_type' => $metricType,
                    'count' => $typeMetrics->count(),
                    'total_value' => $typeMetrics->sum('value'),
                    'average_value' => round($typeMetrics->avg('value'), 2),
                    'min_value' => $typeMetrics->min('value'),
                    'max_value' => $typeMetrics->max('value'),
                ];
            })->values(),
        ];
    }

    /**
     * Generate conversion analysis report
     */
    private function generateConversionAnalysisReport(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $userType = $filters['user_type'] ?? null;
        $funnelData = $this->getConversionFunnel($startDate, $endDate, $userType);

        return [
            'funnel_analysis' => $funnelData,
            'conversion_insights' => [
                'overall_conversion_rate' => $funnelData['overall_conversion'],
                'biggest_drop_off' => $this->findBiggestDropOff($funnelData['funnel_steps']),
                'best_performing_step' => $this->findBestPerformingStep($funnelData['funnel_steps']),
            ],
        ];
    }

    /**
     * Find the biggest drop-off point in the funnel
     */
    private function findBiggestDropOff(array $funnelSteps): array
    {
        $maxDropOff = 0;
        $dropOffStep = null;

        foreach ($funnelSteps as $step) {
            if ($step['drop_off_rate'] > $maxDropOff) {
                $maxDropOff = $step['drop_off_rate'];
                $dropOffStep = $step;
            }
        }

        return $dropOffStep ?? [];
    }

    /**
     * Find the best performing step in the funnel
     */
    private function findBestPerformingStep(array $funnelSteps): array
    {
        $maxConversion = 0;
        $bestStep = null;

        foreach ($funnelSteps as $step) {
            if ($step['conversion_rate'] > $maxConversion) {
                $maxConversion = $step['conversion_rate'];
                $bestStep = $step;
            }
        }

        return $bestStep ?? [];
    }

    /**
     * Convert report data to CSV format
     */
    private function convertToCSV(array $data): string
    {
        // This is a simplified CSV conversion
        // In a real implementation, you'd want more sophisticated CSV handling
        $csv = '';
        
        if (is_array($data) && !empty($data)) {
            // Get headers from first row
            $firstRow = reset($data);
            if (is_array($firstRow)) {
                $csv .= implode(',', array_keys($firstRow)) . "\n";
                
                // Add data rows
                foreach ($data as $row) {
                    if (is_array($row)) {
                        $csv .= implode(',', array_map(function ($value) {
                            return is_array($value) ? json_encode($value) : $value;
                        }, $row)) . "\n";
                    }
                }
            }
        }
        
        return $csv;
    }
}
