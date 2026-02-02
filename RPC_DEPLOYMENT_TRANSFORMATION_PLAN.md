# 🚀 RPC Deployment Transformation Plan
## Enterprise-Grade Migration from REST to JSON-RPC 2.0 with Laravel Octane

> **🎯 Strategic Objective**: Transform the Reverse Tender Platform from REST-based Guzzle HTTP communication to high-performance JSON-RPC 2.0 architecture with Laravel Octane persistent memory optimization.

---

## 📊 Executive Summary

### **Current State Analysis**
- **Architecture**: 8+ microservices using REST-based Guzzle HTTP calls
- **Performance Bottleneck**: Framework bootstrapping on every request
- **Communication Overhead**: Multiple HTTP roundtrips for inter-service calls
- **Error Handling**: Generic HTTP status codes with limited context

### **Target State Vision**
- **Protocol**: JSON-RPC 2.0 with `sajya/server` & `sajya/client`
- **Runtime**: Laravel Octane (FrankenPHP/Swoole) with persistent memory
- **Performance**: Sub-millisecond RPC execution with batching capabilities
- **Security**: Laravel Sanctum token-based service authentication
- **Observability**: Distributed tracing with Correlation IDs

### **Expected Performance Gains**
| **Metric** | **Current (REST)** | **Target (RPC + Octane)** | **Improvement** |
|------------|-------------------|---------------------------|-----------------|
| **Framework Boot** | Every Request | Once (Persistent) | **~90% reduction** |
| **Network Calls** | Multiple Roundtrips | Single Batch | **~70% reduction** |
| **Response Time** | 150-300ms | 50-100ms | **~60% improvement** |
| **Memory Usage** | Variable | Optimized | **~40% reduction** |
| **Error Context** | HTTP Status | RPC Error Codes | **Enhanced debugging** |

---

## 🏗️ Deployment Architecture Transformation

### **Phase 1: Infrastructure Foundation (Weeks 1-2)**

#### **1.1 Environment Preparation**
```yaml
# deployment/k8s/base/rpc-configmap.yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: rpc-config
data:
  OCTANE_SERVER: "frankenphp"
  OCTANE_RPC_HOST: "127.0.0.1"
  OCTANE_RPC_PORT: "6001"
  RPC_TIMEOUT: "2000"
  RPC_BATCH_SIZE: "10"
  CORRELATION_ID_HEADER: "X-Correlation-ID"
```

#### **1.2 Service Dependencies Update**
```dockerfile
# deployment/docker/Dockerfile.rpc-base
FROM php:8.2-fpm-alpine

# Install Octane dependencies
RUN apk add --no-cache \
    supervisor \
    nodejs \
    npm

# Install PHP extensions for Octane
RUN docker-php-ext-install \
    pcntl \
    sockets \
    opcache

# Install Sajya RPC libraries
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Octane configuration
COPY deployment/config/octane/ /app/config/octane/
COPY deployment/config/supervisor/ /etc/supervisor/conf.d/
```

#### **1.3 Kubernetes Service Mesh Updates**
```yaml
# deployment/k8s/base/rpc-services.yaml
apiVersion: v1
kind: Service
metadata:
  name: rpc-gateway
  labels:
    app: rpc-gateway
    tier: infrastructure
spec:
  selector:
    app: rpc-gateway
  ports:
  - name: http
    port: 8000
    targetPort: 8000
  - name: rpc
    port: 6001
    targetPort: 6001
  type: ClusterIP
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: rpc-gateway
spec:
  replicas: 3
  selector:
    matchLabels:
      app: rpc-gateway
  template:
    metadata:
      labels:
        app: rpc-gateway
    spec:
      containers:
      - name: rpc-gateway
        image: reversetender/rpc-gateway:latest
        ports:
        - containerPort: 8000
        - containerPort: 6001
        env:
        - name: OCTANE_SERVER
          value: "frankenphp"
        - name: OCTANE_WORKERS
          value: "4"
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
```

### **Phase 2: Service-by-Service Migration (Weeks 3-8)**

