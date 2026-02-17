# Helm Charts for Reverse Tender Platform

This directory contains Helm charts for deploying the Reverse Tender Platform microservices on Kubernetes clusters.

## Overview

Helm charts provide templated Kubernetes manifests that can be customized for different environments and cloud providers. These charts work in conjunction with the cloud-specific Infrastructure as Code (IaC) modules.

## Directory Structure

```
helm/
├── README.md                 # This file
├── rpc-services/            # Main microservices chart
│   ├── Chart.yaml          # Chart metadata
│   ├── values.yaml         # Default values
│   ├── templates/          # Kubernetes manifest templates
│   └── charts/             # Sub-charts (if any)
└── monitoring/             # Monitoring stack chart (future)
```

## Usage with Cloud Providers

### Azure (AKS)
The Azure Terraform modules automatically deploy Helm charts:
- NGINX Ingress Controller
- Cert-Manager for SSL
- Prometheus monitoring stack
- Custom microservices charts

### Linode (LKE)
The Linode Terraform modules include Helm deployments:
- NGINX Ingress with Linode-specific annotations
- Let's Encrypt integration
- Prometheus with persistent storage
- Microservices with HPA

### DigitalOcean (DOKS)
DigitalOcean Terraform handles Helm chart deployment:
- Load Balancer integration
- SSL termination
- Monitoring and logging
- Auto-scaling configurations

### OpenStack
OpenStack Heat templates can optionally deploy Helm charts:
- Conditional Prometheus stack
- Load balancer integration
- Custom networking configurations

## Chart Customization

### Environment-Specific Values
Each cloud provider can override default values:

```yaml
# values-azure.yaml
ingress:
  className: "nginx"
  annotations:
    service.beta.kubernetes.io/azure-load-balancer-health-probe-request-path: "/healthz"

# values-linode.yaml
ingress:
  className: "nginx"
  annotations:
    kubernetes.io/ingress.class: "nginx"
    cert-manager.io/cluster-issuer: "letsencrypt-prod"

# values-digitalocean.yaml
ingress:
  className: "nginx"
  annotations:
    kubernetes.io/ingress.class: "nginx"
    cert-manager.io/cluster-issuer: "letsencrypt-prod"
```

### Service Configuration
Services are configured through the unified configuration system in `../shared/configs/services.yaml` and can be overridden per environment.

## Integration with IaC

### Terraform Integration
Cloud-specific Terraform modules automatically:
1. Deploy required Helm charts (ingress, cert-manager, monitoring)
2. Create namespaces and service accounts
3. Configure RBAC and security policies
4. Set up persistent volumes and storage classes

### Heat Integration (OpenStack)
Heat templates can conditionally deploy Helm charts based on parameters.

## Manual Deployment

If you need to deploy charts manually:

```bash
# Add required repositories
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo add jetstack https://charts.jetstack.io
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update

# Deploy microservices chart
helm install reverse-tender ./rpc-services \
  --namespace microservices \
  --create-namespace \
  --values values-production.yaml

# Deploy monitoring (if not handled by IaC)
helm install prometheus prometheus-community/kube-prometheus-stack \
  --namespace monitoring \
  --create-namespace
```

## Development

### Local Testing
```bash
# Lint charts
helm lint ./rpc-services

# Template and validate
helm template reverse-tender ./rpc-services --values values-dev.yaml

# Dry run
helm install reverse-tender ./rpc-services --dry-run --debug
```

### Chart Updates
When updating charts:
1. Update `Chart.yaml` version
2. Test with different value files
3. Validate against all target Kubernetes versions
4. Update documentation

## Related Documentation

- Cloud-specific deployment guides: `../azure/README.md`, `../linode/README.md`, etc.
- Shared configuration: `../shared/README.md`
- Kubernetes manifests: `../k8s/README.md`
- Main deployment documentation: `../README.md`
