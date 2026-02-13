<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🔧 Shared Services Infrastructure</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Comprehensive <strong>cross-service infrastructure</strong> for Laravel microservices with micro procedures, macro procedures, and unified communication patterns supporting REST and RPC architectures.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Infrastructure Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🏗️ Core Architecture**: Unified procedure engine with REST/RPC handlers, configuration management, and base procedure functionality
- **🔄 Micro Procedures**: Event publishing, cache management, validation, security, monitoring, and comprehensive testing procedures
- **📊 Infrastructure Components**: Event-driven architecture, distributed caching, service discovery, and comprehensive error handling

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">🔧 Complete Shared Services Documentation</summary>

A comprehensive cross-service infrastructure for Laravel microservices with both micro procedures (single-responsibility) and macro procedures (complex workflows) supporting REST and RPC communication patterns.

### Features

#### Core Architecture
- **Procedure Engine**: Unified execution engine for micro and macro procedures
- **REST Handler**: RESTful API with CORS, rate limiting, and standardized responses
- **RPC Handler**: High-performance JSON-RPC 2.0 with service discovery
- **Configuration Management**: Centralized configuration with environment overrides
- **Base Procedure**: Common functionality for logging, caching, validation, and metrics

#### Micro Procedures (Implemented)
- **Event Publishing**: Reliable event emission with retry logic and audit trails
- **Cache Management**: Distributed caching with compression, tagging, and analytics
- **More procedures**: Validation, Security, Monitoring, Database, Communication, Testing

#### Infrastructure Components
- **Event-Driven Architecture**: Redis/RabbitMQ/Kafka support with dead letter queues
- **Distributed Caching**: Redis/Memcached/File with automatic compression
- **Service Discovery**: Dynamic service registration with health checks
- **Comprehensive Logging**: Structured logging with trace IDs
- **Metrics Collection**: Performance and business metrics tracking
- **Error Handling**: Circuit breakers, retry logic, and comprehensive error tracking

### Project Structure

```
services/shared/
├── src/
│   ├── Core/                          # Core infrastructure
│   │   ├── ProcedureEngine.php        # Procedure execution engine
│   │   ├── BaseProcedure.php          # Base class for all procedures
│   │   ├── RestHandler.php            # REST API handler
│   │   └── RpcHandler.php             # RPC handler with service discovery
│   ├── Config/
│   │   └── CrossServiceConfig.php     # Configuration management
│   ├── Procedures/
│   │   ├── CrossServiceProcedure.php  # Main procedure hub
│   │   ├── Micro/                     # Micro procedures (single-responsibility)
│   │   │   ├── EventPublishingProcedure.php
│   │   │   ├── CacheManagementProcedure.php
│   │   │   ├── ValidationProcedure.php
│   │   │   ├── SecurityProcedure.php
│   │   │   └── ...
│   │   └── Macro/                     # Macro procedures (complex workflows)
│   │       ├── WorkflowOrchestrationProcedure.php
│   │       └── ...
├── routes/
│   ├── api.php                        # REST API routes
│   └── rpc.php                        # RPC routes and helpers
└── README.md                          # This file
```

## 🛠 Installation & Setup

### 1. Configuration

The system uses a centralized configuration manager that supports environment overrides:

```php
use Shared\Config\CrossServiceConfig;

$config = CrossServiceConfig::getInstance();

// Get configuration
$cacheDriver = $config->get('caching.default_driver');
$eventDriver = $config->get('events.default_driver');

// Update configuration at runtime
$config->set('caching.default_ttl', 7200);
```

### 2. Environment Variables

Add these to your `.env` file:

```env
# Cross-Service Configuration
CROSS_SERVICE_TIMEOUT=30
CROSS_SERVICE_ENABLE_METRICS=true
CROSS_SERVICE_CACHE_DRIVER=redis
CROSS_SERVICE_EVENT_DRIVER=redis

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# JWT Configuration
JWT_SECRET=your-secret-key
JWT_TTL=3600

# Monitoring
CROSS_SERVICE_ENABLE_MONITORING=true
PROMETHEUS_GATEWAY_URL=http://localhost:9091
```

## 📚 Usage Examples

