# Multi-Service Deployment Architecture

## Overview

This document defines the deployment architecture for the notification system microservices, designed to be compatible with Laravel Cloud, Forge, and Vapor platforms. The architecture prioritizes flexibility, maintainability, and platform portability while ensuring optimal performance and cost efficiency.

## Architecture Principles

### Core Design Principles

1. **Platform Agnostic**: Architecture works across Cloud, Forge, and Vapor
2. **Service Independence**: Services can be deployed and scaled independently
3. **Communication Flexibility**: Support for HTTP, message queues, and direct database access
4. **Environment Parity**: Consistent behavior across development, staging, and production
5. **Operational Simplicity**: Minimize operational complexity while maintaining flexibility

### Service Boundaries

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           Application Layer                                 │
├─────────────────────────────────────┬───────────────────────────────────────┤
│            Shared Service           │         Notification Service          │
│                                     │                                       │
│  ┌─────────────────────────────┐    │  ┌─────────────────────────────────┐  │
│  │     Communication Layer     │    │  │        Business Logic          │  │
│  │                             │    │  │                                 │  │
│  │  • NotificationProcedure    │◄───┼──┤  • NotificationFactory          │  │
│  │  • RPC Client               │    │  │  • Builders (Email, SMS, etc.) │  │
│  │  • Error Handling           │    │  │  • Templates                    │  │
│  │  • Correlation Tracking     │    │  │  • Channel Implementations     │  │
│  └─────────────────────────────┘    │  └─────────────────────────────────┘  │
└─────────────────────────────────────┴───────────────────────────────────────┘
```

## Service Communication Architecture

### Communication Patterns

The architecture supports multiple communication patterns based on deployment platform and requirements:

#### 1. HTTP/HTTPS Communication (Primary)

**Use Case**: All platforms, synchronous operations  
**Implementation**: RESTful API between services  

```php
// Shared Service -> Notification Service
POST https://notification-service.internal/api/notifications/email
Content-Type: application/json
Authorization: Bearer {service_token}
X-Correlation-ID: {trace_id}

{
    "to": "user@example.com",
    "template": "welcome_email",
    "data": {"name": "John Doe"},
    "context": {
        "trace_id": "trace_123",
        "user_id": "user_456"
    }
}
```

**Platform-Specific URLs**:
- **Laravel Cloud**: `https://notification-service.internal.cloud.laravel.com`
- **Laravel Forge**: `https://notification.yourdomain.com` or `http://10.0.0.2:8080`
- **Laravel Vapor**: `https://api-gateway-url.amazonaws.com/notification`

#### 2. Message Queue Communication (Asynchronous)

**Use Case**: High-volume operations, decoupled processing  
**Implementation**: Redis/SQS queues for async communication  

```php
// Queue-based communication
Queue::push('ProcessNotificationJob', [
    'method' => 'sendEmail',
    'params' => [...],
    'context' => [...]
]);
```

#### 3. Database-Level Communication (Fallback)

**Use Case**: Platform limitations, high-performance requirements  
**Implementation**: Shared database with service-specific schemas  

### Service Discovery Strategy

#### Environment-Based Discovery

```php
// config/services.php
return [
    'notification_service' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://localhost:8080'),
        'timeout' => env('NOTIFICATION_SERVICE_TIMEOUT', 30),
        'retry_attempts' => env('NOTIFICATION_SERVICE_RETRIES', 3),
        'api_key' => env('NOTIFICATION_SERVICE_API_KEY'),
    ],
];
```

#### Platform-Specific Configuration

**Laravel Cloud**:
```env
NOTIFICATION_SERVICE_URL=https://notification-service.internal.cloud.laravel.com
NOTIFICATION_SERVICE_API_KEY=cloud_internal_key
```

**Laravel Forge**:
```env
NOTIFICATION_SERVICE_URL=https://notification.yourdomain.com
# OR for internal communication
NOTIFICATION_SERVICE_URL=http://10.0.0.2:8080
NOTIFICATION_SERVICE_API_KEY=forge_api_key_123
```

**Laravel Vapor**:
```env
NOTIFICATION_SERVICE_URL=https://xyz123.execute-api.us-east-1.amazonaws.com/production
NOTIFICATION_SERVICE_API_KEY=vapor_lambda_key
```

## Database Architecture

### Database Strategy Options

The architecture supports multiple database strategies based on platform capabilities and requirements:

#### Option 1: Shared Database with Service Schemas

**Best For**: Laravel Cloud, Laravel Forge  
**Complexity**: Low  
**Performance**: High  

