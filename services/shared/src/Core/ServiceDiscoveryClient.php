<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service Discovery Client
 * 
 * Provides service discovery functionality for RPC clients.
 * Integrates with the existing RpcHandler service registry.
 */
class ServiceDiscoveryClient
{
    private RpcHandler $rpcHandler;
    private array $config;
    private array $serviceCache = [];
    private int $cacheExpiry = 300; // 5 minutes

    public function __construct(RpcHandler $rpcHandler, array $config = [])
    {
        $this->rpcHandler = $rpcHandler;
        $this->config = array_merge([
            'cache_ttl' => 300, // 5 minutes
            'health_check_interval' => 30, // seconds
            'default_protocol' => 'http',
            'default_rpc_path' => '/rpc',
            'fallback_services' => []
        ], $config);
        
        $this->cacheExpiry = $this->config['cache_ttl'];
    }

    /**
     * Get service information by name
     *
     * @param string $serviceName The service name to lookup
     * @return array|null Service information or null if not found
     */
    public function getService(string $serviceName): ?array
    {
        // Check cache first
        if ($this->isCacheValid($serviceName)) {
            return $this->serviceCache[$serviceName]['data'];
        }

        // Get from service registry
        $serviceInfo = $this->rpcHandler->getService($serviceName);
        
        if (!$serviceInfo) {
            // Try fallback services
            $serviceInfo = $this->getFallbackService($serviceName);
        }

        if ($serviceInfo) {
            // Enhance service info with defaults
            $serviceInfo = $this->enhanceServiceInfo($serviceInfo);
            
            // Cache the result
            $this->cacheService($serviceName, $serviceInfo);
            
            Log::debug('Service discovered', [
                'service' => $serviceName,
                'host' => $serviceInfo['host'],
                'port' => $serviceInfo['port']
            ]);
        } else {
            Log::warning('Service not found', ['service' => $serviceName]);
        }

        return $serviceInfo;
    }

    /**
     * Get all available services
     *
     * @return array Array of all registered services
     */
    public function getAllServices(): array
    {
        return $this->rpcHandler->getAllServices();
    }

    /**
     * Register a service in the registry
     *
     * @param string $serviceName The service name
     * @param array $serviceInfo Service configuration
     * @return void
     */
    public function registerService(string $serviceName, array $serviceInfo): void
    {
        // Enhance service info with defaults
        $enhancedInfo = $this->enhanceServiceInfo($serviceInfo);
        
        // Register with RPC handler
        $this->rpcHandler->registerService($serviceName, $enhancedInfo);
        
        // Update cache
        $this->cacheService($serviceName, $enhancedInfo);
        
        Log::info('Service registered via discovery client', [
            'service' => $serviceName,
            'info' => $enhancedInfo
        ]);
    }

    /**
     * Unregister a service from the registry
     *
     * @param string $serviceName The service name
     * @return void
     */
    public function unregisterService(string $serviceName): void
    {
        $this->rpcHandler->unregisterService($serviceName);
        
        // Remove from cache
        unset($this->serviceCache[$serviceName]);
        
        Log::info('Service unregistered via discovery client', [
            'service' => $serviceName
        ]);
    }

    /**
     * Check service health
     *
     * @param string $serviceName The service name
     * @return array Health check result
     */
    public function checkServiceHealth(string $serviceName): array
    {
        return $this->rpcHandler->checkServiceHealth($serviceName);
    }

    /**
     * Update service heartbeat
     *
     * @param string $serviceName The service name
     * @return bool Success status
     */
    public function updateHeartbeat(string $serviceName): bool
    {
        $result = $this->rpcHandler->updateHeartbeat($serviceName);
        
        // Invalidate cache to force refresh
        if ($result) {
            unset($this->serviceCache[$serviceName]);
        }
        
        return $result;
    }

    /**
     * Get service registry statistics
     *
     * @return array Registry statistics
     */
    public function getRegistryStats(): array
    {
        return $this->rpcHandler->getRegistryStats();
    }