### REST API Usage

#### Health Check
```bash
curl -X GET http://localhost:8000/api/health
```

#### Publish Event
```bash
curl -X POST http://localhost:8000/api/events/publish \
  -H "Content-Type: application/json" \
  -H "X-Source-Service: user-service" \
  -d '{
    "event_type": "user.created",
    "event_data": {"user_id": 123, "email": "user@example.com"},
    "source_service": "user-service",
    "target_services": ["notification-service", "analytics-service"]
  }'
```

#### Cache Operations
```bash
# Set cache
curl -X POST http://localhost:8000/api/cache/set \
  -H "Content-Type: application/json" \
  -d '{
    "key": "user:123",
    "value": {"name": "John Doe", "email": "john@example.com"},
    "ttl": 3600,
    "tags": ["user", "profile"]
  }'

# Get cache
curl -X GET http://localhost:8000/api/cache/get/user:123

# Delete cache
curl -X DELETE http://localhost:8000/api/cache/delete/user:123
```

### RPC Usage

#### PHP Client Example
```php
// Include RPC helpers
$rpc = include 'services/shared/routes/rpc.php';

// Publish event via RPC
$response = publishEventRpc(
    'user.updated',
    ['user_id' => 123, 'changes' => ['email']],
    'user-service',
    ['notification-service']
);

// Cache operations via RPC
setCacheRpc('session:abc123', ['user_id' => 123], 1800);
$cached = getCacheRpc('session:abc123');

// Batch RPC call
$batchResponse = makeBatchRpcCall([
    [
        'jsonrpc' => '2.0',
        'method' => 'cache.set',
        'params' => ['key' => 'temp:1', 'value' => 'data1'],
        'id' => 1
    ],
    [
        'jsonrpc' => '2.0',
        'method' => 'events.publish',
        'params' => [
            'event_type' => 'cache.updated',
            'event_data' => ['key' => 'temp:1'],
            'source_service' => 'cache-service'
        ],
        'id' => 2
    ]
]);
```

#### JSON-RPC 2.0 Example
```json
{
  "jsonrpc": "2.0",
  "method": "events.publish",
  "params": {
    "event_type": "order.completed",
    "event_data": {
      "order_id": 456,
      "customer_id": 123,
      "total": 99.99
    },
    "source_service": "order-service",
    "target_services": ["payment-service", "inventory-service"]
  },
  "id": 1
}
```

### Procedure Development

#### Creating a Micro Procedure
```php
<?php

namespace Shared\Procedures\Micro;

use Shared\Core\BaseProcedure;

trait MyCustomProcedure
{
    public function myMethod(array $params, array $context = []): array
    {
        try {
            // Validate parameters
            $validation = $this->validateParams($params, [
                'required_field' => ['required' => true, 'type' => 'string'],
                'optional_field' => ['type' => 'int']
            ]);

            if (!$validation['success']) {
                return $this->errorResponse('Validation failed', $validation['errors']);
            }

            // Your business logic here
            $result = $this->performBusinessLogic($params);

            // Cache result if needed
            $this->cache('result_key', $result, 3600);

            // Record metrics
            $this->recordMetric('custom_operation', 1, ['status' => 'success']);

            // Log operation
            $this->log('info', 'Custom operation completed', [
                'params' => $params,
                'result_size' => count($result)
            ]);

            return $this->successResponse($result, 'Operation completed successfully');

        } catch (\Exception $e) {
            $this->log('error', 'Custom operation failed', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return $this->errorResponse('Operation failed: ' . $e->getMessage());
        }
    }

    private function performBusinessLogic(array $params): array
    {
        // Implement your business logic
        return ['processed' => true, 'data' => $params];
    }
}
```

#### Integrating the Procedure
```php
// In CrossServiceProcedure.php
use Shared\Procedures\Micro\MyCustomProcedure;

class CrossServiceProcedure extends BaseProcedure
{
    use MyCustomProcedure; // Add your trait

    private function registerProcedures(): void
    {
        // Register your procedure
        $this->engine->registerProcedure('custom', static::class, 'micro', [
            'description' => 'My custom operations',
            'methods' => ['myMethod']
        ]);
    }
}
```

