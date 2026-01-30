# 🚀 Installation Guide

This guide will help you set up the Reverse Tender Platform on your local development environment or production server.

## 📋 Prerequisites

### System Requirements

- **Operating System**: Linux (Ubuntu 20.04+), macOS, or Windows with WSL2
- **PHP**: 8.1 or higher
- **Composer**: 2.0 or higher
- **Node.js**: 18.0 or higher
- **Docker**: 20.10 or higher
- **Docker Compose**: 2.0 or higher

### Required Services

- **MySQL**: 8.0 or higher
- **Redis**: 6.0 or higher
- **Nginx**: 1.18 or higher (for production)

## 🛠️ Development Installation

### 1. Clone the Repository

```bash
git clone https://github.com/abdoElHodaky/larvrevrstender.git
cd larvrevrstender
```

### 2. Environment Configuration

#### Copy Environment Files

```bash
# Main environment file
cp .env.example .env

# Service-specific environment files
cp services/auth-service/.env.example services/auth-service/.env
cp services/bidding-service/.env.example services/bidding-service/.env
cp services/user-service/.env.example services/user-service/.env
cp services/order-service/.env.example services/order-service/.env
cp services/notification-service/.env.example services/notification-service/.env
cp services/payment-service/.env.example services/payment-service/.env
cp services/analytics-service/.env.example services/analytics-service/.env
cp services/vin-ocr-service/.env.example services/vin-ocr-service/.env
cp services/api-gateway/.env.example services/api-gateway/.env
```

#### Configure Environment Variables

Edit each `.env` file with your specific configurations:

**Main .env file:**
```env
APP_ENV=local
APP_DEBUG=true

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# Services URLs
API_GATEWAY_URL=http://localhost:8000
AUTH_SERVICE_URL=http://localhost:8001
BIDDING_SERVICE_URL=http://localhost:8002
```

### 3. Install Dependencies

#### Install PHP Dependencies for Each Service

```bash
# Auth Service
cd services/auth-service
composer install
cd ../..

# Bidding Service
cd services/bidding-service
composer install
cd ../..

# Analytics Service
cd services/analytics-service
composer install
cd ../..

# Repeat for all services...
```

#### Automated Installation Script

```bash
# Make the script executable
chmod +x scripts/install-dependencies.sh

# Run the installation script
./scripts/install-dependencies.sh
```

### 4. Database Setup

#### Start Infrastructure Services

```bash
# Start MySQL and Redis using Docker
docker-compose -f deployment/docker/development/docker-compose.yml up -d mysql redis
```

#### Create Databases

```bash
# Connect to MySQL
mysql -h 127.0.0.1 -u root -p

# Create databases for each service
CREATE DATABASE reverse_tender_auth;
CREATE DATABASE reverse_tender_users;
CREATE DATABASE reverse_tender_orders;
CREATE DATABASE reverse_tender_bidding;
CREATE DATABASE reverse_tender_notifications;
CREATE DATABASE reverse_tender_payments;
CREATE DATABASE reverse_tender_analytics;
CREATE DATABASE reverse_tender_vehicles;
```

#### Run Migrations

```bash
# Auth Service
cd services/auth-service
php artisan key:generate
php artisan migrate --seed
cd ../..

# User Service
cd services/user-service
php artisan key:generate
php artisan migrate --seed
cd ../..

# Repeat for all services...
```

#### Automated Migration Script

```bash
# Make the script executable
chmod +x scripts/migrate-all.sh

# Run migrations for all services
./scripts/migrate-all.sh
```

### 5. Start Services

#### Option 1: Docker Compose (Recommended)

```bash
# Start all services
docker-compose -f deployment/docker/development/docker-compose.yml up -d

# View logs
docker-compose -f deployment/docker/development/docker-compose.yml logs -f
```

#### Option 2: Individual Services

```bash
# Terminal 1 - Auth Service
cd services/auth-service
php artisan serve --port=8001

# Terminal 2 - Bidding Service (with Reverb)
cd services/bidding-service
php artisan serve --port=8002 &
php artisan reverb:start --port=8080

# Terminal 3 - User Service
cd services/user-service
php artisan serve --port=8003

# Continue for all services...
```

### 6. Verify Installation

#### Health Checks

```bash
# Check all services
curl http://localhost:8001/api/v1/health  # Auth Service
curl http://localhost:8002/api/v1/health  # Bidding Service
curl http://localhost:8003/api/v1/health  # User Service
curl http://localhost:8007/api/v1/health  # Analytics Service
```

#### Test Real-time Features

```bash
# Test Laravel Reverb WebSocket
wscat -c ws://localhost:8080/app/reverse-tender-key
```

## 🏭 Production Installation

### 1. Server Preparation

#### System Updates

```bash
# Ubuntu/Debian
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx mysql-server redis-server php8.1-fpm php8.1-mysql php8.1-redis composer
```

#### PHP Configuration

```bash
# Install PHP extensions
sudo apt install -y php8.1-curl php8.1-gd php8.1-mbstring php8.1-xml php8.1-zip php8.1-bcmath

# Configure PHP-FPM
sudo nano /etc/php/8.1/fpm/php.ini
```

### 2. Database Configuration

#### MySQL Setup

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create production databases
mysql -u root -p
```

```sql
-- Create databases
CREATE DATABASE reverse_tender_auth;
CREATE DATABASE reverse_tender_users;
CREATE DATABASE reverse_tender_orders;
CREATE DATABASE reverse_tender_bidding;
CREATE DATABASE reverse_tender_notifications;
CREATE DATABASE reverse_tender_payments;
CREATE DATABASE reverse_tender_analytics;
CREATE DATABASE reverse_tender_vehicles;

