<?php

namespace Shared\Procedures;

use Shared\Core\BaseProcedure;
use Shared\Core\ProcedureEngine;
use Shared\Core\RestHandler;
use Shared\Core\RpcHandler;
use Shared\Config\CrossServiceConfig;

// Import all micro procedures
use Shared\Procedures\Micro\EventPublishingProcedure;
use Shared\Procedures\Micro\CacheManagementProcedure;
use Shared\Procedures\Micro\NotificationProcedure;
use Shared\Procedures\Micro\WebPushProcedure;
use Shared\Procedures\Micro\ValidationProcedure;
use Shared\Procedures\Micro\SecurityProcedure;
use Shared\Procedures\Micro\CircuitBreakerProcedure;
use Shared\Procedures\Micro\QueueCircuitBreakerProcedure;
use Shared\Procedures\Micro\ThirdPartyIntegrationProcedure;
use Shared\Procedures\Macro\WorkflowProcedure;

/**
 * Cross-Service Procedure Hub
 * 
 * Central procedure that integrates all micro and macro procedures
 * providing unified REST and RPC endpoints for cross-service functionality.
 */
class CrossServiceProcedure extends BaseProcedure
{
    // Import all micro procedures via traits
    use EventPublishingProcedure;
    use CacheManagementProcedure;
    use NotificationProcedure;
    use WebPushProcedure;
    use ValidationProcedure;
    use SecurityProcedure;
    use CircuitBreakerProcedure;
    use QueueCircuitBreakerProcedure;
    use ThirdPartyIntegrationProcedure;
    use WorkflowProcedure;
    
    private ProcedureEngine $engine;
    private RestHandler $restHandler;
    private RpcHandler $rpcHandler;
    private CrossServiceConfig $config;

    public function __construct()
    {
        parent::__construct();
        
        $this->config = CrossServiceConfig::getInstance();
        $this->engine = new ProcedureEngine($this->config->getComponent('procedure_engine'));
        $this->restHandler = new RestHandler($this->engine, $this->config->getComponent('rest_handler'));
        $this->rpcHandler = new RpcHandler($this->engine, $this->config->getComponent('rpc_handler'));
        
        $this->registerProcedures();
    }

    /**
     * Register all available procedures with the engine
     *
     * @return void
     */
    private function registerProcedures(): void
    {
        // Register micro procedures
        $this->engine->registerProcedure('events', static::class, 'micro', [
            'description' => 'Event publishing and management',
            'methods' => ['publishEvent', 'publishBatchEvents', 'retryEventPublication', 'getEventStatus']
        ]);

        $this->engine->registerProcedure('cache', static::class, 'micro', [
            'description' => 'Cache management operations',
            'methods' => ['cacheSet', 'cacheGet', 'cacheDelete', 'cacheExists', 'cacheStats', 'cacheFlush']
        ]);

        $this->engine->registerProcedure('notification', static::class, 'micro', [
            'description' => 'Notification delivery and subscription management',
            'methods' => ['sendEmail', 'sendSms', 'sendPushNotification', 'getNotificationStatus', 'manageSubscriptions']
        ]);

        $this->engine->registerProcedure('validation', static::class, 'micro', [
            'description' => 'Data validation and sanitization',
            'methods' => ['validateData', 'validateApiRequest', 'validateCrossFields', 'sanitizeData']
        ]);

        $this->engine->registerProcedure('security', static::class, 'micro', [
            'description' => 'Security operations and authentication',
            'methods' => ['authenticateToken', 'checkAuthorization', 'applyRateLimit', 'encryptData', 'decryptData']
        ]);

        $this->engine->registerProcedure('circuit_breaker', static::class, 'micro', [
            'description' => 'Circuit breaker pattern for fault tolerance',
            'methods' => ['executeWithCircuitBreaker', 'getCircuitBreakerStats', 'resetCircuitBreaker', 'forceOpenCircuitBreaker', 'executeHttpWithCircuitBreaker']
        ]);

        $this->engine->registerProcedure('queue_circuit_breaker', static::class, 'micro', [
            'description' => 'Queue circuit breaker pattern for asynchronous fault tolerance',
            'methods' => ['dispatchWithCircuitBreaker', 'getQueueCircuitBreakerStats', 'resetQueueCircuitBreaker', 'forceOpenQueueCircuitBreaker', 'getQueueHealth']
        ]);

        $this->engine->registerProcedure('third_party_integration', static::class, 'micro', [
            'description' => 'Third-party service integration with authentication and circuit breaker protection',
            'methods' => ['initializeIntegration', 'makeApiCall', 'handleWebhook', 'testConnection', 'getIntegrationStats', 'resetIntegrationCircuitBreaker']
        ]);

        // Register macro procedures
        $this->engine->registerProcedure('workflow', static::class, 'macro', [
            'description' => 'Complex workflow orchestration and management',
            'methods' => ['startWorkflow', 'getWorkflowStatus', 'registerWorkflowDefinition', 'executeSimpleWorkflow']
        ]);
    }