#### **2.1 Migration Priority Matrix**
| **Service** | **Priority** | **Complexity** | **Dependencies** | **Timeline** |
|-------------|--------------|----------------|------------------|--------------|
| **Analytics Service** | HIGH | LOW | None | Week 3 |
| **Notification Service** | HIGH | MEDIUM | User, Order | Week 4 |
| **User Service** | CRITICAL | HIGH | Auth, Profile | Week 5 |
| **Order Service** | CRITICAL | HIGH | User, Bidding, Payment | Week 6 |
| **Bidding Service** | CRITICAL | VERY HIGH | Order, User, WebSocket | Week 7 |
| **Payment Service** | CRITICAL | HIGH | Order, ZATCA | Week 8 |
| **VIN OCR Service** | MEDIUM | LOW | Order | Week 8 |
| **Auth Service** | CRITICAL | MEDIUM | All Services | Week 8 |

#### **2.2 Service Transformation Template**

**Example: User Service RPC Transformation**

```php
<?php
// services/user-service/app/RPC/Procedures/UserProcedure.php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use App\Services\UserService;
use Illuminate\Support\Facades\Validator;

class UserProcedure extends BaseProcedure
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Create a new user
     * 
     * @param array $params
     * @return array
     */
    public function create(array $params): array
    {
        $this->validate($params, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|unique:users',
            'type' => 'required|in:customer,merchant',
        ]);

        $user = $this->userService->createUser($params);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->type,
            'created_at' => $user->created_at->toISOString(),
        ];
    }

    /**
     * Get user profile
     * 
     * @param array $params
     * @return array
     */
    public function getProfile(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = $this->userService->getUserProfile($params['user_id']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile' => $user->profile?->toArray(),
            'verification_status' => $user->verification_status,
        ];
    }

    /**
     * Batch user operations
     * 
     * @param array $params
     * @return array
     */
    public function batchOperations(array $params): array
    {
        $this->validate($params, [
            'operations' => 'required|array',
            'operations.*.type' => 'required|in:create,update,delete',
            'operations.*.data' => 'required|array',
        ]);

        $results = [];
        foreach ($params['operations'] as $operation) {
            try {
                $result = match($operation['type']) {
                    'create' => $this->create($operation['data']),
                    'update' => $this->update($operation['data']),
                    'delete' => $this->delete($operation['data']),
                };
                $results[] = ['success' => true, 'data' => $result];
            } catch (\Exception $e) {
                $results[] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }
}
```

```php
<?php
// services/user-service/app/RPC/BaseProcedure.php

namespace App\RPC;

use Sajya\Server\Procedure;
use Illuminate\Support\Facades\Validator;
use Sajya\Server\Exceptions\RuntimeException;

abstract class BaseProcedure extends Procedure
{
    /**
     * Validate request parameters
     * 
     * @param array $data
     * @param array $rules
     * @throws RuntimeException
     */
    protected function validate(array $data, array $rules): void
    {
        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new RuntimeException(
                'Invalid parameters',
                -32602,
                $validator->errors()->toArray()
            );
        }
    }

    /**
     * Get correlation ID from request
     * 
     * @return string
     */
    protected function getCorrelationId(): string
    {
        return request()->header('X-Correlation-ID', uniqid('rpc_', true));
    }

    /**
     * Log RPC call for observability
     * 
     * @param string $method
     * @param array $params
     * @param mixed $result
     */
    protected function logRpcCall(string $method, array $params, mixed $result): void
    {
        logger()->info('RPC Call', [
            'correlation_id' => $this->getCorrelationId(),
            'method' => $method,
            'params_count' => count($params),
            'execution_time' => microtime(true) - LARAVEL_START,
            'memory_usage' => memory_get_peak_usage(true),
        ]);
    }
}
```

#### **2.3 RPC Client Configuration**

```php
<?php
// services/order-service/app/Providers/RpcServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Sajya\Client\Client;
use Illuminate\Support\Facades\Http;

class RpcServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // User Service RPC Client
        $this->app->singleton('UserRpc', function () {
            return new Client(
                Http::baseUrl(config('rpc.user_service.url'))
                    ->withToken(config('rpc.user_service.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'order-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.timeout', 2))
            );
        });

        // Payment Service RPC Client
        $this->app->singleton('PaymentRpc', function () {
            return new Client(
                Http::baseUrl(config('rpc.payment_service.url'))
                    ->withToken(config('rpc.payment_service.token'))
                    ->withHeaders([
                        'X-Service-Name' => 'order-service',
                        'X-Correlation-ID' => request()->header('X-Correlation-ID', uniqid('rpc_', true)),
                    ])
                    ->timeout(config('rpc.timeout', 2))
            );
        });
    }
}
```

