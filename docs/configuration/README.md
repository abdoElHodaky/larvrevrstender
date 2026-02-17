# Configuration Management Guide

This guide explains the configuration architecture and management for the Reverse Tender Platform.

## 📋 Table of Contents

- [Configuration Architecture](#configuration-architecture)
- [ConfigMap Structure](#configmap-structure)
- [Environment-Specific Configuration](#environment-specific-configuration)
- [Secret Management](#secret-management)
- [Service Configuration](#service-configuration)
- [Best Practices](#best-practices)

## 🏗️ Configuration Architecture

The platform uses a layered configuration approach:

```
Base Configuration (Shared)
├── Common Settings (all services)
├── Service-Specific Settings
└── Microservice Patterns

Environment Overlays
├── Development Overrides
├── Staging Overrides
└── Production Overrides

Secrets (Sensitive Data)
├── Application Secrets
├── Database Credentials
├── External Service Keys
└── Environment-Specific Secrets
```

## 📦 ConfigMap Structure

### Base ConfigMaps

#### 1. Common Configuration (`common-config.yaml`)

Shared settings across all services:

```yaml
# Database Configuration
DB_CONNECTION: mysql
DB_HOST: mysql
DB_PORT: 3306
DB_DATABASE: reverse_tender

# Redis Configuration
REDIS_HOST: redis
REDIS_PORT: 6379
REDIS_DB: 0

# Application Settings
APP_NAME: "Reverse Tender Platform"
APP_TIMEZONE: UTC
APP_LOCALE: en
LOG_CHANNEL: stack
CACHE_DRIVER: redis
SESSION_DRIVER: redis
QUEUE_CONNECTION: redis
```

#### 2. Gateway Configuration (`gateway-config.yaml`)

Gateway service specific settings:

```yaml
# Gateway Service Configuration
GATEWAY_SERVICE_NAME: gateway-service
GATEWAY_PORT: 8000
GATEWAY_HEALTH_ENDPOINT: /health

# Routing Configuration
GATEWAY_TIMEOUT: 30
GATEWAY_RETRY_ATTEMPTS: 3
GATEWAY_CIRCUIT_BREAKER_THRESHOLD: 5

# Rate Limiting
RATE_LIMIT_ENABLED: true
RATE_LIMIT_REQUESTS_PER_MINUTE: 60
RATE_LIMIT_BURST: 10

# Authentication
JWT_ALGO: RS256
JWT_TTL: 900
REFRESH_TTL: 43200
```

#### 3. Service Discovery Configuration (`service-discovery-config.yaml`)

Service registry and discovery settings:

```yaml
# Service Registry
SERVICE_REGISTRY_ENABLED: true
SERVICE_REGISTRY_TTL: 30
SERVICE_REGISTRY_HEARTBEAT_INTERVAL: 10

# Health Check Configuration
HEALTH_CHECK_ENABLED: true
HEALTH_CHECK_INTERVAL: 30s
HEALTH_CHECK_TIMEOUT: 10s
HEALTH_CHECK_RETRIES: 3

# Service Endpoints
AUTH_SERVICE_URL: http://auth-service:8001
AUCTION_SERVICE_URL: http://auction-service:8002
BIDDING_SERVICE_URL: http://bidding-service:8003
USER_SERVICE_URL: http://user-service:8004
ORDER_SERVICE_URL: http://order-service:8005
NOTIFICATION_SERVICE_URL: http://notification-service:8006
PAYMENT_SERVICE_URL: http://payment-service:8007
ANALYTICS_SERVICE_URL: http://analytics-service:8008
VIN_OCR_SERVICE_URL: http://vin-ocr-service:8009
```

#### 4. Resilience Configuration (`resilience-config.yaml`)

Circuit breaker and retry policies:

```yaml
# Circuit Breaker Configuration
CIRCUIT_BREAKER_ENABLED: true
CIRCUIT_BREAKER_FAILURE_THRESHOLD: 5
CIRCUIT_BREAKER_RECOVERY_TIMEOUT: 60
CIRCUIT_BREAKER_TIMEOUT: 30

# Retry Configuration
RETRY_ENABLED: true
RETRY_MAX_ATTEMPTS: 3
RETRY_DELAY: 1000
RETRY_BACKOFF_MULTIPLIER: 2

# Timeout Configuration
DEFAULT_TIMEOUT: 30
DATABASE_TIMEOUT: 10
CACHE_TIMEOUT: 5
HTTP_CLIENT_TIMEOUT: 30
```

#### 5. Saga Configuration (`saga-config.yaml`)

Distributed transaction coordination:

```yaml
# Saga Coordinator Configuration
SAGA_COORDINATOR_ENABLED: true
SAGA_COORDINATOR_TIMEOUT: 300
SAGA_COORDINATOR_RETRY_ATTEMPTS: 3

# Saga Transaction Settings
SAGA_TRANSACTION_TIMEOUT: 180
SAGA_COMPENSATION_TIMEOUT: 60
SAGA_STEP_TIMEOUT: 30

# Saga Workflows
SAGA_ORDER_WORKFLOW_ENABLED: true
SAGA_PAYMENT_WORKFLOW_ENABLED: true
SAGA_AUCTION_WORKFLOW_ENABLED: true
```

#### 6. Event Bus Configuration (`event-bus-config.yaml`)

Inter-service event communication:

```yaml
# Event Bus Configuration
EVENT_BUS_ENABLED: true
EVENT_BUS_DRIVER: redis
EVENT_BUS_CONNECTION: redis

# Event Channels
EVENT_CHANNEL_AUCTIONS: auctions
EVENT_CHANNEL_BIDS: bids
EVENT_CHANNEL_ORDERS: orders
EVENT_CHANNEL_PAYMENTS: payments
EVENT_CHANNEL_NOTIFICATIONS: notifications
EVENT_CHANNEL_USERS: users

# Event Persistence
EVENT_STORE_ENABLED: true
EVENT_STORE_TTL: 2592000
EVENT_REPLAY_ENABLED: true
```

## 🌍 Environment-Specific Configuration

### Development Environment

```yaml
# Development-specific settings
APP_ENV: development
APP_DEBUG: true
LOG_LEVEL: debug

# Reduced timeouts for faster development
CACHE_TTL: 60
SESSION_LIFETIME: 120
JWT_TTL: 3600

# Development features
METRICS_ENABLED: true
TRACING_ENABLED: true
CIRCUIT_BREAKER_ENABLED: false
RETRY_MAX_ATTEMPTS: 1

# Development rate limiting
API_RATE_LIMIT: 1000
HEALTH_CHECK_INTERVAL: 60s
```

### Staging Environment

```yaml
# Staging-specific settings
APP_ENV: staging
APP_DEBUG: false
LOG_LEVEL: info

# Production-like timeouts
CACHE_TTL: 300
SESSION_LIFETIME: 7200
JWT_TTL: 1800

# Staging features
METRICS_ENABLED: true
TRACING_ENABLED: true
CIRCUIT_BREAKER_ENABLED: true
RETRY_MAX_ATTEMPTS: 2

# Staging rate limiting
API_RATE_LIMIT: 500
HEALTH_CHECK_INTERVAL: 30s
SAGA_COORDINATOR_TIMEOUT: 180
RATE_LIMIT_REQUESTS_PER_SECOND: 50
```

### Production Environment

```yaml
# Production-specific settings
APP_ENV: production
APP_DEBUG: false
LOG_LEVEL: warning

# Production timeouts
CACHE_TTL: 3600
SESSION_LIFETIME: 28800
JWT_TTL: 900

# Production features
METRICS_ENABLED: true
TRACING_ENABLED: true
CIRCUIT_BREAKER_ENABLED: true
RETRY_MAX_ATTEMPTS: 3

# Production rate limiting
API_RATE_LIMIT: 100
HEALTH_CHECK_INTERVAL: 15s
SAGA_COORDINATOR_TIMEOUT: 300
RATE_LIMIT_REQUESTS_PER_SECOND: 20

# Production optimizations
OCTANE_WORKERS: 8
OCTANE_TASK_WORKERS: 12
OCTANE_MAX_REQUESTS: 1000
OCTANE_MEMORY_LIMIT: 768M
PHP_OPCACHE_ENABLE: 1
PHP_OPCACHE_MEMORY_CONSUMPTION: 256
PHP_OPCACHE_VALIDATE_TIMESTAMPS: 0
```

## 🔐 Secret Management

### Secret Categories

#### 1. Application Secrets

```yaml
# JWT and encryption keys
JWT_SECRET: "your-jwt-secret-key"
JWT_REFRESH_SECRET: "your-jwt-refresh-secret"
APP_KEY: "base64:your-laravel-app-key"
ENCRYPTION_KEY: "your-encryption-key"
SESSION_ENCRYPT_KEY: "your-session-key"
```

#### 2. Database Secrets

```yaml
# Database credentials
DB_PASSWORD: "your-database-password"
MYSQL_ROOT_PASSWORD: "your-mysql-root-password"
DATABASE_URL: "mysql://user:password@host:port/database"
```

#### 3. External Service Secrets

```yaml
# Payment gateways
STRIPE_SECRET_KEY: "sk_live_your_stripe_secret"
STRIPE_WEBHOOK_SECRET: "whsec_your_webhook_secret"
PAYPAL_CLIENT_SECRET: "your_paypal_secret"

# Notification services
PUSHER_APP_SECRET: "your_pusher_secret"
FCM_SERVER_KEY: "your_fcm_key"

# File storage
AWS_ACCESS_KEY_ID: "your_aws_access_key"
AWS_SECRET_ACCESS_KEY: "your_aws_secret_key"
```

### Secret Deployment

```bash
# Create secrets from literals
kubectl create secret generic app-secrets \
  --from-literal=JWT_SECRET="your-secret" \
  --from-literal=DB_PASSWORD="your-password" \
  -n reverse-tender

# Create secrets from files
kubectl create secret generic app-secrets \
  --from-file=JWT_SECRET=./jwt-secret.txt \
  --from-file=DB_PASSWORD=./db-password.txt \
  -n reverse-tender

# Apply from YAML
kubectl apply -f deployment/k8s/base/secrets/app-secrets.yaml
```

## ⚙️ Service Configuration

### Gateway Service

```yaml
# Gateway-specific configuration
GATEWAY_SERVICE_NAME: gateway-service
GATEWAY_PORT: 8000
GATEWAY_HEALTH_ENDPOINT: /health

# Routing and load balancing
GATEWAY_TIMEOUT: 30
GATEWAY_RETRY_ATTEMPTS: 3
GATEWAY_LOAD_BALANCER_ALGORITHM: round_robin

# Authentication and authorization
JWT_ALGO: RS256
JWT_TTL: 900
OAUTH_ENABLED: true
```

### Auth Service

```yaml
# Authentication configuration
AUTH_SERVICE_NAME: auth-service
AUTH_PORT: 8001
AUTH_TOKEN_TTL: 900
AUTH_REFRESH_TTL: 43200

# Password policies
PASSWORD_MIN_LENGTH: 8
PASSWORD_REQUIRE_UPPERCASE: true
PASSWORD_REQUIRE_NUMBERS: true
PASSWORD_REQUIRE_SYMBOLS: true

# OAuth providers
GOOGLE_OAUTH_ENABLED: true
FACEBOOK_OAUTH_ENABLED: true
```

### Bidding Service

```yaml
# Bidding service configuration
BIDDING_SERVICE_NAME: bidding-service
BIDDING_PORT: 8003
BIDDING_WEBSOCKET_PORT: 8080

# Real-time bidding
WEBSOCKET_ENABLED: true
WEBSOCKET_HEARTBEAT_INTERVAL: 30
BID_TIMEOUT: 300
BID_INCREMENT_MINIMUM: 1.00

# Auction rules
AUCTION_EXTENSION_TIME: 300
AUCTION_SOFT_CLOSE_ENABLED: true
```

## 📋 Best Practices

### Configuration Management

1. **Separation of Concerns**
   - Keep sensitive data in secrets
   - Use ConfigMaps for non-sensitive configuration
   - Separate environment-specific settings

2. **Naming Conventions**
   - Use consistent naming across environments
   - Prefix environment-specific configs
   - Use descriptive names for configuration keys

3. **Version Control**
   - Track configuration changes in Git
   - Use meaningful commit messages
   - Review configuration changes

### Environment Management

1. **Environment Parity**
   - Keep environments as similar as possible
   - Use the same configuration structure
   - Test configuration changes in staging first

2. **Configuration Validation**
   - Validate configuration before deployment
   - Use schema validation where possible
   - Test configuration changes thoroughly

3. **Documentation**
   - Document all configuration options
   - Explain the purpose of each setting
   - Keep documentation up to date

### Security

1. **Secret Management**
   - Never store secrets in ConfigMaps
   - Use Kubernetes secrets for sensitive data
   - Rotate secrets regularly

2. **Access Control**
   - Limit access to configuration files
   - Use RBAC for Kubernetes resources
   - Audit configuration changes

3. **Encryption**
   - Encrypt secrets at rest
   - Use TLS for data in transit
   - Implement proper key management

## 🔧 Configuration Updates

### Updating ConfigMaps

```bash
# Update ConfigMap
kubectl patch configmap common-config \
  -p='{"data":{"LOG_LEVEL":"info"}}' \
  -n reverse-tender

# Restart deployments to pick up changes
kubectl rollout restart deployment/gateway-service -n reverse-tender
```

### Rolling Updates

```bash
# Update configuration and deploy
kubectl apply -k deployment/k8s/overlays/production

# Monitor rollout
kubectl rollout status deployment/gateway-service -n reverse-tender

# Verify configuration
kubectl get configmap common-config -o yaml -n reverse-tender
```

### Configuration Validation

```bash
# Validate Kustomize configuration
kustomize build deployment/k8s/overlays/production

# Dry run deployment
kubectl apply -k deployment/k8s/overlays/production --dry-run=client

# Validate with kubeval (if available)
kustomize build deployment/k8s/overlays/production | kubeval
```

---

**Last Updated**: February 2026  
**Version**: 1.0.0
