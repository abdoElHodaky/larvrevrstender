<?php

namespace App\Jobs;

use Shared\Jobs\BaseQueueJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Shared\Contracts\ModelResolverInterface;

/**
 * Cache Warming Job with Laravel Fuse Circuit Breaker Protection
 * 
 * Proactively warms critical cache data to improve application performance
 * and reduce database load. This is essential for maintaining optimal
 * response times and system efficiency across all microservices.
 */
class WarmCacheDataJob extends BaseQueueJob
{
    public array $cacheTypes;
    public array $cacheOptions;
    public int $batchSize;
    public int $tries = 3;
    public int $timeout = 900; // 15 minutes for cache warming
    
    protected ModelResolverInterface $modelResolver;

    /**
     * Create a new job instance.
     */
    public function __construct(
        array $cacheTypes = [],
        array $cacheOptions = [],
        int $batchSize = 1000,
        ?ModelResolverInterface $modelResolver = null
    ) {
        parent::__construct();
        
        $this->cacheTypes = $cacheTypes ?: $this->getDefaultCacheTypes();
        $this->cacheOptions = array_merge($this->getDefaultCacheOptions(), $cacheOptions);
        $this->batchSize = $batchSize;
        $this->modelResolver = $modelResolver ?: app(ModelResolverInterface::class);
        
        // Set queue for cache operations
        $this->onQueue($this->getQueueForCacheComplexity($cacheTypes));
        
        // Configure circuit breaker for cache warming
        $this->configureCircuitBreaker([
            'service_name' => 'cache_warming',
            'failure_threshold' => 40, // 40% failure rate triggers circuit breaker
            'timeout' => 300, // 5 minutes timeout for cache operations
            'recovery_timeout' => 600, // 10 minutes before attempting recovery
            'tags' => [
                'service' => 'shared-service',
                'job_type' => 'optimization',
                'operation' => 'cache_warming',
                'priority' => 'low'
            ]
        ]);
    }

