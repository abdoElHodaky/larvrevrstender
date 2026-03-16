<?php

namespace Shared\Procedures\Micro;

use Exception;
use Illuminate\Support\Facades\Log;
use Shared\Integrations\BaseThirdPartyIntegration;
use Shared\Integrations\Examples\StripeIntegration;

/**
 * Third Party Integration Procedure
 * 
 * Provides third-party service integration capabilities as a procedure trait.
 * Manages authentication, API calls, and webhook handling for external services.
 */
trait ThirdPartyIntegrationProcedure
{
    private array $integrations = [];

    /**
     * Initialize third-party integration
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function initializeIntegration(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;
            $integrationType = $params['integration_type'] ?? null;
            $config = $params['config'] ?? [];

            if (!$serviceName) {
                return [
                    'success' => false,
                    'error' => 'Service name is required',
                    'metadata' => ['procedure' => 'initializeIntegration']
                ];
            }

            if (!$integrationType) {
                return [
                    'success' => false,
                    'error' => 'Integration type is required',
                    'metadata' => ['procedure' => 'initializeIntegration']
                ];
            }

            // Create integration instance
            $integration = $this->createIntegration($integrationType, $serviceName, $config);
            
            if (!$integration) {
                return [
                    'success' => false,
                    'error' => "Unsupported integration type: {$integrationType}",
                    'metadata' => ['procedure' => 'initializeIntegration']
                ];
            }

            // Store integration instance
            $this->integrations[$serviceName] = $integration;

            // Authenticate
            $authenticated = $integration->authenticate();

            Log::info('Third-party integration initialized', [
                'service_name' => $serviceName,
                'integration_type' => $integrationType,
                'authenticated' => $authenticated,
                'context' => $context
            ]);

            return [
                'success' => true,
                'data' => [
                    'service_name' => $serviceName,
                    'integration_type' => $integrationType,
                    'authenticated' => $authenticated,
                    'initialized_at' => now()->toISOString()
                ],
                'metadata' => ['procedure' => 'initializeIntegration']
            ];

        } catch (Exception $e) {
            Log::error('Failed to initialize third-party integration', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null,
                'integration_type' => $params['integration_type'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to initialize integration: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'initializeIntegration']
            ];
        }
    }

    /**
     * Make API call to third-party service
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function makeApiCall(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;
            $method = $params['method'] ?? 'GET';
            $endpoint = $params['endpoint'] ?? null;
            $data = $params['data'] ?? [];
            $headers = $params['headers'] ?? [];

            if (!$serviceName || !$endpoint) {
                return [
                    'success' => false,
                    'error' => 'Service name and endpoint are required',
                    'metadata' => ['procedure' => 'makeApiCall']
                ];
            }

            $integration = $this->getIntegration($serviceName);
            if (!$integration) {
                return [
                    'success' => false,
                    'error' => "Integration not initialized for service: {$serviceName}",
                    'metadata' => ['procedure' => 'makeApiCall']
                ];
            }

            // Use reflection to call makeRequest (it's protected)
            $reflection = new \ReflectionClass($integration);
            $makeRequestMethod = $reflection->getMethod('makeRequest');
            $makeRequestMethod->setAccessible(true);

            $result = $makeRequestMethod->invoke($integration, $method, $endpoint, $data, $headers);

            Log::info('Third-party API call completed', [
                'service_name' => $serviceName,
                'method' => $method,
                'endpoint' => $endpoint,
                'success' => $result['success'],
                'context' => $context
            ]);

            return [
                'success' => $result['success'],
                'data' => $result,
                'metadata' => ['procedure' => 'makeApiCall']
            ];

        } catch (Exception $e) {
            Log::error('Failed to make third-party API call', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null,
                'endpoint' => $params['endpoint'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to make API call: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'makeApiCall']
            ];
        }
    }

    /**
     * Handle webhook from third-party service
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function handleWebhook(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;
            $payload = $params['payload'] ?? null;
            $signature = $params['signature'] ?? null;
            $headers = $params['headers'] ?? [];

            if (!$serviceName || !$payload) {
                return [
                    'success' => false,
                    'error' => 'Service name and payload are required',
                    'metadata' => ['procedure' => 'handleWebhook']
                ];
            }

            $integration = $this->getIntegration($serviceName);
            if (!$integration) {
                return [
                    'success' => false,
                    'error' => "Integration not initialized for service: {$serviceName}",
                    'metadata' => ['procedure' => 'handleWebhook']
                ];
            }

            // Verify webhook signature if provided
            if ($signature && method_exists($integration, 'verifyWebhookSignature')) {
                $verified = $integration->verifyWebhookSignature($payload, $signature);
                if (!$verified) {
                    return [
                        'success' => false,
                        'error' => 'Webhook signature verification failed',
                        'metadata' => ['procedure' => 'handleWebhook']
                    ];
                }
            }

            // Parse payload
            $event = is_string($payload) ? json_decode($payload, true) : $payload;
            if (!$event) {
                return [
                    'success' => false,
                    'error' => 'Invalid webhook payload',
                    'metadata' => ['procedure' => 'handleWebhook']
                ];
            }

            // Handle webhook event
            $result = ['success' => true, 'message' => 'Webhook received'];
            if (method_exists($integration, 'handleWebhookEvent')) {
                $result = $integration->handleWebhookEvent($event);
            }

            Log::info('Third-party webhook handled', [
                'service_name' => $serviceName,
                'event_type' => $event['type'] ?? 'unknown',
                'event_id' => $event['id'] ?? 'unknown',
                'success' => $result['success'],
                'context' => $context
            ]);

            return [
                'success' => $result['success'],
                'data' => $result,
                'metadata' => ['procedure' => 'handleWebhook']
            ];

        } catch (Exception $e) {
            Log::error('Failed to handle third-party webhook', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to handle webhook: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'handleWebhook']
            ];
        }
    }

    /**
     * Test connection to third-party service
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function testConnection(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;

            if (!$serviceName) {
                return [
                    'success' => false,
                    'error' => 'Service name is required',
                    'metadata' => ['procedure' => 'testConnection']
                ];
            }

            $integration = $this->getIntegration($serviceName);
            if (!$integration) {
                return [
                    'success' => false,
                    'error' => "Integration not initialized for service: {$serviceName}",
                    'metadata' => ['procedure' => 'testConnection']
                ];
            }

            $result = $integration->testConnection();

            Log::info('Third-party connection test completed', [
                'service_name' => $serviceName,
                'success' => $result['success'],
                'status' => $result['status'],
                'context' => $context
            ]);

            return [
                'success' => $result['success'],
                'data' => $result,
                'metadata' => ['procedure' => 'testConnection']
            ];

        } catch (Exception $e) {
            Log::error('Failed to test third-party connection', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to test connection: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'testConnection']
            ];
        }
    }

    /**
     * Get integration statistics
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function getIntegrationStats(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;

            if ($serviceName) {
                // Get stats for specific service
                $integration = $this->getIntegration($serviceName);
                if (!$integration) {
                    return [
                        'success' => false,
                        'error' => "Integration not initialized for service: {$serviceName}",
                        'metadata' => ['procedure' => 'getIntegrationStats']
                    ];
                }

                $stats = $integration->getStats();
            } else {
                // Get stats for all services
                $stats = [];
                foreach ($this->integrations as $name => $integration) {
                    $stats[$name] = $integration->getStats();
                }
            }

            return [
                'success' => true,
                'data' => $stats,
                'metadata' => ['procedure' => 'getIntegrationStats']
            ];

        } catch (Exception $e) {
            Log::error('Failed to get integration stats', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get integration stats: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'getIntegrationStats']
            ];
        }
    }

    /**
     * Reset circuit breaker for integration
     *
     * @param array $params
     * @param array $context
     * @return array
     */
    public function resetIntegrationCircuitBreaker(array $params, array $context = []): array
    {
        try {
            $serviceName = $params['service_name'] ?? null;

            if (!$serviceName) {
                return [
                    'success' => false,
                    'error' => 'Service name is required',
                    'metadata' => ['procedure' => 'resetIntegrationCircuitBreaker']
                ];
            }

            $integration = $this->getIntegration($serviceName);
            if (!$integration) {
                return [
                    'success' => false,
                    'error' => "Integration not initialized for service: {$serviceName}",
                    'metadata' => ['procedure' => 'resetIntegrationCircuitBreaker']
                ];
            }

            $integration->resetCircuitBreaker();

            Log::info('Integration circuit breaker reset', [
                'service_name' => $serviceName,
                'context' => $context
            ]);

            return [
                'success' => true,
                'data' => [
                    'service_name' => $serviceName,
                    'reset_at' => now()->toISOString()
                ],
                'metadata' => ['procedure' => 'resetIntegrationCircuitBreaker']
            ];

        } catch (Exception $e) {
            Log::error('Failed to reset integration circuit breaker', [
                'error' => $e->getMessage(),
                'service_name' => $params['service_name'] ?? null,
                'context' => $context
            ]);

            return [
                'success' => false,
                'error' => 'Failed to reset circuit breaker: ' . $e->getMessage(),
                'metadata' => ['procedure' => 'resetIntegrationCircuitBreaker']
            ];
        }
    }

    /**
     * Create integration instance based on type
     *
     * @param string $type
     * @param string $serviceName
     * @param array $config
     * @return BaseThirdPartyIntegration|null
     */
    private function createIntegration(string $type, string $serviceName, array $config): ?BaseThirdPartyIntegration
    {
        switch (strtolower($type)) {
            case 'stripe':
                return new StripeIntegration($serviceName, $config);
            
            // Add more integration types here
            // case 'mailgun':
            //     return new MailgunIntegration($serviceName, $config);
            // case 'unifonic':
            //     return new UnifonicIntegration($serviceName, $config);
            // case 'msegat':
            //     return new MsegatIntegration($serviceName, $config);
            
            default:
                return null;
        }
    }

    /**
     * Get integration instance
     *
     * @param string $serviceName
     * @return BaseThirdPartyIntegration|null
     */
    private function getIntegration(string $serviceName): ?BaseThirdPartyIntegration
    {
        return $this->integrations[$serviceName] ?? null;
    }
}
