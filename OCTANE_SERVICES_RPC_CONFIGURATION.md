# 🔥 Octane Services RPC Configuration Guide
## Laravel Octane + JSON-RPC 2.0 Implementation for Reverse Tender Platform

> **🎯 Objective**: Comprehensive configuration guide for implementing Laravel Octane with JSON-RPC across all microservices in the Reverse Tender Platform.

---

## 📋 Service Configuration Matrix

### **Current Octane-Enabled Services**
Based on the analysis of existing Octane configurations found in the codebase:

| **Service** | **Octane Config** | **RPC Port** | **Server Type** | **Status** |
|-------------|-------------------|--------------|-----------------|------------|
| **User Service** | ✅ Configured | 6001 | FrankenPHP | Ready for RPC |
| **Notification Service** | ✅ Configured | 6002 | FrankenPHP | Ready for RPC |
| **Bidding Service** | ✅ Configured | 6003 | FrankenPHP | Ready for RPC |
| **Payment Service** | ✅ Configured | 6004 | FrankenPHP | Ready for RPC |
| **Order Service** | ⚠️ Needs Config | 6005 | FrankenPHP | Requires Setup |
| **Analytics Service** | ⚠️ Needs Config | 6006 | FrankenPHP | Requires Setup |
| **VIN OCR Service** | ⚠️ Needs Config | 6007 | FrankenPHP | Requires Setup |
| **Auth Service** | ⚠️ Needs Config | 6008 | FrankenPHP | Requires Setup |

---

## 🔧 Service-Specific Configurations

### **1. User Service - RPC Configuration**

```php
<?php
// services/user-service/config/rpc.php

return [
    'server' => [
        'host' => env('RPC_HOST', '0.0.0.0'),
        'port' => env('RPC_PORT', 6001),
        'timeout' => env('RPC_TIMEOUT', 30),
    ],
    
    'client' => [
        'timeout' => env('RPC_CLIENT_TIMEOUT', 5),
        'retry_attempts' => env('RPC_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('RPC_RETRY_DELAY', 100), // milliseconds
    ],
    
    'services' => [
        'auth' => [
            'url' => env('AUTH_RPC_URL', 'http://auth-service:6008/rpc'),
            'token' => env('AUTH_RPC_TOKEN'),
        ],
        'notification' => [
            'url' => env('NOTIFICATION_RPC_URL', 'http://notification-service:6002/rpc'),
            'token' => env('NOTIFICATION_RPC_TOKEN'),
        ],
        'analytics' => [
            'url' => env('ANALYTICS_RPC_URL', 'http://analytics-service:6006/rpc'),
            'token' => env('ANALYTICS_RPC_TOKEN'),
        ],
    ],
    
    'procedures' => [
        'user' => [
            'create' => \App\RPC\Procedures\UserProcedure::class . '@create',
            'update' => \App\RPC\Procedures\UserProcedure::class . '@update',
            'delete' => \App\RPC\Procedures\UserProcedure::class . '@delete',
            'getProfile' => \App\RPC\Procedures\UserProcedure::class . '@getProfile',
            'batchOperations' => \App\RPC\Procedures\UserProcedure::class . '@batchOperations',
        ],
        'profile' => [
            'update' => \App\RPC\Procedures\ProfileProcedure::class . '@update',
            'getDetails' => \App\RPC\Procedures\ProfileProcedure::class . '@getDetails',
            'uploadAvatar' => \App\RPC\Procedures\ProfileProcedure::class . '@uploadAvatar',
        ],
    ],
];
```

```php
<?php
// services/user-service/routes/rpc.php

use Sajya\Server\Route;
use App\RPC\Procedures\UserProcedure;
use App\RPC\Procedures\ProfileProcedure;
use App\RPC\Procedures\HealthProcedure;

Route::rpc('/', [
    UserProcedure::class,
    ProfileProcedure::class,
    HealthProcedure::class,
])->middleware(['auth:sanctum', 'rpc.correlation']);
```

### **2. Notification Service - RPC Configuration**

