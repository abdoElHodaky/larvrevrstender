<?php

use Shared\Procedures\CrossServiceProcedure;
use Shared\Core\RpcHandler;

/*
|--------------------------------------------------------------------------
| Cross-Service RPC Routes
|--------------------------------------------------------------------------
|
| These routes provide RPC access to cross-service procedures
| with high-performance binary protocols and service discovery.
|
*/

// Initialize the cross-service procedure and RPC handler
$crossService = new CrossServiceProcedure();
$rpcHandler = $crossService->getRpcHandler();

/**
 * Handle RPC requests
 * 
 * This function processes incoming RPC requests and routes them
 * to the appropriate procedures through the RPC handler.
 */
function handleRpcRequest($request, $context = [])
{
    global $rpcHandler;
    
    // Handle single request
    if (isset($request['method'])) {
        return $rpcHandler->handle($request, $context);
    }
    
    // Handle batch requests
    if (is_array($request) && !isset($request['method'])) {
        return $rpcHandler->handleBatch($request, $context);
    }
    
    // Invalid request format
    return [
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32600,
            'message' => 'Invalid Request'
        ],
        'id' => null
    ];
}

/**
 * RPC Method Registry
 * 
 * This array maps RPC method names to their corresponding
 * procedure and method combinations for easy routing.
 */
$rpcMethods = [
    // Event management methods
    'events.publish' => ['procedure' => 'events', 'method' => 'publishEvent'],
    'events.publishBatch' => ['procedure' => 'events', 'method' => 'publishBatchEvents'],
    'events.retry' => ['procedure' => 'events', 'method' => 'retryEventPublication'],
    'events.getStatus' => ['procedure' => 'events', 'method' => 'getEventStatus'],
    
    // Cache management methods
    'cache.set' => ['procedure' => 'cache', 'method' => 'cacheSet'],
    'cache.get' => ['procedure' => 'cache', 'method' => 'cacheGet'],
    'cache.delete' => ['procedure' => 'cache', 'method' => 'cacheDelete'],
    'cache.exists' => ['procedure' => 'cache', 'method' => 'cacheExists'],
    'cache.stats' => ['procedure' => 'cache', 'method' => 'cacheStats'],
    'cache.flush' => ['procedure' => 'cache', 'method' => 'cacheFlush'],
    
    // System management methods
    'system.health' => ['procedure' => 'system', 'method' => 'healthCheck'],
    'system.info' => ['procedure' => 'system', 'method' => 'getSystemInfo'],
    'system.listProcedures' => ['procedure' => 'system', 'method' => 'listProcedures'],
    'system.updateConfig' => ['procedure' => 'system', 'method' => 'updateConfiguration'],
    
    // Service registry methods
    'services.register' => ['procedure' => 'services', 'method' => 'registerService'],
    'services.getRegistry' => ['procedure' => 'services', 'method' => 'getServiceRegistry'],
    'services.updateHeartbeat' => ['procedure' => 'services', 'method' => 'updateHeartbeat'],
    'services.checkHealth' => ['procedure' => 'services', 'method' => 'checkServiceHealth'],
];

/**
 * Example RPC usage:
 * 
 * Single request:
 * {
 *   "jsonrpc": "2.0",
 *   "method": "events.publish",
 *   "params": {
 *     "event_type": "user.created",
 *     "event_data": {"user_id": 123},
 *     "source_service": "user-service"
 *   },
 *   "id": 1
 * }
 * 
 * Batch request:
 * [
 *   {
 *     "jsonrpc": "2.0",
 *     "method": "cache.set",
 *     "params": {"key": "user:123", "value": {"name": "John"}},
 *     "id": 1
 *   },
 *   {
 *     "jsonrpc": "2.0",
 *     "method": "events.publish",
 *     "params": {
 *       "event_type": "cache.updated",
 *       "event_data": {"key": "user:123"},
 *       "source_service": "cache-service"
 *     },
 *     "id": 2
 *   }
 * ]
 */

/**
 * RPC Client Helper Functions
 * 
 * These functions can be used by other services to make RPC calls
 * to the cross-service infrastructure.
 */

/**
 * Make an RPC call to the cross-service infrastructure
 *
 * @param string $method The RPC method name (e.g., 'events.publish')
 * @param array $params The parameters for the method
 * @param mixed $id The request ID (optional)
 * @return array The RPC response
 */
