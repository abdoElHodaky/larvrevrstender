<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MetricsController extends Controller
{
    /**
     * Get all available metrics
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'nullable|string|in:user,auction,system,revenue',
                'active_only' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $metrics = [
                'user' => [
                    'total_users' => 'Total registered users',
                    'active_users' => 'Active users in period',
                    'new_registrations' => 'New user registrations',
                    'user_retention_rate' => 'User retention rate',
                    'average_session_duration' => 'Average session duration',
                ],
                'auction' => [
                    'total_auctions' => 'Total auctions created',
                    'active_auctions' => 'Currently active auctions',
                    'completed_auctions' => 'Completed auctions',
                    'auction_completion_rate' => 'Auction completion rate',
                    'average_bids_per_auction' => 'Average bids per auction',
                    'total_bid_amount' => 'Total bid amount',
                ],
                'system' => [
                    'api_response_time' => 'Average API response time',
                    'system_uptime' => 'System uptime percentage',
                    'error_rate' => 'System error rate',
                    'database_performance' => 'Database query performance',
                    'cache_hit_rate' => 'Cache hit rate',
                ],
                'revenue' => [
                    'total_revenue' => 'Total revenue generated',
                    'commission_revenue' => 'Commission revenue',
                    'subscription_revenue' => 'Subscription revenue',
                    'average_transaction_value' => 'Average transaction value',
                    'revenue_growth_rate' => 'Revenue growth rate',
                ],
            ];

            $result = $metrics;

            if ($request->filled('category')) {
                $result = [$request->category => $metrics[$request->category] ?? []];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'metrics' => $result,
                    'categories' => array_keys($metrics),
                ],
                'message' => 'Metrics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get metrics summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'metric_names' => 'nullable|array',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'group_by' => 'nullable|string|in:hour,day,week,month',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
            $dateTo = $request->date_to ? Carbon::parse($request->date_to) : Carbon::now();
            $groupBy = $request->group_by ?? 'day';
            $metricNames = $request->metric_names ?? [];

            // Generate summary data
            $summary = $this->generateMetricsSummary($dateFrom, $dateTo, $groupBy, $metricNames);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'period' => [
                        'from' => $dateFrom->toISOString(),
                        'to' => $dateTo->toISOString(),
                        'group_by' => $groupBy,
                    ],
                    'metrics_included' => empty($metricNames) ? 'all' : $metricNames,
                ],
                'message' => 'Metrics summary retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get metrics summary', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get metrics summary',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get metrics trends
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'metric_name' => 'required|string',
                'date_from' => 'required|date',
                'date_to' => 'required|date',
                'interval' => 'nullable|string|in:hour,day,week,month',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dateFrom = Carbon::parse($request->date_from);
            $dateTo = Carbon::parse($request->date_to);
            $interval = $request->interval ?? 'day';
            $metricName = $request->metric_name;

            if ($dateFrom->gt($dateTo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date from must be before date to',
                ], 422);
            }

            // Generate trend data
            $trends = $this->generateTrendData($metricName, $dateFrom, $dateTo, $interval);

            return response()->json([
                'success' => true,
                'data' => [
                    'metric_name' => $metricName,
                    'trends' => $trends,
                    'period' => [
                        'from' => $dateFrom->toISOString(),
                        'to' => $dateTo->toISOString(),
                        'interval' => $interval,
                    ],
                    'statistics' => $this->calculateTrendStatistics($trends),
                ],
                'message' => 'Metrics trends retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get metrics trends', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get metrics trends',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformance(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'service_name' => 'nullable|string|in:auth,auction,payment,notification,user,order,analytics',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'metrics' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $dateFrom = $request->date_from ? Carbon::parse($request->date_from) : Carbon::now()->subHours(24);
            $dateTo = $request->date_to ? Carbon::parse($request->date_to) : Carbon::now();
            $serviceName = $request->service_name;
            $metrics = $request->metrics ?? ['response_time', 'error_rate', 'throughput', 'availability'];

            // Generate performance data
            $performance = $this->generatePerformanceData($serviceName, $dateFrom, $dateTo, $metrics);

            return response()->json([
                'success' => true,
                'data' => [
                    'performance' => $performance,
                    'period' => [
                        'from' => $dateFrom->toISOString(),
                        'to' => $dateTo->toISOString(),
                    ],
                    'service' => $serviceName ?? 'all',
                    'metrics_included' => $metrics,
                ],
                'message' => 'Performance metrics retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get performance metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get performance metrics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Generate metrics summary data
     */
    private function generateMetricsSummary(Carbon $dateFrom, Carbon $dateTo, string $groupBy, array $metricNames): array
    {
        // In a real implementation, this would query actual database tables
        // For now, we'll generate sample data that matches the expected structure

        $summary = [
            'overview' => [
                'total_users' => rand(5000, 15000),
                'active_users' => rand(2000, 8000),
                'total_auctions' => rand(500, 2000),
                'completed_auctions' => rand(400, 1800),
                'total_revenue' => rand(100000, 500000),
                'system_uptime' => rand(95, 100) . '%',
            ],
            'growth_rates' => [
                'user_growth' => rand(-5, 25) . '%',
                'auction_growth' => rand(-10, 30) . '%',
                'revenue_growth' => rand(-15, 40) . '%',
            ],
            'time_series' => [],
        ];

        // Generate time series data based on groupBy
        $current = $dateFrom->copy();
        while ($current->lte($dateTo)) {
            $summary['time_series'][] = [
                'timestamp' => $current->toISOString(),
                'active_users' => rand(100, 1000),
                'new_auctions' => rand(10, 50),
                'completed_auctions' => rand(8, 45),
                'revenue' => rand(1000, 10000),
                'api_calls' => rand(5000, 25000),
                'error_rate' => rand(0, 5) . '%',
            ];

            // Increment based on groupBy (PHP 8.3 match expression)
            match ($groupBy) {
                'hour' => $current->addHour(),
                'day' => $current->addDay(),
                'week' => $current->addWeek(),
                'month' => $current->addMonth(),
                default => throw new \InvalidArgumentException("Invalid groupBy value: {$groupBy}")
            };
        }

        return $summary;
    }

    /**
     * Generate trend data for a specific metric
     */
    private function generateTrendData(string $metricName, Carbon $dateFrom, Carbon $dateTo, string $interval): array
    {
        $trends = [];
        $current = $dateFrom->copy();

        while ($current->lte($dateTo)) {
            $value = $this->generateMetricValue($metricName);
            
            $trends[] = [
                'timestamp' => $current->toISOString(),
                'value' => $value,
                'formatted_value' => $this->formatMetricValue($metricName, $value),
            ];

            // Increment based on interval (PHP 8.3 match expression)
            match ($interval) {
                'hour' => $current->addHour(),
                'day' => $current->addDay(),
                'week' => $current->addWeek(),
                'month' => $current->addMonth(),
                default => throw new \InvalidArgumentException("Invalid interval value: {$interval}")
            };
        }

        return $trends;
    }

    /**
     * Generate performance data
     */
    private function generatePerformanceData(?string $serviceName, Carbon $dateFrom, Carbon $dateTo, array $metrics): array
    {
        $services = $serviceName ? [$serviceName] : ['auth', 'auction', 'payment', 'notification', 'user', 'order', 'analytics'];
        
        // Modern PHP 8.3 collection approach
        $performance = collect($services)->map(fn($service) => [
            'service_name' => $service,
            'status' => rand(0, 100) > 5 ? 'healthy' : 'degraded', // 95% healthy
            'metrics' => collect($metrics)->mapWithKeys(fn($metric) => [
                $metric => $this->generatePerformanceMetric($metric)
            ])->toArray(),
        ])->toArray();

        return [
            'services' => $performance,
            'overall_health' => $this->calculateOverallHealth($performance),
            'alerts' => $this->generateAlerts($performance),
        ];
    }

    /**
     * Generate a value for a specific metric
     */
    private function generateMetricValue(string $metricName): float
    {
        return match ($metricName) {
            'total_users' => rand(1000, 10000),
            'active_users' => rand(500, 5000),
            'new_registrations' => rand(10, 100),
            'total_auctions' => rand(50, 500),
            'completed_auctions' => rand(40, 450),
            'total_revenue' => rand(5000, 50000),
            'api_response_time' => rand(50, 500) / 1000, // in seconds
            'error_rate' => rand(0, 10) / 100, // as percentage
            'system_uptime' => rand(95, 100) / 100, // as percentage
            default => rand(1, 1000)
        };
    }

    /**
     * Format metric value for display
     */
    private function formatMetricValue(string $metricName, float $value): string
    {
        return match ($metricName) {
            'api_response_time' => number_format($value * 1000, 2) . 'ms',
            'error_rate', 'system_uptime' => number_format($value * 100, 2) . '%',
            'total_revenue' => '$' . number_format($value, 2),
            default => number_format($value)
        };
    }

    /**
     * Generate performance metric data
     */
    private function generatePerformanceMetric(string $metric): array
    {
        return match ($metric) {
            'response_time' => (function() {
                $value = rand(50, 500);
                return [
                    'current' => $value,
                    'average' => rand(100, 300),
                    'p95' => rand(200, 800),
                    'p99' => rand(500, 1500),
                    'unit' => 'ms',
                    'status' => $value < 300 ? 'good' : ($value < 500 ? 'warning' : 'critical'),
                ];
            })(),
            'error_rate' => (function() {
                $value = rand(0, 10) / 100;
                return [
                    'current' => $value,
                    'average' => rand(1, 5) / 100,
                    'unit' => '%',
                    'status' => $value < 0.01 ? 'good' : ($value < 0.05 ? 'warning' : 'critical'),
                ];
            })(),
            'throughput' => (function() {
                $value = rand(100, 1000);
                return [
                    'current' => $value,
                    'average' => rand(200, 800),
                    'peak' => rand(500, 1500),
                    'unit' => 'req/min',
                    'status' => 'good',
                ];
            })(),
            'availability' => (function() {
                $value = rand(95, 100) / 100;
                return [
                    'current' => $value,
                    'target' => 0.999,
                    'unit' => '%',
                    'status' => $value > 0.99 ? 'good' : ($value > 0.95 ? 'warning' : 'critical'),
                ];
            })(),
            default => [
                'current' => rand(1, 100),
                'unit' => 'units',
                'status' => 'good',
            ]
        };
    }

    /**
     * Calculate trend statistics
     */
    private function calculateTrendStatistics(array $trends): array
    {
        if (empty($trends)) {
            return [];
        }

        $values = array_column($trends, 'value');
        $count = count($values);

        if ($count === 0) {
            return [];
        }

        $min = min($values);
        $max = max($values);
        $average = array_sum($values) / $count;
        
        // Calculate trend direction
        $firstValue = $values[0];
        $lastValue = $values[$count - 1];
        $change = $lastValue - $firstValue;
        $changePercent = $firstValue != 0 ? ($change / $firstValue) * 100 : 0;

        return [
            'min' => $min,
            'max' => $max,
            'average' => round($average, 2),
            'change' => round($change, 2),
            'change_percent' => round($changePercent, 2),
            'trend' => $change > 0 ? 'increasing' : ($change < 0 ? 'decreasing' : 'stable'),
            'data_points' => $count,
        ];
    }

    /**
     * Calculate overall system health
     */
    private function calculateOverallHealth(array $performance): array
    {
        $totalServices = count($performance);
        $healthyServices = 0;
        $degradedServices = 0;

        $healthyServices = collect($performance)->where('status', 'healthy')->count();
        $degradedServices = collect($performance)->where('status', '!=', 'healthy')->count();

        $healthPercentage = $totalServices > 0 ? ($healthyServices / $totalServices) * 100 : 100;

        return [
            'status' => $healthPercentage >= 90 ? 'healthy' : ($healthPercentage >= 70 ? 'degraded' : 'critical'),
            'health_percentage' => round($healthPercentage, 1),
            'healthy_services' => $healthyServices,
            'degraded_services' => $degradedServices,
            'total_services' => $totalServices,
        ];
    }

    /**
     * Generate system alerts
     */
    private function generateAlerts(array $performance): array
    {
        $serviceAlerts = collect($performance)
            ->filter(fn($service) => $service['status'] !== 'healthy')
            ->map(fn($service) => [
                'service' => $service['service_name'],
                'severity' => 'warning',
                'message' => "Service {$service['service_name']} is experiencing degraded performance",
                'timestamp' => now()->toISOString(),
            ]);

        $metricAlerts = collect($performance)
            ->flatMap(fn($service) => 
                collect($service['metrics'])
                    ->filter(fn($metricData) => isset($metricData['status']) && $metricData['status'] === 'critical')
                    ->map(fn($metricData, $metricName) => [
                        'service' => $service['service_name'],
                        'metric' => $metricName,
                        'severity' => 'critical',
                        'message' => "Critical threshold exceeded for {$metricName} in {$service['service_name']}",
                        'timestamp' => now()->toISOString(),
                    ])
            );

        return $serviceAlerts->concat($metricAlerts)->toArray();
    }
}
