<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🚀 Deployment Infrastructure</span>
## <span style="font-size: 20px; font-weight: 500; line-height: 1.618; color: #4ECDC4;">Version 2.0 - Multi-Tier Caching Architecture</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Complete deployment infrastructure for the <strong>Reverse Tender Platform V2</strong>, a Laravel 12 + Octane microservices architecture with <strong>multi-tier caching system</strong> supporting multiple environments and cloud providers with enterprise-grade performance optimization.</p>

<div style="margin: 2rem 0; padding: 1.5rem; background: linear-gradient(135deg, #FF6B6B10, #4ECDC410); border-radius: 12px; border-left: 4px solid #FF6B6B;">

### <span style="font-size: 18px; font-weight: 600; color: #FF6B6B;">🚀 V2 Deployment Features</span>

**Multi-Tier Caching Infrastructure:**
- **L1 (Varnish)**: HTTP cache server deployment across all cloud providers
- **L2 (Upstash Redis)**: Managed cloud Redis integration with TLS
- **L3 (MongoDB Atlas)**: Serverless database deployment with auto-scaling
- **Cache Orchestration**: Intelligent cache coordination and failover

**Cloud Provider Support:**
- **DigitalOcean**: Optimized droplet configurations with Varnish
- **Linode**: Enhanced compute instances with multi-tier caching
- **Azure**: Container instances with cache layer integration
- **GCP**: Cloud Run services with intelligent caching
- **OpenStack**: Private cloud deployment with cache optimization

</div>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Infrastructure Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🔥 Laravel 12 + Octane Optimization**: FrankenPHP integration with persistent application state and concurrent request handling
- **🏗️ Microservices Architecture**: 9 independent services with dedicated ports and domain-specific responsibilities
- **📊 Enterprise Monitoring**: Prometheus, Grafana, and Jaeger for comprehensive observability and distributed tracing

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">📋 Complete Infrastructure Configuration</summary>

### Laravel 12 + Octane Optimized

This deployment infrastructure is fully optimized for Laravel 12 with Octane using FrankenPHP for maximum performance:

- **🔥 FrankenPHP Integration** - High-performance PHP server with persistent application state
- **⚡ Octane Worker Management** - Configured for concurrent request handling and optimal resource usage
- **🚀 Performance Tuning** - OPcache, realpath cache, and worker settings optimized for production
- **📊 Health Monitoring** - Octane-specific health checks and metrics endpoints
- **🔄 Graceful Operations** - Proper worker scaling, cache warming, and graceful shutdowns

### Microservices (9 Services)
1. **API Gateway** (Port 8000) - Central entry point and request routing
2. **Auth Service** (Port 8001) - Authentication and authorization
3. **Bidding Service** (Port 8002) - Real-time bidding with Laravel Reverb WebSocket
4. **User Service** (Port 8003) - User management and profiles
5. **Order Service** (Port 8004) - Order processing and management
6. **Notification Service** (Port 8005) - Multi-channel notifications
7. **Payment Service** (Port 8006) - Payment processing and transactions
8. **Analytics Service** (Port 8007) - Data analytics and reporting
9. **VIN OCR Service** (Port 8008) - Vehicle identification and OCR processing

### V2 Infrastructure Components
- **Varnish Cache** - L1 HTTP caching with 2GB allocation
- **Upstash Redis** - L2 managed cloud Redis with TLS
- **MongoDB Atlas** - L3 serverless database with auto-scaling
- **MySQL 8.0** - Primary database with per-service databases
- **Laravel Reverb** - WebSocket server for real-time features
- **Prometheus + Grafana** - Multi-tier cache monitoring and observability
- **Jaeger** - Distributed tracing with cache layer visibility

---

## 📁 **Directory Structure**

