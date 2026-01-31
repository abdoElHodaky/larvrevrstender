# Unified Deployment System for Reverse Tender Platform

This directory contains the unified deployment system that consolidates all deployment configurations and scripts into a single, maintainable structure.

## 🏗️ Architecture Overview

The unified deployment system supports:
- **Multi-Environment**: Development, Staging, Production
- **Multi-Cloud**: DigitalOcean, Linode
- **Multi-Platform**: Docker Compose, Kubernetes, Terraform
- **Configuration Management**: Hierarchical configuration loading
- **Validation**: Comprehensive pre-deployment validation

## 📁 Directory Structure

```
deployment/
├── deploy.sh                    # 🎯 Single entry point for all deployments
├── config/                      # 📋 Hierarchical configuration management
│   ├── base.env                # Common settings (100+ variables)
│   ├── environments/           # Environment-specific configs
│   │   ├── development.env     # Local development settings
│   │   ├── staging.env         # Staging cloud deployment
│   │   └── production.env      # Production deployment
│   └── providers/              # Cloud provider configurations
│       ├── digitalocean.env    # DigitalOcean-specific settings
│       └── linode.env          # Linode-specific settings
├── docker/                     # 🐳 Consolidated Docker configurations
│   ├── docker-compose.base.yml        # All 8 microservices (450+ lines)
│   ├── docker-compose.override.yml    # Development tools & overrides
│   └── environments/                  # Environment-specific overlays
│       ├── production.yml      # Production resource limits
│       └── staging.yml         # Staging configuration
├── terraform/                  # ☁️ Infrastructure as Code
│   ├── main.tf                # Main orchestration layer
│   ├── variables.tf           # Comprehensive variable definitions
│   └── modules/               # Provider-specific modules
│       ├── common/            # Shared resources
│       ├── digitalocean/      # DO K8s, VPC, Load Balancer, SSL
│       └── linode/            # Linode LKE, NodeBalancer, Storage
├── k8s/                       # ⚓ Kubernetes with Kustomize
│   ├── base/                  # Base Kubernetes manifests
│   │   ├── kustomization.yaml # Base configuration
│   │   ├── namespace.yaml     # Namespace definition
│   │   ├── deployments.yaml   # All microservice deployments
│   │   └── services.yaml      # Service definitions with LB
│   └── overlays/              # Environment-specific overlays
│       ├── development/       # Local development
│       ├── staging/           # Staging with reduced resources
│       └── production/        # Production with scaling & security
├── scripts/                   # 🛠️ Deployment automation
│   ├── lib/                   # Shared deployment libraries
│   │   ├── common.sh         # Common utilities (500+ lines)
│   │   ├── docker.sh         # Docker operations (600+ lines)
│   │   ├── terraform.sh      # Terraform automation
│   │   └── kubernetes.sh     # Kubernetes deployment
│   └── validate.sh           # Comprehensive validation (400+ lines)
└── README.md                  # This file
```

## 🚀 Quick Start

### 1. Development Environment (Local Docker)

```bash
cd deployment
./deploy.sh -e development -t docker
```

This will:
- Start all 8 microservices locally
- Include development tools (phpMyAdmin, MailHog, Redis Commander)
- Enable hot-reload for code changes
- Use local MySQL and Redis containers

### 2. Staging Environment (Cloud)

```bash
cd deployment
./deploy.sh -e staging -p digitalocean
```

This will:
- Deploy to DigitalOcean Kubernetes
- Use managed MySQL and Redis
- Enable debug mode for troubleshooting
- Include monitoring stack

### 3. Production Environment (Cloud)

```bash
cd deployment
./deploy.sh -e production -p digitalocean
```

This will:
- Deploy to production Kubernetes cluster
- Use high-availability configurations
- Enable full monitoring and alerting
- Apply security hardening

## 🔧 Configuration Management

### Configuration Hierarchy

Configurations load in this order (later overrides earlier):
1. `config/base.env` - Common/shared configuration
2. `config/environments/${ENVIRONMENT}.env` - Environment-specific overrides
3. `config/providers/${CLOUD_PROVIDER}.env` - Provider-specific configuration
4. **Environment variables** - Highest priority (runtime overrides)

### Example: Production DigitalOcean

