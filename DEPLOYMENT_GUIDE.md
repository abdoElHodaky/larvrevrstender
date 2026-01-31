# Reverse Tender Platform - Complete Deployment Guide

## Overview

This guide covers the complete deployment of the Reverse Tender Platform microservices architecture with Laravel 12+ and modern containerization.

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│           External Clients / API Users              │
└────────────────────┬────────────────────────────────┘
                     │
        ┌────────────▼────────────┐
        │   Nginx API Gateway     │
        │    (Port 80, 443)       │
        └────────────┬────────────┘
                     │
        ┌────────────┴────────────────────────┬──────────────┐
        │                                     │              │
   ┌────▼──────┐  ┌──────────┐  ┌─────────┐ │  ┌──────────┐ │
   │Auth Svc   │  │User Svc  │  │Bid Svc  │ │  │Order Svc │ │
   │(8000)     │  │(8001)    │  │(8002)   │ │  │(8003)    │ │
   └────┬──────┘  └──────────┘  └─────────┘ │  └──────────┘ │
        │                                    │               │
        │  ┌──────────────┐  ┌─────────────┐│  ┌──────────┐  │
        │  │Payment Svc   │  │Analytics Sv ││  │VIN OCR   │  │
        │  │(8004)        │  │(8005)       ││  │(8006)    │  │
        │  └──────────────┘  └─────────────┘│  └──────────┘  │
        │                                    │               │
        └────────────┬─────────────────────┬┘               │
                     │                     │                │
        ┌────────────▼──────┐  ┌──────────▼────────┐       │
        │   MySQL 8.0       │  │   Redis 7         │       │
        │   Database        │  │   Cache/Queue     │       │
        │   (Port 3306)     │  │   (Port 6379)     │       │
        └───────────────────┘  └───────────────────┘       │
                                                             │
                    ┌──────────────────┐                    │
                    │ Horizon Workers  │◄───────────────────┘
                    │ (Queue Monitor)  │
                    └──────────────────┘
```

## Services Overview

### 1. Auth Service (Port 8000)
- **Purpose**: Authentication and authorization
- **Database**: `auth_service`
- **Key Features**: JWT tokens, OTP verification, OAuth
- **Dependencies**: MySQL, Redis, JWT Auth 2.1+

### 2. User Service (Port 8001)
- **Purpose**: User profile management
- **Database**: `user_service`
- **Key Features**: Customer/Merchant profiles, KYC verification
- **Dependencies**: MySQL, Redis, Media Library

### 3. Bidding Service (Port 8002)
- **Purpose**: Real-time bidding and auctions
- **Database**: `bidding_service`
- **Key Features**: WebSocket connections, real-time updates
- **Dependencies**: MySQL, Redis, Pusher 7.2+, Ratchet/Pawl

### 4. Order Service (Port 8003)
- **Purpose**: Order and part request management
- **Database**: `order_service`
- **Key Features**: File uploads, order tracking
- **Dependencies**: MySQL, Redis, Media Library 11.9+

### 5. Payment Service (Port 8004)
- **Purpose**: Payment processing and invoicing
- **Database**: `payment_service`
- **Key Features**: Stripe, PayPal, ZATCA e-invoicing
- **Dependencies**: MySQL, Redis, Stripe 15.8+, PayPal 1.0+

### 6. Analytics Service (Port 8005)
- **Purpose**: Analytics and reporting
- **Database**: `analytics_service`
- **Key Features**: Data aggregation, report generation
- **Dependencies**: MySQL, Redis, Excel 3.1+, Analytics 4.2+

### 7. VIN OCR Service (Port 8006)
- **Purpose**: VIN extraction from images
- **Database**: `vin_ocr_service`
- **Key Features**: OCR processing, vehicle identification
- **Dependencies**: MySQL, Redis, Tesseract OCR 2.13+, Intervention Image

## Infrastructure Services

### MySQL 8.0
- **Container**: `reverse_tender_mysql`
- **Port**: 3306
- **Databases**: 7 separate databases (one per service)
- **Volume**: `mysql_data` for persistence

### Redis 7
- **Container**: `reverse_tender_redis`
- **Port**: 6379
- **Usage**: Caching (DB 1), Queues (DB 0), Sessions
- **Volume**: `redis_data` for persistence

### Nginx API Gateway
- **Container**: `reverse_tender_gateway`
- **Ports**: 80 (HTTP), 443 (HTTPS)
- **Purpose**: Load balancing, SSL termination, routing

### Horizon Queue Worker
- **Container**: `reverse_tender_horizon`
- **Purpose**: Background job processing
- **Monitors**: All Redis queues across services

## Deployment Instructions

### Prerequisites
- Docker 20.10+
- Docker Compose 2.0+
- 4GB+ RAM
- 20GB+ disk space

### 1. Clone and Setup
```bash
git clone <repository-url>
cd larvrevrstender
```

### 2. Environment Configuration
Copy and configure environment files for each service:
```bash
# Copy environment files
for service in auth-service user-service bidding-service order-service payment-service analytics-service vin-ocr-service; do
  cp services/$service/.env.example services/$service/.env
