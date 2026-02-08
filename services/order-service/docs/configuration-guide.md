# Configuration Guide - Laravel Workflow Saga Pattern

## 📋 Table of Contents

1. [Environment Setup](#environment-setup)
2. [Service Configuration](#service-configuration)
3. [Queue Configuration](#queue-configuration)
4. [Cache Configuration](#cache-configuration)
5. [Broadcasting Configuration](#broadcasting-configuration)
6. [Database Configuration](#database-configuration)
7. [Telescope Configuration](#telescope-configuration)
8. [Production Deployment](#production-deployment)
9. [Security Configuration](#security-configuration)
10. [Performance Tuning](#performance-tuning)

---

## 🔧 Environment Setup

### **Environment Variables**

Create a comprehensive `.env` file with the following workflow-specific configurations:

```bash
# Application Configuration
APP_NAME="Laravel Workflow Saga"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=workflow_saga
DB_USERNAME=workflow_user
DB_PASSWORD=secure_password

# Redis Configuration (Required for caching and queues)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Queue Configuration
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# Broadcasting Configuration
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-key
PUSHER_APP_SECRET=your-pusher-secret
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

# Workflow-Specific Configuration
WORKFLOW_DEFAULT_TIMEOUT=3600
WORKFLOW_MAX_RETRIES=3
WORKFLOW_DLQ_ENABLED=true
WORKFLOW_CORRELATION_ENABLED=true
WORKFLOW_TRACING_ENABLED=true
WORKFLOW_ALERTING_ENABLED=true

# Correlation Service Configuration
CORRELATION_ID_PREFIX=wf
CORRELATION_STORAGE_TTL=86400
CORRELATION_HEADER_NAME=X-Correlation-ID

# Dead Letter Queue Configuration
DLQ_MAX_RETRY_ATTEMPTS=5
DLQ_RETRY_DELAY_SECONDS=60
DLQ_EXPONENTIAL_BACKOFF=true
DLQ_MAX_DELAY_SECONDS=3600

# Alerting Configuration
ALERTING_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/your/webhook/url
ALERTING_EMAIL_ENABLED=true
ALERTING_SMS_ENABLED=false
ALERTING_PAGERDUTY_ENABLED=false

# Telescope Configuration
TELESCOPE_ENABLED=true
TELESCOPE_DOMAIN=null
TELESCOPE_PATH=telescope

# Performance Configuration
WORKFLOW_CACHE_TTL=3600
WORKFLOW_METRICS_RETENTION_DAYS=30
WORKFLOW_TRACE_SAMPLING_RATE=1.0
```

### **Docker Environment Variables**

For Docker deployments, create a `docker-compose.override.yml`:

```yaml
version: '3.8'

services:
  app:
    environment:
      - APP_ENV=production
      - WORKFLOW_DEFAULT_TIMEOUT=3600
      - WORKFLOW_MAX_RETRIES=3
      - DLQ_MAX_RETRY_ATTEMPTS=5
      - CORRELATION_STORAGE_TTL=86400
      - WORKFLOW_CACHE_TTL=3600
      - WORKFLOW_METRICS_RETENTION_DAYS=30
      
  redis:
    environment:
      - REDIS_PASSWORD=your-secure-redis-password
      
  mysql:
    environment:
      - MYSQL_ROOT_PASSWORD=your-secure-root-password
      - MYSQL_DATABASE=workflow_saga
      - MYSQL_USER=workflow_user
      - MYSQL_PASSWORD=your-secure-password
```

---

## ⚙️ Service Configuration

### **Service Provider Registration**

Ensure all workflow services are registered in `config/app.php`:

```php
'providers' => [
    // ... other providers
    App\Providers\WorkflowServiceProvider::class,
],
```

### **Service Bindings**

Configure service bindings in `app/Providers/AppServiceProvider.php`:

```php
public function register()
{
    // Workflow Services
    $this->app->singleton(WorkflowEventPublisher::class);
    $this->app->singleton(WorkflowSignalHandler::class);
    $this->app->singleton(WorkflowDeadLetterQueue::class);
    $this->app->singleton(CorrelationService::class);
    $this->app->singleton(WorkflowTracingService::class);
    $this->app->singleton(WorkflowAlertingService::class);
    
    // Service Configuration
    $this->app->when(WorkflowEventPublisher::class)
        ->needs('$config')
        ->give(config('workflow.event_publisher'));
        
    $this->app->when(WorkflowAlertingService::class)
        ->needs('$alertingConfig')
        ->give(config('workflow.alerting'));
}
```

### **Workflow Configuration File**

Create `config/workflow.php`:

```php
<?php

return [
    'default_timeout' => env('WORKFLOW_DEFAULT_TIMEOUT', 3600),
    'max_retries' => env('WORKFLOW_MAX_RETRIES', 3),
    'dlq_enabled' => env('WORKFLOW_DLQ_ENABLED', true),
    'correlation_enabled' => env('WORKFLOW_CORRELATION_ENABLED', true),
    'tracing_enabled' => env('WORKFLOW_TRACING_ENABLED', true),
    'alerting_enabled' => env('WORKFLOW_ALERTING_ENABLED', true),
    
    'event_publisher' => [
        'enabled' => true,
        'broadcast_events' => true,
        'store_events' => true,
        'event_retention_days' => 30,
    ],
    
    'correlation' => [
        'id_prefix' => env('CORRELATION_ID_PREFIX', 'wf'),
        'storage_ttl' => env('CORRELATION_STORAGE_TTL', 86400),
        'header_name' => env('CORRELATION_HEADER_NAME', 'X-Correlation-ID'),
        'auto_generate' => true,
    ],
    
    'dlq' => [
        'max_retry_attempts' => env('DLQ_MAX_RETRY_ATTEMPTS', 5),
        'retry_delay_seconds' => env('DLQ_RETRY_DELAY_SECONDS', 60),
        'exponential_backoff' => env('DLQ_EXPONENTIAL_BACKOFF', true),
        'max_delay_seconds' => env('DLQ_MAX_DELAY_SECONDS', 3600),
        'manual_intervention_threshold' => 3,
    ],
    
    'alerting' => [
        'slack' => [
            'enabled' => !empty(env('ALERTING_SLACK_WEBHOOK_URL')),
            'webhook_url' => env('ALERTING_SLACK_WEBHOOK_URL'),
            'channel' => '#workflow-alerts',
            'username' => 'Workflow Bot',
        ],
        'email' => [
            'enabled' => env('ALERTING_EMAIL_ENABLED', true),
            'recipients' => explode(',', env('ALERTING_EMAIL_RECIPIENTS', '')),
        ],
        'thresholds' => [
            'error_rate' => 0.05, // 5% error rate
            'response_time_ms' => 5000, // 5 seconds
            'queue_depth' => 1000,
        ],
    ],
    
    'performance' => [
        'cache_ttl' => env('WORKFLOW_CACHE_TTL', 3600),
        'metrics_retention_days' => env('WORKFLOW_METRICS_RETENTION_DAYS', 30),
        'trace_sampling_rate' => env('WORKFLOW_TRACE_SAMPLING_RATE', 1.0),
        'batch_size' => 100,
    ],
];
```

---

## 🔄 Queue Configuration

### **Queue Connection Configuration**

Update `config/queue.php`:

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
    
    'workflow' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'workflow',
        'retry_after' => 300,
        'block_for' => null,
        'after_commit' => false,
    ],
    
    'dlq' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'dlq',
        'retry_after' => 600,
        'block_for' => null,
        'after_commit' => false,
    ],
],

'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'failed_jobs',
],
```

### **Queue Worker Configuration**

Create supervisor configuration `/etc/supervisor/conf.d/workflow-workers.conf`:

```ini
[program:workflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=workflow --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/worker.log
stopwaitsecs=3600

[program:dlq-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=dlq --sleep=5 --tries=1 --max-time=1800
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/storage/logs/dlq-worker.log
stopwaitsecs=3600
```

---

## 💾 Cache Configuration

### **Redis Cache Configuration**

Update `config/cache.php`:

```php
'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
    
    'workflow' => [
        'driver' => 'redis',
        'connection' => 'workflow_cache',
        'prefix' => 'workflow:',
    ],
],

'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'),
```

### **Redis Connection Configuration**

Update `config/database.php`:

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],
    
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
    
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
    
    'workflow_cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_WORKFLOW_DB', '2'),
    ],
],
```

---

## 📡 Broadcasting Configuration

### **Pusher Configuration**

Update `config/broadcasting.php`:

```php
'connections' => [
    'pusher' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusherapp.com',
            'port' => env('PUSHER_PORT', 443),
            'scheme' => env('PUSHER_SCHEME', 'https'),
            'encrypted' => true,
            'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
        ],
        'client_options' => [
            // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
        ],
    ],
    
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
    ],
],
```

### **WebSocket Configuration**

For Laravel WebSockets, create `config/websockets.php`:

```php
<?php

return [
    'dashboard' => [
        'port' => env('LARAVEL_WEBSOCKETS_PORT', 6001),
    ],
    
    'apps' => [
        [
            'id' => env('PUSHER_APP_ID'),
            'name' => env('APP_NAME'),
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'path' => env('PUSHER_APP_PATH'),
            'capacity' => null,
            'enable_client_messages' => false,
            'enable_statistics' => true,
        ],
    ],
    
    'app_provider' => BeyondCode\LaravelWebSockets\Apps\ConfigAppProvider::class,
    
    'allowed_origins' => [
        env('APP_URL'),
    ],
    
    'max_request_size_in_kb' => 250,
    
    'path' => 'laravel-websockets',
    
    'middleware' => [
        'web',
        BeyondCode\LaravelWebSockets\Dashboard\Http\Middleware\Authorize::class,
    ],
    
    'statistics' => [
        'model' => \BeyondCode\LaravelWebSockets\Statistics\Models\WebSocketsStatisticsEntry::class,
        'logger' => BeyondCode\LaravelWebSockets\Statistics\Logger\HttpStatisticsLogger::class,
        'interval_in_seconds' => 60,
        'delete_statistics_older_than_days' => 60,
        'perform_dns_lookup' => false,
    ],
    
    'ssl' => [
        'local_cert' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_CERT', null),
        'local_pk' => env('LARAVEL_WEBSOCKETS_SSL_LOCAL_PK', null),
        'passphrase' => env('LARAVEL_WEBSOCKETS_SSL_PASSPHRASE', null),
    ],
    
    'channel_manager' => \BeyondCode\LaravelWebSockets\WebSockets\Channels\ChannelManagers\ArrayChannelManager::class,
];
```

---

## 🗄️ Database Configuration

### **Migration Configuration**

Ensure all workflow tables are created:

```bash
# Run workflow-specific migrations
php artisan migrate --path=database/migrations/workflow

# Create indexes for performance
php artisan migrate --path=database/migrations/indexes
```

### **Database Optimization**

Add to `config/database.php`:

```php
'mysql' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'unix_socket' => env('DB_SOCKET', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'prefix_indexes' => true,
    'strict' => true,
    'engine' => null,
    'options' => extension_loaded('pdo_mysql') ? array_filter([
        PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
        PDO::ATTR_TIMEOUT => 60,
        PDO::ATTR_PERSISTENT => true,
    ]) : [],
    
    // Workflow-specific optimizations
    'read' => [
        'host' => [
            env('DB_READ_HOST', env('DB_HOST', '127.0.0.1')),
        ],
    ],
    'write' => [
        'host' => [
            env('DB_WRITE_HOST', env('DB_HOST', '127.0.0.1')),
        ],
    ],
],
```

---

## 🔭 Telescope Configuration

### **Telescope Configuration**

Update `config/telescope.php`:

```php
'watchers' => [
    Watchers\CacheWatcher::class => env('TELESCOPE_CACHE_WATCHER', true),
    Watchers\CommandWatcher::class => env('TELESCOPE_COMMAND_WATCHER', true),
    Watchers\DumpWatcher::class => env('TELESCOPE_DUMP_WATCHER', true),
    Watchers\EventWatcher::class => [
        'enabled' => env('TELESCOPE_EVENT_WATCHER', true),
        'ignore' => [
            // Add events to ignore
        ],
    ],
    Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
    Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
    Watchers\LogWatcher::class => env('TELESCOPE_LOG_WATCHER', true),
    Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
    Watchers\ModelWatcher::class => [
        'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
        'hydrations' => true,
    ],
    Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
    Watchers\QueryWatcher::class => [
        'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
        'ignore_packages' => true,
        'slow' => 100,
    ],
    Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),
    Watchers\RequestWatcher::class => [
        'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
        'size_limit' => env('TELESCOPE_RESPONSE_SIZE_LIMIT', 64),
    ],
    Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
    Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
],

'ignore_paths' => [
    'nova-api*',
    'telescope*',
    'vendor/telescope*',
],

'ignore_commands' => [
    'schedule:run',
    'schedule:finish',
    'package:discover',
],
```

---

## 🚀 Production Deployment

### **Environment Optimization**

```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Clear development caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

### **Supervisor Configuration**

Complete supervisor setup:

```bash
# Install supervisor
sudo apt-get install supervisor

# Copy configuration
sudo cp workflow-workers.conf /etc/supervisor/conf.d/

# Update supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start workflow-worker:*
sudo supervisorctl start dlq-worker:*
```

### **Nginx Configuration**

Example Nginx configuration:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/public;
    
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    
    index index.php;
    
    charset utf-8;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # WebSocket proxy for Laravel WebSockets
    location /laravel-websockets {
        proxy_pass http://127.0.0.1:6001;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## 🔒 Security Configuration

### **Security Headers**

Add to middleware:

```php
// Add to app/Http/Middleware/SecurityHeaders.php
public function handle($request, Closure $next)
{
    $response = $next($request);
    
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    
    return $response;
}
```

### **API Rate Limiting**

Configure in `config/sanctum.php`:

```php
'middleware' => [
    'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
    'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    'throttle:api' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
],
```

---

## ⚡ Performance Tuning

### **PHP Configuration**

Optimize `php.ini`:

```ini
memory_limit = 512M
max_execution_time = 300
max_input_vars = 3000
post_max_size = 100M
upload_max_filesize = 100M

; OPcache settings
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### **Redis Optimization**

Redis configuration:

```conf
# /etc/redis/redis.conf
maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

This configuration guide provides comprehensive setup instructions for deploying the Laravel Workflow Saga Pattern in production environments with optimal performance and security.
