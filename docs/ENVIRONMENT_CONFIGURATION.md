# Environment Configuration Guide

This document explains the environment configuration patterns and special configurations used across the microservices architecture.

## Standard Environment Files

All services follow a consistent pattern for environment configuration:

### Required Files
- `.env.example` - Template with all required environment variables
- `.env.testing` - Testing-specific configuration (now standardized across all services)

### Optional Files
- `.env.local` - Local development overrides
- `.env.production` - Production-specific settings (managed via deployment)

## Service-Specific Configurations

### Standard Configuration Pattern

Each service uses the following standard environment variables:

```bash
# Application Configuration
APP_NAME="Service Name"
APP_ENV=local
APP_KEY=base64:generated-key
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_URL=http://localhost:PORT

# Service Identification
SERVICE_NAME=service-name
SERVICE_PORT=PORT
SERVICE_VERSION=1.0.0

# Database Configuration (handled by failover system)
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=service_database
DB_USERNAME=username
DB_PASSWORD=password

# Cache and Session
CACHE_STORE=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=redis

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

## Special Configuration Files

### 1. Auth Service Failover Configuration

**File**: `services/auth-service/.env.failover-example`

**Purpose**: Demonstrates the three-tier database failover strategy for the authentication service.

**Configuration Tiers**:
1. **Primary**: Neon PostgreSQL (serverless, auto-scaling)
2. **Secondary**: Cloud Provider PostgreSQL (DigitalOcean/Linode/Azure)
3. **Fallback**: MongoDB Atlas (NoSQL alternative)

**Key Variables**:
```bash
# Primary Database (Neon PostgreSQL)
NEON_DATABASE_URL=postgresql://user:password@host:5432/database
NEON_DB_HOST=your-neon-host.neon.tech
NEON_DB_DATABASE=reverse_tender_auth

# Secondary Database (Cloud PostgreSQL)
CLOUD_DATABASE_URL=postgresql://user:password@host:5432/database
CLOUD_DB_HOST=your-cloud-host.com

# Fallback Database (MongoDB Atlas)
MONGO_DB_HOST=your-cluster.mongodb.net
MONGO_DB_DATABASE=reverse_tender_auth

# Failover Settings
DATABASE_FAILOVER_ENABLED=true
DB_AUTOMATIC_FAILOVER=true
DB_GRACEFUL_DEGRADATION=true
```

### 2. Payment Service PostgreSQL Configuration

**File**: `services/payment-service/.env.postgresql`

**Purpose**: Production-ready PostgreSQL configuration for payment processing with enhanced security and performance settings.

**Key Features**:
- Production-optimized database settings
- Enhanced security configurations
- Payment-specific cache and session management
- Broadcasting configuration for real-time payment updates

**Key Variables**:
```bash
# PostgreSQL Optimized Configuration
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=payment_service
DB_USERNAME=payment_user
DB_PASSWORD=secure_payment_password

# Performance Settings
CACHE_STORE=redis
CACHE_PREFIX=payment_service
SESSION_DRIVER=database

# Broadcasting for Real-time Updates
BROADCAST_CONNECTION=pusher
```

## Database Failover Architecture

### Overview

The system implements a sophisticated three-tier database failover strategy:

1. **Neon PostgreSQL** (Primary)
   - Serverless PostgreSQL with auto-scaling
   - Automatic suspend/resume based on activity
   - Branch-based development workflow
   - Connection pooling enabled

2. **Cloud Provider PostgreSQL** (Secondary)
   - Cloud-Native PostgreSQL (CNPG) on Kubernetes
   - High availability with replica clusters
   - Automated backups and WAL retention
   - Streaming replication

3. **MongoDB Atlas** (Fallback)
   - NoSQL alternative for graceful degradation
   - Collection mapping from relational tables
   - Async/sync data synchronization strategies
   - Replica set configuration

### Failover Configuration

Each service includes database failover configuration:

```php
// config/database-failover.php
'connections' => [
    'primary' => 'neon_postgresql',
    'secondary' => 'cloud_postgresql', 
    'fallback' => 'mongodb_atlas',
],

