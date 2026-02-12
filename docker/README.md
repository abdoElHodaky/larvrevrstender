<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🐳 Docker Configuration</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Docker configurations for <strong>local development and testing</strong> of the Reverse Tender Platform microservices with containerized infrastructure and service orchestration.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Container Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🚀 Development Environment**: Complete microservices stack with Docker Compose orchestration
- **🔧 Service Configuration**: Nginx reverse proxy, MySQL database, and SSL certificate management
- **⚡ Quick Deployment**: One-command startup with automated service discovery and health checks

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">📋 Complete Docker Configuration</summary>

### Directory Structure

```
docker/
├── README.md                    # This file
├── nginx/                       # Nginx reverse proxy configuration
│   ├── nginx.conf              # Main Nginx configuration
│   └── conf.d/                 # Virtual host configurations
├── mysql/                      # MySQL database configuration
│   └── init/                   # Database initialization scripts
└── ssl/                        # SSL certificates for HTTPS
```

### Development Environment

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop all services
docker-compose down

# Rebuild services
docker-compose up --build -d
```

### Service URLs

- **API Gateway**: http://localhost:8000
- **Auth Service**: http://localhost:8001
- **User Service**: http://localhost:8003
- **Order Service**: http://localhost:8002
- **Bidding Service**: http://localhost:8004
- **Notification Service**: http://localhost:8005
- **Payment Service**: http://localhost:8007
- **Analytics Service**: http://localhost:8008
- **VIN OCR Service**: http://localhost:8006

### Database Access

- **MySQL**: localhost:3306
  - Username: `root`
  - Password: `root_password`
  - Database: `reverse_tender`

- **Redis**: localhost:6379

## 🔧 Configuration

### Environment Variables

Key environment variables are configured in `docker-compose.yml`:

```yaml
# Database Configuration
MYSQL_ROOT_PASSWORD: root_password
MYSQL_DATABASE: reverse_tender

# Service Configuration
APP_ENV: development
APP_DEBUG: true
DB_HOST: mysql
REDIS_HOST: redis
```

### Nginx Configuration

The Nginx service acts as a reverse proxy and load balancer:

- **Port 80**: HTTP traffic
- **Port 443**: HTTPS traffic (with SSL certificates)
- **Upstream Services**: All microservices with health checks

### Volume Mounts

- `mysql_data`: Persistent MySQL data
- `redis_data`: Persistent Redis data
- `./docker/nginx/nginx.conf`: Nginx configuration
- `./docker/ssl`: SSL certificates

## 🔍 Health Checks

All services include health check endpoints:

```bash
# Check service health
curl http://localhost:8000/health
curl http://localhost:8001/health
# ... for all services
```

## 🛠️ Development Tips

### Debugging Services

```bash
# View service logs
docker-compose logs -f auth-service

# Execute commands in containers
docker-compose exec auth-service php artisan migrate

# Access container shell
docker-compose exec mysql mysql -u root -p
```

### Database Management

```bash
# Run migrations
docker-compose exec auth-service php artisan migrate

# Seed database
docker-compose exec auth-service php artisan db:seed

# Reset database
docker-compose exec auth-service php artisan migrate:fresh --seed
```

## 🚀 Production Deployment

For production deployment, use the configurations in `/deployment/docker/production/`:

```bash
cd deployment/docker/production
docker-compose -f docker-compose.yml up -d
```

## 🔗 Related Documentation

- **[Deployment Guide](../DEPLOYMENT_GUIDE.md)**: Production deployment instructions
- **[Multi-Cloud Deployment](../MULTI_CLOUD_DEPLOYMENT.md)**: Kubernetes deployment
- **[Development Guide](../docs/development.md)**: Development setup and guidelines
