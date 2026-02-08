# Service Architecture - Laravel Workflow Saga Pattern

## 📋 Overview

This document provides detailed documentation of the service architecture for the Laravel Workflow Saga Pattern implementation. It covers service layer design, component interactions, dependency management, and integration patterns.

---

## 🏗️ Service Layer Architecture

### **Service Hierarchy**

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application Layer                        │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │   Controllers   │  │   API Routes    │  │   Middleware    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────┐
│                         Service Layer                          │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ Event Publisher │  │ Signal Handler  │  │ Alerting Service│ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ DLQ Service     │  │ Correlation     │  │ Tracing Service │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────────┐
│                      Infrastructure Layer                      │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │ Queue System    │  │ Cache Layer     │  │ Broadcasting    │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔧 Core Services

### 1. WorkflowEventPublisher

**Purpose:** Centralized event publishing for workflow lifecycle events.

**Responsibilities:**
- Publish workflow events (OrderInitiated, ActivityCompleted, etc.)
- Handle event broadcasting for real-time updates
- Manage event correlation and context propagation
- Integrate with external event systems

**Key Methods:**
```php
public function publishOrderInitiated(int $orderId, string $correlationId): void
public function publishActivityCompleted(string $workflowId, string $activityType, array $result): void
public function publishActivityFailed(string $workflowId, string $activityType, string $reason): void
public function publishWorkflowCompleted(string $workflowId, array $summary): void
public function publishWorkflowFailed(string $workflowId, string $reason, array $context): void
```

**Dependencies:**
- Laravel Event System
- Broadcasting Service
- CorrelationService
- Cache (for metrics)

**Configuration:**
```php
// config/workflow.php
'events' => [
    'broadcasting' => [
        'enabled' => true,
        'channels' => ['workflow.status', 'workflow.metrics'],
    ],
    'correlation' => [
        'auto_propagate' => true,
        'header_name' => 'X-Correlation-ID',
    ],
],
```

### 2. WorkflowSignalHandler

**Purpose:** Handle workflow signals for pause, resume, and intervention operations.

**Responsibilities:**
- Process workflow signals (pause, resume, manual_intervention, external_signal)
- Manage signal queuing and priority handling
- Coordinate with DLQ service for intervention escalation
- Broadcast signal events for real-time updates

**Key Methods:**
```php
public function pauseWorkflow(string $workflowId, string $reason, string $userId): array
public function resumeWorkflow(string $workflowId, string $userId): array
public function requestManualIntervention(string $workflowId, string $reason, string $priority): array
public function processExternalSignal(string $workflowId, string $signalType, array $payload): array
public function getWorkflowSignals(string $workflowId): array
```

**Signal Types:**
- **pause**: Temporarily halt workflow execution
- **resume**: Continue paused workflow execution
- **manual_intervention**: Request human intervention
- **external_signal**: Generic signal with custom payload

**Queue Configuration:**
```php
// Queue priorities for signal processing
'signals-high'   => ['timeout' => 30, 'retry_after' => 60],
'signals-medium' => ['timeout' => 60, 'retry_after' => 120],
'signals-low'    => ['timeout' => 120, 'retry_after' => 300],
```

### 3. WorkflowDeadLetterQueue

**Purpose:** Manage failed activities with intelligent retry and escalation.

**Responsibilities:**
- Store failed activities with context and retry metadata
- Implement exponential backoff retry strategy
- Escalate to manual intervention after max retries
- Provide statistics and management interfaces

**Key Methods:**
```php
public function addFailedActivity(string $workflowId, string $activityType, string $reason, array $context): string
public function retryFailedActivity(string $failureId): array
public function processRetryQueue(int $batchSize = 10): array
public function getStatistics(): array
public function getManualInterventionQueue(): array
public function resolveManualIntervention(string $failureId, string $resolution, string $resolverId): array
```

**Retry Strategy:**
```php
// Exponential backoff configuration
'retry_strategy' => [
    'initial_delay' => 30,      // seconds
    'max_delay' => 3600,        // 1 hour
    'multiplier' => 2,          // exponential factor
    'max_attempts' => 5,        // before manual intervention
    'jitter' => true,           // add randomization
],
```

### 4. CorrelationService

**Purpose:** Distributed correlation tracking across services.

**Responsibilities:**
- Generate and manage correlation IDs
- Track hierarchical span relationships
- Record RPC calls between services
- Provide context propagation utilities

**Key Methods:**
```php
public function generateCorrelationId(): string
public function getCurrentCorrelationId(): ?string
public function startSpan(string $operation, ?string $parentSpanId = null): string
public function finishSpan(string $spanId, array $result = []): void
public function recordRpcCall(string $service, string $method, float $duration, array $context = []): void
public function getCorrelationTrace(string $correlationId): array
public function propagateContext(array $headers = []): array
```

