# Complete API Reference

## 📡 **API Overview**

The Laravel Reverse Tender Platform provides a comprehensive REST API with **80+ endpoints** across multiple service categories. All APIs support both REST and RPC protocols with consistent response formats.

### **Base URLs**
- **Development**: `http://localhost:8001/api`
- **Production**: `https://your-domain.com/api`

### **Authentication**
```bash
# Include authentication headers
Authorization: Bearer {your-token}
X-API-Key: {your-api-key}
```

### **Common Headers**
```bash
Content-Type: application/json
Accept: application/json
X-Trace-ID: {unique-request-id}
X-Source-Service: {calling-service-name}
```

## 🔄 **Response Format**

All API endpoints return a consistent response format:

```json
{
  "success": true,
  "data": {
    // Response data
  },
  "error": null,
  "metadata": {
    "procedure": "procedure_name",
    "execution_time_ms": 123.45,
    "trace_id": "abc123",
    "timestamp": "2024-01-01T12:00:00Z"
  }
}
```

## 🏗️ **Core Infrastructure APIs**

### **1. Event Publishing API**
Base path: `/api/event-publishing`

#### **Publish Event**
```bash
POST /api/event-publishing/publish
```

**Request Body:**
```json
{
  "event_type": "user_registered",
  "event_data": {
    "user_id": 123,
    "email": "user@example.com",
    "timestamp": "2024-01-01T12:00:00Z"
  },
  "target_services": ["notification", "analytics"],
  "priority": "high"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "event_id": "evt_abc123",
    "published_at": "2024-01-01T12:00:00Z",
    "target_services": ["notification", "analytics"],
    "delivery_status": "queued"
  }
}
```

#### **Get Event Status**
```bash
GET /api/event-publishing/status/{eventId}
```

#### **Subscribe to Events**
```bash
POST /api/event-publishing/subscribe
```

**Request Body:**
```json
{
  "service_name": "analytics",
  "event_types": ["user_registered", "order_created"],
  "webhook_url": "https://analytics.example.com/webhooks/events"
}
```

### **2. Cache Management API**
Base path: `/api/cache-management`

#### **Set Cache**
```bash
POST /api/cache-management/set
```

**Request Body:**
```json
{
  "key": "user_profile_123",
  "value": {
    "name": "John Doe",
    "email": "john@example.com"
  },
  "ttl": 3600,
  "tags": ["user", "profile"]
}
```

#### **Get Cache**
```bash
GET /api/cache-management/get/{key}
```

#### **Delete Cache**
```bash
DELETE /api/cache-management/delete/{key}
```

#### **Flush Cache by Tags**
```bash
POST /api/cache-management/flush-tags
```

**Request Body:**
```json
{
  "tags": ["user", "profile"]
}
```

### **3. Notification API**
Base path: `/api/notification`

#### **Send Notification**
```bash
POST /api/notification/send
```

**Request Body:**
```json
{
  "type": "email",
  "recipient": "user@example.com",
  "template": "welcome_email",
  "data": {
    "name": "John Doe",
    "verification_url": "https://example.com/verify/abc123"
  },
  "priority": "high",
  "schedule_at": null
}
```

#### **Send Bulk Notifications**
```bash
POST /api/notification/send-bulk
```

**Request Body:**
```json
{
  "notifications": [
    {
      "type": "email",
      "recipient": "user1@example.com",
      "template": "newsletter",
      "data": {"name": "User 1"}
    },
    {
      "type": "sms",
      "recipient": "+1234567890",
      "template": "order_update",
      "data": {"order_id": "ORD123"}
    }
  ],
  "batch_size": 10
}
```

#### **Get Notification Status**
```bash
GET /api/notification/status/{notificationId}
```

### **4. Validation API**
Base path: `/api/validation`

#### **Validate Data**
```bash
POST /api/validation/validate
```

**Request Body:**
```json
{
  "data": {
    "email": "user@example.com",
    "name": "John Doe",
    "age": 25
  },
  "rules": {
    "email": "required|email",
    "name": "required|string|min:2",
    "age": "required|integer|min:18"
  }
}
```

#### **Custom Validation**
```bash
POST /api/validation/custom
```

**Request Body:**
```json
{
  "validator": "email_domain_check",
  "data": {
    "email": "user@example.com"
  },
  "options": {
    "allowed_domains": ["example.com", "company.com"]
  }
}
```

### **5. Security API**
Base path: `/api/security`

#### **Encrypt Data**
```bash
POST /api/security/encrypt
```

**Request Body:**
```json
{
  "data": "sensitive information",
  "algorithm": "AES-256-GCM",
  "key_id": "key_123"
}
```

#### **Decrypt Data**
```bash
POST /api/security/decrypt
```

#### **Generate Token**
```bash
POST /api/security/generate-token
```

**Request Body:**
```json
{
  "user_id": 123,
  "permissions": ["read", "write"],
  "expires_in": 3600
}
```

#### **Verify Token**
```bash
POST /api/security/verify-token
```

## 🛡️ **Circuit Breaker APIs**

### **6. Synchronous Circuit Breaker API**
Base path: `/api/circuit-breaker`

