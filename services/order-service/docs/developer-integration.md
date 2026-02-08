# Developer Integration Guide - Laravel Workflow Saga Pattern

## 📋 Overview

This guide provides comprehensive instructions for developers to integrate with the Laravel Workflow Saga Pattern implementation. It covers API usage, SDK examples, best practices, and common integration patterns.

---

## 🚀 Quick Start Integration

### **Basic Workflow Integration**

```php
<?php

use App\Services\WorkflowEventPublisher;
use App\Services\CorrelationService;
use App\Services\WorkflowSignalHandler;

class OrderWorkflowIntegration
{
    public function __construct(
        private WorkflowEventPublisher $eventPublisher,
        private CorrelationService $correlationService,
        private WorkflowSignalHandler $signalHandler
    ) {}

    public function initiateOrderWorkflow(int $orderId): string
    {
        // Generate correlation ID for tracking
        $correlationId = $this->correlationService->generateCorrelationId();
        
        // Publish order initiated event
        $this->eventPublisher->publishOrderInitiated($orderId, $correlationId);
        
        return $correlationId;
    }

    public function pauseWorkflow(string $workflowId, string $reason): array
    {
        return $this->signalHandler->pauseWorkflow(
            $workflowId,
            $reason,
            auth()->id()
        );
    }
}
```

---

## 🔗 API Integration Patterns

### **1. RESTful API Integration**

#### **Workflow Management**

```javascript
// JavaScript/TypeScript integration
class WorkflowApiClient {
    constructor(baseUrl, apiToken) {
        this.baseUrl = baseUrl;
        this.headers = {
            'Authorization': `Bearer ${apiToken}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    }

    async initiateWorkflow(orderId, activities = ['payment', 'inventory', 'shipping']) {
        const response = await fetch(`${this.baseUrl}/orders/${orderId}/workflow/initiate`, {
            method: 'POST',
            headers: this.headers,
            body: JSON.stringify({
                activities,
                priority: 'high'
            })
        });
        
        return await response.json();
    }

    async getWorkflowStatus(orderId) {
        const response = await fetch(`${this.baseUrl}/orders/${orderId}/workflow/status`, {
            headers: this.headers
        });
        
        return await response.json();
    }

    async pauseWorkflow(orderId, reason) {
        const response = await fetch(`${this.baseUrl}/orders/${orderId}/workflow/pause`, {
            method: 'POST',
            headers: this.headers,
            body: JSON.stringify({
                reason,
                user_id: this.getCurrentUserId()
            })
        });
        
        return await response.json();
    }
}

// Usage example
const workflowClient = new WorkflowApiClient('https://api.example.com', 'your-api-token');

// Initiate workflow
const workflow = await workflowClient.initiateWorkflow(123);
console.log('Workflow initiated:', workflow.data.workflow_id);

// Check status
const status = await workflowClient.getWorkflowStatus(123);
console.log('Current status:', status.data.status);
```

#### **Dashboard Integration**

```javascript
class WorkflowDashboard {
    constructor(apiClient) {
        this.api = apiClient;
        this.refreshInterval = null;
    }

    async loadExecutiveDashboard() {
        const response = await this.api.get('/workflow/dashboard/executive');
        return response.data;
    }

    async loadOperationsDashboard() {
        const response = await this.api.get('/workflow/dashboard/operations');
        return response.data;
    }

    startAutoRefresh(dashboardType = 'operations') {
        const intervals = {
            executive: 30000,   // 30 seconds
            operations: 10000,  // 10 seconds
            performance: 60000  // 60 seconds
        };

        this.refreshInterval = setInterval(async () => {
            const data = await this.loadDashboard(dashboardType);
            this.updateDashboard(data);
        }, intervals[dashboardType]);
    }

    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
}
```

### **2. WebSocket Integration for Real-time Updates**

```javascript
class WorkflowWebSocketClient {
    constructor(wsUrl, channels = []) {
        this.wsUrl = wsUrl;
        this.channels = channels;
        this.connection = null;
        this.eventHandlers = new Map();
    }