For production DigitalOcean deployment, configuration loads:
```
base.env → production.env → digitalocean.env → ${ENV_VARS}
```

### Required Environment Variables

#### For All Environments
```bash
export ENVIRONMENT=production
export CLOUD_PROVIDER=digitalocean
```

#### For Production
```bash
export JWT_SECRET="your-jwt-secret"
export APP_KEY="your-app-key"
export DB_PASSWORD="your-db-password"
export REDIS_PASSWORD="your-redis-password"
```

#### For DigitalOcean
```bash
export DIGITALOCEAN_TOKEN="your-do-token"
export DO_REGION="fra1"
```

## 📋 Command Reference

### Basic Usage

```bash
./deploy.sh [OPTIONS]
```

### Options

- `-e, --environment ENV` - Environment (development, staging, production)
- `-p, --provider PROVIDER` - Cloud provider (digitalocean, linode)
- `-t, --type TYPE` - Deployment type (full, infrastructure, application, docker)
- `-d, --dry-run` - Preview changes without executing
- `-v, --verbose` - Enable verbose output
- `-s, --skip-validation` - Skip configuration validation
- `-f, --force` - Force deployment even if validation fails
- `-h, --help` - Show help message

### Examples

```bash
# Full production deployment to DigitalOcean
./deploy.sh -e production -p digitalocean

# Staging deployment to Linode (dry run)
./deploy.sh -e staging -p linode --dry-run

# Infrastructure only deployment
./deploy.sh -e production -p digitalocean -t infrastructure

# Application only deployment (assumes infrastructure exists)
./deploy.sh -e production -p digitalocean -t application

# Local Docker development
./deploy.sh -e development -t docker

# Verbose output for debugging
./deploy.sh -e production -p digitalocean -v
```

## 🐳 Docker Deployment

### Development Environment