done

# Generate application keys
for service in auth-service user-service bidding-service order-service payment-service analytics-service vin-ocr-service; do
  cd services/$service
  php artisan key:generate
  cd ../..
done
```

### 3. Database Initialization
Create MySQL initialization scripts:
```bash
mkdir -p docker/mysql/init
```

Create `docker/mysql/init/01-create-databases.sql`:
```sql
CREATE DATABASE IF NOT EXISTS auth_service;
CREATE DATABASE IF NOT EXISTS user_service;
CREATE DATABASE IF NOT EXISTS bidding_service;
CREATE DATABASE IF NOT EXISTS order_service;
CREATE DATABASE IF NOT EXISTS payment_service;
CREATE DATABASE IF NOT EXISTS analytics_service;
CREATE DATABASE IF NOT EXISTS vin_ocr_service;

GRANT ALL PRIVILEGES ON auth_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON user_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON bidding_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON order_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON payment_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON analytics_service.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON vin_ocr_service.* TO 'root'@'%';

FLUSH PRIVILEGES;
```

### 4. Nginx Configuration
Create `docker/nginx/nginx.conf`:
```nginx
events {
    worker_connections 1024;
}

http {
    upstream auth_service {
        server auth-service:8000;
    }
    
    upstream user_service {
        server user-service:8000;
    }
    
    upstream bidding_service {
        server bidding-service:8000;
    }
    
    upstream order_service {
        server order-service:8000;
    }
    
    upstream payment_service {
        server payment-service:8000;
    }
    
    upstream analytics_service {
        server analytics-service:8000;
    }
    
    upstream vin_ocr_service {
        server vin-ocr-service:8000;
    }

    server {
        listen 80;
        server_name localhost;

        location /api/auth/ {
            proxy_pass http://auth_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        location /api/users/ {
            proxy_pass http://user_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        location /api/bidding/ {
            proxy_pass http://bidding_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        location /api/orders/ {
            proxy_pass http://order_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        location /api/payments/ {
            proxy_pass http://payment_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        location /api/analytics/ {
            proxy_pass http://analytics_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }

        location /api/vin-ocr/ {
            proxy_pass http://vin_ocr_service/;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
        }
    }
}
```

### 5. Service Dockerfiles
Create `Dockerfile` for each service (example for auth-service):
```dockerfile
FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory contents
COPY . /var/www/html

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www/html

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port 8000 and start php-fpm server
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
```

### 6. Deploy Services
```bash
# Build and start all services
docker-compose up -d

# Run migrations for each service
docker-compose exec auth-service php artisan migrate
docker-compose exec user-service php artisan migrate
docker-compose exec bidding-service php artisan migrate
docker-compose exec order-service php artisan migrate
docker-compose exec payment-service php artisan migrate
docker-compose exec analytics-service php artisan migrate
docker-compose exec vin-ocr-service php artisan migrate
```

## Service Dependencies

### Common Dependencies (All Services)
- **Laravel Framework**: ^12.0
- **PHP**: ^8.3
- **Laravel Sanctum**: ^4.0 (API authentication)
- **Laravel Horizon**: ^5.28 (Queue monitoring)
- **Laravel Telescope**: ^5.2 (Debugging)
- **Guzzle HTTP**: ^7.8 (HTTP client)
- **Predis**: ^2.2 (Redis client)

### Service-Specific Dependencies

#### Auth Service
- **JWT Auth**: ^2.1 (JSON Web Tokens)
- **Twilio SDK**: ^7.0 (SMS verification)

#### Bidding Service
- **Pusher**: ^7.2 (Real-time WebSocket)
- **Ratchet/Pawl**: ^0.4 (WebSocket client)

#### Order Service
- **Media Library**: ^11.9 (File management)

#### Payment Service
- **Stripe**: ^15.8 (Payment processing)
- **PayPal SDK**: ^1.0 (PayPal integration)
- **QR Code**: ^5.0 (QR code generation)

#### Analytics Service
- **Laravel Excel**: ^3.1 (Excel export)
- **Google Analytics**: ^4.2 (Analytics integration)

#### VIN OCR Service
- **Intervention Image**: ^3.8 (Image processing)
- **Tesseract OCR**: ^2.13 (OCR processing)
- **Media Library**: ^11.9 (File management)

## Database Dependencies

### Per-Service Databases
Each service has its own isolated database:

1. **auth_service**: Authentication data, JWT tokens, OTP records
2. **user_service**: User profiles, KYC documents, addresses
3. **bidding_service**: Bids, auctions, real-time data
4. **order_service**: Orders, part requests, file attachments
5. **payment_service**: Transactions, invoices, payment records
6. **analytics_service**: Aggregated data, reports, metrics
7. **vin_ocr_service**: OCR results, vehicle data, processing logs

### Redis Usage
- **DB 0**: Default operations, queues, pub/sub
- **DB 1**: Application cache, session storage

## Monitoring and Health Checks

### Health Check Endpoints
Each service exposes a health check at `/up`:
- http://localhost:8000/up (Auth Service)
- http://localhost:8001/up (User Service)
- http://localhost:8002/up (Bidding Service)
- http://localhost:8003/up (Order Service)
- http://localhost:8004/up (Payment Service)
- http://localhost:8005/up (Analytics Service)
- http://localhost:8006/up (VIN OCR Service)

### Queue Monitoring
Access Horizon dashboard at: http://localhost:8000/horizon

### Logs
View service logs:
```bash
docker-compose logs -f [service-name]
```

## Security Considerations

### Environment Variables
- All sensitive data in `.env` files
- Separate environment per service
- No hardcoded credentials

### Network Security
- Services communicate via internal Docker network
- External access only through Nginx gateway
- Database and Redis not exposed externally

### Authentication
- JWT tokens for API authentication
- Sanctum for SPA authentication
- Service-to-service authentication via internal tokens

## Scaling and Performance

### Horizontal Scaling
```bash
# Scale specific services
docker-compose up -d --scale auth-service=3
docker-compose up -d --scale user-service=2
```

### Load Balancing
Nginx automatically load balances between scaled instances.

### Database Optimization
- Separate databases prevent cross-service impact
- Redis caching reduces database load
- Connection pooling for better performance

## Troubleshooting

### Common Issues

1. **Service won't start**: Check logs with `docker-compose logs [service]`
2. **Database connection failed**: Verify MySQL is running and credentials are correct
3. **Redis connection failed**: Ensure Redis container is healthy
4. **Port conflicts**: Check if ports 8000-8006 are available

### Debug Commands
```bash
# Check service status
docker-compose ps

# View service logs
docker-compose logs -f [service-name]

# Execute commands in service container
docker-compose exec [service-name] bash

# Restart specific service
docker-compose restart [service-name]
```

## Next Steps

1. **SSL Configuration**: Add SSL certificates for HTTPS
2. **CI/CD Pipeline**: Implement automated deployment
3. **Monitoring**: Add Prometheus/Grafana monitoring
4. **Backup Strategy**: Implement database backup automation
5. **Documentation**: Create API documentation with Swagger
6. **Testing**: Implement comprehensive test suites
7. **Performance**: Add APM monitoring and optimization

## Support

For deployment issues or questions, refer to:
- Laravel 12 Documentation
- Docker Compose Documentation
- Service-specific package documentation

