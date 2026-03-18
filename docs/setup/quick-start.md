# 🚀 Quick Start Guide

## 📋 Overview

This guide will help you get the Reverse Tender Platform up and running quickly for development purposes. Follow these steps to set up your local development environment.

## ⚡ Prerequisites

### Required Software
- **PHP 8.3+** with extensions:
  - `ext-bcmath`
  - `ext-ctype`
  - `ext-curl`
  - `ext-dom`
  - `ext-fileinfo`
  - `ext-json`
  - `ext-mbstring`
  - `ext-openssl`
  - `ext-pcre`
  - `ext-pdo`
  - `ext-tokenizer`
  - `ext-xml`
- **Composer 2.0+**
- **MySQL 8.0+** or **MariaDB 10.4+**
- **Redis 6.0+**
- **Node.js 18+** (for frontend assets)
- **Git**

### Optional but Recommended
- **Docker & Docker Compose** (for containerized development)
- **Laravel Valet** (for macOS users)
- **Laravel Herd** (for Windows users)

## 🏗️ Quick Setup (5 Minutes)

### 1. Clone the Repository

```bash
git clone https://github.com/your-org/reverse-tender-platform.git
cd reverse-tender-platform
git checkout v2
```

### 2. Install Dependencies for All Services

```bash
# Run the setup script
./scripts/setup-dev.sh

# Or manually for each service:
for service in services/*/; do
    echo "Installing dependencies for $(basename $service)..."
    cd "$service"
    composer install --no-interaction --prefer-dist
    cd ../..
done
```

### 3. Environment Configuration

```bash
# Copy environment files for all services
for service in services/*/; do
    if [ -f "$service/.env.example" ]; then
        cp "$service/.env.example" "$service/.env"
        echo "Created .env for $(basename $service)"
    fi
done
```

### 4. Generate Application Keys

```bash
# Generate Laravel application keys for all services
for service in services/*/; do
    if [ -f "$service/artisan" ]; then
        cd "$service"
        php artisan key:generate
        cd ../..
    fi
done
```

### 5. Database Setup

```bash
# Create databases for all services
mysql -u root -p << EOF
CREATE DATABASE auth_service;
CREATE DATABASE user_service;
CREATE DATABASE auction_service;
CREATE DATABASE bidding_service;
CREATE DATABASE order_service;
CREATE DATABASE payment_service;
CREATE DATABASE gateway_service;
CREATE DATABASE notification_service;
CREATE DATABASE analytics_service;
CREATE DATABASE vin_ocr_service;
EOF

# Run migrations for all services
for service in services/*/; do
    if [ -f "$service/artisan" ]; then
        cd "$service"
        php artisan migrate
        cd ../..
    fi
done
```

### 6. Start Development Servers

```bash
# Start all services (requires multiple terminals)
# Terminal 1 - Gateway Service
cd services/gateway-service && php artisan serve --port=8000

# Terminal 2 - Auth Service  
cd services/auth-service && php artisan serve --port=8001

# Terminal 3 - User Service
cd services/user-service && php artisan serve --port=8002

# Continue for other services...
```

## 🐳 Docker Quick Setup (Recommended)

### 1. Using Docker Compose

```bash
# Clone and navigate
git clone https://github.com/your-org/reverse-tender-platform.git
cd reverse-tender-platform
git checkout v2

# Start all services with Docker
docker-compose up -d

# Install dependencies
docker-compose exec gateway-service composer install
docker-compose exec auth-service composer install
# ... repeat for other services

# Run migrations
docker-compose exec auth-service php artisan migrate
docker-compose exec user-service php artisan migrate
# ... repeat for other services
```

### 2. Access Services

Once running, services will be available at:

- **Gateway Service**: http://localhost:8000
- **Auth Service**: http://localhost:8001  
- **User Service**: http://localhost:8002
- **Auction Service**: http://localhost:8003
- **Bidding Service**: http://localhost:8004
- **Order Service**: http://localhost:8005
- **Payment Service**: http://localhost:8006
- **Notification Service**: http://localhost:8007
- **Analytics Service**: http://localhost:8008
- **VIN OCR Service**: http://localhost:8009

## 🔧 Configuration

### Environment Variables

Each service requires specific environment variables. Key configurations:

#### Database Configuration (All Services)
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=service_name_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

