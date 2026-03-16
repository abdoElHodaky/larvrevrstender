# Database Query Circuit Breaker Examples

This document provides comprehensive examples of how to use the Database Query Circuit Breaker system implemented in Step 2 of the database failover integration plan.

## Table of Contents

1. [Basic Usage with Trait](#basic-usage-with-trait)
2. [QueryExecutionService Usage](#queryexecutionservice-usage)
3. [Configuration Examples](#configuration-examples)
4. [Advanced Scenarios](#advanced-scenarios)
5. [Monitoring and Debugging](#monitoring-and-debugging)
6. [Best Practices](#best-practices)

## Basic Usage with Trait

### Simple Query Protection

```php
<?php

namespace App\Services;

use Shared\Traits\DatabaseQueryCircuitBreaker;
use Illuminate\Support\Facades\DB;

class UserService
{
    use DatabaseQueryCircuitBreaker;

    public function getActiveUsers()
    {
        return $this->executeProtectedQuery('user_queries', function() {
            return DB::table('users')->where('active', true)->get();
        });
    }

    public function updateUserStatus($userId, $status)
    {
        return $this->executeProtectedQuery('user_updates', function() use ($userId, $status) {
            return DB::table('users')
                ->where('id', $userId)
                ->update(['status' => $status]);
        });
    }
}
```

### Transaction Protection

```php
<?php

namespace App\Services;

use Shared\Traits\DatabaseQueryCircuitBreaker;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    use DatabaseQueryCircuitBreaker;

    public function processPayment($paymentData)
    {
        return $this->executeProtectedTransaction('payment_processing', function() use ($paymentData) {
            // Create payment record
            $payment = DB::table('payments')->insertGetId([
                'user_id' => $paymentData['user_id'],
                'amount' => $paymentData['amount'],
                'status' => 'processing',
                'created_at' => now()
            ]);

            // Update user balance
            DB::table('users')
                ->where('id', $paymentData['user_id'])
                ->increment('balance', $paymentData['amount']);

            // Log transaction
            DB::table('payment_logs')->insert([
                'payment_id' => $payment,
                'action' => 'payment_processed',
                'created_at' => now()
            ]);

            return $payment;
        });
    }
}
```

### Custom Circuit Breaker Configuration

```php
<?php

namespace App\Services;

use Shared\Traits\DatabaseQueryCircuitBreaker;
use Illuminate\Support\Facades\DB;

class CriticalService
{
    use DatabaseQueryCircuitBreaker;

    public function performCriticalOperation()
    {
        // Custom configuration for this specific operation
        $circuitOptions = [
            'failure_threshold' => 2,  // Very sensitive
            'recovery_timeout' => 60,  // Longer recovery time
            'expected_exceptions' => [
                \Illuminate\Database\QueryException::class,
                \PDOException::class,
            ]
        ];

        return $this->executeProtectedQuery('critical_ops', function() {
            return DB::table('critical_data')->get();
        }, $circuitOptions);
    }
}
```

## QueryExecutionService Usage

### Basic Service Usage

```php
<?php

namespace App\Services;

use Shared\Services\QueryExecutionService;

class OrderService
{
    private QueryExecutionService $queryService;

    public function __construct()
    {
        $this->queryService = new QueryExecutionService('order-service');
    }

    public function getOrdersByStatus($status)
    {
        return $this->queryService->select(
            'SELECT * FROM orders WHERE status = ? ORDER BY created_at DESC',
            [$status]
        );
    }

    public function createOrder($orderData)
    {
        return $this->queryService->insert(
            'INSERT INTO orders (user_id, total, status, created_at) VALUES (?, ?, ?, ?)',
            [$orderData['user_id'], $orderData['total'], 'pending', now()]
        );
    }

    public function updateOrderStatus($orderId, $status)
    {
        return $this->queryService->update(
            'UPDATE orders SET status = ?, updated_at = ? WHERE id = ?',
            [$status, now(), $orderId]
        );
    }
}
```

### Eloquent Query Protection

```php
<?php

namespace App\Services;

use Shared\Services\QueryExecutionService;
use App\Models\User;

class UserAnalyticsService
{
    private QueryExecutionService $queryService;

    public function __construct()
    {
        $this->queryService = new QueryExecutionService('user-analytics');
    }

    public function getUserStatistics()
    {
        return $this->queryService->eloquent(function() {
            return User::with(['orders', 'payments'])
                ->where('created_at', '>=', now()->subDays(30))
                ->get();
        }, 'user_statistics');
    }

    public function getTopUsers($limit = 10)
    {
        return $this->queryService->eloquent(function() use ($limit) {
            return User::withCount('orders')
                ->orderBy('orders_count', 'desc')
                ->limit($limit)
                ->get();
        }, 'top_users');
    }
}
```

### Multi-Connection Usage

```php
<?php

namespace App\Services;

use Shared\Services\QueryExecutionService;

class ReportingService
{
    private QueryExecutionService $queryService;

    public function __construct()
    {
        $this->queryService = new QueryExecutionService('reporting-service');
    }

    public function generateSalesReport()
    {
        // Use read replica for reporting
        $salesData = $this->queryService->select(
            'SELECT DATE(created_at) as date, SUM(amount) as total FROM payments WHERE created_at >= ? GROUP BY DATE(created_at)',
            [now()->subDays(30)],
            'mysql_read_replica'
        );

        // Use analytics database for additional metrics
        $analyticsData = $this->queryService->select(
            'SELECT * FROM user_engagement_metrics WHERE date >= ?',
            [now()->subDays(30)],
            'analytics_db'
        );

        return [
            'sales' => $salesData,
            'engagement' => $analyticsData
        ];
    }
}
```

## Configuration Examples

### Environment Variables

```bash
# Query circuit breaker defaults
FUSE_QUERY_FAILURE_THRESHOLD=5
FUSE_QUERY_RECOVERY_TIMEOUT=30
FUSE_QUERY_SUCCESS_THRESHOLD=3
FUSE_QUERY_TIMEOUT=10

# Query type specific settings
FUSE_SELECT_FAILURE_THRESHOLD=8
FUSE_SELECT_RECOVERY_TIMEOUT=20
FUSE_INSERT_FAILURE_THRESHOLD=3
FUSE_UPDATE_FAILURE_THRESHOLD=3
FUSE_DELETE_FAILURE_THRESHOLD=2
FUSE_TRANSACTION_FAILURE_THRESHOLD=2

# Connection specific settings
FUSE_PAYMENT_DB_FAILURE_THRESHOLD=2
FUSE_PAYMENT_DB_RECOVERY_TIMEOUT=60
FUSE_AUDIT_DB_FAILURE_THRESHOLD=3
```

### Service-Specific Configuration

```php
<?php

// config/services.php

return [
    'circuit_breaker' => [
        'payment_service' => [
            'query_defaults' => [
                'failure_threshold' => 2,
                'recovery_timeout' => 60,
            ]
        ],
        'analytics_service' => [
            'query_defaults' => [
                'failure_threshold' => 10,
                'recovery_timeout' => 15,
            ]
        ]
    ]
];
```

## Advanced Scenarios

### Graceful Degradation with Circuit Breaker

```php
<?php

namespace App\Services;

use Shared\Traits\DatabaseQueryCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Fuse\CircuitOpenException;

class ProductService
{
    use DatabaseQueryCircuitBreaker;

    public function getProducts($category = null)
    {
        try {
            return $this->executeProtectedQuery('product_queries', function() use ($category) {
                $query = DB::table('products')->where('active', true);
                
                if ($category) {
                    $query->where('category', $category);
                }
                
                return $query->get();
            });
        } catch (CircuitOpenException $e) {
            // Circuit is open, fall back to cached data
            $cacheKey = 'products_' . ($category ?? 'all');
            
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }
            
            // Return minimal fallback data
            return collect([
                ['id' => 0, 'name' => 'Service temporarily unavailable', 'category' => 'system']
            ]);
        }
    }
}
```

### Circuit Breaker with Retry Logic

```php
<?php

namespace App\Services;

use Shared\Services\QueryExecutionService;
use Fuse\CircuitOpenException;

class ResilientService
{
    private QueryExecutionService $queryService;

    public function __construct()
    {
        $this->queryService = new QueryExecutionService('resilient-service');
    }

    public function performOperationWithRetry($data, $maxRetries = 3)
    {
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return $this->queryService->insert(
                    'INSERT INTO operations (data, status) VALUES (?, ?)',
                    [json_encode($data), 'pending']
                );
            } catch (CircuitOpenException $e) {
                $attempt++;
                
                if ($attempt >= $maxRetries) {
                    throw $e;
                }
                
                // Wait before retry (exponential backoff)
                sleep(pow(2, $attempt));
            }
        }
    }
}
```

### Performance Monitoring Integration

```php
<?php

namespace App\Services;

use Shared\Services\QueryExecutionService;
use Illuminate\Support\Facades\Log;

class MonitoredService
{
    private QueryExecutionService $queryService;

    public function __construct()
    {
        $this->queryService = new QueryExecutionService('monitored-service');
    }

    public function performMonitoredOperation()
    {
        $startTime = microtime(true);
        
        try {
            $result = $this->queryService->select('SELECT COUNT(*) as total FROM large_table');
            
            $duration = (microtime(true) - $startTime) * 1000;
            
            // Log performance metrics
            Log::info('Query performance', [
                'operation' => 'large_table_count',
                'duration_ms' => $duration,
                'circuit_stats' => $this->queryService->getCircuitHealthStatus()
            ]);
            
            return $result;
        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            
            Log::error('Query failed', [
                'operation' => 'large_table_count',
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'circuit_stats' => $this->queryService->getCircuitHealthStatus()
            ]);
            
            throw $e;
        }
    }
}
```

## Monitoring and Debugging

### Circuit Breaker Health Check

```php
<?php

namespace App\Http\Controllers;

use Shared\Services\QueryExecutionService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function circuitBreakerStatus(): JsonResponse
    {
        $queryService = new QueryExecutionService();
        
        return response()->json([
            'circuit_breakers' => $queryService->getCircuitHealthStatus(),
            'performance_metrics' => $queryService->getPerformanceMetrics(),
            'timestamp' => now()->toISOString()
        ]);
    }
}
```

### Manual Circuit Breaker Reset

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Shared\Services\QueryExecutionService;

class ResetCircuitBreaker extends Command
{
    protected $signature = 'circuit-breaker:reset {circuit_name}';
    protected $description = 'Reset a specific circuit breaker';

    public function handle()
    {
        $circuitName = $this->argument('circuit_name');
        $queryService = new QueryExecutionService();
        
        if ($queryService->resetCircuitBreaker($circuitName)) {
            $this->info("Circuit breaker '{$circuitName}' has been reset.");
        } else {
            $this->error("Circuit breaker '{$circuitName}' not found or not initialized.");
        }
    }
}
```

### Logging Integration

```php
<?php

// Example of custom logging for circuit breaker events

use Shared\Facades\SharedLog;

// This is automatically handled by the trait and service, but you can add custom logging:

class CustomCircuitBreakerLogger
{
    public static function logCircuitEvent($eventType, $circuitName, $context = [])
    {
        SharedLog::databaseFailover("custom_circuit_event_{$eventType}", array_merge([
            'circuit_name' => $circuitName,
            'event_type' => $eventType,
            'timestamp' => now()->toISOString()
        ], $context));
    }
}
```

## Best Practices

### 1. Circuit Naming Convention

```php
// Good: Descriptive and hierarchical
$this->executeProtectedQuery('user_service_profile_updates', $query);
$this->executeProtectedQuery('payment_service_stripe_charges', $query);
$this->executeProtectedQuery('analytics_service_report_generation', $query);

// Bad: Generic or unclear
$this->executeProtectedQuery('query1', $query);
$this->executeProtectedQuery('db_operation', $query);
```

### 2. Configuration Strategy

```php
// Configure based on criticality and expected behavior
$criticalOperations = [
    'failure_threshold' => 2,    // Very sensitive
    'recovery_timeout' => 60,    // Longer recovery
];

$analyticsOperations = [
    'failure_threshold' => 10,   // More tolerant
    'recovery_timeout' => 15,    // Quick recovery
];

$readOperations = [
    'failure_threshold' => 8,    // Tolerant for reads
    'recovery_timeout' => 20,
];
```

### 3. Error Handling Strategy

```php
public function handleDatabaseOperation()
{
    try {
        return $this->executeProtectedQuery('operation_name', $query);
    } catch (CircuitOpenException $e) {
        // Circuit is open - provide fallback
        return $this->getFallbackData();
    } catch (QueryException $e) {
        // Database error - log and handle appropriately
        Log::error('Database query failed', ['error' => $e->getMessage()]);
        throw new ServiceException('Operation temporarily unavailable');
    }
}
```

### 4. Testing Circuit Breakers

```php
// In your tests, you can simulate circuit breaker behavior
public function testCircuitBreakerOpensOnFailures()
{
    // Mock database failures
    DB::shouldReceive('select')->andThrow(new QueryException('Connection failed'));
    
    $service = new TestService();
    
    // Trigger enough failures to open circuit
    for ($i = 0; $i < 6; $i++) {
        try {
            $service->performQuery();
        } catch (QueryException $e) {
            // Expected
        }
    }
    
    // Next call should throw CircuitOpenException
    $this->expectException(CircuitOpenException::class);
    $service->performQuery();
}
```

### 5. Monitoring Integration

```php
// Set up monitoring dashboards to track:
// - Circuit breaker state changes
// - Query failure rates
// - Recovery times
// - Performance metrics

// Example metrics collection
$metrics = [
    'circuit_open_count' => $this->getOpenCircuitCount(),
    'average_query_time' => $this->getAverageQueryTime(),
    'failure_rate' => $this->getFailureRate(),
    'recovery_success_rate' => $this->getRecoverySuccessRate()
];
```

This completes the comprehensive examples and documentation for the Database Query Circuit Breaker system. The implementation provides robust protection for database operations while maintaining observability and configurability.
