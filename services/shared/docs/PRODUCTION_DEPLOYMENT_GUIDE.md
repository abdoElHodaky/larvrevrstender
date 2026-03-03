# Database Failover System - Production Deployment Guide

## 🚀 Overview

This guide provides step-by-step instructions for deploying the Database Failover Integration System to production. The system includes comprehensive database failover capabilities, circuit breaker protection, email notifications, and real-time monitoring.

## 📋 Prerequisites

### System Requirements
- **PHP**: 8.1 or higher
- **Laravel**: 10.x or higher
- **Redis**: 6.0+ (for queues and caching)
- **PostgreSQL**: 13+ (primary and secondary databases)
- **MongoDB**: 5.0+ (fallback database)
- **SMTP Service**: SMTP2Go or similar (for email notifications)

### Infrastructure Requirements
- **Primary Database**: PostgreSQL with read/write capabilities
- **Secondary Database**: PostgreSQL read replica or standby
- **Fallback Database**: MongoDB cluster for emergency fallback
- **Queue Workers**: Redis-backed queue processing
- **Email Service**: SMTP2Go account with API credentials

## 🔧 Step 1: Configuration Setup

### 1.1 Environment Configuration

Copy the production environment template:
```bash
cp services/shared/config/production/.env.production.example .env.production
```

### 1.2 Update Critical Configuration Values

Edit `.env.production` with your production values:

#### Database Connections
```env
# Primary Database (PostgreSQL)
DB_HOST=your-primary-db-host.amazonaws.com
DB_PORT=5432
DB_DATABASE=your_production_database
DB_USERNAME=your_db_username
DB_PASSWORD=your_secure_db_password

# Secondary Database (PostgreSQL Read Replica)
DB_SECONDARY_HOST=your-secondary-db-host.amazonaws.com
DB_SECONDARY_PORT=5432
DB_SECONDARY_DATABASE=your_production_database
DB_SECONDARY_USERNAME=your_db_username
DB_SECONDARY_PASSWORD=your_secure_db_password

# Fallback Database (MongoDB)
MONGODB_HOST=your-mongodb-host.amazonaws.com
MONGODB_PORT=27017
MONGODB_DATABASE=your_fallback_database
MONGODB_USERNAME=your_mongodb_username
MONGODB_PASSWORD=your_secure_mongodb_password
```

#### SMTP2Go Email Configuration
```env
MAIL_USERNAME=your_smtp2go_username
MAIL_PASSWORD=your_smtp2go_api_key
MAIL_FROM_ADDRESS=database-alerts@yourcompany.com
MAIL_FROM_NAME="Database Failover System"
```

#### Email Recipients
```env
DB_FAILOVER_OPS_TEAM_EMAILS=ops@yourcompany.com,devops@yourcompany.com
DB_FAILOVER_ENG_LEADS_EMAILS=engineering-leads@yourcompany.com,cto@yourcompany.com
DB_FAILOVER_ON_CALL_EMAILS=oncall@yourcompany.com,emergency@yourcompany.com
```

#### Redis Configuration
```env
REDIS_HOST=your-redis-host.amazonaws.com
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379
```

### 1.3 Publish Configuration Files

Run the configuration publishing command:
```bash
php artisan vendor:publish --tag=shared-config
```

This will publish:
- `config/database-failover.php`
- `config/fuse.php`
- `config/shared.php`

## 🔧 Step 2: Service Registration

### 2.1 Register Service Provider

Add to `config/app.php` providers array:
```php
'providers' => [
    // ... other providers
    Shared\Providers\SharedServiceProvider::class,
],
```

### 2.2 Verify Service Registration

Test service container registration:
```bash
php artisan tinker
```

```php
// Test service resolution
app('shared.topology-mapper');
app('shared.circuit-breaker-tuner');
app('shared.email-notifications');
app('shared.failover-orchestrator');
```

## 📧 Step 3: Email Template Setup

### 3.1 Verify Email Templates

Ensure email templates are in place:
```
services/shared/resources/views/emails/
├── database-failover/
│   └── alert.blade.php
├── circuit-breaker/
│   └── alert.blade.php
└── database-topology/
    └── (future templates)
```