**Context Structure:**
```php
[
    'correlation_id' => 'corr-65c3f2a1b4d8e-a1b2c3d4e5f6',
    'trace_id' => 'trace-123',
    'span_id' => 'span-456',
    'parent_span_id' => 'span-789',
    'service' => 'order-service',
    'operation' => 'ProcessPaymentActivity',
    'started_at' => '2026-02-08T05:30:00Z',
]
```

### 5. WorkflowTracingService

**Purpose:** Enhanced tracing and observability for workflow operations.

**Responsibilities:**
- Create custom Telescope entries for workflow operations
- Tag entries for easy filtering and analysis
- Integrate with correlation service for distributed tracing
- Provide workflow-specific debugging information

**Key Methods:**
```php
public function recordWorkflowEvent(string $event, array $data): void
public function recordActivityExecution(string $activityType, float $duration, array $context): void
public function recordSignalProcessing(string $signalType, array $data): void
public function recordDlqOperation(string $operation, array $data): void
public function getWorkflowTrace(string $workflowId): array
```

### 6. WorkflowAlertingService

**Purpose:** Intelligent alerting and notification management.

**Responsibilities:**
- Evaluate alert rules and thresholds
- Send multi-channel notifications (Slack, email, PagerDuty, SMS)
- Store alert history and statistics
- Provide alert management interfaces

**Key Methods:**
```php
public function checkAlerts(): array
public function triggerDlqThresholdAlert(int $pendingCount, int $threshold): void
public function triggerCriticalInterventionAlert(string $workflowId, string $interventionId, string $reason): void
public function triggerFailureRateAlert(float $failureRate, float $threshold): void
public function getRecentAlerts(int $limit = 20): array
public function getAlertStatistics(): array
```

**Alert Rules Configuration:**
```php
'alert_rules' => [
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
],
```

---

## 🔗 Service Dependencies

### **Dependency Graph**

```
WorkflowDashboardController
    ├── WorkflowDeadLetterQueue
    ├── WorkflowSignalHandler
    └── Cache

WorkflowEventPublisher
    ├── Event System
    ├── Broadcasting
    ├── CorrelationService
    └── Cache

WorkflowSignalHandler
    ├── WorkflowDeadLetterQueue
    ├── Broadcasting
    ├── Queue System
    └── Cache

WorkflowDeadLetterQueue
    ├── Cache
    ├── Queue System
    └── WorkflowAlertingService

CorrelationService
    ├── Cache
    ├── WorkflowTracingService
    └── HTTP Client

WorkflowTracingService
    ├── Telescope
    ├── CorrelationService
    └── Cache

WorkflowAlertingService
    ├── Cache
    ├── Notification System
    └── HTTP Client
```

### **Service Registration**

All services are registered as singletons in `AppServiceProvider`:

```php
public function register(): void
{
    // Register workflow services as singletons
    $this->app->singleton(WorkflowEventPublisher::class);
    $this->app->singleton(WorkflowSignalHandler::class);
    $this->app->singleton(WorkflowDeadLetterQueue::class);
    $this->app->singleton(CorrelationService::class);
    $this->app->singleton(WorkflowTracingService::class);
    $this->app->singleton(WorkflowAlertingService::class);
}
```

---

## 📊 Data Flow Patterns

### **Event-Driven Flow**

```
Order Creation
    ↓
OrderController::store()
    ↓
WorkflowEventPublisher::publishOrderInitiated()
    ↓
Event Broadcasting → WebSocket → Dashboard Updates
    ↓
Event Listeners → Workflow Progression
    ↓
Activity Execution → Success/Failure Events
    ↓
WorkflowEventPublisher::publishActivity*()
```

### **Signal Processing Flow**

```
Signal Request (API/Console)
    ↓
WorkflowSignalHandler::processSignal()
    ↓
Queue Job Dispatch (Priority-based)
    ↓
ProcessWorkflowSignal Job
    ↓
Signal Processing Logic
    ↓
Broadcasting Events → Real-time Updates
    ↓
Cache Updates → Metrics
```

### **DLQ Management Flow**

```
Activity Failure
    ↓
WorkflowDeadLetterQueue::addFailedActivity()
    ↓
Retry Queue Storage
    ↓
ProcessDlqRetry Job (Scheduled/Manual)
    ↓
Exponential Backoff Logic
    ↓
Success → Remove from Queue
    ↓
Max Retries → Manual Intervention
    ↓
WorkflowAlertingService::triggerAlert()
```