    /**
     * Health check for the cross-service infrastructure
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function healthCheck(array $params = [], array $context = []): array
    {
        try {
            $checks = [];
            $overallStatus = 'healthy';

            // Check procedure engine
            $engineHealth = $this->engine->healthCheck();
            $checks['procedure_engine'] = $engineHealth;
            if ($engineHealth['status'] !== 'healthy') {
                $overallStatus = 'unhealthy';
            }

            // Check cache connectivity
            $cacheHealth = $this->checkCacheHealth();
            $checks['cache'] = $cacheHealth;
            if (!$cacheHealth['healthy']) {
                $overallStatus = 'degraded';
            }

            // Check event system
            $eventHealth = $this->checkEventSystemHealth();
            $checks['events'] = $eventHealth;
            if (!$eventHealth['healthy']) {
                $overallStatus = 'degraded';
            }

            // Check configuration
            $configHealth = $this->checkConfigurationHealth();
            $checks['configuration'] = $configHealth;
            if (!$configHealth['healthy']) {
                $overallStatus = 'unhealthy';
            }

            return $this->successResponse([
                'status' => $overallStatus,
                'timestamp' => now()->toISOString(),
                'checks' => $checks,
                'version' => '1.0.0',
                'uptime' => $this->getUptime()
            ], "Cross-service infrastructure is {$overallStatus}");

        } catch (\Exception $e) {
            $this->log('error', 'Health check failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Health check failed: ' . $e->getMessage());
        }
    }

    /**
     * Get system information and statistics
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getSystemInfo(array $params = [], array $context = []): array
    {
        try {
            $info = [
                'version' => '1.0.0',
                'environment' => app()->environment(),
                'timestamp' => now()->toISOString(),
                'uptime' => $this->getUptime(),
                'procedures' => [
                    'registered' => $this->engine->getRegisteredProcedures(),
                    'total_count' => count($this->engine->getRegisteredProcedures())
                ],
                'configuration' => $this->config->getSummary(),
                'handlers' => [
                    'rest' => [
                        'enabled' => true,
                        'config' => $this->restHandler->getConfig()
                    ],
                    'rpc' => [
                        'enabled' => true,
                        'config' => $this->rpcHandler->getConfig(),
                        'service_registry' => $this->rpcHandler->getRegistryStats()
                    ]
                ],
                'system' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'memory_usage' => memory_get_usage(true),
                    'memory_peak' => memory_get_peak_usage(true)
                ]
            ];

            return $this->successResponse($info, 'System information retrieved');

        } catch (\Exception $e) {
            $this->log('error', 'Get system info failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Get system info failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute a procedure by name and method
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function executeProcedure(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'procedure' => ['required' => true, 'type' => 'string'],
                'method' => ['required' => true, 'type' => 'string'],
                'parameters' => ['type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $procedure = $params['procedure'];
            $method = $params['method'];
            $parameters = $params['parameters'] ?? [];

            // Execute through the engine
            $result = $this->engine->execute($procedure, $method, $parameters, $context);

            return $result;

        } catch (\Exception $e) {
            $this->log('error', 'Execute procedure failed', [
                'error' => $e->getMessage(),
                'procedure' => $params['procedure'] ?? null,
                'method' => $params['method'] ?? null
            ]);

            return $this->errorResponse('Execute procedure failed: ' . $e->getMessage());
        }
    }

    /**
     * Get list of available procedures and their methods
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function listProcedures(array $params = [], array $context = []): array
    {
        try {
            $procedures = $this->engine->getRegisteredProcedures();
            $procedureList = [];

            foreach ($procedures as $name => $info) {
                $procedureList[] = [
                    'name' => $name,
                    'type' => $info['type'],
                    'description' => $info['metadata']['description'] ?? 'No description available',
                    'methods' => $info['metadata']['methods'] ?? [],
                    'registered_at' => $info['registered_at']
                ];
            }

            return $this->successResponse([
                'procedures' => $procedureList,
                'total_count' => count($procedureList)
            ], 'Procedures listed successfully');

        } catch (\Exception $e) {
            $this->log('error', 'List procedures failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('List procedures failed: ' . $e->getMessage());
        }
    }

    /**
     * Update configuration at runtime
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function updateConfiguration(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'component' => ['required' => true, 'type' => 'string'],
                'settings' => ['required' => true, 'type' => 'array']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $component = $params['component'];
            $settings = $params['settings'];

            // Update configuration
            $currentConfig = $this->config->getComponent($component);
            $newConfig = array_merge($currentConfig, $settings);
            $this->config->set($component, $newConfig);

            // Update handlers if needed
            if ($component === 'rest_handler') {
                $this->restHandler->updateConfig($settings);
            } elseif ($component === 'rpc_handler') {
                $this->rpcHandler->updateConfig($settings);
            }

            $this->log('info', 'Configuration updated', [
                'component' => $component,
                'settings' => $settings
            ]);

            return $this->successResponse([
                'component' => $component,
                'updated_settings' => $settings,
                'current_config' => $this->config->getComponent($component)
            ], 'Configuration updated successfully');

        } catch (\Exception $e) {
            $this->log('error', 'Update configuration failed', [
                'error' => $e->getMessage(),
                'component' => $params['component'] ?? null
            ]);

            return $this->errorResponse('Update configuration failed: ' . $e->getMessage());
        }
    }

    /**
     * Register a new service in the RPC service registry
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function registerService(array $params, array $context = []): array
    {
        try {
            $validation = $this->validateParams($params, [
                'service_name' => ['required' => true, 'type' => 'string'],
                'host' => ['required' => true, 'type' => 'string'],
                'port' => ['required' => true, 'type' => 'int'],
                'protocol' => ['type' => 'string'],
                'health_check_url' => ['type' => 'string'],
                'weight' => ['type' => 'int']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            $serviceName = $params['service_name'];
            $serviceInfo = [
                'host' => $params['host'],
                'port' => $params['port'],
                'protocol' => $params['protocol'] ?? 'http',
                'health_check_url' => $params['health_check_url'] ?? '/health',
                'weight' => $params['weight'] ?? 1
            ];

            $this->rpcHandler->registerService($serviceName, $serviceInfo);

            return $this->successResponse([
                'service_name' => $serviceName,
                'service_info' => $serviceInfo
            ], 'Service registered successfully');

        } catch (\Exception $e) {
            $this->log('error', 'Register service failed', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null
            ]);

            return $this->errorResponse('Register service failed: ' . $e->getMessage());
        }
    }

    /**
     * Get service registry information
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getServiceRegistry(array $params = [], array $context = []): array
    {
        try {
            $registry = $this->rpcHandler->getRegistryStats();

            return $this->successResponse($registry, 'Service registry retrieved');

        } catch (\Exception $e) {
            $this->log('error', 'Get service registry failed', [
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse('Get service registry failed: ' . $e->getMessage());
        }
    }

    /**
     * Check cache system health
     *
     * @return array
     */
    private function checkCacheHealth(): array
    {
        try {
            $testKey = 'health_check_' . uniqid();
            $testValue = 'test_' . time();

            // Test cache set/get/delete
            $setResult = $this->cacheSet(['key' => $testKey, 'value' => $testValue, 'ttl' => 60]);
            if (!$setResult['success']) {
                return ['healthy' => false, 'error' => 'Cache set failed'];
            }

            $getResult = $this->cacheGet(['key' => $testKey]);
            if (!$getResult['success'] || $getResult['data']['value'] !== $testValue) {
                return ['healthy' => false, 'error' => 'Cache get failed'];
            }

            $deleteResult = $this->cacheDelete(['key' => $testKey]);
            if (!$deleteResult['success']) {
                return ['healthy' => false, 'error' => 'Cache delete failed'];
            }

            return ['healthy' => true, 'message' => 'Cache system operational'];

        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check event system health
     *
     * @return array
     */
    private function checkEventSystemHealth(): array
    {
        try {
            // Test event publishing
            $testEvent = [
                'event_type' => 'health_check',
                'event_data' => ['test' => true, 'timestamp' => time()],
                'source_service' => 'cross_service_health_check'
            ];

            $result = $this->publishEvent($testEvent);
            if (!$result['success']) {
                return ['healthy' => false, 'error' => 'Event publishing failed'];
            }

            return ['healthy' => true, 'message' => 'Event system operational'];

        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check configuration health
     *
     * @return array
     */
    private function checkConfigurationHealth(): array
    {
        try {
            $validationErrors = $this->config->validate();
            
            if (!empty($validationErrors)) {
                return [
                    'healthy' => false,
                    'errors' => $validationErrors
                ];
            }

            return ['healthy' => true, 'message' => 'Configuration valid'];

        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get system uptime (placeholder)
     *
     * @return string
     */
    private function getUptime(): string
    {
        // This would typically track actual service uptime
        // For now, return a placeholder
        return 'Unknown';
    }

    /**
     * Get procedure engine instance
     *
     * @return ProcedureEngine
     */
    public function getEngine(): ProcedureEngine
    {
        return $this->engine;
    }

    /**
     * Get REST handler instance
     *
     * @return RestHandler
     */
    public function getRestHandler(): RestHandler
    {
        return $this->restHandler;
    }

    /**
     * Get RPC handler instance
     *
     * @return RpcHandler
     */
    public function getRpcHandler(): RpcHandler
    {
        return $this->rpcHandler;
    }

    /**
     * Get configuration instance
     *
     * @return CrossServiceConfig
     */
    public function getConfig(): CrossServiceConfig
    {
        return $this->config;
    }
}