### 3.2 Test Email Configuration

Test email sending:
```bash
php artisan tinker
```

```php
use Shared\Mail\DatabaseFailoverAlert;
use Illuminate\Support\Facades\Mail;

$notificationData = [
    'event_type' => 'connection_health_check_failed',
    'severity' => 'high',
    'context' => ['connection' => 'pgsql', 'error' => 'Connection timeout']
];

Mail::to('test@yourcompany.com')->send(new DatabaseFailoverAlert($notificationData, 'test-123'));
```

## 🔄 Step 4: Queue Configuration

### 4.1 Configure Queue Workers

Set up queue workers for email processing:
```bash
# Start email queue worker
php artisan queue:work redis --queue=emails --tries=3 --timeout=60

# Start reports queue worker  
php artisan queue:work redis --queue=reports --tries=3 --timeout=120
```

### 4.2 Configure Supervisor (Production)

Create `/etc/supervisor/conf.d/database-failover-queues.conf`:
```ini
[program:database-failover-emails]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work redis --queue=emails --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/database-failover-emails.log
stopwaitsecs=3600

[program:database-failover-reports]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work redis --queue=reports --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisor/database-failover-reports.log
stopwaitsecs=3600
```

Reload supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start database-failover-emails:*
sudo supervisorctl start database-failover-reports:*
```

## 🎯 Step 5: Event System Integration

### 5.1 Register Event Listeners

Create event listener registration in `EventServiceProvider`:
```php
use Shared\Events\DatabaseFailoverSystemEvent;

protected $listen = [
    DatabaseFailoverSystemEvent::class => [
        // Add your event listeners here
    ],
];
```

### 5.2 Test Event Dispatching

Test event system:
```bash
php artisan tinker
```

```php
use Shared\Events\DatabaseFailoverSystemEvent;

$event = new DatabaseFailoverSystemEvent(
    'connection_health_check_failed',
    ['connection' => 'pgsql', 'error' => 'Connection timeout'],
    'high',
    'database-service'
);