### **Phase 3: Octane Optimization (Weeks 9-10)**

#### **3.1 Octane Configuration Enhancement**

```php
<?php
// services/user-service/config/octane.php

return [
    'server' => env('OCTANE_SERVER', 'frankenphp'),
    'https' => env('OCTANE_HTTPS', false),
    'workers' => env('OCTANE_WORKERS', 4),
    'task_workers' => env('OCTANE_TASK_WORKERS', 6),
    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),

    // RPC-specific configuration
    'rpc' => [
        'host' => env('OCTANE_RPC_HOST', '127.0.0.1'),
        'port' => env('OCTANE_RPC_PORT', 6001),
        'timeout' => env('OCTANE_RPC_TIMEOUT', 2000),
        'batch_size' => env('OCTANE_RPC_BATCH_SIZE', 10),
    ],

    // Warm-up procedures for better performance
    'warm' => [
        'procedures' => [
            \App\RPC\Procedures\UserProcedure::class,
            \App\RPC\Procedures\OrderProcedure::class,
            \App\RPC\Procedures\BiddingProcedure::class,
        ],
    ],

    // Memory optimization
    'cache' => [
        'procedures' => true,
        'routes' => true,
        'config' => true,
    ],

    // Concurrency settings
    'concurrency' => [
        'max_concurrent_requests' => env('OCTANE_MAX_CONCURRENT', 100),
        'worker_pool_size' => env('OCTANE_WORKER_POOL', 8),
    ],
];
```

#### **3.2 Performance Optimization Examples**

```php
<?php
// services/order-service/app/RPC/Procedures/OrderProcedure.php

use Laravel\Octane\Facades\Octane;

class OrderProcedure extends BaseProcedure
{
    /**
     * Get order dashboard with concurrent data fetching
     */
    public function getDashboard(array $params): array
    {
        $this->validate($params, [
            'user_id' => 'required|integer',
        ]);

        // Concurrent RPC calls using Octane
        [$userProfile, $recentOrders, $notifications, $analytics] = Octane::concurrently([
            fn() => app('UserRpc')->execute('User@getProfile', ['user_id' => $params['user_id']]),
            fn() => $this->getRecentOrders($params['user_id']),
            fn() => app('NotificationRpc')->execute('Notification@getUnread', ['user_id' => $params['user_id']]),
            fn() => app('AnalyticsRpc')->execute('Analytics@getUserStats', ['user_id' => $params['user_id']]),
        ]);

        return [
            'user' => $userProfile->getResult(),
            'recent_orders' => $recentOrders,
            'notifications' => $notifications->getResult(),
            'analytics' => $analytics->getResult(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Batch order processing
     */
    public function batchProcess(array $params): array
    {
        $this->validate($params, [
            'orders' => 'required|array|max:50',
            'orders.*.id' => 'required|integer',
            'orders.*.action' => 'required|in:approve,reject,cancel',
        ]);

        // Process orders in batches for optimal performance
        $batches = array_chunk($params['orders'], 10);
        $results = [];

        foreach ($batches as $batch) {
            $batchResults = Octane::concurrently(
                array_map(fn($order) => fn() => $this->processOrder($order), $batch)
            );
            $results = array_merge($results, $batchResults);
        }

        return [
            'processed' => count($results),
            'results' => $results,
            'processing_time' => microtime(true) - LARAVEL_START,
        ];
    }
}
```

### **Phase 4: Monitoring & Observability (Week 11)**

#### **4.1 RPC Monitoring Dashboard**

```yaml
# deployment/monitoring/grafana/dashboards/rpc-performance.json
{
  "dashboard": {
    "title": "RPC Performance Dashboard",
    "panels": [
      {
        "title": "RPC Response Times",
        "type": "graph",
        "targets": [
          {
            "expr": "histogram_quantile(0.95, rate(rpc_request_duration_seconds_bucket[5m]))",
            "legendFormat": "95th percentile"
          },
          {
            "expr": "histogram_quantile(0.50, rate(rpc_request_duration_seconds_bucket[5m]))",
            "legendFormat": "50th percentile"
          }
        ]
      },
      {
        "title": "RPC Throughput",
        "type": "graph",
        "targets": [
          {
            "expr": "rate(rpc_requests_total[5m])",
            "legendFormat": "Requests/sec"
          }
        ]
      },
      {
        "title": "RPC Error Rate",
        "type": "singlestat",
        "targets": [
          {
            "expr": "rate(rpc_errors_total[5m]) / rate(rpc_requests_total[5m]) * 100",
            "legendFormat": "Error Rate %"
          }
        ]
      }
    ]
  }
}
```

