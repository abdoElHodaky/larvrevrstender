# Laravel Reverse Tender Platform

## 🚀 **Enterprise Microservices Architecture with Advanced Workflow Orchestration**

A comprehensive Laravel-based reverse tender platform featuring **enterprise-grade microservices architecture** with advanced **workflow orchestration**, **circuit breaker patterns**, and **third-party integration capabilities**.

## 📋 **Table of Contents**

- [🏗️ Architecture Overview](#️-architecture-overview)
- [✨ Key Features](#-key-features)
- [🔧 Infrastructure Components](#-infrastructure-components)
- [📡 API Endpoints](#-api-endpoints)
- [🚀 Quick Start](#-quick-start)
- [📖 Documentation](#-documentation)
- [🧪 Testing](#-testing)
- [🔄 Deployment](#-deployment)

## 🏗️ **Architecture Overview**

### **Microservices Architecture**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Gateway API   │    │  Shared Service │    │   Auth Service  │
│   (Routing)     │◄──►│ (Orchestration) │◄──►│ (Authentication)│
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Tender Service  │    │ Bidding Service │    │Notification Svc │
│   (Tenders)     │    │    (Bids)       │    │  (Messages)     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Payment Service │    │ Analytics Svc   │    │ External APIs   │
│   (Payments)    │    │   (Metrics)     │    │ (Integrations)  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### **Cross-Service Infrastructure**
```
┌─────────────────────────────────────────────────────────────────┐
│                    Cross-Service Infrastructure                  │
├─────────────────────────────────────────────────────────────────┤
│  🔄 Workflow Orchestration  │  🛡️ Circuit Breakers  │  🔌 Integrations │
│  • Macro Procedures         │  • Sync & Async       │  • Third-Party   │
│  • State Management         │  • Queue Protection    │  • Webhooks       │
│  • Compensation Logic       │  • Auto Recovery       │  • Authentication │
├─────────────────────────────────────────────────────────────────┤
│  📊 Micro Procedures        │  🚀 Queue Management   │  🔐 Security      │
│  • Event Publishing         │  • Circuit Breaking    │  • Token Auth     │
│  • Cache Management         │  • Job Dispatch        │  • Rate Limiting  │
│  • Notifications            │  • Health Monitoring   │  • Encryption     │
│  • Validation               │  • Statistics          │  • Authorization  │
└─────────────────────────────────────────────────────────────────┘
```

## ✨ **Key Features**

### **🔄 Advanced Workflow Orchestration**
- **Macro Procedures Framework** - Complex multi-step business workflows
- **State Management** - Persistent workflow state with recovery capabilities
- **Compensation Logic** - Automatic rollback for failed workflows
- **Parallel & Sequential Execution** - Optimized workflow performance
- **Conditional Branching** - Dynamic workflow paths based on runtime conditions

### **🛡️ Comprehensive Fault Tolerance**
- **Dual Circuit Breaker Patterns** - Synchronous and asynchronous protection
- **Laravel Fuse Integration** - Queue job circuit breaking with intelligent failure classification
- **Auto Recovery** - Automatic service recovery testing and restoration
- **Exponential Backoff** - Intelligent retry mechanisms with configurable delays
- **Release Limiting** - Prevents infinite retry loops in queue processing

### **🔌 Third-Party Integration Framework**
- **Standardized Integration Patterns** - Consistent external service connectivity
- **Authentication Strategies** - Bearer tokens, API keys, OAuth2 support
- **Rate Limiting & Retry Logic** - Intelligent request management
- **Webhook Security** - Signature verification and secure event handling
- **Circuit Breaker Protection** - Fault tolerance for external service calls

### **📊 Production-Ready Monitoring**
- **Comprehensive Logging** - Structured logging with correlation IDs
- **Health Monitoring** - Service health checks and status reporting
- **Performance Metrics** - Execution time tracking and optimization
- **Circuit Breaker Statistics** - Real-time fault tolerance monitoring
- **Workflow Progress Tracking** - Complete workflow lifecycle visibility

## 🔧 **Infrastructure Components**

### **Micro Procedures (8 Total)**
| Procedure | Description | Key Features |
|-----------|-------------|--------------|
| **Event Publishing** | Event-driven communication | Async messaging, event routing |
| **Cache Management** | Distributed caching operations | Redis integration, TTL management |
| **Notification** | Multi-channel notifications | Email, SMS, push notifications |
| **Validation** | Data validation and sanitization | Rule-based validation, custom rules |
| **Security** | Authentication and authorization | Token management, encryption |
| **Circuit Breaker** | Synchronous fault tolerance | HTTP request protection |
| **Queue Circuit Breaker** | Asynchronous fault tolerance | Queue job protection, Laravel Fuse |
| **Third-Party Integration** | External service management | API calls, webhooks, authentication |

### **Macro Procedures (2 Types)**
| Type | Description | Examples |
|------|-------------|----------|
| **Workflow Orchestration** | Complex business process management | Order processing, user onboarding |
| **Business Logic** | Domain-specific workflows | Payment processing, auction management |

### **Built-in Workflows**
- **User Onboarding** - Complete registration and setup process
- **Order Processing** - End-to-end order lifecycle management
- **Data Synchronization** - Cross-service data consistency

## 📡 **API Endpoints**

### **Core Infrastructure (65+ endpoints)**
```
/api/
├── event-publishing/          # Event management
├── cache-management/          # Caching operations
├── notification/              # Multi-channel messaging
├── validation/                # Data validation
├── security/                  # Authentication & authorization
├── circuit-breaker/           # Sync fault tolerance
├── queue-circuit-breaker/     # Async fault tolerance
├── third-party-integration/   # External services
└── workflow/                  # Workflow orchestration
```

### **Queue Circuit Breaker API**
```bash
# Dispatch job with circuit breaker protection
POST /api/queue-circuit-breaker/dispatch
{
  "job_class": "App\\Jobs\\PaymentProcessingJob",
  "service_name": "stripe",
  "job_data": {...}
}

# Get circuit breaker statistics
GET /api/queue-circuit-breaker/stats/stripe

# Reset circuit breaker
POST /api/queue-circuit-breaker/reset
{
  "service_name": "stripe"
}

# Get queue health status
GET /api/queue-circuit-breaker/health?queue=default
```

### **Third-Party Integration API**
```bash
# Initialize service integration
POST /api/third-party-integration/initialize
{
  "service_name": "stripe",
  "integration_type": "stripe",
  "config": {...}
}

# Make authenticated API call
POST /api/third-party-integration/api-call
{
  "service_name": "stripe",
  "method": "POST",
  "endpoint": "/payment_intents",
  "data": {...}
}

# Handle webhook
POST /api/third-party-integration/webhook
{
  "service_name": "stripe",
  "payload": "...",
  "signature": "..."
}
```

### **Workflow Orchestration API**
```bash
# Start workflow
POST /api/workflow/start
{
  "workflow_name": "user_onboarding",
  "workflow_params": {...}
}

# Get workflow status
GET /api/workflow/status/{workflowId}

# Register custom workflow
POST /api/workflow/register
{
  "workflow_name": "custom_process",
  "definition": {...}
}

# Execute simple workflow
POST /api/workflow/execute-simple
{
  "steps": [...],
  "workflow_params": {...}
}
```

## 🚀 **Quick Start**

### **Prerequisites**
- PHP 8.1+
- Laravel 10+
- Redis (for caching and circuit breaker state)
- MySQL/PostgreSQL
- Composer

### **Installation**

1. **Clone the repository**
```bash
git clone https://github.com/abdoElHodaky/larvrevrstender.git
cd larvrevrstender
```

2. **Install dependencies**
```bash
composer install
npm install
```

3. **Environment setup**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure services**
```bash
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_tender
DB_USERNAME=root
DB_PASSWORD=

# Redis (required for circuit breakers)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Circuit Breaker Configuration
FUSE_ENABLED=true
FUSE_DEFAULT_THRESHOLD=50
FUSE_DEFAULT_TIMEOUT=60

# Third-Party Services
STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET=your_stripe_secret_key
STRIPE_WEBHOOK_SECRET=your_webhook_secret
```

5. **Run migrations**
```bash
php artisan migrate
```

6. **Start the application**
```bash
php artisan serve
```

### **Testing the Infrastructure**

1. **Test Circuit Breaker**
```bash
curl -X POST http://localhost:8000/api/circuit-breaker/stats
```

2. **Test Queue Circuit Breaker**
```bash
curl -X GET http://localhost:8000/api/queue-circuit-breaker/health
```

3. **Test Workflow Orchestration**
```bash
curl -X POST http://localhost:8000/api/workflow/start \
  -H "Content-Type: application/json" \
  -d '{"workflow_name": "user_onboarding", "workflow_params": {"email": "test@example.com", "name": "Test User"}}'
```

## 📖 **Documentation**

### **Architecture Guides**
- [Micro Procedures](docs/architecture/micro-procedures.md) - Atomic operation building blocks
- [Macro Procedures](docs/architecture/macro-procedures.md) - Complex workflow orchestration
- [Circuit Breaker Patterns](docs/architecture/circuit-breakers.md) - Fault tolerance strategies
- [Third-Party Integrations](docs/architecture/integrations.md) - External service patterns

### **Developer Guides**
- [Creating Custom Workflows](docs/guides/workflow-development.md)
- [Third-Party Integration Development](docs/guides/integration-development.md)
- [Circuit Breaker Configuration](docs/guides/circuit-breaker-config.md)
- [Monitoring and Observability](docs/guides/monitoring.md)

### **API Documentation**
- [REST API Reference](docs/api/rest-endpoints.md)
- [RPC API Reference](docs/api/rpc-methods.md)
- [Workflow API](docs/api/workflow-api.md)
- [Integration API](docs/api/integration-api.md)

## 🧪 **Testing**

### **Running Tests**
```bash
# Unit tests
php artisan test --testsuite=Unit

# Feature tests
php artisan test --testsuite=Feature

# Integration tests
php artisan test --testsuite=Integration

# All tests
php artisan test
```

### **Circuit Breaker Testing**
```bash
# Test circuit breaker functionality
php artisan test tests/Feature/CircuitBreakerTest.php

# Test queue circuit breaker
php artisan test tests/Feature/QueueCircuitBreakerTest.php
```

### **Workflow Testing**
```bash
# Test workflow orchestration
php artisan test tests/Feature/WorkflowOrchestrationTest.php

# Test macro procedures
php artisan test tests/Unit/MacroProcedures/
```

## 🔄 **Deployment**

### **Docker Deployment**
```bash
# Build and start services
docker-compose up -d

# Scale services
docker-compose up -d --scale shared-service=3
```

### **Kubernetes Deployment**
```bash
# Apply configurations
kubectl apply -f deployment/kubernetes/

# Check status
kubectl get pods -l app=laravel-tender
```

### **Production Configuration**

1. **Circuit Breaker Tuning**
```env
# Production circuit breaker settings
FUSE_DEFAULT_THRESHOLD=30
FUSE_DEFAULT_TIMEOUT=120
FUSE_QUEUE_MAX_RELEASES=5
```

2. **Queue Configuration**
```env
# Queue worker settings
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database
```

3. **Monitoring Setup**
```env
# Enable comprehensive monitoring
FUSE_MONITORING_ENABLED=true
FUSE_LOG_STATE_CHANGES=true
FUSE_METRICS_ENABLED=true
```

## 🤝 **Contributing**

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### **Development Guidelines**
- Follow PSR-12 coding standards
- Write comprehensive tests for new features
- Update documentation for API changes
- Ensure circuit breaker compatibility
- Test workflow integrations thoroughly

## 📊 **Performance Metrics**

### **Current Architecture Stats**
- **8 Microservices** with full connectivity
- **8 Micro Procedures** for atomic operations
- **2 Macro Procedure Types** for complex workflows
- **80+ API Endpoints** (REST + RPC + Workflow)
- **~12,000+ lines** of production-ready code
- **Dual Circuit Breaker Protection** (sync + async)
- **Built-in Workflow Definitions** for common business processes

### **Fault Tolerance Features**
- **Circuit Breaker States**: CLOSED → OPEN → HALF_OPEN with automatic recovery
- **Intelligent Failure Classification**: 5xx errors trigger circuits, 4xx errors ignored
- **Queue Protection**: Exponential backoff with configurable max releases
- **Service Recovery**: Automatic testing and restoration of failed services

## 📄 **License**

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 **Acknowledgments**

- Laravel Framework for the robust foundation
- Laravel Fuse for queue circuit breaker patterns
- Redis for high-performance caching and state management
- The open-source community for inspiration and best practices

---

**Built with ❤️ for enterprise-grade microservices architecture**

