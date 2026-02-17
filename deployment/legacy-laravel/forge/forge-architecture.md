# Laravel Forge Deployment Architecture

## Overview

This document outlines the Laravel Forge deployment architecture for the notification system microservices, implementing the multi-service architecture designed in Phase 1 with Forge-specific configurations and optimizations.

## Server Architecture

### Option 1: Shared Server Deployment (Recommended for Development/Testing)

```
┌─────────────────────────────────────────────────────────────┐
│                 Forge Server (4GB RAM)                     │
│                                                             │
│  ┌─────────────────────┐    ┌─────────────────────────────┐ │
│  │   Shared Service    │    │   Notification Service      │ │
│  │   (Port 80)         │    │   (Port 8080)               │ │
│  │                     │    │                             │ │
│  │  • API Gateway      │◄──►│  • Business Logic           │ │
│  │  • RPC Client       │    │  • Builders & Templates     │ │
│  │  • Authentication   │    │  • Factory & Channels       │ │
│  └─────────────────────┘    └─────────────────────────────┘ │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                MySQL Database                           │ │
│  │  • Shared tables (users, configurations)               │ │
│  │  • Notification tables (templates, logs)               │ │
│  └─────────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                Redis Cache                              │ │
│  │  • Session storage                                      │ │
│  │  • Queue management                                     │ │
│  │  • Service communication cache                         │ │
│  └─────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

**Specifications**:
- **Server Size**: 4GB RAM, 2 CPU cores, 80GB SSD
- **Cost**: ~$40-60/month
- **Use Case**: Development, testing, small production deployments

### Option 2: Multi-Server Deployment (Recommended for Production)

```
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│   Load Balancer     │    │   Shared Service    │    │  Notification       │
│   (Nginx Proxy)     │    │   Server (2GB)      │    │  Service Server     │
│                     │    │                     │    │  (2GB)              │
│  • SSL Termination  │    │  ┌─────────────────┐│    │  ┌─────────────────┐│
│  • Request Routing  │◄──►│  │  Laravel App    ││    │  │  Laravel App    ││
│  • Health Checks    │    │  │  (Port 80)      ││    │  │  (Port 80)      ││
│                     │    │  └─────────────────┘│    │  └─────────────────┘│
└─────────────────────┘    └─────────────────────┘    └─────────────────────┘
                                       │                           │
                                       └─────────┬─────────────────┘
                                                 │
                           ┌─────────────────────────────────────┐
                           │         Database Server             │
                           │         (MySQL 8.0)                 │
                           │                                     │
                           │  ┌─────────────────────────────────┐│
                           │  │  notification_system DB         ││
                           │  │  • users (shared)               ││
                           │  │  • notification_templates       ││
                           │  │  • notification_logs            ││
                           │  │  • api_logs                     ││
                           │  └─────────────────────────────────┘│
                           └─────────────────────────────────────┘
```

**Specifications**:
- **Load Balancer**: 1GB RAM, 1 CPU core (~$12/month)
- **Service Servers**: 2GB RAM, 1 CPU core each (~$25/month each)
- **Database Server**: 2GB RAM, 1 CPU core (~$25/month)
- **Total Cost**: ~$87/month
- **Use Case**: Production deployments with high availability

## Service Communication

### HTTP-Based Communication

**Internal Service URLs**:
- **Shared Service**: `http://shared-service.internal:80` or `http://10.0.0.2:80`
- **Notification Service**: `http://notification-service.internal:8080` or `http://10.0.0.3:8080`

**External Access**:
- **Shared Service**: `https://api.yourdomain.com`
- **Notification Service**: `https://notifications.yourdomain.com` (if direct access needed)

### Service Discovery Configuration

```php
// config/services.php (Shared Service)
'notification_service' => [
    'url' => env('NOTIFICATION_SERVICE_URL', 'http://10.0.0.3:8080'),
    'api_key' => env('NOTIFICATION_SERVICE_API_KEY', 'forge_secure_key_123'),
    'timeout' => env('NOTIFICATION_SERVICE_TIMEOUT', 30),
    'retry_attempts' => env('NOTIFICATION_SERVICE_RETRIES', 3),
    'health_check_url' => env('NOTIFICATION_SERVICE_HEALTH_URL', 'http://10.0.0.3:8080/api/health'),
],
```