The development environment includes:
- All 8 microservices with hot-reload
- Development tools:
  - **phpMyAdmin** (http://localhost:8080)
  - **MailHog** (http://localhost:8025)
  - **Redis Commander** (http://localhost:8081)
- Local MySQL and Redis containers
- Volume mounts for live code editing

### Service Ports

| Service | Port | Description |
|---------|------|-------------|
| API Gateway | 8000 | Main application entry point |
| Auth Service | 8001 | Authentication and authorization |
| Bidding Service | 8002/8080 | Bidding with WebSocket support |
| User Service | 8003 | User and profile management |
| Order Service | 8004 | Order and request management |
| Notification Service | 8005 | Multi-channel notifications |
| Payment Service | 8006 | Payment processing |
| Analytics Service | 8007 | Business intelligence |
| VIN OCR Service | 8008 | Vehicle identification |

### Development Tools

| Tool | Port | Credentials |
|------|------|-------------|
| phpMyAdmin | 8080 | root/password |
| MailHog | 8025 | No auth required |
| Redis Commander | 8081 | No auth required |

## ☁️ Cloud Deployment

### Infrastructure Components

#### DigitalOcean
- **Kubernetes Cluster** with auto-scaling
- **VPC** with private networking
- **Load Balancer** with SSL termination
- **Managed MySQL** with backups
- **Managed Redis** for caching
- **Spaces** for object storage
- **Container Registry** for images

#### Linode
- **LKE Cluster** with auto-scaling
- **VLAN** for private networking
- **NodeBalancer** with SSL termination
- **Managed MySQL** with backups
- **Managed Redis** for caching
- **Object Storage** for files
- **Container Registry** for images

### Monitoring Stack

When `MONITORING_ENABLED=true`:
- **Prometheus** for metrics collection
- **Grafana** for dashboards and visualization
- **AlertManager** for alerting
- **Node Exporter** for system metrics

## ⚓ Kubernetes Deployment

### Base Configuration

The base Kubernetes configuration includes:
- **Namespace** isolation
- **Deployments** for all microservices
- **Services** with load balancing
- **ConfigMaps** for configuration
- **Secrets** for sensitive data
- **Health checks** and readiness probes

### Environment Overlays

#### Production Overlay
- **Higher replica counts** for scalability
- **Resource limits** and requests
- **Security contexts** with non-root users
- **Pod disruption budgets** for availability
- **Network policies** for security
- **Horizontal Pod Autoscaler** for auto-scaling

#### Staging Overlay
- **Reduced resources** for cost optimization
- **Debug mode** enabled for troubleshooting
- **Optional monitoring** stack

## 🔍 Validation

The deployment system includes comprehensive validation:

### Configuration Validation
- Environment variable validation
- Configuration file syntax checking
- Required variable verification
- Security configuration validation

### Infrastructure Validation
- Terraform configuration validation
- Kubernetes manifest validation
- Docker Compose syntax validation
- Provider-specific validation

### Security Validation
- Weak password detection
- SSL configuration verification
- Debug mode checks for production
- Secret strength validation

## 🔒 Security Features

### Production Security
- **SSL/TLS termination** with Let's Encrypt
- **Security contexts** with non-root containers
- **Network policies** for traffic isolation
- **Secrets management** with encryption
- **Resource limits** to prevent resource exhaustion
- **Pod security policies** for hardening

### Development Security
- **Local-only access** for development tools
- **Mock services** for external integrations
- **Isolated networking** for containers

## 📊 Monitoring and Observability

### Health Checks
- **Liveness probes** for container health
- **Readiness probes** for traffic routing
- **Service health endpoints** (/health, /ready)
- **Infrastructure monitoring** with alerts

### Metrics Collection
- **Application metrics** via Prometheus
- **Infrastructure metrics** via Node Exporter
- **Custom business metrics** via Analytics Service
- **Performance monitoring** with APM integration

### Alerting
- **Slack notifications** for critical alerts
- **Email alerts** for infrastructure issues
- **PagerDuty integration** for on-call rotation
- **Custom alert rules** for business metrics

## 🛠️ Troubleshooting

### Common Issues

#### Docker Issues
```bash
# Check container logs
docker-compose logs [service-name]

# Restart specific service
docker-compose restart [service-name]

# Rebuild containers
docker-compose build --no-cache [service-name]
```

#### Kubernetes Issues
```bash
# Check pod status
kubectl get pods -n reverse-tender

# Check pod logs
kubectl logs -f deployment/[service-name] -n reverse-tender

# Describe pod for events
kubectl describe pod [pod-name] -n reverse-tender
```

#### Terraform Issues
```bash
# Check Terraform state
terraform show

# Refresh state
terraform refresh

# Plan changes
terraform plan
```

### Debug Mode

Enable debug mode for detailed logging:
```bash
./deploy.sh -e development -t docker -v
```

### Dry Run

Preview changes without executing:
```bash
./deploy.sh -e production -p digitalocean --dry-run
```

## 🔄 Maintenance

### Updates

#### Application Updates
```bash
# Update application only (assumes infrastructure exists)
./deploy.sh -e production -p digitalocean -t application
```

#### Infrastructure Updates
```bash
# Update infrastructure only
./deploy.sh -e production -p digitalocean -t infrastructure
```

### Scaling

#### Manual Scaling
```bash
# Scale specific service
kubectl scale deployment [service-name] --replicas=5 -n reverse-tender
```

#### Auto-scaling
Auto-scaling is configured via HPA (Horizontal Pod Autoscaler) in production overlay.

### Backups

#### Database Backups
- Automated daily backups for managed databases
- Point-in-time recovery available
- Cross-region backup replication for production

#### Configuration Backups
- All configurations are version-controlled
- Infrastructure state is stored in remote backend
- Deployment history is tracked in Git

## 📚 Additional Resources

- **[Deployment Analysis](../DEPLOYMENT_ANALYSIS.md)** - Comprehensive analysis of the refactoring
- **[Migration Guide](../DEPLOYMENT_MIGRATION_GUIDE.md)** - Step-by-step migration instructions
- **[Architecture Documentation](../docs/architecture.md)** - Detailed system architecture
- **[API Documentation](../docs/api.md)** - API reference and examples

## 🆘 Support

For deployment issues or questions:
1. Check the troubleshooting section above
2. Review the logs with verbose mode: `./deploy.sh -v`
3. Run validation: `./scripts/validate.sh`
4. Check the GitHub issues for known problems
5. Contact the development team

---

**Note**: This unified deployment system replaces the previous fragmented deployment structure and provides a single, consistent interface for all deployment operations.