#### **4.2 Health Check Implementation**

```php
<?php
// services/shared/app/Http/Controllers/RpcHealthController.php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Sajya\Client\Client;

class RpcHealthController extends Controller
{
    public function check(): JsonResponse
    {
        $services = config('rpc.services');
        $results = [];

        foreach ($services as $serviceName => $config) {
            try {
                $client = new Client($config['url']);
                $response = $client->execute('Health@ping', []);
                
                $results[$serviceName] = [
                    'status' => 'healthy',
                    'response_time' => $response->getExecutionTime(),
                    'last_check' => now()->toISOString(),
                ];
            } catch (\Exception $e) {
                $results[$serviceName] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'last_check' => now()->toISOString(),
                ];
            }
        }

        $overallStatus = collect($results)->every(fn($result) => $result['status'] === 'healthy')
            ? 'healthy' 
            : 'degraded';

        return response()->json([
            'status' => $overallStatus,
            'services' => $results,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
```

---

## 🚀 Deployment Execution Strategy

### **Pre-Migration Checklist**
- [ ] **Environment Validation**: All services running PHP 8.2+ and Laravel 12
- [ ] **Dependency Installation**: Sajya packages installed across all services
- [ ] **Configuration Review**: RPC endpoints and authentication tokens configured
- [ ] **Testing Environment**: Staging environment with RPC setup completed
- [ ] **Monitoring Setup**: Grafana dashboards and alerts configured
- [ ] **Rollback Plan**: Documented rollback procedures for each service
- [ ] **Team Training**: Development team trained on RPC procedures and debugging

### **Migration Execution Timeline**

#### **Week 1-2: Foundation**
- Deploy RPC infrastructure components
- Update Kubernetes configurations
- Set up monitoring and alerting
- Configure service mesh for RPC communication

#### **Week 3-8: Service Migration**
- Migrate services according to priority matrix
- Implement dual-run strategy for validation
- Monitor performance metrics during migration
- Address any issues or performance bottlenecks

#### **Week 9-10: Optimization**
- Enable Octane concurrency features
- Implement batch processing where applicable
- Fine-tune performance parameters
- Optimize memory usage and caching

#### **Week 11: Validation & Cleanup**
- Comprehensive performance testing
- Remove old REST endpoints
- Update documentation
- Conduct team retrospective

### **Success Metrics**
- **Performance**: 60% improvement in response times
- **Reliability**: 99.9% RPC call success rate
- **Scalability**: Handle 2x current traffic with same resources
- **Maintainability**: Reduced debugging time by 50%
- **Developer Experience**: Improved type safety and error handling

### **Risk Mitigation**
- **Circuit Breaker**: Automatic fallback to REST for critical failures
- **Gradual Rollout**: Service-by-service migration with validation
- **Performance Monitoring**: Real-time alerts for performance degradation
- **Rollback Capability**: Quick rollback to previous version if needed

---

## 📈 Expected Business Impact

### **Technical Benefits**
- **Performance**: 60% faster inter-service communication
- **Scalability**: Handle 2x traffic with same infrastructure
- **Reliability**: Improved error handling and debugging
- **Maintainability**: Type-safe procedure contracts

### **Business Benefits**
- **Cost Reduction**: 40% reduction in infrastructure costs
- **User Experience**: Faster page loads and real-time updates
- **Developer Productivity**: Reduced debugging and development time
- **System Reliability**: Improved uptime and error recovery

### **Long-term Strategic Value**
- **Future-Proof Architecture**: Modern RPC-based microservices
- **Competitive Advantage**: Superior performance in automotive marketplace
- **Scalability Foundation**: Ready for 10x growth in user base
- **Technology Leadership**: Cutting-edge Laravel Octane implementation

---

*This deployment transformation plan represents a strategic evolution toward high-performance, scalable microservices architecture optimized for the Saudi Arabian automotive parts marketplace.*