```php
<?php
// services/notification-service/config/rpc.php

return [
    'server' => [
        'host' => env('RPC_HOST', '0.0.0.0'),
        'port' => env('RPC_PORT', 6002),
        'timeout' => env('RPC_TIMEOUT', 30),
    ],
    
    'channels' => [
        'email' => [
            'driver' => 'sendgrid',
            'batch_size' => env('EMAIL_BATCH_SIZE', 100),
            'rate_limit' => env('EMAIL_RATE_LIMIT', 1000), // per hour
        ],
        'sms' => [
            'driver' => 'twilio',
            'batch_size' => env('SMS_BATCH_SIZE', 50),
            'rate_limit' => env('SMS_RATE_LIMIT', 500), // per hour
        ],
        'push' => [
            'driver' => 'fcm',
            'batch_size' => env('PUSH_BATCH_SIZE', 1000),
            'rate_limit' => env('PUSH_RATE_LIMIT', 10000), // per hour
        ],
        'whatsapp' => [
            'driver' => 'twilio',
            'batch_size' => env('WHATSAPP_BATCH_SIZE', 25),
            'rate_limit' => env('WHATSAPP_RATE_LIMIT', 250), // per hour
        ],
    ],
    
    'procedures' => [
        'notification' => [
            'send' => \App\RPC\Procedures\NotificationProcedure::class . '@send',
            'sendBatch' => \App\RPC\Procedures\NotificationProcedure::class . '@sendBatch',
            'getUnread' => \App\RPC\Procedures\NotificationProcedure::class . '@getUnread',
            'markAsRead' => \App\RPC\Procedures\NotificationProcedure::class . '@markAsRead',
            'getHistory' => \App\RPC\Procedures\NotificationProcedure::class . '@getHistory',
        ],
        'template' => [
            'create' => \App\RPC\Procedures\TemplateProcedure::class . '@create',
            'update' => \App\RPC\Procedures\TemplateProcedure::class . '@update',
            'render' => \App\RPC\Procedures\TemplateProcedure::class . '@render',
        ],
    ],
];
```

### **3. Bidding Service - RPC Configuration**

```php
<?php
// services/bidding-service/config/rpc.php

return [
    'server' => [
        'host' => env('RPC_HOST', '0.0.0.0'),
        'port' => env('RPC_PORT', 6003),
        'timeout' => env('RPC_TIMEOUT', 30),
    ],
    
    'websocket' => [
        'enabled' => env('WEBSOCKET_ENABLED', true),
        'port' => env('WEBSOCKET_PORT', 9001),
        'channels' => [
            'bidding_room_{order_id}',
            'merchant_dashboard_{merchant_id}',
            'customer_updates_{customer_id}',
        ],
    ],
    
    'bidding' => [
        'auto_close_minutes' => env('BID_AUTO_CLOSE_MINUTES', 60),
        'min_bid_increment' => env('MIN_BID_INCREMENT', 1.00),
        'max_bids_per_merchant' => env('MAX_BIDS_PER_MERCHANT', 5),
        'real_time_updates' => env('REAL_TIME_UPDATES', true),
    ],
    
    'procedures' => [
        'bid' => [
            'place' => \App\RPC\Procedures\BidProcedure::class . '@place',
            'update' => \App\RPC\Procedures\BidProcedure::class . '@update',
            'withdraw' => \App\RPC\Procedures\BidProcedure::class . '@withdraw',
            'getHistory' => \App\RPC\Procedures\BidProcedure::class . '@getHistory',
            'getRanking' => \App\RPC\Procedures\BidProcedure::class . '@getRanking',
        ],
        'auction' => [
            'start' => \App\RPC\Procedures\AuctionProcedure::class . '@start',
            'close' => \App\RPC\Procedures\AuctionProcedure::class . '@close',
            'extend' => \App\RPC\Procedures\AuctionProcedure::class . '@extend',
            'getStatus' => \App\RPC\Procedures\AuctionProcedure::class . '@getStatus',
            'getLiveUpdates' => \App\RPC\Procedures\AuctionProcedure::class . '@getLiveUpdates',
        ],
        'auto_bid' => [
            'enable' => \App\RPC\Procedures\AutoBidProcedure::class . '@enable',
            'disable' => \App\RPC\Procedures\AutoBidProcedure::class . '@disable',
            'configure' => \App\RPC\Procedures\AutoBidProcedure::class . '@configure',
        ],
    ],
];
```

### **4. Payment Service - RPC Configuration**