    connect() {
        this.connection = new WebSocket(this.wsUrl);
        
        this.connection.onopen = () => {
            console.log('WebSocket connected');
            this.subscribeToChannels();
        };

        this.connection.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleMessage(data);
        };

        this.connection.onclose = () => {
            console.log('WebSocket disconnected');
            // Implement reconnection logic
            setTimeout(() => this.connect(), 5000);
        };
    }

    subscribeToChannels() {
        this.channels.forEach(channel => {
            this.send({
                type: 'subscribe',
                channel: channel
            });
        });
    }

    on(eventType, handler) {
        if (!this.eventHandlers.has(eventType)) {
            this.eventHandlers.set(eventType, []);
        }
        this.eventHandlers.get(eventType).push(handler);
    }

    handleMessage(data) {
        const handlers = this.eventHandlers.get(data.event) || [];
        handlers.forEach(handler => handler(data));
    }

    send(data) {
        if (this.connection && this.connection.readyState === WebSocket.OPEN) {
            this.connection.send(JSON.stringify(data));
        }
    }
}

// Usage example
const wsClient = new WorkflowWebSocketClient('wss://api.example.com/ws', [
    'workflow.status',
    'workflow.interventions',
    'workflow.interventions.urgent'
]);

// Handle workflow events
wsClient.on('workflow.paused', (data) => {
    console.log('Workflow paused:', data);
    updateWorkflowStatus(data.workflow_id, 'paused');
});

wsClient.on('workflow.intervention.requested', (data) => {
    console.log('Manual intervention required:', data);
    if (data.priority === 'critical') {
        showUrgentAlert(data);
    }
});

wsClient.connect();
```

---

## 🔧 Service Integration

### **1. Event Publishing Integration**

```php
<?php

namespace App\Services;

use App\Services\WorkflowEventPublisher;
use App\Services\CorrelationService;

class PaymentService
{
    public function __construct(
        private WorkflowEventPublisher $eventPublisher,
        private CorrelationService $correlationService
    ) {}

    public function processPayment(array $paymentData): array
    {
        $correlationId = $this->correlationService->getCurrentCorrelationId();
        $spanId = $this->correlationService->startSpan('ProcessPayment');

        try {
            // Process payment logic
            $result = $this->executePayment($paymentData);

            // Publish success event
            $this->eventPublisher->publishActivityCompleted(
                $paymentData['workflow_id'],
                'payment',
                $result
            );

            $this->correlationService->finishSpan($spanId, $result);

            return $result;

        } catch (\Exception $e) {
            // Publish failure event
            $this->eventPublisher->publishActivityFailed(
                $paymentData['workflow_id'],
                'payment',
                $e->getMessage()
            );

            $this->correlationService->finishSpan($spanId, [
                'error' => $e->getMessage(),
                'status' => 'failed'
            ]);

            throw $e;
        }
    }

    private function executePayment(array $data): array
    {
        // Record RPC call to payment gateway
        $startTime = microtime(true);
        
        try {
            $response = $this->callPaymentGateway($data);
            
            $this->correlationService->recordRpcCall(
                'payment-gateway',
                'processPayment',
                microtime(true) - $startTime,
                ['transaction_id' => $response['transaction_id']]
            );

            return $response;
        } catch (\Exception $e) {
            $this->correlationService->recordRpcCall(
                'payment-gateway',
                'processPayment',
                microtime(true) - $startTime,
                ['error' => $e->getMessage(), 'status' => 'failed']
            );
            
            throw $e;
        }
    }
}
```

### **2. Signal Handling Integration**

```php
<?php

namespace App\Http\Controllers;

