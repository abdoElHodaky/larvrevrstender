# Reverse Tender Platform - Deployment Guide

This guide covers the deployment of the Reverse Tender Platform across different environments using Kubernetes and Kustomize.

## 📋 Table of Contents

- [Architecture Overview](#architecture-overview)
- [Prerequisites](#prerequisites)
- [Environment Setup](#environment-setup)
- [Deployment Process](#deployment-process)
- [Environment-Specific Deployments](#environment-specific-deployments)
- [Secret Management](#secret-management)
- [Monitoring and Health Checks](#monitoring-and-health-checks)
- [Troubleshooting](#troubleshooting)

## 🏗️ Architecture Overview

The Reverse Tender Platform is built as a microservices architecture with the following components:

### Core Services

| Service | Port | Purpose | Replicas (Prod) |
|---------|------|---------|------------------|
| **gateway-service** | 8000 | Main entry point, routing, authentication | 3 |
| **auth-service** | 8001 | User authentication and authorization | 3 |
| **auction-service** | 8002 | Auction management and lifecycle | 2 |
| **bidding-service** | 8003 | Real-time bidding with WebSocket (8080) | 3 |
| **user-service** | 8004 | User profile and management | 2 |
| **order-service** | 8005 | Order processing and fulfillment | 2 |
| **notification-service** | 8006 | Multi-channel notifications | 2 |
| **payment-service** | 8007 | Payment processing and transactions | 3 |
| **analytics-service** | 8008 | Business intelligence and reporting | 2 |
| **vin-ocr-service** | 8009 | VIN number OCR processing | 2 |

### Configuration Architecture

The platform uses a modular configuration approach:

- **Base Configuration**: Common settings shared across all services
- **Service-Specific ConfigMaps**: Individual service configurations
- **Environment Overlays**: Environment-specific patches (dev/staging/production)
- **Secret Management**: Secure handling of sensitive data

## 🔧 Prerequisites

### Required Tools

```bash
# Kubernetes cluster (1.24+)
kubectl version --client

# Kustomize (4.0+)
kustomize version

# Docker (for building images)
docker --version

# Helm (optional, for dependencies)
helm version
```

### Cluster Requirements

- **Kubernetes Version**: 1.24 or higher
- **Node Resources**: Minimum 4 CPU cores, 8GB RAM per node
- **Storage**: Persistent volume support for databases
- **Networking**: Ingress controller (NGINX recommended)
- **DNS**: CoreDNS for service discovery

### External Dependencies

- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Cache**: Redis 6.0+
- **Message Queue**: Redis or RabbitMQ
- **File Storage**: S3-compatible storage (AWS S3, MinIO)
- **Monitoring**: Prometheus + Grafana (optional)

## 🌍 Environment Setup

### Development Environment

```bash
# Clone the repository
git clone https://github.com/your-org/reverse-tender-platform.git
cd reverse-tender-platform

# Deploy to development
kubectl apply -k deployment/k8s/overlays/dev

# Verify deployment
kubectl get pods -n reverse-tender-dev
```

### Staging Environment

```bash
# Deploy to staging
kubectl apply -k deployment/k8s/overlays/staging

# Verify deployment
kubectl get pods -n reverse-tender-staging
```

### Production Environment

```bash
# Deploy to production (requires proper secrets)
kubectl apply -k deployment/k8s/overlays/production

# Verify deployment
kubectl get pods -n reverse-tender
```

## 🚀 Deployment Process

### Step 1: Prepare Secrets

Before deploying, ensure all secrets are properly configured:

```bash
# Copy secret template
cp deployment/k8s/base/secrets/app-secrets.yaml deployment/k8s/base/secrets/app-secrets-actual.yaml

# Edit with actual values
vim deployment/k8s/base/secrets/app-secrets-actual.yaml

# Apply secrets
kubectl apply -f deployment/k8s/base/secrets/app-secrets-actual.yaml
```

### Step 2: Deploy Base Infrastructure

```bash
# Deploy base resources
kubectl apply -k deployment/k8s/base
```

### Step 3: Deploy Environment-Specific Configuration

```bash
# For development
kubectl apply -k deployment/k8s/overlays/dev

# For staging
kubectl apply -k deployment/k8s/overlays/staging

# For production
kubectl apply -k deployment/k8s/overlays/production
```

### Step 4: Verify Deployment

```bash
# Check pod status
kubectl get pods -n <namespace>

# Check service endpoints
kubectl get svc -n <namespace>

# Check ingress
kubectl get ingress -n <namespace>

# View logs
kubectl logs -f deployment/gateway-service -n <namespace>
```

## 🎯 Environment-Specific Deployments

### Development Configuration

- **Purpose**: Local development and testing
- **Namespace**: `reverse-tender-dev`
- **Replicas**: 1 for all services (cost optimization)
- **Resources**: Minimal (128Mi-256Mi memory)
- **Features**: Debug enabled, verbose logging, circuit breakers disabled

```yaml
# Key development settings
APP_ENV: development
APP_DEBUG: true
LOG_LEVEL: debug
CIRCUIT_BREAKER_ENABLED: false
```

### Staging Configuration

- **Purpose**: Pre-production testing and validation
- **Namespace**: `reverse-tender-staging`
- **Replicas**: 2-3 for critical services
- **Resources**: Moderate (256Mi-512Mi memory)
- **Features**: Production-like but with reduced resources

```yaml
# Key staging settings
APP_ENV: staging
APP_DEBUG: false
LOG_LEVEL: info
CIRCUIT_BREAKER_ENABLED: true
```

### Production Configuration

- **Purpose**: Live production environment
- **Namespace**: `reverse-tender`
- **Replicas**: 3+ for high availability
- **Resources**: Full production limits (512Mi-1Gi+ memory)
- **Features**: All production optimizations enabled

```yaml
# Key production settings
APP_ENV: production
APP_DEBUG: false
LOG_LEVEL: warning
CIRCUIT_BREAKER_ENABLED: true
```

## 🔐 Secret Management

### Secret Categories

1. **Application Secrets**: JWT keys, encryption keys, app keys
2. **Database Secrets**: Database passwords and connection strings
3. **Cache Secrets**: Redis passwords and connection strings
4. **External Service Secrets**: API keys for third-party services

### Secret Deployment

```bash
# Create namespace-specific secrets
kubectl create secret generic app-secrets \
  --from-literal=JWT_SECRET="your-jwt-secret" \
  --from-literal=DB_PASSWORD="your-db-password" \
  -n reverse-tender

# Or use YAML files
kubectl apply -f deployment/k8s/base/secrets/app-secrets.yaml
```

### Secret Rotation

```bash
# Update secret
kubectl patch secret app-secrets -p='{"data":{"JWT_SECRET":"bmV3LWp3dC1zZWNyZXQ="}}' -n reverse-tender

# Restart deployments to pick up new secrets
kubectl rollout restart deployment/gateway-service -n reverse-tender
```

## 📊 Monitoring and Health Checks

### Health Check Endpoints

All services expose health check endpoints:

- **Liveness Probe**: `/health` - Service is running
- **Readiness Probe**: `/health/ready` - Service is ready to accept traffic
- **Startup Probe**: `/health/startup` - Service has started successfully

### Service Discovery

Services are discoverable via:

- **Internal DNS**: `service-name.namespace.svc.cluster.local`
- **Service Registry**: Automatic registration with service discovery
- **Load Balancer**: External access via ingress controller

### Monitoring Integration

```bash
# Check service health
kubectl get pods -n reverse-tender -o wide

# View service logs
kubectl logs -f deployment/gateway-service -n reverse-tender

# Check resource usage
kubectl top pods -n reverse-tender
```

## 🔧 Configuration Management

### ConfigMap Structure

```
deployment/k8s/base/
├── common-config.yaml          # Shared settings
├── gateway-config.yaml         # Gateway-specific settings
├── saga-config.yaml           # Distributed transaction settings
├── service-discovery-config.yaml # Service registry settings
├── resilience-config.yaml     # Circuit breaker settings
└── event-bus-config.yaml      # Event communication settings
```

### Environment Overrides

Each environment can override base configurations:

```yaml
# In overlays/production/kustomization.yaml
configMapGenerator:
  - name: production-config
    literals:
      - LOG_LEVEL=warning
      - API_RATE_LIMIT=100
```

## 🚨 Troubleshooting

### Common Issues

1. **Pod Startup Failures**
   ```bash
   kubectl describe pod <pod-name> -n <namespace>
   kubectl logs <pod-name> -n <namespace>
   ```

2. **Service Discovery Issues**
   ```bash
   kubectl get endpoints -n <namespace>
   kubectl get svc -n <namespace>
   ```

3. **Configuration Problems**
   ```bash
   kubectl get configmap -n <namespace>
   kubectl describe configmap <configmap-name> -n <namespace>
   ```

4. **Secret Issues**
   ```bash
   kubectl get secrets -n <namespace>
   kubectl describe secret <secret-name> -n <namespace>
   ```

### Performance Tuning

1. **Resource Limits**: Adjust based on actual usage
2. **Replica Counts**: Scale based on load patterns
3. **HPA Configuration**: Enable horizontal pod autoscaling
4. **Node Affinity**: Optimize pod placement

### Rollback Procedures

```bash
# Rollback deployment
kubectl rollout undo deployment/gateway-service -n reverse-tender

# Check rollout status
kubectl rollout status deployment/gateway-service -n reverse-tender

# View rollout history
kubectl rollout history deployment/gateway-service -n reverse-tender
```

## 📚 Additional Resources

- [Configuration Guide](../configuration/README.md)
- [Troubleshooting Guide](../troubleshooting/README.md)
- [Service Architecture](../architecture/services.md)
- [Security Guide](../security/README.md)

## 🤝 Support

For deployment issues:

1. Check the [Troubleshooting Guide](../troubleshooting/README.md)
2. Review service logs and pod status
3. Consult the team documentation
4. Contact the DevOps team for infrastructure issues

---

**Last Updated**: February 2026  
**Version**: 1.0.0