```php
<?php
// services/payment-service/config/rpc.php

return [
    'server' => [
        'host' => env('RPC_HOST', '0.0.0.0'),
        'port' => env('RPC_PORT', 6004),
        'timeout' => env('RPC_TIMEOUT', 30),
    ],
    
    'gateways' => [
        'stripe' => [
            'enabled' => env('STRIPE_ENABLED', true),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'timeout' => env('STRIPE_TIMEOUT', 10),
        ],
        'mada' => [
            'enabled' => env('MADA_ENABLED', true),
            'merchant_id' => env('MADA_MERCHANT_ID'),
            'timeout' => env('MADA_TIMEOUT', 15),
        ],
        'stc_pay' => [
            'enabled' => env('STC_PAY_ENABLED', true),
            'merchant_id' => env('STC_PAY_MERCHANT_ID'),
            'timeout' => env('STC_PAY_TIMEOUT', 10),
        ],
    ],
    
    'zatca' => [
        'enabled' => env('ZATCA_ENABLED', true),
        'api_url' => env('ZATCA_API_URL'),
        'certificate_path' => env('ZATCA_CERTIFICATE_PATH'),
        'private_key_path' => env('ZATCA_PRIVATE_KEY_PATH'),
        'timeout' => env('ZATCA_TIMEOUT', 30),
    ],
    
    'procedures' => [
        'payment' => [
            'process' => \App\RPC\Procedures\PaymentProcedure::class . '@process',
            'refund' => \App\RPC\Procedures\PaymentProcedure::class . '@refund',
            'getStatus' => \App\RPC\Procedures\PaymentProcedure::class . '@getStatus',
            'getHistory' => \App\RPC\Procedures\PaymentProcedure::class . '@getHistory',
            'batchProcess' => \App\RPC\Procedures\PaymentProcedure::class . '@batchProcess',
        ],
        'invoice' => [
            'generate' => \App\RPC\Procedures\InvoiceProcedure::class . '@generate',
            'submitToZatca' => \App\RPC\Procedures\InvoiceProcedure::class . '@submitToZatca',
            'getZatcaStatus' => \App\RPC\Procedures\InvoiceProcedure::class . '@getZatcaStatus',
        ],
        'escrow' => [
            'hold' => \App\RPC\Procedures\EscrowProcedure::class . '@hold',
            'release' => \App\RPC\Procedures\EscrowProcedure::class . '@release',
            'dispute' => \App\RPC\Procedures\EscrowProcedure::class . '@dispute',
        ],
    ],
];
```

---

## 🐳 Docker & Kubernetes Configuration

### **Docker Compose for RPC Services**

```yaml
# deployment/docker/docker-compose.rpc.yml
version: '3.8'

services:
  user-service-rpc:
    build:
      context: ../../services/user-service
      dockerfile: ../../deployment/docker/Dockerfile.rpc
    ports:
      - "6001:6001"
      - "8001:8000"
    environment:
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - RPC_PORT=6001
      - RPC_HOST=0.0.0.0
    volumes:
      - ../../services/user-service:/app
    networks:
      - rpc-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  notification-service-rpc:
    build:
      context: ../../services/notification-service
      dockerfile: ../../deployment/docker/Dockerfile.rpc
    ports:
      - "6002:6002"
      - "8002:8000"
    environment:
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - RPC_PORT=6002
      - RPC_HOST=0.0.0.0
    volumes:
      - ../../services/notification-service:/app
    networks:
      - rpc-network
    depends_on:
      - user-service-rpc

  bidding-service-rpc:
    build:
      context: ../../services/bidding-service
      dockerfile: ../../deployment/docker/Dockerfile.rpc
    ports:
      - "6003:6003"
      - "8003:8000"
      - "9001:9001" # WebSocket port
    environment:
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=6
      - RPC_PORT=6003
      - RPC_HOST=0.0.0.0
      - WEBSOCKET_PORT=9001
    volumes:
      - ../../services/bidding-service:/app
    networks:
      - rpc-network
    depends_on:
      - user-service-rpc

  payment-service-rpc:
    build:
      context: ../../services/payment-service
      dockerfile: ../../deployment/docker/Dockerfile.rpc
    ports:
      - "6004:6004"
      - "8004:8000"
    environment:
      - OCTANE_SERVER=frankenphp
      - OCTANE_WORKERS=4
      - RPC_PORT=6004
      - RPC_HOST=0.0.0.0
    volumes:
      - ../../services/payment-service:/app
    networks:
      - rpc-network
    depends_on:
      - user-service-rpc

networks:
  rpc-network:
    driver: bridge
```