#### **Get Circuit Breaker Stats**
```bash
GET /api/circuit-breaker/stats/{serviceName?}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "service_name": "payment_service",
    "state": "CLOSED",
    "failure_count": 2,
    "success_count": 98,
    "failure_rate": 2.0,
    "last_failure_time": "2024-01-01T11:30:00Z",
    "next_attempt_time": null
  }
}
```

#### **Reset Circuit Breaker**
```bash
POST /api/circuit-breaker/reset
```

**Request Body:**
```json
{
  "service_name": "payment_service"
}
```

#### **Force Open Circuit Breaker**
```bash
POST /api/circuit-breaker/force-open
```

### **7. Queue Circuit Breaker API**
Base path: `/api/queue-circuit-breaker`

#### **Dispatch Job with Circuit Breaker**
```bash
POST /api/queue-circuit-breaker/dispatch
```

**Request Body:**
```json
{
  "job_class": "App\\Jobs\\PaymentProcessingJob",
  "service_name": "stripe",
  "job_data": {
    "payment_intent_id": "pi_abc123",
    "amount": 2000,
    "currency": "usd"
  },
  "queue": "payments",
  "delay": 0
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "job_id": "job_abc123",
    "service_name": "stripe",
    "circuit_state": "CLOSED",
    "dispatched_at": "2024-01-01T12:00:00Z",
    "queue": "payments"
  }
}
```

#### **Get Queue Circuit Breaker Stats**
```bash
GET /api/queue-circuit-breaker/stats/{serviceName?}
```

#### **Get Queue Health**
```bash
GET /api/queue-circuit-breaker/health?queue=default
```

**Response:**
```json
{
  "success": true,
  "data": {
    "queue_name": "default",
    "queue_size": 15,
    "failed_jobs": 2,
    "circuit_states": {
      "stripe": "CLOSED",
      "mailgun": "OPEN",
      "twilio": "HALF_OPEN"
    },
    "health_status": "degraded"
  }
}
```

## 🔌 **Third-Party Integration API**
Base path: `/api/third-party-integration`

### **Initialize Integration**
```bash
POST /api/third-party-integration/initialize
```

**Request Body:**
```json
{
  "service_name": "stripe",
  "integration_type": "stripe",
  "config": {
    "secret_key": "sk_test_...",
    "webhook_secret": "whsec_...",
    "rate_limit": {
      "max_requests": 100,
      "window_size": 1
    }
  }
}
```

### **Make API Call**
```bash
POST /api/third-party-integration/api-call
```

**Request Body:**
```json
{
  "service_name": "stripe",
  "method": "POST",
  "endpoint": "/payment_intents",
  "data": {
    "amount": 2000,
    "currency": "usd",
    "customer": "cus_abc123"
  },
  "headers": {
    "Idempotency-Key": "unique_key_123"
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "status_code": 200,
    "response_data": {
      "id": "pi_abc123",
      "amount": 2000,
      "currency": "usd",
      "status": "requires_payment_method"
    },
    "execution_time_ms": 245.67
  }
}
```

### **Handle Webhook**
```bash
POST /api/third-party-integration/webhook
```

**Request Body:**
```json
{
  "service_name": "stripe",
  "payload": "{\"id\":\"evt_abc123\",\"type\":\"payment_intent.succeeded\"}",
  "signature": "t=1234567890,v1=abc123..."
}
```

### **Test Connection**
```bash
POST /api/third-party-integration/test-connection
```

**Request Body:**
```json
{
  "service_name": "stripe"
}
```

### **Get Integration Stats**
```bash
GET /api/third-party-integration/stats/{serviceName?}
```

## 🔄 **Workflow Orchestration API**
Base path: `/api/workflow`

### **Start Workflow**
```bash
POST /api/workflow/start
```

**Request Body:**
```json
{
  "workflow_name": "user_onboarding",
  "workflow_params": {
    "email": "user@example.com",
    "name": "John Doe",
    "password": "secure_password",
    "marketing_consent": true
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_id": "wf_user_onboarding_abc123_1704110400",
    "workflow_name": "user_onboarding",
    "status": "running",
    "started_at": "2024-01-01T12:00:00Z",
    "total_steps": 11,
    "current_step": 1
  }
}
```

### **Get Workflow Status**
```bash
GET /api/workflow/status/{workflowId}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "workflow_id": "wf_user_onboarding_abc123_1704110400",
    "workflow_name": "user_onboarding",
    "status": "completed",
    "current_step_index": 11,
    "total_steps": 11,
    "progress_percentage": 100.0,
    "started_at": "2024-01-01T12:00:00Z",
    "completed_at": "2024-01-01T12:02:30Z",
    "executed_steps": 11,
    "error": null
  }
}
```

### **Register Workflow Definition**
```bash
POST /api/workflow/register
```