    /**
     * Find services by criteria
     *
     * @param array $criteria Search criteria (e.g., ['status' => 'healthy'])
     * @return array Matching services
     */
    public function findServices(array $criteria = []): array
    {
        $allServices = $this->getAllServices();
        
        if (empty($criteria)) {
            return $allServices;
        }

        return collect($allServices)->filter(function ($serviceInfo, $serviceName) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (!isset($serviceInfo[$key]) || $serviceInfo[$key] !== $value) {
                    return false;
                }
            }
            return true;
        })->toArray();
    }

    /**
     * Get healthy services only
     *
     * @return array Array of healthy services
     */
    public function getHealthyServices(): array
    {
        return $this->findServices(['status' => 'healthy']);
    }

    /**
     * Get service with load balancing
     *
     * @param string $serviceName Base service name
     * @param string $strategy Load balancing strategy ('round_robin', 'least_connections', 'random')
     * @return array|null Service information
     */
    public function getServiceWithLoadBalancing(string $serviceName, string $strategy = 'round_robin'): ?array
    {
        // For now, return single service - can be extended for multiple instances
        return $this->getService($serviceName);
    }

    /**
     * Check if cached service info is valid
     */
    private function isCacheValid(string $serviceName): bool
    {
        if (!isset($this->serviceCache[$serviceName])) {
            return false;
        }

        $cached = $this->serviceCache[$serviceName];
        return (time() - $cached['timestamp']) < $this->cacheExpiry;
    }

    /**
     * Cache service information
     */
    private function cacheService(string $serviceName, array $serviceInfo): void
    {
        $this->serviceCache[$serviceName] = [
            'data' => $serviceInfo,
            'timestamp' => time()
        ];
    }

    /**
     * Enhance service info with defaults
     */
    private function enhanceServiceInfo(array $serviceInfo): array
    {
        return array_merge([
            'protocol' => $this->config['default_protocol'],
            'rpc_path' => $this->config['default_rpc_path'],
            'health_check_url' => '/health',
            'weight' => 1,
            'max_connections' => 100,
            'timeout' => 30,
            'status' => 'healthy'
        ], $serviceInfo);
    }

    /**
     * Get fallback service configuration
     */
    private function getFallbackService(string $serviceName): ?array
    {
        $fallbacks = $this->config['fallback_services'];
        
        if (isset($fallbacks[$serviceName])) {
            Log::info('Using fallback service configuration', [
                'service' => $serviceName,
                'fallback' => $fallbacks[$serviceName]
            ]);
            
            return $fallbacks[$serviceName];
        }

        // Try environment-based fallback
        $envHost = env(strtoupper($serviceName) . '_HOST');
        $envPort = env(strtoupper($serviceName) . '_PORT');
        
        if ($envHost && $envPort) {
            Log::info('Using environment-based service configuration', [
                'service' => $serviceName,
                'host' => $envHost,
                'port' => $envPort
            ]);
            
            return [
                'host' => $envHost,
                'port' => (int) $envPort,
                'protocol' => $this->config['default_protocol'],
                'rpc_path' => $this->config['default_rpc_path']
            ];
        }

        return null;
    }

    /**
     * Clear service cache
     *
     * @param string|null $serviceName Specific service to clear, or null for all
     * @return void
     */
    public function clearCache(?string $serviceName = null): void
    {
        if ($serviceName) {
            unset($this->serviceCache[$serviceName]);
            Log::debug('Service cache cleared', ['service' => $serviceName]);
        } else {
            $this->serviceCache = [];
            Log::debug('All service cache cleared');
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics
     */
    public function getCacheStats(): array
    {
        $now = time();
        $validEntries = 0;
        $expiredEntries = 0;

        foreach ($this->serviceCache as $cached) {
            if (($now - $cached['timestamp']) < $this->cacheExpiry) {
                $validEntries++;
            } else {
                $expiredEntries++;
            }
        }

        return [
            'total_entries' => count($this->serviceCache),
            'valid_entries' => $validEntries,
            'expired_entries' => $expiredEntries,
            'cache_ttl' => $this->cacheExpiry,
            'hit_ratio' => count($this->serviceCache) > 0 ? $validEntries / count($this->serviceCache) : 0
        ];
    }

    /**
     * Update configuration
     *
     * @param array $config New configuration values
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        
        if (isset($config['cache_ttl'])) {
            $this->cacheExpiry = $config['cache_ttl'];
        }
    }

    /**
     * Get current configuration
     *
     * @return array Current configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