## Database Configuration

### Shared Database Strategy

**Database Name**: `notification_system`

**Connection Configuration**:
```php
// config/database.php
'connections' => [
    'mysql' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '10.0.0.4'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'notification_system'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
        'engine' => null,
    ],
],
```

**Schema Design**:
```sql
-- Shared tables
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_email (email)
);

-- Notification service tables
CREATE TABLE notification_templates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    language VARCHAR(10) DEFAULT 'en',
    subject VARCHAR(255) NULL,
    content TEXT NOT NULL,
    variables JSON NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_name_channel (name, channel),
    INDEX idx_channel (channel)
);

CREATE TABLE notification_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    template_id BIGINT UNSIGNED NULL,
    channel VARCHAR(50) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    content TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    sent_at TIMESTAMP NULL DEFAULT NULL,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    failed_at TIMESTAMP NULL DEFAULT NULL,
    error_message TEXT NULL,
    attempts INT DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (template_id) REFERENCES notification_templates(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_channel (channel),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Shared service tables
CREATE TABLE api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    service VARCHAR(50) NOT NULL,
    method VARCHAR(100) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    trace_id VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    request_data JSON NULL,
    response_data JSON NULL,
    status_code INT NULL,
    duration_ms INT NULL,
    created_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_service (service),
    INDEX idx_trace_id (trace_id),
    INDEX idx_created_at (created_at)
);
```

## Environment Configuration

### Shared Service Environment (.env)

```env
# Application
APP_NAME="Shared Service"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=10.0.0.4
DB_PORT=3306
DB_DATABASE=notification_system
DB_USERNAME=forge
DB_PASSWORD=your-secure-password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=10.0.0.4
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Notification Service
NOTIFICATION_SERVICE_URL=http://10.0.0.3:8080
NOTIFICATION_SERVICE_API_KEY=forge_secure_api_key_123
NOTIFICATION_SERVICE_TIMEOUT=30
NOTIFICATION_SERVICE_RETRIES=3
NOTIFICATION_SERVICE_HEALTH_URL=http://10.0.0.3:8080/api/health

# Service Communication
SERVICE_COMMUNICATION_METHOD=http
CIRCUIT_BREAKER_THRESHOLD=5
CIRCUIT_BREAKER_TIMEOUT=60

# Monitoring
TRACE_SERVICE_REQUESTS=true
SERVICE_LOG_LEVEL=info
SERVICE_METRICS_ENABLED=true

# Security
API_KEYS_NOTIFICATION_SERVICE=forge_secure_api_key_123
```

### Notification Service Environment (.env)

```env
# Application
APP_NAME="Notification Service"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=http://10.0.0.3:8080

# Database
DB_CONNECTION=mysql
DB_HOST=10.0.0.4
DB_PORT=3306
DB_DATABASE=notification_system
DB_USERNAME=forge
DB_PASSWORD=your-secure-password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=10.0.0.4
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379

# Queue
QUEUE_CONNECTION=redis

# Service Configuration
SERVICE_PORT=8080
SERVICE_NAME=notification-service

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-mailgun-username
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Your App"

# SMS Configuration (Twilio)
TWILIO_SID=your-twilio-sid
TWILIO_TOKEN=your-twilio-token
TWILIO_FROM=+1234567890

# Push Notifications (FCM)
FCM_SERVER_KEY=your-fcm-server-key

# Security
API_KEYS_SHARED_SERVICE=forge_secure_api_key_123

# Monitoring
LOG_CHANNEL=stack
LOG_LEVEL=info
```

## Deployment Scripts

### Shared Service Deployment Script