```sql
-- Database: notification_system

-- Shared tables
CREATE TABLE users (
    id BIGINT PRIMARY KEY,
    email VARCHAR(255),
    created_at TIMESTAMP
);

-- Notification service schema
CREATE TABLE notification_templates (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),
    channel VARCHAR(50),
    content TEXT,
    created_at TIMESTAMP
);

CREATE TABLE notification_logs (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    template_id BIGINT,
    channel VARCHAR(50),
    status VARCHAR(50),
    sent_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Shared service schema (minimal)
CREATE TABLE api_logs (
    id BIGINT PRIMARY KEY,
    service VARCHAR(50),
    method VARCHAR(100),
    trace_id VARCHAR(255),
    created_at TIMESTAMP
);
```

#### Option 2: Separate Databases per Service

**Best For**: High isolation requirements, different scaling needs  
**Complexity**: Medium  
**Performance**: Medium  

```
┌─────────────────┐    ┌─────────────────┐
│ Shared Service  │    │ Notification    │
│ Database        │    │ Service         │
│                 │    │ Database        │
│ • api_logs      │    │ • templates     │
│ • user_cache    │    │ • logs          │
│ • sessions      │    │ • subscriptions │
└─────────────────┘    └─────────────────┘
```

#### Option 3: Hybrid Approach

**Best For**: Complex requirements, gradual migration  
**Complexity**: High  
**Performance**: Variable  

```
┌─────────────────────────────────────┐
│         Shared Database             │
│ • users                             │
│ • shared_configurations             │
└─────────────────────────────────────┘
                  │
    ┌─────────────┴─────────────┐
    │                           │
┌───▼─────────────┐    ┌────────▼──────────┐
│ Shared Service  │    │ Notification      │
│ Database        │    │ Service Database  │
│ • api_logs      │    │ • templates       │
│ • cache         │    │ • delivery_logs   │
└─────────────────┘    └───────────────────┘
```

### Database Connection Management

#### Connection Configuration

```php
// config/database.php
'connections' => [
    'shared' => [
        'driver' => 'mysql',
        'host' => env('SHARED_DB_HOST', '127.0.0.1'),
        'database' => env('SHARED_DB_DATABASE', 'shared_service'),
        'username' => env('SHARED_DB_USERNAME', 'forge'),
        'password' => env('SHARED_DB_PASSWORD', ''),
    ],
    
    'notification' => [
        'driver' => 'mysql',
        'host' => env('NOTIFICATION_DB_HOST', '127.0.0.1'),
        'database' => env('NOTIFICATION_DB_DATABASE', 'notification_service'),
        'username' => env('NOTIFICATION_DB_USERNAME', 'forge'),
        'password' => env('NOTIFICATION_DB_PASSWORD', ''),
    ],
],
```

#### Platform-Specific Database Setup

**Laravel Cloud**:
```env
# Managed database with automatic scaling
SHARED_DB_HOST=shared-db.cloud.laravel.com
NOTIFICATION_DB_HOST=notification-db.cloud.laravel.com
```

**Laravel Forge**:
```env
# Self-managed or RDS
SHARED_DB_HOST=10.0.0.3
NOTIFICATION_DB_HOST=10.0.0.3  # Same server, different databases
# OR
NOTIFICATION_DB_HOST=10.0.0.4  # Separate database server
```

**Laravel Vapor**:
```env
# RDS/Aurora with Lambda integration
SHARED_DB_HOST=shared-cluster.cluster-xyz.us-east-1.rds.amazonaws.com
NOTIFICATION_DB_HOST=notification-cluster.cluster-abc.us-east-1.rds.amazonaws.com
```

## RPC Client Implementation

### HTTP-Based RPC Client