### **Kubernetes RPC Service Deployment**

```yaml
# deployment/k8s/base/rpc-services-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: user-service-rpc
  labels:
    app: user-service
    tier: rpc
spec:
  replicas: 3
  selector:
    matchLabels:
      app: user-service
      tier: rpc
  template:
    metadata:
      labels:
        app: user-service
        tier: rpc
    spec:
      containers:
      - name: user-service-rpc
        image: reversetender/user-service:rpc-latest
        ports:
        - containerPort: 6001
          name: rpc
        - containerPort: 8000
          name: http
        env:
        - name: OCTANE_SERVER
          value: "frankenphp"
        - name: OCTANE_WORKERS
          value: "4"
        - name: RPC_PORT
          value: "6001"
        - name: RPC_HOST
          value: "0.0.0.0"
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
        livenessProbe:
          httpGet:
            path: /health
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: user-service-rpc
  labels:
    app: user-service
    tier: rpc
spec:
  selector:
    app: user-service
    tier: rpc
  ports:
  - name: rpc
    port: 6001
    targetPort: 6001
  - name: http
    port: 8000
    targetPort: 8000
  type: ClusterIP
```

---

## 🔍 Monitoring & Observability

### **RPC Metrics Collection**

```php
<?php
// services/shared/app/RPC/Middleware/MetricsMiddleware.php

namespace App\RPC\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;

class MetricsMiddleware
{
    private Counter $requestCounter;
    private Counter $errorCounter;
    private Histogram $responseTimeHistogram;

    public function __construct(CollectorRegistry $registry)
    {
        $this->requestCounter = $registry->getOrRegisterCounter(
            'rpc',
            'requests_total',
            'Total RPC requests',
            ['service', 'method', 'status']
        );

        $this->errorCounter = $registry->getOrRegisterCounter(
            'rpc',
            'errors_total',
            'Total RPC errors',
            ['service', 'method', 'error_code']
        );

        $this->responseTimeHistogram = $registry->getOrRegisterHistogram(
            'rpc',
            'request_duration_seconds',
            'RPC request duration',
            ['service', 'method'],
            [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]
        );
    }

    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        $serviceName = config('app.name');
        $method = $request->getMethod();

        try {
            $response = $next($request);
            
            $this->requestCounter->inc([
                $serviceName,
                $method,
                'success'
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->requestCounter->inc([
                $serviceName,
                $method,
                'error'
            ]);

            $this->errorCounter->inc([
                $serviceName,
                $method,
                $e->getCode()
            ]);

            throw $e;
        } finally {
            $duration = microtime(true) - $startTime;
            $this->responseTimeHistogram->observe($duration, [
                $serviceName,
                $method
            ]);
        }
    }
}
```

### **Health Check Procedures**

```php
<?php
// services/shared/app/RPC/Procedures/HealthProcedure.php

namespace App\RPC\Procedures;

use App\RPC\BaseProcedure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthProcedure extends BaseProcedure
{
    /**
     * Basic health check
     */
    public function ping(): array
    {
        return [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'service' => config('app.name'),
            'version' => config('app.version', '1.0.0'),
        ];
    }

    /**
     * Comprehensive health check
     */
    public function check(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'memory' => $this->checkMemory(),
            'disk' => $this->checkDisk(),
        ];

        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'healthy')
            ? 'healthy'
            : 'degraded';

        return [
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'uptime' => $this->getUptime(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::ping();
            return ['status' => 'healthy', 'message' => 'Redis connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }
    }

    private function checkMemory(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $percentage = ($memoryUsage / $memoryLimit) * 100;

        return [
            'status' => $percentage < 80 ? 'healthy' : 'warning',
            'usage' => $this->formatBytes($memoryUsage),
            'limit' => $this->formatBytes($memoryLimit),
            'percentage' => round($percentage, 2),
        ];
    }

    private function checkDisk(): array
    {
        $freeBytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        $percentage = (($totalBytes - $freeBytes) / $totalBytes) * 100;

        return [
            'status' => $percentage < 90 ? 'healthy' : 'warning',
            'free' => $this->formatBytes($freeBytes),
            'total' => $this->formatBytes($totalBytes),
            'percentage' => round($percentage, 2),
        ];
    }

    private function getUptime(): string
    {
        $uptime = file_get_contents('/proc/uptime');
        $seconds = (int) explode(' ', $uptime)[0];
        
        return gmdate('H:i:s', $seconds);
    }

    private function parseBytes(string $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
```