    /**
     * Execute the job with circuit breaker protection.
     */
    public function handle(): void
    {
        Log::info('Starting cache warming with circuit breaker protection', [
            'cache_types' => $this->cacheTypes,
            'cache_options' => $this->cacheOptions,
            'batch_size' => $this->batchSize,
            'job_id' => $this->job?->getJobId(),
            'circuit_breaker_service' => 'cache_warming'
        ]);

        $this->executeWithCircuitBreaker(function() {
            $results = [
                'cache_types_processed' => 0,
                'cache_entries_warmed' => 0,
                'cache_entries_failed' => 0,
                'total_memory_used' => 0,
                'processing_time_ms' => 0,
                'errors' => []
            ];

            $startTime = microtime(true);

            foreach ($this->cacheTypes as $cacheType) {
                try {
                    $cacheResult = $this->warmCacheType($cacheType);
                    
                    $results['cache_types_processed']++;
                    $results['cache_entries_warmed'] += $cacheResult['entries_warmed'];
                    $results['cache_entries_failed'] += $cacheResult['entries_failed'];
                    $results['total_memory_used'] += $cacheResult['memory_used'];
                    
                    Log::debug('Cache type warmed successfully', [
                        'cache_type' => $cacheType,
                        'entries_warmed' => $cacheResult['entries_warmed'],
                        'memory_used_mb' => round($cacheResult['memory_used'] / 1024 / 1024, 2)
                    ]);
                    
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'cache_type' => $cacheType,
                        'error' => $e->getMessage()
                    ];
                    
                    Log::error('Failed to warm cache type', [
                        'cache_type' => $cacheType,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $results['processing_time_ms'] = round((microtime(true) - $startTime) * 1000);

            Log::info('Cache warming completed successfully', [
                'cache_types_processed' => $results['cache_types_processed'],
                'total_entries_warmed' => $results['cache_entries_warmed'],
                'total_memory_used_mb' => round($results['total_memory_used'] / 1024 / 1024, 2),
                'processing_time_ms' => $results['processing_time_ms'],
                'job_id' => $this->job?->getJobId()
            ]);

            return $results;
        }, function(\Exception $e) {
            Log::error('Cache warming failed with circuit breaker protection', [
                'cache_types' => $this->cacheTypes,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'job_id' => $this->job?->getJobId(),
            ]);

            throw $e;
        });
    }

    /**
     * Warm a specific cache type
     */
    private function warmCacheType(string $cacheType): array
    {
        $startTime = microtime(true);
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        Log::debug('Starting cache warming for type', [
            'cache_type' => $cacheType,
            'batch_size' => $this->batchSize
        ]);

        return match ($cacheType) {
            'user_profiles' => $this->warmUserProfilesCache(),
            'auction_data' => $this->warmAuctionDataCache(),
            'analytics_metrics' => $this->warmAnalyticsMetricsCache(),
            'system_config' => $this->warmSystemConfigCache(),
            'notification_templates' => $this->warmNotificationTemplatesCache(),
            'payment_methods' => $this->warmPaymentMethodsCache(),
            default => throw new \InvalidArgumentException("Unknown cache type: {$cacheType}")
        };
    }

    /**
     * Warm user profiles cache
     */
    private function warmUserProfilesCache(): array
    {
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        // Get active users for cache warming using Eloquent (Laravel 12)
        $userQuery = $this->modelResolver->query('User');
        if (!$userQuery) {
            Log::warning('User model not available for cache warming');
            return ['entries_warmed' => 0, 'entries_failed' => 0, 'memory_used' => 0];
        }
        
        $activeUsers = $userQuery
            ->where('status', 'active')
            ->where('last_login_at', '>=', now()->subDays(30))
            ->select('id', 'email', 'name', 'role')
            ->limit($this->batchSize)
            ->get();

        foreach ($activeUsers as $user) {
            try {
                $cacheKey = "user_profile:{$user->id}";
                $userData = [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                    'role' => $user->role,
                    'cached_at' => now()->toISOString()
                ];
                
                Cache::put($cacheKey, $userData, $this->cacheOptions['user_profiles_ttl']);
                $entriesWarmed++;
                $memoryUsed += strlen(json_encode($userData));
                
            } catch (\Exception $e) {
                $entriesFailed++;
                Log::warning('Failed to cache user profile', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'entries_warmed' => $entriesWarmed,
            'entries_failed' => $entriesFailed,
            'memory_used' => $memoryUsed
        ];
    }

    /**
     * Warm auction data cache
     */
    private function warmAuctionDataCache(): array
    {
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        // Get active auctions for cache warming using Eloquent (Laravel 12)
        $auctionQuery = $this->modelResolver->query('Auction');
        if (!$auctionQuery) {
            Log::warning('Auction model not available for cache warming');
            return ['entries_warmed' => 0, 'entries_failed' => 0, 'memory_used' => 0];
        }
        
        $activeAuctions = $auctionQuery
            ->where('status', 'active')
            ->where('end_time', '>', now())
            ->select('id', 'title', 'current_bid', 'bid_count', 'end_time')
            ->limit($this->batchSize)
            ->get();

        foreach ($activeAuctions as $auction) {
            try {
                $cacheKey = "auction_data:{$auction->id}";
                $auctionData = [
                    'id' => $auction->id,
                    'title' => $auction->title,
                    'current_bid' => $auction->current_bid,
                    'bid_count' => $auction->bid_count,
                    'end_time' => $auction->end_time,
                    'cached_at' => now()->toISOString()
                ];
                
                Cache::put($cacheKey, $auctionData, $this->cacheOptions['auction_data_ttl']);
                $entriesWarmed++;
                $memoryUsed += strlen(json_encode($auctionData));
                
            } catch (\Exception $e) {
                $entriesFailed++;
                Log::warning('Failed to cache auction data', [
                    'auction_id' => $auction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'entries_warmed' => $entriesWarmed,
            'entries_failed' => $entriesFailed,
            'memory_used' => $memoryUsed
        ];
    }

    /**
     * Warm analytics metrics cache
     */
    private function warmAnalyticsMetricsCache(): array
    {
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        // Get recent business metrics for cache warming using Eloquent (Laravel 12)
        $metricsQuery = $this->modelResolver->query('BusinessMetric');
        if (!$metricsQuery) {
            Log::warning('BusinessMetric model not available for cache warming');
            return ['entries_warmed' => 0, 'entries_failed' => 0, 'memory_used' => 0];
        }
        
        $recentMetrics = $metricsQuery
            ->where('metric_date', '>=', now()->subDays(7))
            ->select('metric_type', 'metric_date', 'value', 'breakdown_data')
            ->limit($this->batchSize)
            ->get();

        foreach ($recentMetrics as $metric) {
            try {
                $cacheKey = "analytics_metric:{$metric->metric_type}:{$metric->metric_date}";
                $metricData = [
                    'metric_type' => $metric->metric_type,
                    'metric_date' => $metric->metric_date,
                    'value' => $metric->value,
                    'breakdown_data' => json_decode($metric->breakdown_data, true),
                    'cached_at' => now()->toISOString()
                ];
                
                Cache::put($cacheKey, $metricData, $this->cacheOptions['analytics_metrics_ttl']);
                $entriesWarmed++;
                $memoryUsed += strlen(json_encode($metricData));
                
            } catch (\Exception $e) {
                $entriesFailed++;
                Log::warning('Failed to cache analytics metric', [
                    'metric_type' => $metric->metric_type,
                    'metric_date' => $metric->metric_date,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'entries_warmed' => $entriesWarmed,
            'entries_failed' => $entriesFailed,
            'memory_used' => $memoryUsed
        ];
    }

    /**
     * Warm system configuration cache
     */
    private function warmSystemConfigCache(): array
    {
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        // System configurations to cache
        $systemConfigs = [
            'app_settings' => $this->getAppSettings(),
            'feature_flags' => $this->getFeatureFlags(),
            'service_endpoints' => $this->getServiceEndpoints(),
            'rate_limits' => $this->getRateLimits(),
            'cache_settings' => $this->getCacheSettings()
        ];

        foreach ($systemConfigs as $configType => $configData) {
            try {
                $cacheKey = "system_config:{$configType}";
                $configWithMeta = [
                    'config_type' => $configType,
                    'data' => $configData,
                    'cached_at' => now()->toISOString()
                ];
                
                Cache::put($cacheKey, $configWithMeta, $this->cacheOptions['system_config_ttl']);
                $entriesWarmed++;
                $memoryUsed += strlen(json_encode($configWithMeta));
                
            } catch (\Exception $e) {
                $entriesFailed++;
                Log::warning('Failed to cache system config', [
                    'config_type' => $configType,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'entries_warmed' => $entriesWarmed,
            'entries_failed' => $entriesFailed,
            'memory_used' => $memoryUsed
        ];
    }

    /**
     * Warm notification templates cache
     */
    private function warmNotificationTemplatesCache(): array
    {
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        // Get active notification templates using Eloquent (Laravel 12)
        $templateQuery = $this->modelResolver->query('NotificationTemplate');
        if (!$templateQuery) {
            Log::warning('NotificationTemplate model not available for cache warming');
            return ['entries_warmed' => 0, 'entries_failed' => 0, 'memory_used' => 0];
        }
        
        $templates = $templateQuery
            ->where('status', 'active')
            ->select('id', 'name', 'type', 'template_data', 'variables')
            ->limit($this->batchSize)
            ->get();

        foreach ($templates as $template) {
            try {
                $cacheKey = "notification_template:{$template->id}";
                $templateData = [
                    'id' => $template->id,
                    'name' => $template->name,
                    'type' => $template->type,
                    'template_data' => json_decode($template->template_data, true),
                    'variables' => json_decode($template->variables, true),
                    'cached_at' => now()->toISOString()
                ];
                
                Cache::put($cacheKey, $templateData, $this->cacheOptions['notification_templates_ttl']);
                $entriesWarmed++;
                $memoryUsed += strlen(json_encode($templateData));
                
            } catch (\Exception $e) {
                $entriesFailed++;
                Log::warning('Failed to cache notification template', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'entries_warmed' => $entriesWarmed,
            'entries_failed' => $entriesFailed,
            'memory_used' => $memoryUsed
        ];
    }

    /**
     * Warm payment methods cache
     */
    private function warmPaymentMethodsCache(): array
    {
        $entriesWarmed = 0;
        $entriesFailed = 0;
        $memoryUsed = 0;

        // Get active payment methods using Eloquent (Laravel 12)
        $paymentMethodQuery = $this->modelResolver->query('PaymentMethod');
        if (!$paymentMethodQuery) {
            Log::warning('PaymentMethod model not available for cache warming');
            return ['entries_warmed' => 0, 'entries_failed' => 0, 'memory_used' => 0];
        }
        
        $paymentMethods = $paymentMethodQuery
            ->where('status', 'active')
            ->select('id', 'name', 'type', 'configuration', 'supported_currencies')
            ->limit($this->batchSize)
            ->get();

        foreach ($paymentMethods as $method) {
            try {
                $cacheKey = "payment_method:{$method->id}";
                $methodData = [
                    'id' => $method->id,
                    'name' => $method->name,
                    'type' => $method->type,
                    'configuration' => json_decode($method->configuration, true),
                    'supported_currencies' => json_decode($method->supported_currencies, true),
                    'cached_at' => now()->toISOString()
                ];
                
                Cache::put($cacheKey, $methodData, $this->cacheOptions['payment_methods_ttl']);
                $entriesWarmed++;
                $memoryUsed += strlen(json_encode($methodData));
                
            } catch (\Exception $e) {
                $entriesFailed++;
                Log::warning('Failed to cache payment method', [
                    'method_id' => $method->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'entries_warmed' => $entriesWarmed,
            'entries_failed' => $entriesFailed,
            'memory_used' => $memoryUsed
        ];
    }

    /**
     * Helper methods for system configuration
     */
    private function getAppSettings(): array
    {
        return [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_timezone' => config('app.timezone'),
            'maintenance_mode' => false
        ];
    }

    private function getFeatureFlags(): array
    {
        return [
            'new_auction_ui' => true,
            'advanced_analytics' => true,
            'multi_currency_support' => true,
            'real_time_notifications' => true,
            'enhanced_security' => true
        ];
    }

    private function getServiceEndpoints(): array
    {
        return [
            'auth_service' => config('services.auth.url', 'http://auth-service:8001'),
            'user_service' => config('services.user.url', 'http://user-service:8002'),
            'analytics_service' => config('services.analytics.url', 'http://analytics-service:8003'),
            'payment_service' => config('services.payment.url', 'http://payment-service:8004'),
            'notification_service' => config('services.notification.url', 'http://notification-service:8006')
        ];
    }

    private function getRateLimits(): array
    {
        return [
            'api_requests_per_minute' => 60,
            'login_attempts_per_hour' => 5,
            'bid_submissions_per_minute' => 10,
            'notification_sends_per_minute' => 100
        ];
    }

    private function getCacheSettings(): array
    {
        return [
            'default_ttl' => 3600,
            'user_data_ttl' => 1800,
            'auction_data_ttl' => 300,
            'analytics_ttl' => 7200,
            'config_ttl' => 86400
        ];
    }

    /**
     * Get default cache types
     */
    private function getDefaultCacheTypes(): array
    {
        return [
            'user_profiles',
            'auction_data',
            'analytics_metrics',
            'system_config',
            'notification_templates'
        ];
    }

    /**
     * Get default cache options
     */
    private function getDefaultCacheOptions(): array
    {
        return [
            'user_profiles_ttl' => 1800, // 30 minutes
            'auction_data_ttl' => 300, // 5 minutes
            'analytics_metrics_ttl' => 7200, // 2 hours
            'system_config_ttl' => 86400, // 24 hours
            'notification_templates_ttl' => 3600, // 1 hour
            'payment_methods_ttl' => 3600, // 1 hour
            'compression_enabled' => true,
            'memory_limit_mb' => 512
        ];
    }

    /**
     * Get queue name based on cache complexity
     */
    private function getQueueForCacheComplexity(array $cacheTypes): string
    {
        $heavyCacheTypes = ['analytics_metrics', 'user_profiles'];
        $hasHeavyCache = !empty(array_intersect($cacheTypes, $heavyCacheTypes));
        
        return match (true) {
            count($cacheTypes) >= 5 => 'cache-warming-large',
            $hasHeavyCache => 'cache-warming-heavy',
            count($cacheTypes) >= 3 => 'cache-warming-medium',
            default => 'cache-warming-default',
        };
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Cache warming job failed permanently', [
            'cache_types' => $this->cacheTypes,
            'cache_options' => $this->cacheOptions,
            'batch_size' => $this->batchSize,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job?->getJobId(),
        ]);

        // Could broadcast failure event for monitoring
        // broadcast(new \App\Events\Cache\CacheWarmingFailed(...));
    }
}