```php
<?php

namespace Shared\Core;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationRpcClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private int $retryAttempts;

    public function __construct()
    {
        $this->baseUrl = config('services.notification_service.url');
        $this->apiKey = config('services.notification_service.api_key');
        $this->timeout = config('services.notification_service.timeout', 30);
        $this->retryAttempts = config('services.notification_service.retry_attempts', 3);
    }

    public function call(string $method, array $params, array $context = []): array
    {
        $endpoint = $this->buildEndpoint($method);
        $payload = $this->buildPayload($method, $params, $context);
        
        try {
            $response = $this->makeRequest($endpoint, $payload);
            return $this->handleResponse($response, $method, $context);
        } catch (\Exception $e) {
            return $this->handleError($e, $method, $context);
        }
    }

    private function buildEndpoint(string $method): string
    {
        $endpoints = [
            'sendEmail' => '/api/notifications/email',
            'sendSms' => '/api/notifications/sms',
            'sendPushNotification' => '/api/notifications/push',
            'getNotificationStatus' => '/api/notifications/status',
            'manageSubscriptions' => '/api/subscriptions',
            'sendWhatsApp' => '/api/notifications/whatsapp',
            'sendTelegram' => '/api/notifications/telegram',
            'sendMultiChannel' => '/api/notifications/multi-channel',
            'sendBulkNotification' => '/api/notifications/bulk',
            'scheduleNotification' => '/api/notifications/schedule',
            'cancelNotification' => '/api/notifications/cancel',
        ];

        return $this->baseUrl . ($endpoints[$method] ?? '/api/notifications/generic');
    }

    private function buildPayload(string $method, array $params, array $context): array
    {
        return [
            'method' => $method,
            'params' => $params,
            'context' => array_merge($context, [
                'timestamp' => now()->toISOString(),
                'source_service' => 'shared-service',
                'trace_id' => $context['trace_id'] ?? $this->generateTraceId(),
            ]),
        ];
    }

    private function makeRequest(string $endpoint, array $payload): Response
    {
        return Http::timeout($this->timeout)
            ->retry($this->retryAttempts, 1000)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Correlation-ID' => $payload['context']['trace_id'],
                'X-Source-Service' => 'shared-service',
            ])
            ->post($endpoint, $payload);
    }

    private function handleResponse(Response $response, string $method, array $context): array
    {
        if ($response->successful()) {
            $data = $response->json();
            
            // Log successful request for monitoring
            Log::info('Notification RPC call successful', [
                'method' => $method,
                'trace_id' => $context['trace_id'] ?? null,
                'response_time' => $response->handlerStats()['total_time'] ?? null,
            ]);
            
            return $data;
        }

        // Handle HTTP errors
        throw new \Exception(
            "HTTP {$response->status()}: " . $response->body(),
            $response->status()
        );
    }

    private function handleError(\Exception $e, string $method, array $context): array
    {
        Log::error('Notification RPC call failed', [
            'method' => $method,
            'error' => $e->getMessage(),
            'trace_id' => $context['trace_id'] ?? null,
            'code' => $e->getCode(),
        ]);

        return [
            'success' => false,
            'message' => 'Notification service communication failed',
            'details' => [
                'method' => $method,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ],
            'timestamp' => now()->toISOString(),
            'trace_id' => $context['trace_id'] ?? null,
        ];
    }

    private function generateTraceId(): string
    {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xffffffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffffffffffff)
        );
    }
}
```

### Queue-Based RPC Client (Alternative)

```php
<?php

namespace Shared\Core;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;

class NotificationQueueClient
{
    public function call(string $method, array $params, array $context = []): array
    {
        $jobId = $this->generateJobId();
        $cacheKey = "rpc_response_{$jobId}";
        
        // Dispatch job to notification service queue
        Queue::push('ProcessNotificationRpcJob', [
            'job_id' => $jobId,
            'method' => $method,
            'params' => $params,
            'context' => $context,
        ]);
        
        // Wait for response (with timeout)
        $timeout = 30; // seconds
        $startTime = time();
        
        while (time() - $startTime < $timeout) {
            $response = Cache::get($cacheKey);
            if ($response !== null) {
                Cache::forget($cacheKey);
                return $response;
            }
            usleep(100000); // 100ms
        }
        
        return [
            'success' => false,
            'message' => 'RPC call timeout',
            'details' => ['job_id' => $jobId, 'method' => $method],
        ];
    }
    
    private function generateJobId(): string
    {
        return 'rpc_' . uniqid() . '_' . time();
    }
}
```

## Environment Configuration Management

### Configuration Strategy

#### Centralized Configuration

```php
// config/microservices.php
return [
    'services' => [
        'notification' => [
            'url' => env('NOTIFICATION_SERVICE_URL'),
            'api_key' => env('NOTIFICATION_SERVICE_API_KEY'),
            'timeout' => env('NOTIFICATION_SERVICE_TIMEOUT', 30),
            'retry_attempts' => env('NOTIFICATION_SERVICE_RETRIES', 3),
            'health_check_url' => env('NOTIFICATION_SERVICE_HEALTH_URL'),
        ],
    ],
    
    'communication' => [
        'default_method' => env('SERVICE_COMMUNICATION_METHOD', 'http'),
        'fallback_method' => env('SERVICE_COMMUNICATION_FALLBACK', 'queue'),
        'circuit_breaker' => [
            'failure_threshold' => env('CIRCUIT_BREAKER_THRESHOLD', 5),
            'timeout' => env('CIRCUIT_BREAKER_TIMEOUT', 60),
        ],
    ],
    
    'monitoring' => [
        'trace_requests' => env('TRACE_SERVICE_REQUESTS', true),
        'log_level' => env('SERVICE_LOG_LEVEL', 'info'),
        'metrics_enabled' => env('SERVICE_METRICS_ENABLED', true),
    ],
];
```

