<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">📡 Order Service API Documentation</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Comprehensive API documentation for <strong>33 endpoints</strong> implemented in the Laravel Workflow Saga Pattern, organized into logical groups for easy navigation and integration.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 API Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🔄 Order Workflow Management**: 8 endpoints for workflow initiation, execution, and lifecycle management
- **📊 Comprehensive Analytics**: Dead letter queue management, metrics, dashboards, and correlation tracking (18 endpoints)
- **🔔 Signal & Alert Systems**: Workflow signals and intelligent alerting with real-time notifications (9 endpoints)

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">📡 Complete API Documentation</summary>

### API Endpoint Groups

1. **Order Workflow Management** (8 endpoints)
2. **Dead Letter Queue Management** (5 endpoints)  
3. **Workflow Metrics & Analytics** (4 endpoints)
4. **Workflow Dashboards** (3 endpoints)
5. **Workflow Signals** (7 endpoints)
6. **Correlation & Tracing** (4 endpoints)
7. **Workflow Alerts** (2 endpoints)

### Order Workflow Management

#### **POST** `/orders/{id}/workflow/initiate`
Initiate a new workflow for an order.

**Parameters:**
- `id` (path, required): Order ID

**Request Body:**
```json
{
  "correlation_id": "corr-65c3f2a1b4d8e-a1b2c3d4e5f6",
  "activities": ["payment", "inventory", "shipping"],
  "priority": "high"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_id": "order-saga-123",
    "correlation_id": "corr-65c3f2a1b4d8e-a1b2c3d4e5f6",
    "status": "initiated",
    "activities": ["payment", "inventory", "shipping"],
    "created_at": "2026-02-08T05:30:00Z"
  }
}
```

### **GET** `/orders/{id}/workflow/status`
Get current workflow status for an order.

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_id": "order-saga-123",
    "status": "running",
    "current_activity": "payment",
    "completed_activities": [],
    "failed_activities": [],
    "progress": 33,
    "estimated_completion": "2026-02-08T05:35:00Z"
  }
}
```

### **POST** `/orders/{id}/workflow/pause`
Pause a running workflow.

**Request Body:**
```json
{
  "reason": "Manual review required",
  "user_id": "user-123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_id": "order-saga-123",
    "status": "paused",
    "reason": "Manual review required",
    "paused_at": "2026-02-08T05:32:00Z"
  }
}
```

### **POST** `/orders/{id}/workflow/resume`
Resume a paused workflow.

**Request Body:**
```json
{
  "user_id": "user-123",
  "notes": "Review completed, resuming workflow"
}
```

### **POST** `/orders/{id}/workflow/intervention`
Request manual intervention for a workflow.

**Request Body:**
```json
{
  "reason": "Payment gateway timeout",
  "priority": "high",
  "requester_id": "user-123"
}
```

### **GET** `/orders/{id}/workflow/signals`
Get all signals for a workflow.

**Response:**
```json
{
  "success": true,
  "data": {
    "signals": [
      {
        "id": "signal-456",
        "type": "pause",
        "status": "processed",
        "created_at": "2026-02-08T05:30:00Z"
      }
    ],
    "total": 1
  }
}
```

### **POST** `/orders/{id}/workflow/signal`
Send a signal to a workflow.

**Request Body:**
```json
{
  "signal_type": "external_signal",
  "payload": {
    "action": "update_priority",
    "new_priority": "critical"
  }
}
```

### **GET** `/orders/{id}/workflow/history`
Get workflow execution history.

**Response:**
```json
{
  "success": true,
  "data": {
    "events": [
      {
        "event": "workflow_initiated",
        "timestamp": "2026-02-08T05:30:00Z",
        "data": {"correlation_id": "corr-123"}
      },
      {
        "event": "activity_started",
        "activity": "payment",
        "timestamp": "2026-02-08T05:30:15Z"
      }
    ]
  }
}
```

---

## 🔄 2. Dead Letter Queue Management

### **GET** `/workflow/dlq/statistics`
Get DLQ statistics and metrics.

**Response:**
```json
{
  "success": true,
  "data": {
    "pending_retries": 5,
    "manual_interventions": 2,
    "daily_failures": 8,
    "resolution_rate": 94.5,
    "by_activity_type": {
      "payment": 3,
      "inventory": 1,
      "shipping": 1
    }
  }
}
```

### **GET** `/workflow/dlq/manual-interventions`
Get manual intervention queue.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "failure_id": "dlq-failure-789",
      "workflow_id": "order-saga-123",
      "activity_type": "payment",
      "reason": "Payment gateway timeout",
      "priority": "high",
      "created_at": "2026-02-08T05:25:00Z"
    }
  ]
}
```