use App\Services\WorkflowSignalHandler;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowSignalHandler $signalHandler
    ) {}

    public function pauseWorkflow(Request $request, int $orderId)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $workflowId = "order-saga-{$orderId}";
        
        $result = $this->signalHandler->pauseWorkflow(
            $workflowId,
            $request->reason,
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function requestIntervention(Request $request, int $orderId)
    {
        $request->validate([
            'reason' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        $workflowId = "order-saga-{$orderId}";
        
        $result = $this->signalHandler->requestManualIntervention(
            $workflowId,
            $request->reason,
            $request->priority
        );

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
```

### **3. Correlation Context Propagation**

```php
<?php

namespace App\Http\Middleware;

use App\Services\CorrelationService;
use Closure;
use Illuminate\Http\Request;

class CorrelationMiddleware
{
    public function __construct(
        private CorrelationService $correlationService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // Extract correlation context from headers
        $correlationId = $request->header('X-Correlation-ID');
        $traceId = $request->header('X-Trace-ID');
        $spanId = $request->header('X-Span-ID');

        if ($correlationId) {
            $this->correlationService->setCurrentContext([
                'correlation_id' => $correlationId,
                'trace_id' => $traceId,
                'parent_span_id' => $spanId,
            ]);
        }

        $response = $next($request);

        // Add correlation headers to response
        $currentContext = $this->correlationService->getCurrentContext();
        if ($currentContext) {
            $response->headers->set('X-Correlation-ID', $currentContext['correlation_id']);
            $response->headers->set('X-Trace-ID', $currentContext['trace_id']);
        }

        return $response;
    }
}
```

---

## 📊 Monitoring Integration

### **1. Custom Metrics Integration**

```php
<?php

namespace App\Services;

use App\Services\WorkflowTracingService;
use Illuminate\Support\Facades\Cache;

class CustomMetricsService
{
    public function __construct(
        private WorkflowTracingService $tracingService
    ) {}

    public function recordCustomMetric(string $metricName, $value, array $tags = []): void
    {
        // Record in cache for dashboard
        $cacheKey = "custom.metrics.{$metricName}." . now()->format('Y-m-d-H');
        Cache::increment($cacheKey, $value);

        // Record in tracing service
        $this->tracingService->recordWorkflowEvent('custom_metric', [
            'metric_name' => $metricName,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => now()->toISOString(),
        ]);
    }

    public function recordBusinessEvent(string $eventType, array $data): void
    {
        $this->tracingService->recordWorkflowEvent('business_event', [
            'event_type' => $eventType,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ]);

        // Update business metrics
        $this->updateBusinessMetrics($eventType, $data);
    }

    private function updateBusinessMetrics(string $eventType, array $data): void
    {
        $today = now()->format('Y-m-d');
        
        match ($eventType) {
            'order_completed' => Cache::increment("business.metrics.{$today}.orders_completed"),
            'payment_processed' => Cache::increment("business.metrics.{$today}.payments_processed"),
            'inventory_reserved' => Cache::increment("business.metrics.{$today}.inventory_reserved"),
            default => null,
        };
    }
}
```

### **2. Alert Integration**

```php
<?php

namespace App\Services;

use App\Services\WorkflowAlertingService;

class CustomAlertService
{
    public function __construct(
        private WorkflowAlertingService $alertingService
    ) {}

    public function checkBusinessRules(): void
    {
        // Check custom business rules
        $this->checkOrderVolumeThreshold();
        $this->checkPaymentFailureRate();
        $this->checkInventoryLevels();
    }

    private function checkOrderVolumeThreshold(): void
    {
        $hourlyOrders = Cache::get('business.metrics.' . now()->format('Y-m-d-H') . '.orders_completed', 0);
        $threshold = config('business.alerts.order_volume_threshold', 100);

        if ($hourlyOrders > $threshold) {
            $this->alertingService->triggerCustomAlert([
                'type' => 'business_threshold',
                'severity' => 'info',
                'message' => "High order volume: {$hourlyOrders} orders in the last hour",
                'data' => [
                    'hourly_orders' => $hourlyOrders,
                    'threshold' => $threshold,
                ],
            ]);
        }
    }

    private function checkPaymentFailureRate(): void
    {
        $successful = Cache::get('business.metrics.' . now()->format('Y-m-d') . '.payments_successful', 0);
        $failed = Cache::get('business.metrics.' . now()->format('Y-m-d') . '.payments_failed', 0);
        $total = $successful + $failed;

        if ($total > 0) {
            $failureRate = ($failed / $total) * 100;
            $threshold = config('business.alerts.payment_failure_threshold', 5.0);

            if ($failureRate > $threshold) {
                $this->alertingService->triggerCustomAlert([
                    'type' => 'payment_failure_rate',
                    'severity' => 'warning',
                    'message' => "High payment failure rate: {$failureRate}%",
                    'data' => [
                        'failure_rate' => $failureRate,
                        'threshold' => $threshold,
                        'total_payments' => $total,
                    ],
                ]);
            }
        }
    }
}
```

---

## 🔧 Configuration Examples

### **1. Environment Configuration**

```bash
# .env file
# Workflow Configuration
WORKFLOW_BROADCASTING_ENABLED=true
WORKFLOW_CORRELATION_AUTO_PROPAGATE=true
WORKFLOW_SIGNAL_DEFAULT_PRIORITY=medium
WORKFLOW_SIGNAL_TIMEOUT=300

# DLQ Configuration
WORKFLOW_DLQ_MAX_RETRIES=5
WORKFLOW_DLQ_INITIAL_DELAY=30
WORKFLOW_DLQ_MAX_DELAY=3600

# Alerting Configuration
WORKFLOW_ALERTING_ENABLED=true
WORKFLOW_ALERT_CHECK_INTERVAL=60

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Broadcasting Configuration
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
```

### **2. Service Configuration**

```php
<?php
// config/workflow.php

return [
    'services' => [
        'event_publisher' => [
            'broadcasting_enabled' => env('WORKFLOW_BROADCASTING_ENABLED', true),
            'correlation_auto_propagate' => env('WORKFLOW_CORRELATION_AUTO_PROPAGATE', true),
            'channels' => [
                'status' => 'workflow.status',
                'metrics' => 'workflow.metrics',
                'interventions' => 'workflow.interventions',
            ],
        ],
        
        'signal_handler' => [
            'default_priority' => env('WORKFLOW_SIGNAL_DEFAULT_PRIORITY', 'medium'),
            'timeout_seconds' => env('WORKFLOW_SIGNAL_TIMEOUT', 300),
            'queues' => [
                'high' => 'signals-high',
                'medium' => 'signals-medium',
                'low' => 'signals-low',
            ],
        ],
        
        'dlq' => [
            'max_retries' => env('WORKFLOW_DLQ_MAX_RETRIES', 5),
            'initial_delay' => env('WORKFLOW_DLQ_INITIAL_DELAY', 30),
            'max_delay' => env('WORKFLOW_DLQ_MAX_DELAY', 3600),
            'multiplier' => 2,
            'jitter' => true,
            'queues' => [
                'payment' => 'dlq-payment',
                'inventory' => 'dlq-inventory',
                'shipping' => 'dlq-shipping',
                'compensation' => 'dlq-compensation',
            ],
        ],
        
        'correlation' => [
            'header_name' => env('WORKFLOW_CORRELATION_HEADER', 'X-Correlation-ID'),
            'trace_header' => env('WORKFLOW_TRACE_HEADER', 'X-Trace-ID'),
            'span_header' => env('WORKFLOW_SPAN_HEADER', 'X-Span-ID'),
            'auto_generate' => true,
        ],
        
        'alerting' => [
            'enabled' => env('WORKFLOW_ALERTING_ENABLED', true),
            'check_interval' => env('WORKFLOW_ALERT_CHECK_INTERVAL', 60),
            'channels' => [
                'slack' => [
                    'webhook_url' => env('SLACK_WEBHOOK_URL'),
                    'channel' => env('SLACK_CHANNEL', '#alerts'),
                ],
                'email' => [
                    'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
                ],
                'pagerduty' => [
                    'integration_key' => env('PAGERDUTY_INTEGRATION_KEY'),
                ],
            ],
            'rules' => [
                'dlq_threshold' => [
                    'type' => 'dlq_threshold',
                    'threshold' => 10,
                    'severity' => 'warning',
                    'channels' => ['slack', 'email'],
                ],
                'failure_rate' => [
                    'type' => 'failure_rate',
                    'threshold' => 5.0,
                    'timeframe' => '1h',
                    'severity' => 'warning',
                    'channels' => ['slack', 'email'],
                ],
                'response_time' => [
                    'type' => 'response_time',
                    'threshold' => 10.0,
                    'timeframe' => '5m',
                    'severity' => 'critical',
                    'channels' => ['slack', 'pagerduty'],
                ],
            ],
        ],
    ],
];
```

---

## 🧪 Testing Integration

### **1. Unit Testing**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\WorkflowEventPublisher;
use App\Services\CorrelationService;
use Tests\TestCase;
use Mockery;

class WorkflowEventPublisherTest extends TestCase
{
    public function test_publishes_order_initiated_event()
    {
        // Mock dependencies
        $correlationService = Mockery::mock(CorrelationService::class);
        $correlationService->shouldReceive('getCurrentCorrelationId')
                          ->andReturn('test-correlation-id');

        // Create service instance
        $eventPublisher = new WorkflowEventPublisher($correlationService);

        // Test event publishing
        $result = $eventPublisher->publishOrderInitiated(123, 'test-correlation-id');

        // Assertions
        $this->assertTrue($result);
        Event::assertDispatched(OrderInitiated::class);
    }
}
```

### **2. Integration Testing**

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_initiate_workflow()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($user)
                         ->postJson("/api/orders/{$order->id}/workflow/initiate", [
                             'activities' => ['payment', 'inventory', 'shipping'],
                             'priority' => 'high'
                         ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'workflow_id',
                        'correlation_id',
                        'status',
                        'activities'
                    ]
                ]);
    }

    public function test_can_pause_workflow()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $response = $this->actingAs($user)
                         ->postJson("/api/orders/{$order->id}/workflow/pause", [
                             'reason' => 'Manual review required'
                         ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'status' => 'paused'
                    ]
                ]);
    }
}
```

---

## 🔗 Best Practices

### **1. Error Handling**

```php
<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use Illuminate\Support\Facades\Log;