### **Correlation Tracking Flow**

```
HTTP Request with Correlation Headers
    ↓
CorrelationService::extractContext()
    ↓
Span Creation → startSpan()
    ↓
Service-to-Service Calls → recordRpcCall()
    ↓
Child Span Creation → Hierarchical Tracking
    ↓
Span Completion → finishSpan()
    ↓
Telescope Integration → Custom Entries
```

---

## 🔧 Configuration Management

### **Service Configuration**

```php
// config/workflow.php
return [
    'services' => [
        'event_publisher' => [
            'broadcasting_enabled' => env('WORKFLOW_BROADCASTING_ENABLED', true),
            'correlation_auto_propagate' => env('WORKFLOW_CORRELATION_AUTO_PROPAGATE', true),
        ],
        'signal_handler' => [
            'default_priority' => env('WORKFLOW_SIGNAL_DEFAULT_PRIORITY', 'medium'),
            'timeout_seconds' => env('WORKFLOW_SIGNAL_TIMEOUT', 300),
        ],
        'dlq' => [
            'max_retries' => env('WORKFLOW_DLQ_MAX_RETRIES', 5),
            'initial_delay' => env('WORKFLOW_DLQ_INITIAL_DELAY', 30),
            'max_delay' => env('WORKFLOW_DLQ_MAX_DELAY', 3600),
        ],
        'correlation' => [
            'header_name' => env('WORKFLOW_CORRELATION_HEADER', 'X-Correlation-ID'),
            'trace_header' => env('WORKFLOW_TRACE_HEADER', 'X-Trace-ID'),
        ],
        'alerting' => [
            'enabled' => env('WORKFLOW_ALERTING_ENABLED', true),
            'check_interval' => env('WORKFLOW_ALERT_CHECK_INTERVAL', 60),
        ],
    ],
];
```

### **Queue Configuration**

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
    'workflow-signals' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'signals-medium',
        'retry_after' => 120,
    ],
    'workflow-dlq' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'dlq-processing',
        'retry_after' => 300,
    ],
],
```

---

## 🚀 Performance Considerations

### **Caching Strategy**

```php
// Cache key patterns
'workflow.metrics.{timeframe}.{metric_type}'
'workflow.signals.{workflow_id}'
'dlq.metrics.{date}'
'correlation.{correlation_id}.{type}'
'alerts.daily.{date}.{severity}'
```

### **Queue Optimization**

- **Priority-based queues** for signal processing
- **Activity-type-specific queues** for DLQ processing
- **Batch processing** for DLQ retry operations
- **Exponential backoff** for failed job retries

### **Database Optimization**

- **Indexed correlation IDs** for fast trace lookups
- **Partitioned tables** for large-scale workflow data
- **Read replicas** for dashboard and reporting queries
- **Connection pooling** for high-throughput scenarios

---

## 🔐 Security Considerations

### **Authentication & Authorization**

```php
// Middleware for workflow API endpoints
'middleware' => ['auth:api', 'workflow.permissions'],

// Permission-based access control
Gate::define('workflow.manage', function ($user) {
    return $user->hasRole(['admin', 'workflow-manager']);
});

Gate::define('workflow.view', function ($user) {
    return $user->hasRole(['admin', 'workflow-manager', 'operator']);
});
```

### **Data Protection**

- **Sensitive data masking** in logs and traces
- **Encryption** for stored correlation context
- **Rate limiting** on API endpoints
- **Input validation** for all service methods

---

## 🔗 Integration Patterns

### **Service-to-Service Communication**

```php
// HTTP client with correlation propagation
$response = Http::withHeaders([
    'X-Correlation-ID' => $correlationService->getCurrentCorrelationId(),
    'X-Trace-ID' => $correlationService->getCurrentTraceId(),
])->post('https://payment-service/api/process', $data);

// Record RPC call for tracing
$correlationService->recordRpcCall(
    'payment-service',
    'processPayment',
    $duration,
    ['request_id' => $requestId]
);
```

### **Event Integration**

```php
// External event system integration
Event::listen('workflow.*', function ($event) {
    // Forward to external event bus
    ExternalEventBus::publish($event);
});
```

### **Monitoring Integration**

```php
// Custom metrics for external monitoring
Metrics::increment('workflow.activity.completed', [
    'activity_type' => $activityType,
    'workflow_id' => $workflowId,
]);
```

---

## 🔗 Related Documentation

- [API Documentation](api-documentation.md)
- [Console Commands](console-commands.md)
- [Developer Integration Guide](developer-integration.md)
- [Operational Runbooks](operational-runbooks.md)
- [Configuration Guide](configuration-guide.md)