```bash
#!/bin/bash
# File: scripts/forge/deploy-shared-service.sh

set -e

echo "🚀 Starting Shared Service deployment..."

# Navigate to project directory
cd /home/forge/shared-service

# Pull latest changes
echo "📥 Pulling latest changes..."
git pull origin main

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and rebuild caches
echo "🔄 Rebuilding caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Rebuild optimized caches
echo "⚡ Building optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# Restart PHP-FPM
echo "🔄 Restarting PHP-FPM..."
sudo service php8.3-fpm reload

# Health check
echo "🏥 Performing health check..."
sleep 5
curl -f http://localhost/api/health || {
    echo "❌ Health check failed!"
    exit 1
}

echo "✅ Shared Service deployment completed successfully!"
```

### Notification Service Deployment Script

```bash
#!/bin/bash
# File: scripts/forge/deploy-notification-service.sh

set -e

echo "🚀 Starting Notification Service deployment..."

# Navigate to project directory
cd /home/forge/notification-service

# Pull latest changes
echo "📥 Pulling latest changes..."
git pull origin main

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and rebuild caches
echo "🔄 Rebuilding caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Rebuild optimized caches
echo "⚡ Building optimized caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# Restart PHP-FPM (if running on port 8080)
echo "🔄 Restarting PHP-FPM..."
sudo service php8.3-fpm reload

# Health check
echo "🏥 Performing health check..."
sleep 5
curl -f http://localhost:8080/api/health || {
    echo "❌ Health check failed!"
    exit 1
}

echo "✅ Notification Service deployment completed successfully!"
```

## Load Balancer Configuration

### Nginx Configuration for Load Balancer

```nginx
# File: /etc/nginx/sites-available/notification-system-lb

upstream shared_service {
    server 10.0.0.2:80 max_fails=3 fail_timeout=30s;
    # Add more servers for horizontal scaling
    # server 10.0.0.5:80 max_fails=3 fail_timeout=30s;
}

upstream notification_service {
    server 10.0.0.3:8080 max_fails=3 fail_timeout=30s;
    # Add more servers for horizontal scaling
    # server 10.0.0.6:8080 max_fails=3 fail_timeout=30s;
}

# Shared Service (Main API)
server {
    listen 80;
    listen 443 ssl http2;
    server_name api.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/api.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Redirect HTTP to HTTPS
    if ($scheme != "https") {
        return 301 https://$host$request_uri;
    }

    location / {
        proxy_pass http://shared_service;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }

    # Health check endpoint
    location /health {
        proxy_pass http://shared_service/api/health;
        access_log off;
    }
}

# Notification Service (Internal/Admin Access)
server {
    listen 80;
    listen 443 ssl http2;
    server_name notifications.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/notifications.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/notifications.yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Redirect HTTP to HTTPS
    if ($scheme != "https") {
        return 301 https://$host$request_uri;
    }

    # Restrict access to internal networks only
    allow 10.0.0.0/8;
    allow 172.16.0.0/12;
    allow 192.168.0.0/16;
    deny all;

    location / {
        proxy_pass http://notification_service;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }

    # Health check endpoint
    location /health {
        proxy_pass http://notification_service/api/health;
        access_log off;
    }
}
```

## Monitoring and Health Checks

### Health Check Endpoints

**Shared Service Health Check** (`/api/health`):
```php
// routes/api.php (Shared Service)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/up', function () {
    return response()->json(['status' => 'ok', 'service' => 'shared-service']);
});
```

**Notification Service Health Check** (`/api/health`):
```php
// routes/api.php (Notification Service)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/up', function () {
    return response()->json(['status' => 'ok', 'service' => 'notification-service']);
});
```

### Monitoring Script

```bash
#!/bin/bash
# File: scripts/forge/monitor-services.sh

# Service monitoring script for Forge deployment

SHARED_SERVICE_URL="http://10.0.0.2:80/api/health"
NOTIFICATION_SERVICE_URL="http://10.0.0.3:8080/api/health"
LOG_FILE="/var/log/notification-system-monitor.log"

check_service() {
    local service_name=$1
    local service_url=$2
    
    response=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$service_url")
    
    if [ "$response" = "200" ]; then
        echo "$(date): ✅ $service_name is healthy" >> "$LOG_FILE"
        return 0
    else
        echo "$(date): ❌ $service_name is unhealthy (HTTP $response)" >> "$LOG_FILE"
        return 1
    fi
}

# Check services
check_service "Shared Service" "$SHARED_SERVICE_URL"
SHARED_STATUS=$?

check_service "Notification Service" "$NOTIFICATION_SERVICE_URL"
NOTIFICATION_STATUS=$?

# Alert if any service is down
if [ $SHARED_STATUS -ne 0 ] || [ $NOTIFICATION_STATUS -ne 0 ]; then
    echo "$(date): 🚨 One or more services are down!" >> "$LOG_FILE"
    # Add alerting logic here (email, Slack, etc.)
fi
```