#### Platform-Specific Environment Files

**Laravel Cloud (.env.cloud)**:
```env
# Service URLs
NOTIFICATION_SERVICE_URL=https://notification-service.internal.cloud.laravel.com
NOTIFICATION_SERVICE_API_KEY=${CLOUD_INTERNAL_API_KEY}

# Database
DB_CONNECTION=mysql
DB_HOST=${CLOUD_DB_HOST}
DB_DATABASE=${CLOUD_DB_NAME}
DB_USERNAME=${CLOUD_DB_USER}
DB_PASSWORD=${CLOUD_DB_PASSWORD}

# Cache
REDIS_HOST=${CLOUD_REDIS_HOST}
REDIS_PASSWORD=${CLOUD_REDIS_PASSWORD}

# Communication
SERVICE_COMMUNICATION_METHOD=http
CIRCUIT_BREAKER_THRESHOLD=10
```

**Laravel Forge (.env.forge)**:
```env
# Service URLs
NOTIFICATION_SERVICE_URL=https://notification.yourdomain.com
NOTIFICATION_SERVICE_API_KEY=forge_secure_api_key_123

# Database
DB_CONNECTION=mysql
DB_HOST=10.0.0.3
DB_DATABASE=notification_system
DB_USERNAME=forge
DB_PASSWORD=secure_password

# Cache
REDIS_HOST=10.0.0.3
REDIS_PASSWORD=redis_password

# Communication
SERVICE_COMMUNICATION_METHOD=http
CIRCUIT_BREAKER_THRESHOLD=5
```

**Laravel Vapor (.env.vapor)**:
```env
# Service URLs
NOTIFICATION_SERVICE_URL=https://xyz123.execute-api.us-east-1.amazonaws.com/production
NOTIFICATION_SERVICE_API_KEY=${VAPOR_API_GATEWAY_KEY}

# Database
DB_CONNECTION=mysql
DB_HOST=${VAPOR_DB_HOST}
DB_DATABASE=${VAPOR_DB_NAME}
DB_USERNAME=${VAPOR_DB_USER}
DB_PASSWORD=${VAPOR_DB_PASSWORD}

# Cache
REDIS_HOST=${VAPOR_REDIS_HOST}

# Communication
SERVICE_COMMUNICATION_METHOD=http
CIRCUIT_BREAKER_THRESHOLD=15
NOTIFICATION_SERVICE_TIMEOUT=45
```

## Monitoring and Observability

### Distributed Tracing

```php
<?php

namespace Shared\Core;

class DistributedTracing
{
    public static function startTrace(string $operation, array $context = []): string
    {
        $traceId = $context['trace_id'] ?? self::generateTraceId();
        
        Log::info('Trace started', [
            'trace_id' => $traceId,
            'operation' => $operation,
            'service' => 'shared-service',
            'timestamp' => now()->toISOString(),
            'context' => $context,
        ]);
        
        return $traceId;
    }
    
    public static function logSpan(string $traceId, string $span, array $data = []): void
    {
        Log::info('Trace span', [
            'trace_id' => $traceId,
            'span' => $span,
            'service' => 'shared-service',
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ]);
    }
    
    public static function endTrace(string $traceId, bool $success = true, array $result = []): void
    {
        Log::info('Trace completed', [
            'trace_id' => $traceId,
            'success' => $success,
            'service' => 'shared-service',
            'timestamp' => now()->toISOString(),
            'result' => $result,
        ]);
    }
    
    private static function generateTraceId(): string
    {
        return sprintf(
            '%08x-%04x-%04x-%04x-%012x',
            mt_rand(0, 0xffffffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffffffffffff)
        );
    }
}
```

### Health Check Implementation

```php
<?php

namespace Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Shared\Core\NotificationRpcClient;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'notification_service' => $this->checkNotificationService(),
        ];
        
        $healthy = collect($checks)->every(fn($check) => $check['status'] === 'ok');
        
        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'service' => 'shared-service',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
    
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function checkCache(): array
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            return ['status' => $value === 'ok' ? 'ok' : 'error'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    private function checkNotificationService(): array
    {
        try {
            $client = new NotificationRpcClient();
            $response = Http::timeout(5)->get(
                config('services.notification_service.health_check_url', 
                       config('services.notification_service.url') . '/health')
            );
            
            return [
                'status' => $response->successful() ? 'ok' : 'error',
                'response_time' => $response->handlerStats()['total_time'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
```