```
deployment/
├── README.md                    # This file
├── deploy.sh                    # Main deployment script
├── config/                      # Configuration management
│   ├── base.env                 # Base environment variables
│   ├── environments/            # Environment-specific configs
│   └── frankenphp/             # FrankenPHP configuration
├── docker/                      # Docker orchestration
│   ├── docker-compose.base.yml  # Base services definition
│   ├── docker-compose.override.yml
│   └── environments/           # Environment-specific overrides
├── k8s/                        # Kubernetes manifests
│   ├── base/                   # Base Kubernetes resources
│   └── overlays/               # Environment-specific overlays
├── terraform/                  # Infrastructure as Code
│   ├── main.tf                 # Main Terraform configuration
│   ├── variables.tf            # Variable definitions
│   └── modules/                # Reusable modules
├── scripts/                    # Automation scripts
│   ├── lib/                    # Shared library functions
│   └── octane-management.sh    # Octane-specific operations
├── monitoring/                 # Observability stack
│   ├── prometheus.yml          # Prometheus configuration
│   ├── docker-compose.monitoring.yml
│   └── grafana/               # Grafana dashboards
└── security/                  # Security configurations
    ├── network-policies.yaml  # Kubernetes network policies
    └── pod-security-policies.yaml
```

---

## 🚀 **Quick Start**

### Prerequisites
- Docker & Docker Compose
- Kubernetes cluster (for K8s deployment)
- Terraform (for infrastructure provisioning)

### 1. Environment Setup
```bash
# Copy and configure environment variables
cp deployment/config/base.env .env
# Edit .env with your specific values

# Set required secrets
export DB_PASSWORD="your-secure-password"
export JWT_SECRET="your-jwt-secret"
export REDIS_PASSWORD="your-redis-password"
```

### 2. Development Deployment (Docker Compose)
```bash
# Deploy all services locally
./deployment/deploy.sh -e development -t docker

# Check service status
./deployment/scripts/octane-management.sh status

# Monitor logs
docker-compose -f deployment/docker/docker-compose.base.yml logs -f
```

### 3. Production Deployment (Kubernetes)
```bash
# Deploy to production with Terraform + Kubernetes
./deployment/deploy.sh -e production -p digitalocean -t full

# Check deployment status
kubectl get pods -n reverse-tender

# Monitor Octane workers
./deployment/scripts/octane-management.sh status
```

---

## ⚙️ **Configuration**

### Octane Configuration
Key environment variables for Laravel 12 + Octane:

```bash
# Laravel Octane Settings
OCTANE_SERVER=frankenphp
OCTANE_HTTPS=true
OCTANE_WORKERS=4
OCTANE_TASK_WORKERS=6
OCTANE_MAX_REQUESTS=500
OCTANE_MEMORY_LIMIT=512M

# FrankenPHP Settings
FRANKENPHP_WORKER_MODE=true
FRANKENPHP_NUM_THREADS=4

# Performance Optimization
PHP_OPCACHE_ENABLE=1
PHP_OPCACHE_MEMORY_CONSUMPTION=256
PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
```

### Resource Allocation
Recommended resource limits for production:

```yaml
resources:
  requests:
    memory: "1Gi"
    cpu: "500m"
  limits:
    memory: "2Gi"
    cpu: "1500m"
```

---

## 🛠️ **Octane Management**

### Worker Management
```bash
# Check all services status
./deployment/scripts/octane-management.sh status

# Restart specific service workers
./deployment/scripts/octane-management.sh restart auth-service

# Scale workers for high load
./deployment/scripts/octane-management.sh scale bidding-service 8

# Warm application cache
./deployment/scripts/octane-management.sh warm-cache
```

### Performance Monitoring
```bash
# Monitor performance for 2 minutes
./deployment/scripts/octane-management.sh monitor 120

# Get metrics for specific service
./deployment/scripts/octane-management.sh metrics api-gateway

# Check health of all services
./deployment/scripts/octane-management.sh status
```

### Graceful Operations
```bash
# Graceful shutdown
./deployment/scripts/octane-management.sh shutdown payment-service

# Restart all services
./deployment/scripts/octane-management.sh restart

# Warm cache for all services
./deployment/scripts/octane-management.sh warm-cache
```

---

## 📊 **Monitoring & Observability**

