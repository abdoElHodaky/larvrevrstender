<?php

namespace Shared\HealthCheck;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ServiceDiscoveryHealth
{
    /**
     * Service endpoints for health checking
     */
    private array $services = [
        'auth-service' => [
            'url' => 'http://auth-service:8001',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => true,
        ],
        'user-service' => [
            'url' => 'http://user-service:8002',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => true,
        ],
        'auction-service' => [
            'url' => 'http://auction-service:8003',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => true,
        ],
        'bidding-service' => [
            'url' => 'http://bidding-service:8004',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => true,
        ],
        'payment-service' => [
            'url' => 'http://payment-service:8005',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => true,
        ],
        'order-service' => [
            'url' => 'http://order-service:8006',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => true,
        ],
        'notification-service' => [
            'url' => 'http://notification-service:8007',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => false,
        ],
        'analytics-service' => [
            'url' => 'http://analytics-service:8008',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => false,
        ],
        'vin-ocr-service' => [
            'url' => 'http://vin-ocr-service:8009',
            'health_endpoint' => '/health',
            'timeout' => 5,
            'critical' => false,
        ],
    ];

    /**
     * Check health of all services
     */
    public function checkAllServices(): array
    {
        $results = [];
        $overallHealth = true;
        $criticalFailures = [];
        
        foreach ($this->services as $name => $config) {
            $result = $this->checkService($name, $config);
            $results[$name] = $result;
            
            if (!$result['healthy']) {
                if ($config['critical']) {
                    $overallHealth = false;
                    $criticalFailures[] = $name;
                }
            }
        }
        
        // Cache the results for monitoring
        $healthSummary = [
            'overall_healthy' => $overallHealth,
            'critical_failures' => $criticalFailures,
            'total_services' => count($this->services),
            'healthy_services' => count(array_filter($results, fn($r) => $r['healthy'])),
            'timestamp' => now()->toISOString(),
            'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
            'services' => $results,
        ];
        
        Cache::put('service_discovery:health', $healthSummary, 60); // Cache for 1 minute
        
        // Store in Redis for cross-service access
        Redis::setex('health:service_discovery', 60, json_encode($healthSummary));
        
        return $healthSummary;
    }
    
    /**
     * Check health of a specific service
     */
    public function checkService(string $name, array $config): array
    {
        $startTime = microtime(true);
        
        try {
            $url = $config['url'] . $config['health_endpoint'];
            $timeout = $config['timeout'];
            
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'X-Health-Check' => 'service-discovery',
                    'X-Source-Service' => config('app.name'),
                    'X-Environment-Color' => env('ENVIRONMENT_COLOR', 'unknown'),
                ])
                ->get($url);
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'healthy' => true,
                    'status' => $responseData['status'] ?? 'healthy',
                    'response_time_ms' => $responseTime,
                    'environment_color' => $responseData['environment_color'] ?? 'unknown',
                    'timestamp' => $responseData['timestamp'] ?? now()->toISOString(),
                    'version' => $responseData['version'] ?? null,
                    'last_check' => now()->toISOString(),
                ];
            } else {
                Log::warning("Service health check failed for {$name}", [
                    'status_code' => $response->status(),
                    'response_time_ms' => $responseTime,
                    'url' => $url,
                ]);
                
                return [
                    'healthy' => false,
                    'status' => 'unhealthy',
                    'error' => 'HTTP ' . $response->status(),
                    'response_time_ms' => $responseTime,
                    'last_check' => now()->toISOString(),
                ];
            }
            
        } catch (\Exception $e) {
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("Service health check exception for {$name}", [
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
                'url' => $config['url'] . $config['health_endpoint'],
            ]);
            
            return [
                'healthy' => false,
                'status' => 'error',
                'error' => $e->getMessage(),
                'response_time_ms' => $responseTime,
                'last_check' => now()->toISOString(),
            ];
        }
    }
    
    /**
     * Check if a specific service is healthy
     */
    public function isServiceHealthy(string $serviceName): bool
    {
        if (!isset($this->services[$serviceName])) {
            return false;
        }
        
        $result = $this->checkService($serviceName, $this->services[$serviceName]);
        return $result['healthy'];
    }
    
    /**
     * Get cached health status
     */
    public function getCachedHealth(): ?array
    {
        return Cache::get('service_discovery:health');
    }
    
    /**
     * Check cross-environment service health (blue-green)
     */
    public function checkCrossEnvironmentHealth(): array
    {
        $currentColor = env('ENVIRONMENT_COLOR', 'unknown');
        $otherColor = $currentColor === 'blue' ? 'green' : 'blue';
        
        $results = [
            'current_environment' => $currentColor,
            'other_environment' => $otherColor,
            'cross_environment_healthy' => true,
            'services' => [],
        ];
        
        foreach ($this->services as $name => $config) {
            // Check service in other environment
            $otherEnvConfig = $config;
            $otherEnvConfig['url'] = str_replace(
                "://{$name}:",
                "://{$name}-{$otherColor}:",
                $config['url']
            );
            
            $result = $this->checkService("{$name}-{$otherColor}", $otherEnvConfig);
            $results['services'][$name] = $result;
            
            if (!$result['healthy'] && $config['critical']) {
                $results['cross_environment_healthy'] = false;
            }
        }
        
        return $results;
    }
    
    /**
     * Validate service dependencies
     */
    public function validateServiceDependencies(): array
    {
        $dependencies = [
            'gateway-service' => ['auth-service', 'user-service'],
            'auction-service' => ['auth-service', 'user-service'],
            'bidding-service' => ['auth-service', 'auction-service'],
            'payment-service' => ['auth-service', 'order-service'],
            'order-service' => ['auth-service', 'user-service', 'payment-service'],
            'notification-service' => ['auth-service', 'user-service'],
            'analytics-service' => ['auth-service'],
        ];
        
        $results = [];
        $currentService = config('app.name');
        
        if (isset($dependencies[$currentService])) {
            foreach ($dependencies[$currentService] as $dependency) {
                if (isset($this->services[$dependency])) {
                    $results[$dependency] = $this->checkService($dependency, $this->services[$dependency]);
                }
            }
        }
        
        return [
            'service' => $currentService,
            'dependencies' => $results,
            'all_dependencies_healthy' => !in_array(false, array_column($results, 'healthy')),
            'timestamp' => now()->toISOString(),
        ];
    }
    
    /**
     * Get service discovery metrics for monitoring
     */
    public function getMetrics(): array
    {
        $health = $this->getCachedHealth();
        
        if (!$health) {
            $health = $this->checkAllServices();
        }
        
        return [
            'service_discovery_healthy' => $health['overall_healthy'] ? 1 : 0,
            'total_services' => $health['total_services'],
            'healthy_services' => $health['healthy_services'],
            'critical_failures' => count($health['critical_failures']),
            'average_response_time' => $this->calculateAverageResponseTime($health['services']),
            'environment_color' => env('ENVIRONMENT_COLOR', 'unknown'),
            'timestamp' => now()->timestamp,
        ];
    }
    
    /**
     * Calculate average response time from service results
     */
    private function calculateAverageResponseTime(array $services): float
    {
        $responseTimes = array_column($services, 'response_time_ms');
        $responseTimes = array_filter($responseTimes, 'is_numeric');
        
        if (empty($responseTimes)) {
            return 0.0;
        }
        
        return round(array_sum($responseTimes) / count($responseTimes), 2);
    }
}