## Security Configuration

### Firewall Rules

```bash
# UFW firewall configuration for Forge servers

# Shared Service Server (10.0.0.2)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw allow from 10.0.0.0/8 to any port 3306  # MySQL (internal only)
sudo ufw allow from 10.0.0.0/8 to any port 6379  # Redis (internal only)

# Notification Service Server (10.0.0.3)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow from 10.0.0.0/8 to any port 8080  # Service port (internal only)
sudo ufw allow from 10.0.0.0/8 to any port 3306  # MySQL (internal only)
sudo ufw allow from 10.0.0.0/8 to any port 6379  # Redis (internal only)

# Database Server (10.0.0.4)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow from 10.0.0.0/8 to any port 3306  # MySQL (internal only)
sudo ufw allow from 10.0.0.0/8 to any port 6379  # Redis (internal only)

# Enable firewall
sudo ufw --force enable
```

### API Key Management

```php
// config/api-keys.php
return [
    'services' => [
        'notification_service' => env('API_KEYS_NOTIFICATION_SERVICE'),
        'shared_service' => env('API_KEYS_SHARED_SERVICE'),
    ],
    
    'rotation' => [
        'enabled' => env('API_KEY_ROTATION_ENABLED', false),
        'interval_days' => env('API_KEY_ROTATION_INTERVAL', 30),
    ],
];
```

## Backup Strategy

### Database Backup Script

```bash
#!/bin/bash
# File: scripts/forge/backup-database.sh

BACKUP_DIR="/home/forge/backups"
DB_NAME="notification_system"
DB_USER="forge"
DB_PASS="your-secure-password"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/notification_system_$DATE.sql"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Create database backup
mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_FILE"

# Compress backup
gzip "$BACKUP_FILE"

# Remove backups older than 7 days
find "$BACKUP_DIR" -name "notification_system_*.sql.gz" -mtime +7 -delete

echo "Database backup completed: ${BACKUP_FILE}.gz"
```

## Cost Estimation

### Single Server Setup
- **Server**: 4GB RAM, 2 CPU cores - $40-60/month
- **Total Monthly Cost**: $40-60

### Multi-Server Setup
- **Load Balancer**: 1GB RAM - $12/month
- **Shared Service Server**: 2GB RAM - $25/month
- **Notification Service Server**: 2GB RAM - $25/month
- **Database Server**: 2GB RAM - $25/month
- **Total Monthly Cost**: $87/month

### Additional Costs
- **SSL Certificates**: Free (Let's Encrypt)
- **Backup Storage**: $5-10/month
- **Monitoring Tools**: $0-20/month (optional)

## Deployment Checklist

### Pre-Deployment
- [ ] Forge account setup and server provisioning
- [ ] Domain DNS configuration
- [ ] SSL certificate installation
- [ ] Database server setup and user creation
- [ ] Redis server installation and configuration
- [ ] Firewall rules configuration

### Service Deployment
- [ ] Shared service repository setup in Forge
- [ ] Notification service repository setup in Forge
- [ ] Environment variables configuration
- [ ] Database migrations execution
- [ ] Service communication testing
- [ ] Health check endpoint verification

### Post-Deployment
- [ ] Load balancer configuration (if multi-server)
- [ ] Monitoring script setup
- [ ] Backup script configuration
- [ ] Performance testing
- [ ] Security audit
- [ ] Documentation update

---

**Document Version**: 1.0  
**Last Updated**: February 14, 2026  
**Authors**: Codegen AI, AbdElrhman ElHodaky  
**Status**: Ready for implementation