### Prometheus Metrics
- **Octane Worker Health** - Worker status and performance
- **Request Metrics** - Throughput, latency, error rates
- **Resource Usage** - Memory, CPU, and container metrics
- **Business Metrics** - Custom application metrics

### Grafana Dashboards
- **Laravel Octane Overview** - Worker performance and health
- **Microservices Dashboard** - Service-level metrics
- **Infrastructure Monitoring** - System and container metrics
- **Business Intelligence** - Application-specific metrics

### Access Monitoring
```bash
# Start monitoring stack
docker-compose -f deployment/monitoring/docker-compose.monitoring.yml up -d

# Access Grafana: http://localhost:3000 (admin/admin123)
# Access Prometheus: http://localhost:9090
# Access Jaeger: http://localhost:16686
```

---

## 🔒 **Security**

### Network Security
- **Network Policies** - Kubernetes micro-segmentation
- **Pod Security Policies** - Container security standards
- **Secrets Management** - Encrypted secrets handling
- **TLS Encryption** - End-to-end encryption

### Container Security
- **Security Scanning** - Automated vulnerability scanning
- **Non-root Containers** - Principle of least privilege
- **Resource Limits** - Prevent resource exhaustion
- **Health Checks** - Continuous health monitoring

---

## 🌍 **Multi-Cloud Support**

### Supported Providers
- **DigitalOcean** - Kubernetes clusters and managed databases
- **Linode** - Alternative cloud provider support
- **AWS/GCP/Azure** - Extensible for major cloud providers

### Infrastructure as Code
```bash
# Deploy to DigitalOcean
./deployment/deploy.sh -e production -p digitalocean -t terraform

# Deploy to Linode
./deployment/deploy.sh -e production -p linode -t terraform

# Multi-region deployment
./deployment/deploy.sh -e production -p digitalocean -r nyc3,sfo3
```

---

## 🔧 **Troubleshooting**

### Common Issues

#### Octane Workers Not Starting
```bash
# Check worker status
./deployment/scripts/octane-management.sh status api-gateway

# Check logs
docker-compose logs api-gateway

# Restart workers
./deployment/scripts/octane-management.sh restart api-gateway
```

#### High Memory Usage
```bash
# Check current resource usage
kubectl top pods -n reverse-tender

# Scale down workers temporarily
./deployment/scripts/octane-management.sh scale api-gateway 2

# Restart with cache clearing
./deployment/scripts/octane-management.sh restart api-gateway
./deployment/scripts/octane-management.sh warm-cache api-gateway
```

#### WebSocket Connection Issues
```bash
# Check Reverb service status
curl -f http://localhost:8080/health

# Check bidding service logs
docker-compose logs bidding-service

# Restart WebSocket service
./deployment/scripts/octane-management.sh restart bidding-service
```

### Performance Tuning
- **Worker Count** - Adjust based on CPU cores and load
- **Memory Limits** - Monitor and adjust based on usage patterns
- **OPcache Settings** - Tune for your application size
- **Database Connections** - Optimize connection pooling

---

## 📚 **Additional Resources**

### Documentation
- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Laravel Octane Documentation](https://laravel.com/docs/12.x/octane)
- [FrankenPHP Documentation](https://frankenphp.dev/)
- [Laravel Reverb Documentation](https://laravel.com/docs/12.x/reverb)

### Performance Guides
- [Octane Performance Tuning](docs/octane-performance-tuning.md)
- [Production Deployment Guide](docs/production-deployment.md)
- [Monitoring Best Practices](docs/monitoring-guide.md)

---

## 🤝 **Contributing**

1. **Test Changes** - Always test in development environment first
2. **Update Documentation** - Keep README and docs current
3. **Security Review** - Ensure security best practices
4. **Performance Testing** - Validate performance impact

---

## 📄 **License**

This deployment infrastructure is part of the Reverse Tender Platform.

---

**🚀 Ready to deploy your Laravel 12 + Octane microservices platform!**

For support and questions, please refer to the troubleshooting section or create an issue.