class WorkflowIntegrationService
{
    public function safeWorkflowOperation(callable $operation, array $context = []): array
    {
        try {
            $result = $operation();
            
            Log::info('Workflow operation completed successfully', [
                'context' => $context,
                'result' => $result,
            ]);
            
            return ['success' => true, 'data' => $result];
            
        } catch (WorkflowException $e) {
            Log::warning('Workflow operation failed', [
                'context' => $context,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
            
        } catch (\Exception $e) {
            Log::error('Unexpected error in workflow operation', [
                'context' => $context,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return ['success' => false, 'error' => 'Internal server error'];
        }
    }
}
```

### **2. Performance Optimization**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OptimizedWorkflowService
{
    public function getWorkflowStatusWithCaching(string $workflowId): array
    {
        $cacheKey = "workflow.status.{$workflowId}";
        
        return Cache::remember($cacheKey, 60, function () use ($workflowId) {
            return $this->fetchWorkflowStatus($workflowId);
        });
    }

    public function batchProcessWorkflows(array $workflowIds): array
    {
        // Process workflows in batches to avoid memory issues
        $batchSize = 50;
        $results = [];
        
        foreach (array_chunk($workflowIds, $batchSize) as $batch) {
            $batchResults = $this->processBatch($batch);
            $results = array_merge($results, $batchResults);
        }
        
        return $results;
    }
}
```

### **3. Security Best Practices**

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SecureWorkflowController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('throttle:60,1'); // Rate limiting
    }

    public function manageWorkflow(Request $request, int $orderId)
    {
        // Authorization check
        Gate::authorize('workflow.manage');
        
        // Input validation
        $validated = $request->validate([
            'action' => 'required|in:pause,resume,cancel',
            'reason' => 'required_if:action,pause|string|max:255',
        ]);

        // Audit logging
        Log::info('Workflow management action', [
            'user_id' => auth()->id(),
            'order_id' => $orderId,
            'action' => $validated['action'],
            'ip_address' => $request->ip(),
        ]);

        // Process request
        return $this->processWorkflowAction($orderId, $validated);
    }
}
```

---

## 🔗 Related Documentation

- [API Documentation](api-documentation.md)
- [Service Architecture](service-architecture.md)
- [Console Commands](console-commands.md)
- [Operational Runbooks](operational-runbooks.md)
- [Configuration Guide](configuration-guide.md)