### **POST** `/workflow/dlq/retry/{failureId}`
Retry a specific failed activity.

**Parameters:**
- `failureId` (path, required): DLQ failure ID

**Response:**
```json
{
  "success": true,
  "data": {
    "failure_id": "dlq-failure-789",
    "retry_attempt": 3,
    "status": "retrying",
    "scheduled_at": "2026-02-08T05:35:00Z"
  }
}
```

### **POST** `/workflow/dlq/resolve/{failureId}`
Resolve a manual intervention.

**Request Body:**
```json
{
  "resolution": "manual_fix_applied",
  "notes": "Payment processed manually",
  "resolver_id": "user-123"
}
```

### **POST** `/workflow/dlq/process-retry-queue`
Process the DLQ retry queue in batch.

**Request Body:**
```json
{
  "batch_size": 10,
  "max_retries": 5
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "processed": 8,
    "successful": 6,
    "failed": 2,
    "batch_id": "batch-456"
  }
}
```

---

## 📊 3. Workflow Metrics & Analytics

### **GET** `/workflow/metrics/overview`
Get workflow overview metrics.

**Response:**
```json
{
  "success": true,
  "data": {
    "total_workflows": 1250,
    "active_workflows": 45,
    "completed_today": 128,
    "failed_today": 3,
    "success_rate": 97.7,
    "avg_execution_time": 2.3
  }
}
```

### **GET** `/workflow/metrics/activities`
Get activity-specific metrics.

**Query Parameters:**
- `timeframe` (optional): 24h, 7d, 30d (default: 24h)
- `activity_type` (optional): payment, inventory, shipping

**Response:**
```json
{
  "success": true,
  "data": {
    "payment": {
      "total_executions": 245,
      "success_rate": 98.5,
      "avg_execution_time": 0.8,
      "failure_reasons": ["timeout", "invalid_card"]
    },
    "inventory": {
      "total_executions": 198,
      "success_rate": 99.2,
      "avg_execution_time": 0.6,
      "failure_reasons": ["out_of_stock"]
    }
  }
}
```

### **GET** `/workflow/metrics/compensations`
Get compensation activity metrics.

**Response:**
```json
{
  "success": true,
  "data": {
    "total_compensations": 15,
    "by_activity_type": {
      "payment_refund": 8,
      "inventory_release": 4,
      "shipping_cancel": 3
    },
    "success_rate": 96.7
  }
}
```

### **GET** `/workflow/metrics/performance`
Get performance metrics.

**Query Parameters:**
- `timeframe` (optional): 24h, 7d, 30d (default: 24h)

**Response:**
```json
{
  "success": true,
  "data": {
    "execution_times": {
      "avg": 2.3,
      "p95": 4.1,
      "p99": 8.7
    },
    "throughput": {
      "workflows_per_hour": 156,
      "peak_throughput": 203
    },
    "error_rate": 2.3
  }
}
```

---

## 🖥️ 4. Workflow Dashboards

