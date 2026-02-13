<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🔧 Laravel Fuse Circuit Breaker Jobs</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Complete implementation of <strong>10 Laravel Fuse circuit breaker protected jobs</strong> across the microservices architecture with critical business functionality, operational efficiency, and comprehensive error handling.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Circuit Breaker Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🔧 10 Protected Jobs**: Circuit breaker protection across 6 services with 5,700+ lines of production-ready logic
- **🏗️ Base Architecture Pattern**: Consistent `BaseQueueJob` extension with service-specific configurations
- **📊 Dynamic Queue Routing**: Intelligent routing based on complexity, priority, and business criticality

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">🔧 Complete Laravel Fuse Documentation</summary>

### Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Priority 1 Jobs - Critical Business Functions](#priority-1-jobs)
4. [Priority 2 Jobs - Business Intelligence & Security](#priority-2-jobs)
5. [Priority 3 Jobs - Operational Efficiency](#priority-3-jobs)
6. [Circuit Breaker Configuration](#circuit-breaker-configuration)
7. [Queue Routing Strategy](#queue-routing-strategy)
8. [Deployment Guide](#deployment-guide)
9. [Monitoring & Troubleshooting](#monitoring--troubleshooting)
10. [Usage Examples](#usage-examples)

### Implementation Summary
- **Total Jobs**: 10 jobs across 6 services
- **Total Code**: ~5,700+ lines of production-ready logic
- **Services Covered**: Analytics, VIN-OCR, Auth, User, Shared, Notification, Payment
- **Circuit Breaker Protection**: All jobs with service-specific configurations
- **Queue Routing**: Dynamic routing based on complexity and priority

### Base Architecture Pattern

All jobs extend `BaseQueueJob` and follow a consistent pattern:

```php
class ExampleJob extends BaseQueueJob
{
    public function __construct(...) {
        parent::__construct();
        $this->configureCircuitBreaker([...]);
    }
    
    public function handle(): void {
        $this->executeWithCircuitBreaker(
            function() { /* business logic */ },
            function(Exception $e) { /* error handler */ }
        );
    }
    
    public function failed(Throwable $e): void { /* failure handler */ }
}
```

### **Circuit Breaker Integration**

Each job is protected by Laravel Fuse circuit breakers with:
- **Service-specific failure thresholds** (20-40% based on criticality)
- **Configurable timeouts** (2-20 minutes based on operation complexity)
- **Recovery timeouts** (2-4x service timeout for stabilization)
- **Comprehensive tagging** for monitoring and debugging

### **Queue Routing Strategy**

Three-level intelligent routing:
1. **Service Queue**: Primary queue by service
2. **Operation Queue**: Secondary queue by operation type
3. **Complexity Queue**: Tertiary queue by data volume/complexity

---

## 🎯 **Priority 1 Jobs - Critical Business Functions**

### **1. ProcessAnalyticsDataJob** (Analytics Service)

**Purpose**: Aggregates raw user analytics data into business metrics for BI and reporting.

**Key Features**:
- **4 Aggregation Types**: hourly, daily, weekly, monthly
- **5 Metric Types**: user_events, active_users, session_metrics, conversion_funnel, event_types
- **Queue Routing**: Based on aggregation priority (realtime/daily/weekly/monthly)
- **Circuit Breaker**: 30% failure threshold, 2-minute timeout

**Usage Example**:
```php
ProcessAnalyticsDataJob::dispatch(
    aggregationType: 'daily',
    targetDate: now()->subDay(),
    metricTypes: ['user_events_daily', 'active_users_daily']
);
```

**Business Impact**: Enables real-time business intelligence and executive dashboards.

---

### **2. ProcessVinOcrBatchJob** (VIN-OCR Service)

**Purpose**: Batch processes VIN images for OCR recognition - critical for user onboarding.

**Key Features**:
- **Batch Processing**: Configurable chunk sizes with memory management
- **VIN Validation**: Format validation and confidence scoring
- **Queue Routing**: Based on batch size (large/medium/small/default)
- **Circuit Breaker**: 40% failure threshold, 3-minute timeout

**Usage Example**:
```php
ProcessVinOcrBatchJob::dispatch(
    vinScanIds: [1, 2, 3, 4, 5],
    batchSize: 50,
    options: [
        'enhance_images' => true,
        'confidence_threshold' => 0.8
    ]
);
```

**Business Impact**: Automates vehicle identification for reverse tender platform.

---

### **3. CleanupExpiredTokensJob** (Auth Service)

**Purpose**: Cleans up expired authentication tokens and sessions for security/performance.

**Key Features**:
- **5 Cleanup Types**: personal_access_tokens, password_reset_tokens, expired_sessions, inactive_sessions, activity_logs
- **Configurable Retention**: Service-specific retention periods
- **Circuit Breaker**: 25% failure threshold, 5-minute timeout

**Usage Example**:
```php
CleanupExpiredTokensJob::dispatch(
    cleanupTypes: ['personal_access_tokens', 'expired_sessions'],
    retentionPeriods: ['personal_access_tokens' => 90],
    batchSize: 1000
);
```

**Business Impact**: Maintains database performance and reduces security attack surface.

---

## 🚀 **Priority 2 Jobs - Business Intelligence & Security**

### **4. ProcessUserProfileValidationJob** (User Service)

**Purpose**: Validates user profiles, KYC documents, and customer information for compliance.

**Key Features**:
- **6 Validation Types**: basic_profile, contact_verification, customer_profile, kyc_documents, profile_completeness, data_consistency
- **Severity Levels**: critical/warning/info with automated actions
- **Compliance Updates**: CustomerProfile verification status updates
- **Circuit Breaker**: 35% failure threshold, 3-minute timeout

**Usage Example**:
```php
ProcessUserProfileValidationJob::dispatch(
    userIds: [1, 2, 3, 4, 5],
    validationTypes: ['basic_profile', 'kyc_documents'],
    validationRules: ['completeness_threshold' => 80],
    batchSize: 50
);
```

**Business Impact**: Ensures regulatory compliance and platform trust.

---

### **5. GenerateBusinessReportsJob** (Analytics Service)

**Purpose**: Generates comprehensive business reports for executive dashboards and strategic decision-making.

**Key Features**:
- **7 Report Types**: executive_summary, user_engagement, conversion_funnel, revenue_analytics, platform_performance, geographic_distribution, cohort_analysis
- **Multiple Formats**: JSON, CSV, XML with file storage
- **MENA Focus**: Geographic analysis optimized for MENA region
- **Circuit Breaker**: 30% failure threshold, 10-minute timeout

**Usage Example**:
```php
GenerateBusinessReportsJob::dispatch(
    reportTypes: ['executive_summary', 'user_engagement'],
    startDate: now()->subWeek(),
    endDate: now(),
    reportOptions: ['save_to_file' => true],
    outputFormat: 'json'
);
```

**Business Impact**: Enables data-driven decision making and strategic planning.

---

### **6. ProcessSuspiciousLoginJob** (Auth Service)

**Purpose**: Analyzes login patterns for security threats and fraud prevention.

**Key Features**:
- **6 Analysis Types**: ip_reputation, geolocation_anomaly, device_fingerprint, login_frequency, failed_attempts, time_pattern
- **Risk Scoring**: Configurable thresholds for blocking/flagging/alerting
- **Automated Actions**: Account blocking, flagging, and alert generation
- **Circuit Breaker**: 20% failure threshold, 5-minute timeout (critical security)

**Usage Example**:
```php
ProcessSuspiciousLoginJob::dispatch(
    loginAttempts: $suspiciousAttempts,
    analysisTypes: ['ip_reputation', 'geolocation_anomaly'],
    securityRules: ['risk_threshold' => 50],
    batchSize: 100
);
```

**Business Impact**: Protects platform from fraud and unauthorized access.

---

## ⚙️ **Priority 3 Jobs - Operational Efficiency**

### **7. WarmCacheDataJob** (Shared Service)

**Purpose**: Proactively warms critical cache data to improve application performance.

**Key Features**:
- **6 Cache Types**: user_profiles, auction_data, analytics_metrics, system_config, notification_templates, payment_methods
- **Memory Tracking**: Usage monitoring and compression support
- **Queue Routing**: Based on cache complexity (large/heavy/medium/default)
- **Circuit Breaker**: 40% failure threshold, 5-minute timeout

**Usage Example**:
```php
WarmCacheDataJob::dispatch(
    cacheTypes: ['user_profiles', 'auction_data'],
    cacheOptions: ['user_profiles_ttl' => 1800],
    batchSize: 1000
);
```

**Business Impact**: Reduces database load and improves response times.

---

### **8. RotateApplicationLogsJob** (Shared Service)

**Purpose**: Manages log file rotation, compression, and cleanup to prevent disk space issues.

**Key Features**:
- **6 Log Types**: application_logs, access_logs, error_logs, audit_logs, performance_logs, security_logs
- **Compression**: Automatic gzip compression with configurable levels
- **Retention Policies**: Service-specific retention periods
- **Circuit Breaker**: 30% failure threshold, 10-minute timeout

**Usage Example**:
```php
RotateApplicationLogsJob::dispatch(
    logTypes: ['application_logs', 'error_logs'],
    rotationOptions: [
        'application_logs_retention_days' => 30,
        'compress_after_days' => 7
    ]
);
```

**Business Impact**: Prevents storage-related outages and maintains system performance.

---

### **9. OptimizeNotificationQueuesJob** (Notification Service)

**Purpose**: Optimizes notification queues by cleaning up failed jobs and rebalancing workloads.

**Key Features**:
- **6 Optimization Types**: cleanup_failed_jobs, requeue_stuck_jobs, remove_duplicate_jobs, optimize_job_priorities, balance_queue_load, cleanup_expired_jobs
- **Queue Health**: Scoring system with automated optimization
- **Load Balancing**: Dynamic job priority adjustment
- **Circuit Breaker**: 35% failure threshold, 10-minute timeout

**Usage Example**:
```php
OptimizeNotificationQueuesJob::dispatch(
    queueNames: ['notifications', 'emails', 'sms'],
    optimizationTypes: ['cleanup_failed_jobs', 'requeue_stuck_jobs'],
    optimizationOptions: ['failed_job_cleanup_hours' => 24]
);
```

**Business Impact**: Maintains notification delivery performance and prevents bottlenecks.

---

### **10. SyncPaymentReconciliationJob** (Payment Service)

**Purpose**: Synchronizes payment records with external gateways and reconciles discrepancies.

**Key Features**:
- **5 Reconciliation Types**: transaction_status, settlement_amounts, refund_status, chargeback_status, fee_reconciliation
- **Multi-Gateway**: Support for Stripe, PayPal, Square, Authorize.Net
- **Automated Resolution**: Discrepancy detection and resolution
- **Circuit Breaker**: 25% failure threshold, 15-minute timeout (strict for financial)

**Usage Example**:
```php
SyncPaymentReconciliationJob::dispatch(
    paymentGateways: ['stripe', 'paypal'],
    reconciliationDate: now()->subDay(),
    reconciliationTypes: ['transaction_status', 'settlement_amounts'],
    reconciliationOptions: ['auto_resolve_discrepancies' => true]
);
```

**Business Impact**: Ensures financial accuracy and prevents revenue leakage.

---

## 🔧 **Circuit Breaker Configuration**

### **Failure Threshold Strategy**

| Priority | Service Type | Threshold | Rationale |
|----------|-------------|-----------|-----------|
| **Critical Security** | Auth (security) | 20-25% | Strict for security operations |
| **Financial** | Payment | 25% | Strict for financial accuracy |
| **High Priority** | User validation | 35% | Important for compliance |
| **Batch Operations** | VIN OCR, Cache | 40% | Tolerant for batch processing |
| **Reporting** | Analytics | 30% | Moderate for report generation |

### **Timeout Configuration**

| Operation Type | Timeout | Recovery | Use Case |
|---------------|---------|----------|----------|
| **Quick Operations** | 2-5 min | 10-15 min | Analytics, Cache warming |
| **Moderate Operations** | 3-10 min | 10-20 min | Validation, Security analysis |
| **Long Operations** | 10-20 min | 20-40 min | Report generation, Reconciliation |
| **Maintenance** | 10-30 min | 15-45 min | Log rotation, Queue optimization |

---

## 🚦 **Queue Routing Strategy**

### **Service-Level Queues**

```yaml
Analytics Service:
  - analytics-realtime (Priority 1)
  - analytics-daily (Priority 2)
  - analytics-weekly (Priority 3)
  - analytics-monthly (Priority 4)

Auth Service:
  - auth-maintenance (Cleanup operations)
  - security-analysis-large (High-volume security)
  - security-analysis-default (Standard security)

User Service:
  - user-validation-large (200+ users)
  - user-validation-medium (100+ users)
  - user-validation-small (50+ users)
  - user-validation-default (< 50 users)

Shared Service:
  - cache-warming-large (5+ cache types)
  - cache-warming-heavy (Heavy cache types)
  - log-rotation (Log operations)

Notification Service:
  - queue-optimization (Queue maintenance)

Payment Service:
  - payment-reconciliation (Financial operations)
```

### **Queue Priority Mapping**

1. **Critical Operations**: security-analysis, payment-reconciliation
2. **High Priority**: user-validation, analytics-realtime
3. **Medium Priority**: reports, cache-warming
4. **Low Priority**: log-rotation, queue-optimization

---

## 🚀 **Deployment Guide**

### **1. Prerequisites**

```bash
# Ensure Laravel Fuse is installed
composer require timacdonald/laravel-fuse

# Verify Redis is configured for circuit breaker storage
php artisan config:cache
```

### **2. Environment Configuration**

```env
# Circuit Breaker Configuration
CIRCUIT_BREAKER_ENABLED=true
CIRCUIT_BREAKER_STORAGE=redis

# Queue Configuration
QUEUE_CONNECTION=redis
REDIS_HOST=your-redis-host
REDIS_PORT=6379

# Service URLs (for API calls)
AUTH_SERVICE_URL=http://auth-service:8001
USER_SERVICE_URL=http://user-service:8002
ANALYTICS_SERVICE_URL=http://analytics-service:8003
```

### **3. Queue Worker Configuration**

```bash
# Start queue workers for each service
php artisan queue:work --queue=analytics-realtime,analytics-daily --timeout=300
php artisan queue:work --queue=security-analysis-large,security-analysis-default --timeout=600
php artisan queue:work --queue=user-validation-large,user-validation-medium --timeout=900
php artisan queue:work --queue=cache-warming-large,cache-warming-heavy --timeout=600
php artisan queue:work --queue=payment-reconciliation --timeout=2400
```

### **4. Cron Job Setup**

```bash
# Add to crontab for automated execution
# Daily analytics processing
0 2 * * * php /path/to/artisan queue:dispatch "ProcessAnalyticsDataJob" --arguments='{"aggregationType":"daily"}'

# Weekly user validation
0 3 * * 0 php /path/to/artisan queue:dispatch "ProcessUserProfileValidationJob" --arguments='{"userIds":[]}'

# Daily log rotation
0 1 * * * php /path/to/artisan queue:dispatch "RotateApplicationLogsJob"

# Daily payment reconciliation
0 4 * * * php /path/to/artisan queue:dispatch "SyncPaymentReconciliationJob" --arguments='{"reconciliationDate":"yesterday"}'
```

### **5. Monitoring Setup**

```bash
# Install monitoring tools
composer require laravel/horizon
php artisan horizon:install
php artisan horizon:publish

# Configure Horizon dashboard
php artisan horizon
```

---

## 📊 **Monitoring & Troubleshooting**

### **Circuit Breaker Monitoring**

```php
// Check circuit breaker status
use Timacdonald\LaravelFuse\Fuse;

$status = Fuse::for('analytics_data_processing')->status();
// Returns: 'closed', 'open', or 'half-open'

// Get circuit breaker metrics
$metrics = Fuse::for('analytics_data_processing')->metrics();
```

### **Queue Health Monitoring**

```bash
# Monitor queue sizes
php artisan queue:monitor analytics-realtime,security-analysis-large,payment-reconciliation

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### **Common Issues & Solutions**

#### **Circuit Breaker Stuck Open**

```bash
# Reset circuit breaker
php artisan tinker
>>> Fuse::for('service_name')->reset();
```

#### **Queue Backlog**

```bash
# Scale up workers
php artisan queue:work --queue=high-priority-queue --processes=5

# Clear stuck jobs
php artisan queue:flush
```

#### **Memory Issues**

```php
// Increase memory limit in job
public int $timeout = 1800;
public string $memory = '512M';
```

### **Logging & Debugging**

All jobs provide comprehensive logging:

```bash
# View job logs
tail -f storage/logs/laravel.log | grep "ProcessAnalyticsDataJob"

# Monitor circuit breaker events
tail -f storage/logs/laravel.log | grep "circuit_breaker"

# Check job performance
tail -f storage/logs/laravel.log | grep "processing_time_ms"
```

---

## 💡 **Usage Examples**

### **Dispatching Jobs Programmatically**

```php
// Immediate dispatch
ProcessAnalyticsDataJob::dispatch('daily', now()->subDay());

// Delayed dispatch
ProcessUserProfileValidationJob::dispatch($userIds)
    ->delay(now()->addMinutes(30));

// Chain jobs
Bus::chain([
    new ProcessAnalyticsDataJob('daily', now()->subDay()),
    new GenerateBusinessReportsJob(['executive_summary'], now()->subWeek(), now()),
])->dispatch();
```

### **Batch Processing**

```php
// Process large datasets in batches
$userIds = User::pluck('id')->chunk(100);

foreach ($userIds as $chunk) {
    ProcessUserProfileValidationJob::dispatch($chunk->toArray())
        ->onQueue('user-validation-large');
}
```

### **Conditional Job Execution**

```php
// Only run if circuit breaker is closed
if (Fuse::for('analytics_data_processing')->isClosed()) {
    ProcessAnalyticsDataJob::dispatch('hourly', now()->subHour());
}
```

### **Error Handling**

```php
try {
    ProcessVinOcrBatchJob::dispatch($vinScanIds);
} catch (CircuitBreakerOpenException $e) {
    Log::warning('VIN OCR service unavailable', ['error' => $e->getMessage()]);
    // Implement fallback logic
}
```

---

## 🎯 **Best Practices**

### **Job Design**

1. **Idempotency**: Ensure jobs can be safely retried
2. **Batch Processing**: Use chunking for large datasets
3. **Error Handling**: Implement comprehensive error handling
4. **Logging**: Provide detailed logging for debugging
5. **Timeouts**: Set appropriate timeouts for operations

### **Circuit Breaker Configuration**

1. **Failure Thresholds**: Set based on service criticality
2. **Timeouts**: Balance between responsiveness and reliability
3. **Recovery Time**: Allow sufficient time for service recovery
4. **Monitoring**: Implement alerting for circuit breaker events

### **Queue Management**

1. **Queue Separation**: Separate queues by priority and service
2. **Worker Scaling**: Scale workers based on queue depth
3. **Dead Letter Queues**: Implement for failed job analysis
4. **Monitoring**: Track queue health and performance metrics

---

## 📈 **Performance Metrics**

### **Expected Performance**

| Job | Avg Processing Time | Throughput | Memory Usage |
|-----|-------------------|------------|--------------|
| ProcessAnalyticsDataJob | 30-120 seconds | 1000 events/min | 128-256MB |
| ProcessVinOcrBatchJob | 60-180 seconds | 50 images/min | 256-512MB |
| CleanupExpiredTokensJob | 120-300 seconds | 10K records/min | 64-128MB |
| ProcessUserProfileValidationJob | 90-180 seconds | 100 users/min | 128-256MB |
| GenerateBusinessReportsJob | 300-600 seconds | 1 report/5min | 256-512MB |
| ProcessSuspiciousLoginJob | 60-300 seconds | 200 attempts/min | 128-256MB |
| WarmCacheDataJob | 180-900 seconds | 5K entries/min | 256-512MB |
| RotateApplicationLogsJob | 300-1800 seconds | 100 files/min | 64-128MB |
| OptimizeNotificationQueuesJob | 600-1200 seconds | 5 queues/10min | 128-256MB |
| SyncPaymentReconciliationJob | 900-2400 seconds | 1K transactions/min | 256-512MB |

---

## 🔄 **Maintenance & Updates**

### **Regular Maintenance Tasks**

1. **Weekly**: Review circuit breaker metrics and adjust thresholds
2. **Monthly**: Analyze job performance and optimize configurations
3. **Quarterly**: Update job logic based on business requirements
4. **Annually**: Review and update retention policies

### **Updating Jobs**

1. **Version Control**: Use semantic versioning for job updates
2. **Backward Compatibility**: Maintain compatibility with existing queued jobs
3. **Testing**: Thoroughly test job updates in staging environment
4. **Deployment**: Use blue-green deployment for critical jobs

---

## 📞 **Support & Troubleshooting**

### **Common Support Scenarios**

1. **Circuit Breaker Issues**: Check service health and reset if needed
2. **Queue Backlogs**: Scale workers or optimize job logic
3. **Performance Issues**: Review job metrics and optimize queries
4. **Data Discrepancies**: Check reconciliation job logs and results

### **Emergency Procedures**

1. **Service Outage**: Disable affected jobs and implement fallback
2. **Data Corruption**: Stop jobs and restore from backup
3. **Performance Degradation**: Scale resources and optimize queries
4. **Security Incident**: Review security job logs and take action

---

**This documentation provides comprehensive coverage of all 10 Laravel Fuse circuit breaker protected jobs, their configuration, deployment, and operational procedures. For additional support or questions, refer to the individual job source code and Laravel Fuse documentation.**
