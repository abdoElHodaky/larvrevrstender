<?php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Cache;
use Sajya\Server\Exceptions\RuntimeException;

class AnalyticsProcedure extends BaseProcedure
{
    public function __construct(
        private AnalyticsService $analyticsService
    ) {}

    /**
     * Track event
     * 
     * @param array $params
     * @return array
     */
    public function trackEvent(array $params): array
    {
        $this->validate($params, [
            'event_name' => 'required|string|max:255',
            'user_id' => 'sometimes|integer|min:1',
            'session_id' => 'sometimes|string|max:255',
            'properties' => 'sometimes|array',
            'timestamp' => 'sometimes|date',
        ]);

        return $this->executeWithLogging('Analytics@trackEvent', $params, function () use ($params) {
            try {
                $result = $this->analyticsService->trackEvent([
                    'event_name' => $params['event_name'],
                    'user_id' => $params['user_id'] ?? null,
                    'session_id' => $params['session_id'] ?? null,
                    'properties' => $params['properties'] ?? [],
                    'timestamp' => $params['timestamp'] ?? now(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
                
                return [
                    'success' => true,
                    'event_id' => $result['event_id'],
                    'tracked_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to track event: ' . $e->getMessage(),
                    -32001,
                    ['event_name' => $params['event_name']]
                );
            }
        });
    }

    /**
     * Get dashboard metrics
     * 
     * @param array $params
     * @return array
     */
    public function getDashboardMetrics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:today,week,month,quarter,year',
            'metrics' => 'sometimes|array',
            'metrics.*' => 'string|in:users,orders,revenue,bids,conversions,page_views',
        ]);

        return $this->executeWithLogging('Analytics@getDashboardMetrics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $metrics = $params['metrics'] ?? ['users', 'orders', 'revenue', 'bids'];
            
            // Check cache first
            $cacheKey = 'dashboard_metrics:' . $period . ':' . md5(implode(',', $metrics));
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $dashboardData = $this->analyticsService->getDashboardMetrics($period, $metrics);
                
                $result = [
                    'success' => true,
                    'metrics' => $dashboardData,
                    'period' => $period,
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 15 minutes
                Cache::put($cacheKey, $result, 900);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve dashboard metrics: ' . $e->getMessage(),
                    -32001,
                    ['period' => $period, 'metrics' => $metrics]
                );
            }
        });
    }

    /**
     * Get user analytics
     * 
     * @param array $params
     * @return array
     */
    public function getUserAnalytics(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|min:1',
            'period' => 'sometimes|string|in:week,month,quarter,year',
            'include_events' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Analytics@getUserAnalytics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $includeEvents = $params['include_events'] ?? false;
            
            // Check cache first
            $cacheKey = 'user_analytics:' . $params['user_id'] . ':' . $period . ':' . ($includeEvents ? 'with_events' : 'no_events');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $analytics = $this->analyticsService->getUserAnalytics(
                    $params['user_id'],
                    $period,
                    $includeEvents
                );
                
                $result = [
                    'success' => true,
                    'user_id' => $params['user_id'],
                    'analytics' => $analytics,
                    'period' => $period,
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 30 minutes
                Cache::put($cacheKey, $result, 1800);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve user analytics: ' . $e->getMessage(),
                    -32001,
                    ['user_id' => $params['user_id'], 'period' => $period]
                );
            }
        });
    }

    /**
     * Get revenue analytics
     * 
     * @param array $params
     * @return array
     */
    public function getRevenueAnalytics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:week,month,quarter,year',
            'breakdown' => 'sometimes|string|in:daily,weekly,monthly',
            'currency' => 'sometimes|string|max:3',
        ]);

        return $this->executeWithLogging('Analytics@getRevenueAnalytics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $breakdown = $params['breakdown'] ?? 'daily';
            $currency = $params['currency'] ?? 'USD';
            
            // Check cache first
            $cacheKey = 'revenue_analytics:' . $period . ':' . $breakdown . ':' . $currency;
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $revenueData = $this->analyticsService->getRevenueAnalytics($period, $breakdown, $currency);
                
                $result = [
                    'success' => true,
                    'revenue_analytics' => $revenueData,
                    'period' => $period,
                    'breakdown' => $breakdown,
                    'currency' => $currency,
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 1 hour
                Cache::put($cacheKey, $result, 3600);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve revenue analytics: ' . $e->getMessage(),
                    -32001,
                    ['period' => $period, 'breakdown' => $breakdown]
                );
            }
        });
    }

    /**
     * Get bidding analytics
     * 
     * @param array $params
     * @return array
     */
    public function getBiddingAnalytics(array $params): array
    {
        $this->validate($params, [
            'period' => 'sometimes|string|in:week,month,quarter,year',
            'category' => 'sometimes|string|max:255',
            'include_trends' => 'sometimes|boolean',
        ]);

        return $this->executeWithLogging('Analytics@getBiddingAnalytics', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $category = $params['category'] ?? null;
            $includeTrends = $params['include_trends'] ?? true;
            
            // Check cache first
            $cacheKey = 'bidding_analytics:' . $period . ':' . ($category ?? 'all') . ':' . ($includeTrends ? 'with_trends' : 'no_trends');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $biddingData = $this->analyticsService->getBiddingAnalytics($period, $category, $includeTrends);
                
                $result = [
                    'success' => true,
                    'bidding_analytics' => $biddingData,
                    'period' => $period,
                    'category' => $category,
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 45 minutes
                Cache::put($cacheKey, $result, 2700);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve bidding analytics: ' . $e->getMessage(),
                    -32001,
                    ['period' => $period, 'category' => $category]
                );
            }
        });
    }

    /**
     * Generate custom report
     * 
     * @param array $params
     * @return array
     */
    public function generateReport(array $params): array
    {
        $this->validate($params, [
            'report_type' => 'required|string|in:user_activity,revenue,bidding,conversion,custom',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'filters' => 'sometimes|array',
            'format' => 'sometimes|string|in:json,csv,pdf',
        ]);

        return $this->executeWithLogging('Analytics@generateReport', $params, function () use ($params) {
            try {
                $report = $this->analyticsService->generateReport([
                    'report_type' => $params['report_type'],
                    'date_from' => $params['date_from'],
                    'date_to' => $params['date_to'],
                    'filters' => $params['filters'] ?? [],
                    'format' => $params['format'] ?? 'json',
                ]);
                
                return [
                    'success' => true,
                    'report' => $report,
                    'report_type' => $params['report_type'],
                    'date_range' => [
                        'from' => $params['date_from'],
                        'to' => $params['date_to'],
                    ],
                    'generated_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to generate report: ' . $e->getMessage(),
                    -32002,
                    ['report_type' => $params['report_type']]
                );
            }
        });
    }

    /**
     * Get real-time metrics
     * 
     * @param array $params
     * @return array
     */
    public function getRealTimeMetrics(array $params): array
    {
        $this->validate($params, [
            'metrics' => 'sometimes|array',
            'metrics.*' => 'string|in:active_users,current_bids,live_orders,page_views',
        ]);

        return $this->executeWithLogging('Analytics@getRealTimeMetrics', $params, function () use ($params) {
            $metrics = $params['metrics'] ?? ['active_users', 'current_bids', 'live_orders'];
            
            try {
                $realTimeData = $this->analyticsService->getRealTimeMetrics($metrics);
                
                return [
                    'success' => true,
                    'real_time_metrics' => $realTimeData,
                    'metrics_requested' => $metrics,
                    'timestamp' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve real-time metrics: ' . $e->getMessage(),
                    -32001,
                    ['metrics' => $metrics]
                );
            }
        });
    }

    /**
     * Get conversion funnel
     * 
     * @param array $params
     * @return array
     */
    public function getConversionFunnel(array $params): array
    {
        $this->validate($params, [
            'funnel_type' => 'required|string|in:registration,bidding,purchase,seller_onboarding',
            'period' => 'sometimes|string|in:week,month,quarter,year',
            'segment' => 'sometimes|string|max:255',
        ]);

        return $this->executeWithLogging('Analytics@getConversionFunnel', $params, function () use ($params) {
            $period = $params['period'] ?? 'month';
            $segment = $params['segment'] ?? null;
            
            // Check cache first
            $cacheKey = 'conversion_funnel:' . $params['funnel_type'] . ':' . $period . ':' . ($segment ?? 'all');
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            try {
                $funnelData = $this->analyticsService->getConversionFunnel(
                    $params['funnel_type'],
                    $period,
                    $segment
                );
                
                $result = [
                    'success' => true,
                    'funnel_data' => $funnelData,
                    'funnel_type' => $params['funnel_type'],
                    'period' => $period,
                    'segment' => $segment,
                    'generated_at' => now()->toISOString(),
                ];
                
                // Cache for 2 hours
                Cache::put($cacheKey, $result, 7200);
                
                return $result;
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to retrieve conversion funnel: ' . $e->getMessage(),
                    -32001,
                    ['funnel_type' => $params['funnel_type'], 'period' => $period]
                );
            }
        });
    }

    /**
     * Clear analytics cache
     * 
     * @param array $params
     * @return array
     */
    public function clearCache(array $params): array
    {
        $this->validate($params, [
            'cache_type' => 'sometimes|string|in:dashboard,user,revenue,bidding,all',
            'user_id' => 'sometimes|integer|min:1',
        ]);

        return $this->executeWithLogging('Analytics@clearCache', $params, function () use ($params) {
            $cacheType = $params['cache_type'] ?? 'all';
            $userId = $params['user_id'] ?? null;
            
            try {
                $clearedKeys = [];
                
                switch ($cacheType) {
                    case 'dashboard':
                        $clearedKeys = $this->clearDashboardCache();
                        break;
                    case 'user':
                        if ($userId) {
                            $clearedKeys = $this->clearUserCache($userId);
                        } else {
                            throw new RuntimeException('user_id is required for user cache clearing', -32602);
                        }
                        break;
                    case 'revenue':
                        $clearedKeys = $this->clearRevenueCache();
                        break;
                    case 'bidding':
                        $clearedKeys = $this->clearBiddingCache();
                        break;
                    case 'all':
                        $clearedKeys = $this->clearAllAnalyticsCache();
                        break;
                }
                
                return [
                    'success' => true,
                    'cache_type' => $cacheType,
                    'cleared_keys_count' => count($clearedKeys),
                    'cleared_at' => now()->toISOString(),
                ];
                
            } catch (\Exception $e) {
                throw new RuntimeException(
                    'Failed to clear cache: ' . $e->getMessage(),
                    -32003,
                    ['cache_type' => $cacheType]
                );
            }
        });
    }

    /**
     * Clear dashboard cache
     */
    private function clearDashboardCache(): array
    {
        $patterns = ['dashboard_metrics:*'];
        return $this->clearCacheByPatterns($patterns);
    }

    /**
     * Clear user-specific cache
     */
    private function clearUserCache(int $userId): array
    {
        $patterns = ["user_analytics:{$userId}:*"];
        return $this->clearCacheByPatterns($patterns);
    }

    /**
     * Clear revenue cache
     */
    private function clearRevenueCache(): array
    {
        $patterns = ['revenue_analytics:*'];
        return $this->clearCacheByPatterns($patterns);
    }

    /**
     * Clear bidding cache
     */
    private function clearBiddingCache(): array
    {
        $patterns = ['bidding_analytics:*', 'conversion_funnel:bidding:*'];
        return $this->clearCacheByPatterns($patterns);
    }

    /**
     * Clear all analytics cache
     */
    private function clearAllAnalyticsCache(): array
    {
        $patterns = [
            'dashboard_metrics:*',
            'user_analytics:*',
            'revenue_analytics:*',
            'bidding_analytics:*',
            'conversion_funnel:*'
        ];
        return $this->clearCacheByPatterns($patterns);
    }

    /**
     * Clear cache by patterns
     */
    private function clearCacheByPatterns(array $patterns): array
    {
        $clearedKeys = [];
        foreach ($patterns as $pattern) {
            // This would need to be implemented based on your cache driver
            // For Redis, you could use SCAN with pattern matching
            // For now, we'll just return the patterns as a placeholder
            $clearedKeys[] = $pattern;
        }
        return $clearedKeys;
    }
}