function makeRpcCall(string $method, array $params = [], $id = null): array
{
    $request = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params
    ];
    
    if ($id !== null) {
        $request['id'] = $id;
    }
    
    return handleRpcRequest($request);
}

/**
 * Make a batch RPC call
 *
 * @param array $requests Array of RPC requests
 * @return array The batch RPC response
 */
function makeBatchRpcCall(array $requests): array
{
    return handleRpcRequest($requests);
}

/**
 * Publish an event via RPC
 *
 * @param string $eventType The event type
 * @param array $eventData The event data
 * @param string $sourceService The source service name
 * @param array $targetServices Optional target services
 * @return array The RPC response
 */
function publishEventRpc(string $eventType, array $eventData, string $sourceService, array $targetServices = []): array
{
    return makeRpcCall('events.publish', [
        'event_type' => $eventType,
        'event_data' => $eventData,
        'source_service' => $sourceService,
        'target_services' => $targetServices
    ]);
}

/**
 * Set cache value via RPC
 *
 * @param string $key The cache key
 * @param mixed $value The value to cache
 * @param int $ttl Time to live in seconds
 * @param array $tags Optional cache tags
 * @return array The RPC response
 */
function setCacheRpc(string $key, $value, int $ttl = 3600, array $tags = []): array
{
    return makeRpcCall('cache.set', [
        'key' => $key,
        'value' => $value,
        'ttl' => $ttl,
        'tags' => $tags
    ]);
}

/**
 * Get cache value via RPC
 *
 * @param string $key The cache key
 * @param mixed $default Default value if not found
 * @return array The RPC response
 */
function getCacheRpc(string $key, $default = null): array
{
    return makeRpcCall('cache.get', [
        'key' => $key,
        'default' => $default
    ]);
}

/**
 * Register a service via RPC
 *
 * @param string $serviceName The service name
 * @param string $host The service host
 * @param int $port The service port
 * @param array $options Additional service options
 * @return array The RPC response
 */
function registerServiceRpc(string $serviceName, string $host, int $port, array $options = []): array
{
    $params = array_merge([
        'service_name' => $serviceName,
        'host' => $host,
        'port' => $port
    ], $options);
    
    return makeRpcCall('services.register', $params);
}

/**
 * Check system health via RPC
 *
 * @return array The RPC response
 */
function checkHealthRpc(): array
{
    return makeRpcCall('system.health');
}

/**
 * Get system information via RPC
 *
 * @return array The RPC response
 */
function getSystemInfoRpc(): array
{
    return makeRpcCall('system.info');
}

/**
 * List available procedures via RPC
 *
 * @return array The RPC response
 */
function listProceduresRpc(): array
{
    return makeRpcCall('system.listProcedures');
}

/**
 * Update configuration via RPC
 *
 * @param string $component The configuration component
 * @param array $settings The new settings
 * @return array The RPC response
 */
function updateConfigRpc(string $component, array $settings): array
{
    return makeRpcCall('system.updateConfig', [
        'component' => $component,
        'settings' => $settings
    ]);
}

/**
 * RPC Error Codes
 * 
 * Standard JSON-RPC 2.0 error codes used by the system:
 * 
 * -32700: Parse error - Invalid JSON was received
 * -32600: Invalid Request - The JSON sent is not a valid Request object
 * -32601: Method not found - The method does not exist / is not available
 * -32602: Invalid params - Invalid method parameter(s)
 * -32603: Internal error - Internal JSON-RPC error
 * -32000 to -32099: Server error - Reserved for implementation-defined server-errors
 */

// Export the RPC handler and helper functions for use by other services
return [
    'handler' => $rpcHandler,
    'methods' => $rpcMethods,
    'helpers' => [
        'makeRpcCall' => 'makeRpcCall',
        'makeBatchRpcCall' => 'makeBatchRpcCall',
        'publishEventRpc' => 'publishEventRpc',
        'setCacheRpc' => 'setCacheRpc',
        'getCacheRpc' => 'getCacheRpc',
        'registerServiceRpc' => 'registerServiceRpc',
        'checkHealthRpc' => 'checkHealthRpc',
        'getSystemInfoRpc' => 'getSystemInfoRpc',
        'listProceduresRpc' => 'listProceduresRpc',
        'updateConfigRpc' => 'updateConfigRpc'
    ]
];