### **GET** `/workflow/dashboard/executive`
Get executive dashboard data.

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_overview": {
      "total_active": 45,
      "completed_today": 128,
      "failed_today": 3,
      "success_rate": 97.7
    },
    "real_time_status": {
      "paused_workflows": 2,
      "pending_interventions": 1,
      "dlq_items": 5
    },
    "trend_data": [
      {
        "date": "2026-02-07",
        "completed": 145,
        "failed": 5
      }
    ]
  },
  "refresh_interval": 30
}
```

### **GET** `/workflow/dashboard/operations`
Get operations dashboard data.

**Response:**
```json
{
  "success": true,
  "data": {
    "active_workflows": {
      "count": 45,
      "by_status": {
        "running": 40,
        "paused": 3,
        "waiting": 2
      }
    },
    "dlq_status": {
      "pending_retries": 5,
      "manual_interventions": 2
    },
    "signal_status": {
      "total_signals_today": 23,
      "pause_signals": 8,
      "resume_signals": 7
    }
  },
  "refresh_interval": 10
}
```

### **GET** `/workflow/dashboard/performance`
Get performance dashboard data.

**Query Parameters:**
- `timeframe` (optional): 24h, 7d, 30d (default: 24h)

**Response:**
```json
{
  "success": true,
  "data": {
    "execution_metrics": {
      "avg_duration": 2.3,
      "p95_duration": 4.1,
      "p99_duration": 8.7
    },
    "throughput_metrics": {
      "workflows_per_hour": 156,
      "peak_throughput": 203
    },
    "correlation_metrics": {
      "total_correlations": 1250,
      "avg_correlation_duration": 2.1
    }
  },
  "refresh_interval": 60
}
```

---

## 📡 5. Workflow Signals

### **POST** `/workflow/signals/pause`
Send pause signal to workflow.

**Request Body:**
```json
{
  "workflow_id": "order-saga-123",
  "reason": "Manual review required",
  "user_id": "user-123"
}
```

### **POST** `/workflow/signals/resume`
Send resume signal to workflow.

**Request Body:**
```json
{
  "workflow_id": "order-saga-123",
  "user_id": "user-123"
}
```

### **POST** `/workflow/signals/intervention`
Request manual intervention.

**Request Body:**
```json
{
  "workflow_id": "order-saga-123",
  "reason": "Payment gateway timeout",
  "priority": "high"
}
```

### **POST** `/workflow/signals/external`
Send external signal to workflow.

**Request Body:**
```json
{
  "workflow_id": "order-saga-123",
  "signal_type": "priority_update",
  "payload": {
    "new_priority": "critical"
  }
}
```

### **GET** `/workflow/signals/{workflowId}`
Get signals for specific workflow.

### **GET** `/workflow/signals/active`
Get all active signals.

### **DELETE** `/workflow/signals/{signalId}`
Cancel a pending signal.

---

## 🔗 6. Correlation & Tracing

### **GET** `/correlation/{correlationId}/trace`
Get complete trace for correlation ID.

**Response:**
```json
{
  "success": true,
  "data": {
    "correlation_id": "corr-65c3f2a1b4d8e",
    "spans": [
      {
        "span_id": "span-123",
        "operation": "ProcessPaymentActivity",
        "duration": 0.8,
        "status": "success"
      }
    ],
    "rpc_calls": [
      {
        "service": "payment-service",
        "method": "processPayment",
        "duration": 0.6
      }
    ]
  }
}
```

### **GET** `/correlation/{correlationId}/spans`
Get spans for correlation ID.

### **GET** `/correlation/{correlationId}/rpc-calls`
Get RPC calls for correlation ID.

### **POST** `/correlation/context/propagate`
Propagate correlation context.

**Request Body:**
```json
{
  "correlation_id": "corr-123",
  "target_service": "payment-service",
  "operation": "processPayment"
}
```

---

## 🚨 7. Workflow Alerts

### **GET** `/workflow/alerts/recent`
Get recent alerts.

**Query Parameters:**
- `limit` (optional): Number of alerts to return (default: 20)

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "alert_65c3f2a1b4d8e",
      "type": "dlq_threshold",
      "severity": "warning",
      "message": "DLQ pending count (12) exceeds threshold (10)",
      "timestamp": "2026-02-08T05:30:00Z"
    }
  ]
}
```

### **GET** `/workflow/alerts/statistics`
Get alert statistics.

**Response:**
```json
{
  "success": true,
  "data": {
    "today": {
      "total": 15,
      "critical": 2,
      "warning": 10,
      "info": 3
    },
    "by_type": {
      "dlq_threshold": 5,
      "failure_rate": 3,
      "response_time": 2
    }
  }
}
```

---

## 🔐 Authentication & Authorization

All API endpoints require proper authentication. Include the following headers:

```http
Authorization: Bearer {your-api-token}
Content-Type: application/json
Accept: application/json
```

---

## 📝 Error Handling

All endpoints follow consistent error response format:

```json
{
  "success": false,
  "error": "Error type",
  "message": "Detailed error message",
  "code": "ERROR_CODE",
  "timestamp": "2026-02-08T05:30:00Z"
}
```

### Common Error Codes
- `WORKFLOW_NOT_FOUND` (404)
- `INVALID_SIGNAL_TYPE` (400)
- `CORRELATION_NOT_FOUND` (404)
- `DLQ_FAILURE_NOT_FOUND` (404)
- `UNAUTHORIZED` (401)
- `RATE_LIMIT_EXCEEDED` (429)

---

## 🚀 Rate Limiting

API endpoints are rate limited:
- **Dashboard endpoints**: 60 requests per minute
- **Signal endpoints**: 30 requests per minute
- **DLQ endpoints**: 20 requests per minute
- **Metrics endpoints**: 100 requests per minute

---

## 📊 Response Formats

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": "2026-02-08T05:30:00Z",
    "version": "2.0.0"
  }
}
```

### Paginated Response
```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

---

## 🔗 Related Documentation

- [Service Architecture](service-architecture.md)
- [Console Commands](console-commands.md)
- [Monitoring & Alerting](monitoring-alerting.md)
- [Developer Integration Guide](developer-integration.md)