'mongodb_fallback' => [
    'collection_mapping' => [
        'users' => 'user_profiles',
        'payments' => 'payment_transactions',
        'orders' => 'order_data',
    ],
],
```

### Middleware Integration

All services use the `DatabaseFailoverMiddleware` which:
- Monitors database health continuously
- Automatically switches connections on failure
- Implements circuit breaker pattern
- Provides graceful degradation
- Logs failover events for monitoring

## Testing Configuration

### Standardized Testing Environment

All services now have consistent `.env.testing` files with:

```bash
APP_ENV=testing
APP_DEBUG=true
LOG_CHANNEL=single

# Database handled by phpunit.xml (typically SQLite in-memory)
SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

# Service-specific settings
SERVICE_NAME=service-name
SERVICE_PORT=PORT
```

### Testing Database Strategy

- **Unit Tests**: SQLite in-memory database
- **Integration Tests**: Docker PostgreSQL containers
- **E2E Tests**: Full database failover stack

## Port Assignments

Each service has a dedicated port for consistent service discovery:

| Service | Port | URL |
|---------|------|-----|
| auth-service | 8000 | http://localhost:8000 |
| user-service | 8001 | http://localhost:8001 |
| auction-service | 8002 | http://localhost:8002 |
| bidding-service | 8003 | http://localhost:8003 |
| payment-service | 8004 | http://localhost:8004 |
| order-service | 8005 | http://localhost:8005 |
| notification-service | 8006 | http://localhost:8006 |
| analytics-service | 8007 | http://localhost:8007 |
| vin-ocr-service | 8008 | http://localhost:8008 |
| gateway-service | 8080 | http://localhost:8080 |

## Environment-Specific Overrides

### Development
- Debug mode enabled
- Detailed logging
- Local database connections
- Relaxed security settings

### Staging
- Production-like configuration
- Staging database connections
- Enhanced monitoring
- Security hardening

### Production
- Debug mode disabled
- Optimized performance settings
- Production database connections
- Full security measures
- Comprehensive monitoring

## Security Considerations

### Environment Variable Security

1. **Never commit actual .env files** - only .env.example templates
2. **Use strong, unique passwords** for each environment
3. **Rotate database credentials** regularly
4. **Use encrypted connections** (SSL/TLS) for all database connections
5. **Implement proper access controls** for environment variable management

### Database Security

1. **Connection encryption** - All database connections use SSL/TLS
2. **Credential rotation** - Automated credential rotation for production
3. **Network isolation** - Database access restricted to application networks
4. **Audit logging** - All database operations logged for security monitoring

## Monitoring and Alerting

### Database Failover Monitoring

- **Health checks** every 10 seconds
- **Failover alerts** via webhook notifications
- **Performance metrics** collected via Prometheus
- **Dashboard** for real-time failover status

### Configuration Drift Detection

- **Environment validation** on application startup
- **Configuration comparison** between environments
- **Automated alerts** for configuration inconsistencies

## Best Practices

1. **Use environment-specific configurations** for different deployment targets
2. **Validate environment variables** on application startup
3. **Document all custom configurations** in this file
4. **Test failover scenarios** regularly
5. **Monitor configuration drift** between environments
6. **Use secure credential management** systems in production
7. **Implement configuration validation** in CI/CD pipelines

## Troubleshooting

### Common Issues

1. **Missing environment variables** - Check .env.example for required variables
2. **Database connection failures** - Verify failover configuration
3. **Port conflicts** - Ensure each service uses its assigned port
4. **Configuration inconsistencies** - Compare with standardized templates

### Debug Commands

```bash
# Check environment configuration
php artisan config:show

# Test database connections
php artisan db:show

# Validate failover configuration
php artisan db:failover:status

# Test service connectivity
php artisan service:health-check
```