---

## 🚀 Performance Optimization

### **Octane Warm-up Configuration**

```php
<?php
// services/shared/config/octane-warmup.php

return [
    'procedures' => [
        // User Service Procedures
        \App\RPC\Procedures\UserProcedure::class,
        \App\RPC\Procedures\ProfileProcedure::class,
        
        // Order Service Procedures
        \App\RPC\Procedures\OrderProcedure::class,
        \App\RPC\Procedures\OrderStatusProcedure::class,
        
        // Bidding Service Procedures
        \App\RPC\Procedures\BidProcedure::class,
        \App\RPC\Procedures\AuctionProcedure::class,
        
        // Payment Service Procedures
        \App\RPC\Procedures\PaymentProcedure::class,
        \App\RPC\Procedures\InvoiceProcedure::class,
        
        // Notification Service Procedures
        \App\RPC\Procedures\NotificationProcedure::class,
        \App\RPC\Procedures\TemplateProcedure::class,
    ],
    
    'cache' => [
        'config' => true,
        'routes' => true,
        'views' => true,
        'events' => true,
    ],
    
    'preload' => [
        'models' => [
            \App\Models\User::class,
            \App\Models\Order::class,
            \App\Models\Bid::class,
            \App\Models\Payment::class,
            \App\Models\Notification::class,
        ],
        'services' => [
            \App\Services\UserService::class,
            \App\Services\OrderService::class,
            \App\Services\BiddingService::class,
            \App\Services\PaymentService::class,
            \App\Services\NotificationService::class,
        ],
    ],
];
```

### **Batch Processing Configuration**

```php
<?php
// services/shared/config/rpc-batching.php

return [
    'enabled' => env('RPC_BATCHING_ENABLED', true),
    'max_batch_size' => env('RPC_MAX_BATCH_SIZE', 100),
    'batch_timeout' => env('RPC_BATCH_TIMEOUT', 1000), // milliseconds
    
    'batch_strategies' => [
        'user_operations' => [
            'max_size' => 50,
            'timeout' => 500,
            'procedures' => [
                'User@create',
                'User@update',
                'User@delete',
            ],
        ],
        'notification_sending' => [
            'max_size' => 1000,
            'timeout' => 2000,
            'procedures' => [
                'Notification@send',
                'Notification@sendBatch',
            ],
        ],
        'payment_processing' => [
            'max_size' => 25,
            'timeout' => 5000,
            'procedures' => [
                'Payment@process',
                'Payment@refund',
            ],
        ],
    ],
];
```

---

## 📝 Implementation Checklist

### **Phase 1: Infrastructure Setup**
- [ ] Update all service Dockerfiles with Octane dependencies
- [ ] Configure RPC ports for each service (6001-6008)
- [ ] Set up Kubernetes service mesh for RPC communication
- [ ] Configure monitoring and metrics collection
- [ ] Set up health check endpoints

### **Phase 2: Service Configuration**
- [ ] Create RPC configuration files for each service
- [ ] Implement BaseProcedure class with validation
- [ ] Set up RPC routes and middleware
- [ ] Configure service-to-service authentication
- [ ] Implement correlation ID tracing

### **Phase 3: Procedure Implementation**
- [ ] Migrate REST endpoints to RPC procedures
- [ ] Implement batch processing capabilities
- [ ] Add comprehensive error handling
- [ ] Set up concurrent processing with Octane
- [ ] Implement circuit breaker patterns

### **Phase 4: Testing & Validation**
- [ ] Unit tests for all RPC procedures
- [ ] Integration tests for service communication
- [ ] Performance benchmarking
- [ ] Load testing with concurrent requests
- [ ] Failover and recovery testing

### **Phase 5: Deployment & Monitoring**
- [ ] Deploy to staging environment
- [ ] Configure production monitoring
- [ ] Set up alerting and notifications
- [ ] Document rollback procedures
- [ ] Train development team

---

*This configuration guide provides the foundation for implementing high-performance RPC communication across all microservices in the Reverse Tender Platform using Laravel Octane and JSON-RPC 2.0.*