event($event);
```

## 📊 Step 6: Monitoring Setup

### 6.1 Configure Logging Channels

Add to `config/logging.php`:
```php
'channels' => [
    // ... existing channels
    
    'database_failover' => [
        'driver' => 'daily',
        'path' => storage_path('logs/database-failover.log'),
        'level' => env('DB_FAILOVER_LOG_LEVEL', 'info'),
        'days' => env('DB_FAILOVER_LOG_RETENTION_DAYS', 30),
    ],
    
    'database_metrics' => [
        'driver' => 'daily',
        'path' => storage_path('logs/database-metrics.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

### 6.2 Set Up Health Checks

Create health check endpoint:
```php
// routes/api.php
Route::get('/health/database-failover', function () {
    $topologyMapper = app('shared.topology-mapper');
    $health = $topologyMapper->getSystemHealth();
    
    return response()->json([
        'status' => $health['overall_status'],
        'connections' => $health['connections'],
        'timestamp' => now()->toISOString()
    ]);
});
```

## 🔒 Step 7: Security Configuration

### 7.1 Secure Environment Variables

Ensure sensitive environment variables are properly secured:
```bash
# Set proper file permissions
chmod 600 .env.production

# Verify no sensitive data in logs
grep -r "password\|secret\|key" storage/logs/ || echo "No sensitive data found"
```

### 7.2 Configure SSL/TLS

Ensure all database connections use SSL:
```env
DB_SSLMODE=require
MONGODB_SSL=true
MAIL_VERIFY_SSL=true
```

## 🚀 Step 8: Deployment Validation

### 8.1 Pre-Deployment Checklist

- [ ] All environment variables configured
- [ ] Database connections tested
- [ ] Email configuration tested
- [ ] Queue workers configured
- [ ] Logging channels configured
- [ ] SSL/TLS enabled
- [ ] Service provider registered
- [ ] Configuration files published

### 8.2 Deployment Testing

Run comprehensive tests:
```bash
# Test service resolution
php artisan tinker -c "app('shared.failover-orchestrator')"

# Test database connections
php artisan tinker -c "DB::connection('pgsql')->select('SELECT 1')"
php artisan tinker -c "DB::connection('pgsql_secondary')->select('SELECT 1')"

# Test email sending
php artisan queue:work --once

# Test event dispatching
php artisan tinker -c "event(new Shared\Events\DatabaseFailoverSystemEvent('test', [], 'info', 'test'))"
```

### 8.3 Post-Deployment Monitoring

Monitor these key metrics:
- **Email Delivery**: Check SMTP2Go dashboard for delivery rates
- **Queue Processing**: Monitor queue worker logs
- **Database Health**: Monitor connection health checks
- **Error Rates**: Watch for circuit breaker activations
- **Response Times**: Track database response times

## 📈 Step 9: Performance Optimization

### 9.1 Database Connection Pooling

Configure connection pooling:
```env
DB_CONNECTION_POOLING_ENABLED=true
DB_MIN_CONNECTIONS=5
DB_MAX_CONNECTIONS=100
DB_IDLE_TIMEOUT=300
```

### 9.2 Queue Optimization

Optimize queue processing:
```env
QUEUE_CONNECTION=redis
DB_FAILOVER_MAIL_QUEUE=emails
CIRCUIT_BREAKER_MAIL_QUEUE=emails
TOPOLOGY_REPORT_MAIL_QUEUE=reports
```

### 9.3 Caching Configuration

Enable query caching:
```env
DB_QUERY_CACHE_ENABLED=true
DB_PREPARED_STATEMENTS=true
```

## 🔧 Step 10: Maintenance and Operations

### 10.1 Regular Maintenance Tasks

Schedule these maintenance tasks:
```bash
# Daily log rotation
0 2 * * * /usr/bin/find /path/to/logs -name "*.log" -mtime +30 -delete

# Weekly configuration backup
0 3 * * 0 /usr/bin/cp .env.production /backup/env-$(date +\%Y\%m\%d).backup

# Monthly health report
0 8 1 * * php /path/to/artisan database:failover:health-report --email
```

### 10.2 Monitoring Alerts

Set up monitoring alerts for:
- **High Email Volume**: >50 emails/hour
- **Queue Backlog**: >100 jobs in queue
- **Database Failover Events**: Any critical events
- **Circuit Breaker Opens**: Any circuit breaker activations
- **Response Time Degradation**: >2000ms average response time

### 10.3 Troubleshooting

Common issues and solutions:

#### Email Not Sending
```bash
# Check queue workers
sudo supervisorctl status database-failover-emails:*

# Check SMTP credentials
php artisan tinker -c "Mail::raw('Test', function(\$m) { \$m->to('test@company.com')->subject('Test'); })"

# Check logs
tail -f storage/logs/laravel.log
```

#### Database Connection Issues
```bash
# Test connections
php artisan tinker -c "DB::connection('pgsql')->select('SELECT version()')"
php artisan tinker -c "DB::connection('pgsql_secondary')->select('SELECT version()')"

# Check circuit breaker status
php artisan tinker -c "app('shared.circuit-breaker-tuner')->getCircuitStatus()"
```

#### Queue Processing Issues
```bash
# Check Redis connection
redis-cli ping

# Restart queue workers
sudo supervisorctl restart database-failover-emails:*

# Clear failed jobs
php artisan queue:flush
```

## 🎉 Deployment Complete!

Your Database Failover Integration System is now deployed and ready for production use. The system provides:

- ✅ **Automatic Database Failover**: Seamless switching between primary, secondary, and fallback databases
- ✅ **Circuit Breaker Protection**: Prevents cascade failures with intelligent circuit breaking
- ✅ **Real-time Email Notifications**: Immediate alerts for critical events via SMTP2Go
- ✅ **Comprehensive Monitoring**: Detailed logging and metrics collection
- ✅ **Event-Driven Architecture**: Real-time coordination across services
- ✅ **Production-Ready Configuration**: Optimized for high-availability environments

For ongoing support and maintenance, refer to the troubleshooting section and monitor the configured alerts and logs.