-- Create database user
CREATE USER 'reversetender'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON reverse_tender_*.* TO 'reversetender'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Application Deployment

#### Clone and Configure

```bash
# Clone to production directory
cd /var/www
sudo git clone https://github.com/abdoElHodaky/larvrevrstender.git
sudo chown -R www-data:www-data larvrevrstender
cd larvrevrstender

# Copy and configure environment files
sudo -u www-data cp .env.example .env
# Configure all service .env files...
```

#### Install Dependencies

```bash
# Install PHP dependencies (production mode)
cd services/auth-service
sudo -u www-data composer install --no-dev --optimize-autoloader
cd ../..

# Repeat for all services...
```

#### Run Migrations

```bash
# Run migrations for all services
./scripts/migrate-all.sh --env=production
```

### 4. Web Server Configuration

#### Nginx Configuration

```nginx
# /etc/nginx/sites-available/reversetender
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/larvrevrstender/public;

    # API Gateway
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket for Laravel Reverb
    location /app/ {
        proxy_pass http://localhost:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Enable Site

```bash
# Enable the site
sudo ln -s /etc/nginx/sites-available/reversetender /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Process Management

#### Systemd Services

Create systemd service files for each microservice:

```ini
# /etc/systemd/system/reversetender-auth.service
[Unit]
Description=Reverse Tender Auth Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/larvrevrstender/services/auth-service
ExecStart=/usr/bin/php artisan serve --host=127.0.0.1 --port=8001
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

#### Enable Services

```bash
# Enable and start all services
sudo systemctl enable reversetender-auth
sudo systemctl enable reversetender-bidding
sudo systemctl enable reversetender-user
# ... repeat for all services

sudo systemctl start reversetender-auth
sudo systemctl start reversetender-bidding
sudo systemctl start reversetender-user
# ... repeat for all services
```

### 6. SSL Configuration

#### Let's Encrypt SSL

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 🐳 Docker Production Deployment

### 1. Build Images

```bash
# Build all service images
docker-compose -f deployment/docker/production/docker-compose.yml build
```

### 2. Deploy with Docker Compose

```bash
# Deploy to production
docker-compose -f deployment/docker/production/docker-compose.yml up -d

# View logs
docker-compose -f deployment/docker/production/docker-compose.yml logs -f
```

### 3. Environment Variables

Create a `.env` file for Docker Compose:

```env
# .env for Docker Compose
APP_VERSION=latest
DB_USERNAME=reversetender
DB_PASSWORD=secure_password
MYSQL_ROOT_PASSWORD=root_password
REDIS_PASSWORD=redis_password

# ZATCA Configuration
ZATCA_API_URL=https://api.zatca.gov.sa
ZATCA_API_KEY=your_zatca_api_key

# Laravel Reverb
REVERB_APP_ID=reverse-tender
REVERB_APP_KEY=reverse-tender-key
REVERB_APP_SECRET=reverse-tender-secret
```

## ☸️ Kubernetes Deployment

### 1. Prepare Kubernetes Manifests

```bash
# Apply namespace
kubectl apply -f deployment/kubernetes/namespace.yaml

# Apply configmaps and secrets
kubectl apply -f deployment/kubernetes/configmaps/
kubectl apply -f deployment/kubernetes/secrets/
```

### 2. Deploy Services

```bash
# Deploy infrastructure
kubectl apply -f deployment/kubernetes/infrastructure/

# Deploy applications
kubectl apply -f deployment/kubernetes/services/

# Deploy ingress
kubectl apply -f deployment/kubernetes/ingress/
```

### 3. Verify Deployment

```bash
# Check pods
kubectl get pods -n reversetender

# Check services
kubectl get services -n reversetender

# Check ingress
kubectl get ingress -n reversetender
```

## 🔧 Troubleshooting

### Common Issues

#### 1. Database Connection Issues

```bash
# Check database connectivity
mysql -h 127.0.0.1 -u reversetender -p

# Check service logs
docker-compose logs auth-service
```

#### 2. Redis Connection Issues

```bash
# Test Redis connection
redis-cli ping

# Check Redis logs
docker-compose logs redis
```

#### 3. Laravel Reverb WebSocket Issues

```bash
# Check Reverb server status
curl http://localhost:8080/health

# Test WebSocket connection
wscat -c ws://localhost:8080/app/reverse-tender-key
```

#### 4. Permission Issues

```bash
# Fix file permissions
sudo chown -R www-data:www-data /var/www/larvrevrstender
sudo chmod -R 755 /var/www/larvrevrstender
sudo chmod -R 775 /var/www/larvrevrstender/storage
```

### Log Locations

- **Application Logs**: `services/*/storage/logs/laravel.log`
- **Nginx Logs**: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- **MySQL Logs**: `/var/log/mysql/error.log`
- **Redis Logs**: `/var/log/redis/redis-server.log`

### Performance Optimization

#### 1. PHP Optimization

```bash
# Install OPcache
sudo apt install php8.1-opcache

# Configure OPcache in php.ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
```

#### 2. Database Optimization

```sql
-- MySQL optimization
SET GLOBAL innodb_buffer_pool_size = 1G;
SET GLOBAL query_cache_size = 64M;
```

#### 3. Redis Optimization

```bash
# Configure Redis for production
sudo nano /etc/redis/redis.conf

# Set memory limit
maxmemory 512mb
maxmemory-policy allkeys-lru
```

## 📞 Support

If you encounter any issues during installation:

1. Check the [Troubleshooting](#troubleshooting) section
2. Review service logs for error messages
3. Consult the [API Documentation](API.md)
4. Open an issue on [GitHub](https://github.com/abdoElHodaky/larvrevrstender/issues)

---

**Installation complete! 🎉**

Your Reverse Tender Platform should now be running and accessible at your configured domain or localhost.