## Deployment Strategies

### Platform-Specific Deployment Approaches

#### Laravel Cloud Deployment

```yaml
# cloud.yml (hypothetical configuration)
name: notification-system
services:
  shared-service:
    type: web
    php: 8.3
    build:
      - composer install --optimize-autoloader --no-dev
      - php artisan config:cache
      - php artisan route:cache
      - php artisan view:cache
    environment:
      - NOTIFICATION_SERVICE_URL=https://notification-service.internal.cloud.laravel.com
    
  notification-service:
    type: web
    php: 8.3
    build:
      - composer install --optimize-autoloader --no-dev
      - php artisan config:cache
      - php artisan route:cache
      - php artisan view:cache
    environment:
      - APP_ENV=production

databases:
  - name: notification-system
    engine: mysql
    version: 8.0
```

#### Laravel Forge Deployment

```bash
#!/bin/bash
# Forge deployment script for shared service

cd /home/forge/shared-service

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and cache configurations
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
php artisan queue:restart

# Restart PHP-FPM
sudo service php8.3-fpm reload

# Health check
curl -f http://localhost/health || exit 1

echo "Shared service deployment completed successfully"
```

#### Laravel Vapor Deployment

```yaml
# vapor.yml for shared service
id: 12345
name: shared-service
environments:
  production:
    memory: 1024
    timeout: 30
    runtime: php-8.3
    variables:
      NOTIFICATION_SERVICE_URL: ${NOTIFICATION_SERVICE_URL}
      NOTIFICATION_SERVICE_API_KEY: ${NOTIFICATION_SERVICE_API_KEY}
    build:
      - 'composer install --no-dev --optimize-autoloader'
      - 'php artisan config:cache'
      - 'php artisan route:cache'
      - 'php artisan view:cache'
```

## Security Considerations

### Service-to-Service Authentication

```php
<?php

namespace Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ServiceAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('Authorization');
        $expectedKey = 'Bearer ' . config('services.api_keys.notification_service');
        
        if ($apiKey !== $expectedKey) {
            return response()->json([
                'error' => 'Unauthorized service access',
                'timestamp' => now()->toISOString(),
            ], 401);
        }
        
        // Add service context to request
        $request->attributes->set('service_name', 'notification-service');
        $request->attributes->set('authenticated_service', true);
        
        return $next($request);
    }
}
```

### Network Security

**Laravel Cloud**: Automatic internal network isolation  
**Laravel Forge**: VPC configuration with security groups  
**Laravel Vapor**: Lambda security groups and VPC configuration  

## Performance Optimization

### Connection Pooling

```php
<?php

namespace Shared\Core;

class ConnectionPool
{
    private static array $connections = [];
    
    public static function getHttpClient(string $service): \Illuminate\Http\Client\PendingRequest
    {
        if (!isset(self::$connections[$service])) {
            self::$connections[$service] = Http::withOptions([
                'pool' => true,
                'max_connections' => 10,
                'max_idle_connections' => 5,
            ]);
        }
        
        return self::$connections[$service];
    }
}
```

### Caching Strategy

```php
<?php

namespace Shared\Core;

class ServiceCache
{
    public static function remember(string $key, int $ttl, callable $callback)
    {
        return Cache::remember("service_cache:{$key}", $ttl, $callback);
    }
    
    public static function invalidateService(string $service): void
    {
        $pattern = "service_cache:{$service}:*";
        // Implementation depends on cache driver
        Cache::flush(); // Simplified for example
    }
}
```

## Conclusion

This multi-service deployment architecture provides:

1. **Platform Flexibility**: Works across Cloud, Forge, and Vapor
2. **Communication Options**: HTTP, queue-based, and database fallbacks
3. **Monitoring Integration**: Distributed tracing and health checks
4. **Security**: Service authentication and network isolation
5. **Performance**: Connection pooling and caching strategies
6. **Operational Simplicity**: Clear deployment and configuration patterns

The architecture prioritizes Laravel Forge for initial implementation due to its maturity and flexibility, while maintaining compatibility with Cloud and Vapor for future migration or hybrid deployments.

---

**Document Version**: 1.0  
**Last Updated**: February 14, 2026  
**Authors**: Codegen AI, AbdElrhman ElHodaky  
**Status**: Complete - Ready for Phase 2 implementation