#### Redis Configuration (All Services)
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### RPC Configuration (All Services)
```env
# Service-specific RPC tokens
RPC_AUTH_SERVICE_TOKEN=your_auth_token
RPC_USER_SERVICE_TOKEN=your_user_token
RPC_PAYMENT_SERVICE_TOKEN=your_payment_token
# ... add tokens for all services
```

#### Gateway Service Specific
```env
APP_URL=http://localhost:8000

# Service URLs for RPC communication
AUTH_SERVICE_URL=http://localhost:8001
USER_SERVICE_URL=http://localhost:8002
AUCTION_SERVICE_URL=http://localhost:8003
# ... add URLs for all services
```

#### Payment Service Specific
```env
# Payment Gateway Configuration
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_secret

# ZATCA Configuration (Saudi Arabia)
ZATCA_ENVIRONMENT=sandbox
ZATCA_CERTIFICATE_PATH=/path/to/certificate
```

## 🧪 Verify Installation

### 1. Health Checks

```bash
# Check all services are running
curl http://localhost:8000/health  # Gateway
curl http://localhost:8001/health  # Auth
curl http://localhost:8002/health  # User
# ... check all services
```

### 2. Run Tests

```bash
# Run tests for all services
for service in services/*/; do
    if [ -f "$service/phpunit.xml" ]; then
        echo "Testing $(basename $service)..."
        cd "$service"
        ./vendor/bin/phpunit --no-coverage
        cd ../..
    fi
done
```

### 3. Test RPC Communication

```bash
# Test inter-service communication
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

## 📚 Next Steps

### Development Workflow

1. **Choose a Service**: Start with the service you want to work on
2. **Read Service Documentation**: Check `services/{service-name}/README.md`
3. **Understand API Endpoints**: Review `routes/api.php` in each service
4. **Check Database Schema**: Review migrations in `database/migrations/`
5. **Run Tests**: Ensure tests pass before making changes

### Common Development Tasks

```bash
# Create a new migration
cd services/user-service
php artisan make:migration create_new_table

# Create a new controller
php artisan make:controller NewController

# Create a new model
php artisan make:model NewModel

# Run specific tests
./vendor/bin/phpunit tests/Feature/SpecificTest.php

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Debugging

```bash
# Enable debug mode
# In .env files, set:
APP_DEBUG=true
LOG_LEVEL=debug

# View logs
tail -f services/gateway-service/storage/logs/laravel.log
tail -f services/auth-service/storage/logs/laravel.log

# Use Laravel Telescope (if enabled)
# Visit: http://localhost:8001/telescope
```

## 🆘 Troubleshooting

### Common Issues

#### 1. Composer Install Fails
```bash
# Clear composer cache
composer clear-cache

# Install with verbose output
composer install -vvv

# Check PHP extensions
php -m | grep -E "(bcmath|ctype|curl|dom|fileinfo|json|mbstring|openssl|pcre|pdo|tokenizer|xml)"
```

#### 2. Database Connection Issues
```bash
# Test database connection
mysql -h 127.0.0.1 -u your_username -p

# Check database exists
mysql -u your_username -p -e "SHOW DATABASES;"

# Verify .env database settings
grep DB_ services/auth-service/.env
```

#### 3. Permission Issues
```bash
# Fix storage permissions
chmod -R 775 services/*/storage
chmod -R 775 services/*/bootstrap/cache

# Fix ownership (Linux/macOS)
sudo chown -R $USER:www-data services/*/storage
sudo chown -R $USER:www-data services/*/bootstrap/cache
```

#### 4. Port Conflicts
```bash
# Check what's running on ports
lsof -i :8000
lsof -i :8001

# Kill processes if needed
kill -9 $(lsof -t -i:8000)

# Use different ports
php artisan serve --port=8010
```

### Getting Help

- **Documentation**: Check `/docs` directory for detailed guides
- **API Documentation**: Visit service endpoints with `/docs` suffix
- **Logs**: Check `storage/logs/laravel.log` in each service
- **Debug Mode**: Enable `APP_DEBUG=true` for detailed error messages

## 🎯 Success Criteria

You've successfully set up the development environment when:

- ✅ All 11 services start without errors
- ✅ Database connections work for all services
- ✅ Health check endpoints return 200 status
- ✅ Basic API calls work through the gateway
- ✅ RPC communication between services functions
- ✅ Tests pass for all services

**Estimated Setup Time**: 15-30 minutes (depending on internet speed and system performance)

---

You're now ready to start developing on the Reverse Tender Platform! 🎉