**Request Body:**
```json
{
  "workflow_name": "custom_order_process",
  "definition": {
    "name": "custom_order_process",
    "description": "Custom order processing workflow",
    "steps": [
      {
        "name": "validate_order",
        "type": "micro",
        "procedure": "validation",
        "method": "validateData",
        "params": {
          "rules": {
            "customer_id": "required|integer",
            "items": "required|array|min:1"
          }
        },
        "use_workflow_params": true
      },
      {
        "name": "process_payment",
        "type": "micro",
        "procedure": "queue_circuit_breaker",
        "method": "dispatchWithCircuitBreaker",
        "params": {
          "job_class": "App\\Jobs\\PaymentProcessingJob",
          "service_name": "stripe"
        },
        "compensation": {
          "method": "refundPayment",
          "params": {"payment_id": "{{payment_id}}"}
        }
      }
    ]
  }
}
```

### **Execute Simple Workflow**
```bash
POST /api/workflow/execute-simple
```

**Request Body:**
```json
{
  "steps": [
    {
      "name": "send_email",
      "type": "micro",
      "procedure": "notification",
      "method": "sendNotification",
      "params": {
        "type": "email",
        "template": "welcome"
      }
    }
  ],
  "workflow_params": {
    "email": "user@example.com",
    "name": "John Doe"
  }
}
```

## 📊 **Built-in Workflow Examples**

### **User Onboarding Workflow**
```bash
POST /api/workflow/start
```

**Request Body:**
```json
{
  "workflow_name": "user_onboarding",
  "workflow_params": {
    "email": "user@example.com",
    "name": "John Doe",
    "password": "secure_password",
    "phone": "+1234567890",
    "date_of_birth": "1990-01-01",
    "terms_accepted": true,
    "marketing_consent": true,
    "source": "web"
  }
}
```

**Workflow Steps:**
1. Validate user registration data
2. Check email domain reputation
3. Encrypt sensitive data
4. Create user account
5. Generate verification token
6. Send welcome communications (parallel: email, SMS)
7. Setup default preferences
8. Create user profile
9. Assign default role
10. Track onboarding event
11. Schedule onboarding followup

### **Order Processing Workflow**
```bash
POST /api/workflow/start
```

**Request Body:**
```json
{
  "workflow_name": "order_processing",
  "workflow_params": {
    "customer_id": 123,
    "items": [
      {
        "product_id": 456,
        "quantity": 2,
        "price": 29.99
      }
    ],
    "total_amount": 59.98,
    "currency": "usd",
    "shipping_address": {
      "street": "123 Main St",
      "city": "Anytown",
      "state": "CA",
      "zip": "12345"
    },
    "billing_address": {
      "street": "123 Main St",
      "city": "Anytown",
      "state": "CA",
      "zip": "12345"
    }
  }
}
```

**Workflow Steps:**
1. Validate order data
2. Check customer authorization
3. Verify inventory availability
4. Calculate order totals
5. Process payment
6. Create order record
7. Update inventory
8. Send order confirmations (parallel: customer email, SMS, fulfillment)
9. Schedule fulfillment

## 🔍 **Error Handling**

### **Error Response Format**
```json
{
  "success": false,
  "data": null,
  "error": "Validation failed: email field is required",
  "metadata": {
    "procedure": "validation",
    "error_code": "VALIDATION_ERROR",
    "trace_id": "abc123",
    "timestamp": "2024-01-01T12:00:00Z"
  }
}
```

### **Common HTTP Status Codes**
- `200` - Success
- `400` - Bad Request (validation errors, missing parameters)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (resource not found)
- `429` - Too Many Requests (rate limiting)
- `500` - Internal Server Error
- `503` - Service Unavailable (circuit breaker open)

### **Circuit Breaker Error Response**
```json
{
  "success": false,
  "error": "Service temporarily unavailable (circuit breaker open)",
  "data": {
    "circuit_breaker_open": true,
    "service_name": "payment_service",
    "retry_after": 60
  }
}
```

## 📈 **Rate Limiting**

All API endpoints are subject to rate limiting:

- **Default Limit**: 1000 requests per hour per API key
- **Burst Limit**: 100 requests per minute
- **Headers**: Rate limit information is included in response headers

```bash
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1704114000
```

## 🔧 **Testing Examples**

### **cURL Examples**

**Test Circuit Breaker:**
```bash
curl -X GET http://localhost:8001/api/circuit-breaker/stats \
  -H "Content-Type: application/json" \
  -H "X-Trace-ID: test-123"
```

**Start User Onboarding Workflow:**
```bash
curl -X POST http://localhost:8001/api/workflow/start \
  -H "Content-Type: application/json" \
  -H "X-Trace-ID: test-456" \
  -d '{
    "workflow_name": "user_onboarding",
    "workflow_params": {
      "email": "test@example.com",
      "name": "Test User",
      "password": "secure123"
    }
  }'
```

**Dispatch Queue Job with Circuit Breaker:**
```bash
curl -X POST http://localhost:8001/api/queue-circuit-breaker/dispatch \
  -H "Content-Type: application/json" \
  -d '{
    "job_class": "App\\Jobs\\TestJob",
    "service_name": "test_service",
    "job_data": {"test": "data"}
  }'
```

This comprehensive API reference covers all available endpoints with detailed request/response examples, error handling, and testing guidance.

