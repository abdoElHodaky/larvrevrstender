# Kubernetes Manifests for Reverse Tender Platform

This directory contains Kubernetes manifests for deploying the Reverse Tender Platform microservices. These manifests work alongside the cloud-specific Infrastructure as Code (IaC) modules and Helm charts.

## Overview

The Kubernetes manifests provide a declarative approach to deploying and managing the microservices platform. They are organized using Kustomize for environment-specific customizations.

## Directory Structure

```
k8s/
├── README.md              # This file
├── base/                  # Base Kubernetes manifests
│   ├── deployments.yaml   # Service deployments
│   ├── services.yaml      # Service definitions
│   ├── ingress.yaml       # Ingress configurations
│   ├── hpa.yaml          # Horizontal Pod Autoscaling
│   ├── pdb.yaml          # Pod Disruption Budgets
│   ├── rbac.yaml         # Role-Based Access Control
│   ├── networkpolicy.yaml # Network policies
│   ├── secrets.yaml      # Secret templates
│   ├── *-config.yaml     # Service-specific configurations
│   └── kustomization.yaml # Base kustomization
└── overlays/             # Environment-specific overlays
    ├── dev/              # Development environment
    ├── staging/          # Staging environment
    ├── prod/             # Production environment
    └── cloud-specific/   # Cloud provider specific configs
```

## Usage Patterns

### 1. With Cloud-Specific IaC
The preferred approach is to use these manifests through cloud-specific Infrastructure as Code:

**Azure**: Terraform modules automatically apply these manifests to AKS clusters
**Linode**: Terraform modules deploy these to LKE clusters  
**DigitalOcean**: Terraform modules apply to DOKS clusters
**OpenStack**: Heat templates can optionally deploy these manifests

### 2. Direct kubectl Deployment
For manual deployment or testing:

```bash
# Deploy base configuration
kubectl apply -k base/

# Deploy environment-specific overlay
kubectl apply -k overlays/prod/

# Deploy cloud-specific configuration
kubectl apply -k overlays/cloud-specific/azure/
```

### 3. With Kustomize
Build and apply customized manifests:

```bash
# Build and preview
kustomize build overlays/prod/

# Apply directly
kustomize build overlays/prod/ | kubectl apply -f -
```

## Environment Overlays

### Development (`overlays/dev/`)
- Reduced resource requests/limits
- Single replica deployments
- Debug logging enabled
- Development-friendly configurations

### Staging (`overlays/staging/`)
- Production-like resource allocation
- Multiple replicas for testing
- Staging-specific environment variables
- Performance testing configurations

### Production (`overlays/prod/`)
- Full resource allocation
- High availability configurations
- Production security policies
- Monitoring and alerting enabled

## Cloud-Specific Configurations

### Azure AKS (`overlays/cloud-specific/azure/`)
- Azure-specific annotations
- Azure Load Balancer integration
- Azure Key Vault secrets provider
- Azure Monitor integration

### Linode LKE (`overlays/cloud-specific/linode/`)
- Linode-specific load balancer annotations
- Linode Block Storage integration
- Let's Encrypt certificate management
- Linode-optimized resource allocation

### DigitalOcean DOKS (`overlays/cloud-specific/digitalocean/`)
- DigitalOcean Load Balancer integration
- DigitalOcean Spaces integration
- DOCR (DigitalOcean Container Registry) configuration
- DigitalOcean-specific networking

### OpenStack (`overlays/cloud-specific/openstack/`)
- OpenStack-specific storage classes
- Octavia load balancer integration
- OpenStack networking configurations
- Heat template compatibility

## Service Configuration

### Microservices Included
All 11 microservices are configured:
- API Gateway (Port 8000)
- Auth Service (Port 8001)  
- Bidding Service (Port 8002)
- User Service (Port 8003)
- Order Service (Port 8004)
- Notification Service (Port 8005)
- Payment Service (Port 8006)
- Analytics Service (Port 8007)
- VIN OCR Service (Port 8008)
- Auction Service (Port 8009)
- Gateway Service (Port 8010)

### Configuration Sources
Service configurations are sourced from:
1. **Base manifests**: Common configurations for all environments
2. **Shared configs**: `../shared/configs/services.yaml` for unified service definitions
3. **Environment overlays**: Environment-specific customizations
4. **Cloud overlays**: Cloud provider-specific optimizations

## Integration with Other Components

### Helm Charts
These manifests complement Helm charts in `../helm/`:
- Helm charts provide templating and package management
- Kubernetes manifests provide direct declarative configuration
- Both can be used together for complex deployments

### Infrastructure as Code
Cloud-specific IaC modules automatically:
1. Create Kubernetes clusters
2. Apply these manifests
3. Configure networking and security
4. Set up monitoring and logging

### Monitoring Integration
Manifests include:
- Prometheus monitoring annotations
- Health check endpoints
- Metrics collection configuration
- Alerting rule integration

## Security Features

### Network Policies
- Service-to-service communication rules
- Ingress and egress traffic control
- Namespace isolation
- Database access restrictions

### RBAC Configuration
- Service account definitions
- Role and ClusterRole bindings
- Least privilege access principles
- Cloud provider integration

### Pod Security
- Security contexts for all pods
- Non-root user execution
- Read-only root filesystems where possible
- Resource limits and requests

## Troubleshooting

### Common Issues
```bash
# Check pod status
kubectl get pods -n microservices

# View pod logs
kubectl logs -n microservices deployment/api-gateway

# Check service connectivity
kubectl get svc -n microservices

# Validate ingress
kubectl get ingress -n microservices
```

### Debugging Network Policies
```bash
# Test connectivity between services
kubectl exec -n microservices deployment/api-gateway -- curl http://auth-service:8001/health

# Check network policy rules
kubectl describe networkpolicy -n microservices
```

## Related Documentation

- Cloud-specific deployment guides: `../azure/README.md`, `../linode/README.md`, etc.
- Helm charts: `../helm/README.md`
- Shared configuration: `../shared/README.md`
- Main deployment documentation: `../README.md`
