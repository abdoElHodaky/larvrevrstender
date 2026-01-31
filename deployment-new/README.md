# Unified Deployment System for Reverse Tender Platform

## 🎯 **Overview**

This unified deployment system eliminates configuration duplication and provides a single, consistent interface for deploying the Reverse Tender Platform across multiple environments and cloud providers.

## 🏗️ **Architecture**

### **Design Principles**
- **DRY (Don't Repeat Yourself)**: Single source of truth for all configurations
- **Separation of Concerns**: Clear boundaries between infrastructure, orchestration, and application
- **Environment Parity**: Consistent deployment across all environments
- **Provider Agnostic**: Abstract cloud provider specifics through configuration

### **Directory Structure**
```
deployment-new/
├── README.md                    # This file
├── deploy.sh                    # Single deployment entry point
├── config/                      # Configuration management
│   ├── base.env                # Common configuration
│   ├── environments/           # Environment-specific configs
│   │   ├── development.env
│   │   ├── staging.env
│   │   └── production.env
│   └── providers/              # Cloud provider configs
│       ├── digitalocean.env
│       └── linode.env
├── docker/                     # Docker Compose configurations
│   ├── docker-compose.base.yml        # Base service definitions
│   ├── docker-compose.override.yml    # Development overrides
│   └── environments/                  # Environment-specific overrides
│       ├── production.yml
│       └── staging.yml
├── terraform/                  # Infrastructure as Code
│   ├── main.tf                # Main orchestration
│   ├── variables.tf           # Variable definitions
│   └── modules/               # Terraform modules
│       ├── common/            # Shared resources
│       ├── digitalocean/      # DigitalOcean-specific
│       └── linode/            # Linode-specific
├── k8s/                       # Kubernetes configurations
│   ├── base/                  # Base manifests
│   └── overlays/              # Environment overlays
│       ├── development/
│       ├── staging/
│       └── production/
├── scripts/                   # Deployment scripts
│   ├── lib/                   # Shared libraries
│   │   ├── common.sh         # Common utilities
│   │   ├── docker.sh         # Docker operations
│   │   ├── terraform.sh      # Terraform operations
│   │   └── kubernetes.sh     # Kubernetes operations
│   └── validate.sh           # Configuration validation
└── tests/                     # Deployment tests
    ├── docker-compose.test.yml
    ├── terraform.test.tf
    └── integration/
```

## 🚀 **Quick Start**

### **Prerequisites**
- Docker & Docker Compose
- Terraform (for cloud deployments)
- kubectl (for Kubernetes deployments)
- Cloud provider CLI tools (optional)

### **Local Development**
```bash
# Clone and navigate to deployment directory
cd deployment-new

# Deploy local development environment
./deploy.sh -e development -t docker

# Access services
open http://localhost:8000  # API Gateway
open http://localhost:8080  # phpMyAdmin
open http://localhost:8025  # MailHog
```

### **Staging Deployment**
```bash
# Set required environment variables
export DIGITALOCEAN_TOKEN="your_do_token"
# or
export LINODE_TOKEN="your_linode_token"

# Deploy to staging
./deploy.sh -e staging -p digitalocean

# Dry run first (recommended)
./deploy.sh -e staging -p digitalocean --dry-run
```

### **Production Deployment**
```bash
# Set required environment variables
export DIGITALOCEAN_TOKEN="your_do_token"
export PRODUCTION_DB_PASSWORD="secure_password"
export JWT_SECRET="your_jwt_secret"

# Deploy to production
./deploy.sh -e production -p digitalocean

# Infrastructure only
./deploy.sh -e production -p digitalocean -t infrastructure

# Application only
./deploy.sh -e production -p digitalocean -t application
```

## 📋 **Deployment Options**

### **Command Line Interface**
```bash
./deploy.sh [OPTIONS]

OPTIONS:
    -e, --environment ENV       Environment (development, staging, production)
    -p, --provider PROVIDER     Cloud provider (digitalocean, linode)
    -t, --type TYPE            Deployment type (full, infrastructure, application, docker)
    -d, --dry-run              Show what would be deployed without executing
    -v, --verbose              Enable verbose output
    -s, --skip-validation      Skip configuration validation
    -f, --force                Force deployment even if validation fails
    -h, --help                 Show help message
```

### **Environment Variables**
```bash
# Required for cloud deployments
DIGITALOCEAN_TOKEN=your_do_token
LINODE_TOKEN=your_linode_token

# Optional configuration
ENVIRONMENT=production
CLOUD_PROVIDER=digitalocean
DEPLOYMENT_TYPE=full
DRY_RUN=false
VERBOSE=false
```

## 🔧 **Configuration Management**

### **Configuration Hierarchy**
Configurations are loaded in this order (later overrides earlier):
1. `config/base.env` - Common configuration
2. `config/environments/${ENVIRONMENT}.env` - Environment-specific
3. `config/providers/${CLOUD_PROVIDER}.env` - Provider-specific
4. Environment variables - Highest priority

### **Environment-Specific Configuration**

#### **Development**
- Local Docker deployment
- Debug mode enabled
- Mock external services
- Development tools included (phpMyAdmin, MailHog, Redis Commander)

#### **Staging**
- Cloud deployment with reduced resources
- Debug mode enabled
- Sandbox external services
- Optional monitoring

#### **Production**
- Full cloud deployment with resource limits
- Debug mode disabled
- Production external services
- Full monitoring and alerting

### **Provider-Specific Configuration**

#### **DigitalOcean**
- Kubernetes clusters with auto-scaling
- Managed MySQL and Redis
- Load balancers with health checks
- VPC networking
- Spaces for object storage

#### **Linode**
- LKE clusters with auto-scaling
- Managed MySQL databases
- NodeBalancers
- Private networking
- Object storage

## 🐳 **Docker Deployment**

### **Compose File Structure**
- **Base**: `docker-compose.base.yml` - Core service definitions
- **Override**: `docker-compose.override.yml` - Development overrides
- **Environment**: `environments/{env}.yml` - Environment-specific overrides

### **Service Architecture**
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   API Gateway   │    │  Auth Service   │    │ Bidding Service │
│     :8000       │    │     :8001       │    │     :8002       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  User Service   │    │ Order Service   │    │Notification Svc │
│     :8003       │    │     :8004       │    │     :8005       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ Payment Service │    │Analytics Service│    │ VIN OCR Service │
│     :8006       │    │     :8007       │    │     :8008       │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
         ┌─────────────────────────────────────────────────┐
         │              Infrastructure                     │
         │  ┌─────────────┐  ┌─────────────┐  ┌─────────┐ │
         │  │    MySQL    │  │    Redis    │  │  Nginx  │ │
         │  │    :3306    │  │    :6379    │  │ :80/443 │ │
         │  └─────────────┘  └─────────────┘  └─────────┘ │
         └─────────────────────────────────────────────────┘
```

### **Development Tools**
- **phpMyAdmin**: Database management (http://localhost:8080)
- **Redis Commander**: Redis management (http://localhost:8081)
- **MailHog**: Email testing (http://localhost:8025)

## ☁️ **Cloud Deployment**

### **Infrastructure Components**
- **Kubernetes Clusters**: Auto-scaling worker nodes
- **Managed Databases**: MySQL 8.0 with high availability
- **Cache Layer**: Redis 7.0 for sessions and caching
- **Load Balancers**: Health checks and SSL termination
- **Object Storage**: File uploads and static assets
- **Monitoring**: Prometheus and Grafana
- **Networking**: VPC with private subnets

### **Deployment Flow**
1. **Validation**: Check configuration and prerequisites
2. **Infrastructure**: Provision cloud resources with Terraform
3. **Application**: Deploy services to Kubernetes
4. **Verification**: Health checks and smoke tests

## 🔍 **Monitoring & Observability**

### **Health Checks**
- Service-level health endpoints (`/health`)
- Container health checks (Docker)
- Kubernetes liveness and readiness probes
- Load balancer health checks

### **Monitoring Stack**
- **Prometheus**: Metrics collection and alerting
- **Grafana**: Dashboards and visualization
- **Application Logs**: Centralized logging
- **Infrastructure Metrics**: Resource utilization

### **Service URLs**
```bash
# Application Services
API Gateway:      http://localhost:8000
Auth Service:     http://localhost:8001/health
Bidding Service:  http://localhost:8002/health
User Service:     http://localhost:8003/health
Order Service:    http://localhost:8004/health
Notification:     http://localhost:8005/health
Payment Service:  http://localhost:8006/health
Analytics:        http://localhost:8007/health
VIN OCR Service:  http://localhost:8008/health

# Monitoring
Prometheus:       http://localhost:9090
Grafana:          http://localhost:3000

# Development Tools (development only)
phpMyAdmin:       http://localhost:8080
Redis Commander:  http://localhost:8081
MailHog:          http://localhost:8025
```

## 🔒 **Security**

### **Security Features**
- JWT-based authentication with RS256
- Environment-specific secrets management
- TLS encryption for all external communication
- Network isolation with VPCs
- Resource limits and quotas
- Regular security updates

### **Secrets Management**
- Environment variables for sensitive data
- Cloud provider secret management integration
- Separate secrets for each environment
- Rotation procedures documented

## 🧪 **Testing**

### **Validation**
```bash
# Configuration validation
./scripts/validate.sh

# Dry run deployment
./deploy.sh --dry-run

# Infrastructure testing
terraform plan
```

### **Integration Tests**
```bash
# Run integration tests
cd tests/integration
./run-tests.sh
```

## 🔄 **Migration Guide**

### **From Old Deployment Structure**
1. **Backup Current Configuration**
   ```bash
   cp -r deployment deployment-backup
   ```

2. **Update Environment Variables**
   - Review `config/environments/` files
   - Update with your current values

3. **Test New Deployment**
   ```bash
   ./deploy.sh -e development -t docker --dry-run
   ```

4. **Migrate Gradually**
   - Start with development environment
   - Move to staging
   - Finally migrate production

### **Rollback Procedure**
```bash
# Restore old deployment structure
mv deployment deployment-new-backup
mv deployment-backup deployment

# Use old deployment scripts
cd deployment
./scripts/deploy-digitalocean.sh
```

## 📚 **Troubleshooting**

### **Common Issues**

#### **Docker Issues**
```bash
# Check container logs
docker-compose logs [service_name]

# Restart services
docker-compose restart [service_name]

# Clean up resources
docker system prune -f
```

#### **Cloud Deployment Issues**
```bash
# Check Terraform state
terraform show

# Validate Kubernetes deployment
kubectl get pods -n reverse-tender
kubectl describe pod [pod_name] -n reverse-tender

# Check service logs
kubectl logs [pod_name] -n reverse-tender
```

#### **Configuration Issues**
```bash
# Validate configuration
./scripts/validate.sh

# Check environment variables
env | grep -E "(DB_|REDIS_|JWT_)"

# Test connectivity
curl http://localhost:8000/health
```

### **Getting Help**
1. Check the logs for specific error messages
2. Verify all required environment variables are set
3. Ensure all prerequisites are installed
4. Try running with `--verbose` flag for detailed output
5. Use `--dry-run` to see what would be executed

## 🤝 **Contributing**

### **Adding New Environments**
1. Create `config/environments/new-env.env`
2. Add environment-specific Docker override
3. Update validation in `scripts/validate.sh`
4. Test thoroughly

### **Adding New Cloud Providers**
1. Create `config/providers/new-provider.env`
2. Add Terraform module in `terraform/modules/new-provider/`
3. Update deployment scripts
4. Add provider-specific documentation

## 📄 **License**

This deployment system is part of the Reverse Tender Platform and follows the same licensing terms.

---

**🚀 Ready to deploy? Start with `./deploy.sh --help` to see all available options!**