## 🔧 Configuration Options

### Procedure Engine
```php
'procedure_engine' => [
    'timeout' => 30,                    // Default timeout in seconds
    'retry_attempts' => 3,              // Number of retry attempts
    'retry_delay' => 1000,              // Retry delay in milliseconds
    'enable_tracing' => true,           // Enable distributed tracing
    'enable_metrics' => true,           // Enable metrics collection
    'max_execution_time' => 300,        // Max time for macro procedures
]
```

### REST Handler
```php
'rest_handler' => [
    'enable_cors' => true,              // Enable CORS support
    'cors_origins' => ['*'],            // Allowed origins
    'cors_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'rate_limit' => 1000,               // Requests per minute
    'max_request_size' => 10485760,     // 10MB max request size
]
```

### Caching
```php
'caching' => [
    'default_driver' => 'redis',        // Default cache driver
    'default_ttl' => 3600,              // Default TTL in seconds
    'enable_compression' => true,       // Enable automatic compression
    'compression_threshold' => 1024,    // Compress if larger than 1KB
]
```

### Events
```php
'events' => [
    'default_driver' => 'redis',        // Event bus driver
    'enable_dead_letter_queue' => true, // Enable DLQ for failed events
    'max_retry_attempts' => 3,          // Max retry attempts
    'enable_event_replay' => true,      // Enable event replay capability
]
```

## 📊 Monitoring & Observability

### Health Checks
```bash
# System health
curl http://localhost:8000/api/health

# System information
curl http://localhost:8000/api/info

# Service registry
curl http://localhost:8000/api/services/registry
```

### Metrics
The system automatically collects metrics for:
- Procedure execution times
- Cache hit/miss rates
- Event publishing success/failure
- RPC call performance
- Error rates and types

### Logging
Structured logging with trace IDs for:
- All procedure executions
- Cache operations
- Event publishing
- RPC calls
- Errors and exceptions

## 🔒 Security Features

- **Authentication**: JWT token validation
- **Authorization**: Role-based access control
- **Rate Limiting**: Configurable request throttling
- **Input Validation**: Comprehensive parameter validation
- **Audit Logging**: Complete audit trails
- **IP Whitelisting**: Network-level access control

## 🚀 Performance Features

- **Connection Pooling**: Efficient database connections
- **Caching**: Multi-level caching with compression
- **Async Processing**: Non-blocking operations
- **Circuit Breakers**: Fault tolerance patterns
- **Load Balancing**: Service discovery and routing

## 🧪 Testing

### Unit Tests
```bash
# Run procedure tests
phpunit tests/Unit/Procedures/

# Run core component tests
phpunit tests/Unit/Core/
```

### Integration Tests
```bash
# Test cross-service communication
phpunit tests/Integration/CrossServiceTest.php

# Test event publishing
phpunit tests/Integration/EventPublishingTest.php
```

## 📈 Roadmap

### Phase 1 (Completed)
- ✅ Core architecture and procedure engine
- ✅ Event publishing micro procedure
- ✅ Cache management micro procedure
- ✅ REST and RPC handlers
- ✅ Configuration management

### Phase 2 (In Progress)
- 🔄 Validation micro procedure
- 🔄 Security micro procedure
- 🔄 Monitoring micro procedure
- 🔄 Database transaction micro procedure

### Phase 3 (Planned)
- 📋 Workflow orchestration macro procedure
- 📋 Communication macro procedure
- 📋 Testing framework
- 📋 Documentation generation

### Phase 4 (Future)
- 📋 Machine learning integration
- 📋 Advanced analytics
- 📋 Performance optimization
- 📋 Auto-scaling capabilities

## 🤝 Contributing

1. Follow the micro/macro procedure pattern
2. Include comprehensive validation
3. Add proper logging and metrics
4. Write unit and integration tests
5. Update documentation

## 📄 License

This cross-service infrastructure is part of the Laravel microservices platform.

## 🆘 Support

For issues and questions:
1. Check the health endpoints for system status
2. Review logs for detailed error information
3. Use the RPC `system.info` method for debugging
4. Monitor metrics for performance insights
