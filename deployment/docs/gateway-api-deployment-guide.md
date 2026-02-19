# Multi-Cloud Gateway API Deployment Guide

## Overview

This guide provides comprehensive instructions for deploying Kubernetes Gateway API across multiple cloud providers for the Reverse Tender Platform. The implementation supports Google Cloud Platform (GKE), Microsoft Azure (AKS), DigitalOcean (DOKS), Linode (LKE), and OpenStack.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Quick Start](#quick-start)
3. [Cloud Provider Specific Guides](#cloud-provider-specific-guides)
4. [Configuration Management](#configuration-management)
5. [Monitoring and Troubleshooting](#monitoring-and-troubleshooting)
6. [Migration from Ingress](#migration-from-ingress)
7. [Best Practices](#best-practices)

## Prerequisites

### General Requirements

- **Kubernetes Cluster**: Version 1.24+ with Gateway API support
- **kubectl**: Latest version configured for your cluster
- **kustomize**: Version 4.0+ for configuration management
- **Gateway API CRDs**: Automatically installed by deployment script

### Cloud Provider Specific Requirements

#### Google Cloud Platform (GKE)
- **GKE Cluster**: With Gateway API enabled
- **gcloud CLI**: Authenticated and configured
- **Workload Identity**: Configured for certificate management
- **Static IP**: Reserved for load balancer
- **Cloud DNS**: For domain management

#### Microsoft Azure (AKS)
- **AKS Cluster**: With Application Gateway Ingress Controller (AGIC)
- **Azure CLI**: Authenticated and configured
- **Application Gateway**: Provisioned and configured
- **Azure Key Vault**: For SSL certificate storage
- **Managed Identity**: For Key Vault access

#### DigitalOcean (DOKS)
- **DOKS Cluster**: Version 1.24+
- **doctl CLI**: Authenticated (optional but recommended)
- **DigitalOcean Load Balancer**: Managed service
- **SSL Certificates**: Uploaded to DigitalOcean
- **DNS Configuration**: Pointing to load balancer

#### Linode (LKE)
- **LKE Cluster**: Version 1.24+
- **linode-cli**: Authenticated (optional but recommended)
- **NodeBalancer**: Managed load balancing service
- **cert-manager**: For automatic SSL certificate management
- **DNS Configuration**: Pointing to NodeBalancer

#### OpenStack
- **OpenStack Kubernetes**: With Octavia load balancer
- **OpenStack CLI**: Authenticated and configured
- **Octavia Service**: Load balancer as a service
- **Barbican Service**: Key management for SSL certificates
- **Neutron Networking**: Configured with security groups

## Quick Start

### 1. Automatic Deployment

Use the automated deployment script for the easiest setup:

```bash
# Auto-detect cloud provider and deploy
./deployment/scripts/deploy-gateway-api.sh

# Specify cloud provider explicitly
./deployment/scripts/deploy-gateway-api.sh --provider gcp --environment production

# Dry run to see what would be deployed
./deployment/scripts/deploy-gateway-api.sh --provider azure --dry-run

# Validate configuration only
./deployment/scripts/deploy-gateway-api.sh --provider digitalocean --validate-only
```

### 2. Manual Deployment

For more control over the deployment process:

```bash
# 1. Install Gateway API CRDs
kubectl apply -f https://github.com/kubernetes-sigs/gateway-api/releases/download/v1.0.0/standard-install.yaml

# 2. Create namespace
kubectl create namespace reverse-tender

# 3. Deploy cloud provider specific configuration
kubectl apply -k deployment/gcp/gateway-api/  # For GCP
kubectl apply -k deployment/azure/gateway-api/  # For Azure
kubectl apply -f deployment/digitalocean/gateway-api/  # For DigitalOcean
kubectl apply -f deployment/linode/gateway-api/  # For Linode
kubectl apply -f deployment/openstack/gateway-api/  # For OpenStack

# 4. Verify deployment
kubectl get gateway -n reverse-tender
kubectl get httproute -n reverse-tender
```

## Cloud Provider Specific Guides

### Google Cloud Platform (GKE)

#### Prerequisites Setup
```bash
# Enable required APIs
gcloud services enable container.googleapis.com
gcloud services enable compute.googleapis.com
gcloud services enable dns.googleapis.com

# Create GKE cluster with Gateway API
gcloud container clusters create reverse-tender-cluster \
    --enable-ip-alias \
    --enable-network-policy \
    --gateway-api=standard \
    --zone=us-central1-a

# Reserve static IP
gcloud compute addresses create reverse-tender-global-ip --global
```

#### Configuration
```bash
# Set environment variables
export GOOGLE_PROJECT_ID="your-project-id"
export STATIC_IP_NAME="reverse-tender-global-ip"

# Deploy Gateway API
./deployment/scripts/deploy-gateway-api.sh --provider gcp
```

#### Features
- **Global HTTP(S) Load Balancer**: Optimal performance across regions
- **Google-managed SSL certificates**: Automatic provisioning and renewal
- **Cloud Armor integration**: DDoS protection and WAF
- **Cloud CDN**: Static content caching
- **Workload Identity**: Secure service account access

### Microsoft Azure (AKS)

#### Prerequisites Setup
```bash
# Create resource group
az group create --name reverse-tender-rg --location eastus

# Create AKS cluster
az aks create \
    --resource-group reverse-tender-rg \
    --name reverse-tender-cluster \
    --enable-addons ingress-appgw \
    --appgw-name reverse-tender-appgw \
    --appgw-subnet-cidr "10.2.0.0/16"

# Create Key Vault
az keyvault create \
    --name reverse-tender-kv \
    --resource-group reverse-tender-rg \
    --location eastus
```

#### Configuration
```bash
# Set environment variables
export AZURE_SUBSCRIPTION_ID="your-subscription-id"
export AZURE_RESOURCE_GROUP="reverse-tender-rg"
export AZURE_KEYVAULT_URI="https://reverse-tender-kv.vault.azure.net/"

# Deploy Gateway API
./deployment/scripts/deploy-gateway-api.sh --provider azure
```

#### Features
- **Application Gateway**: Layer 7 load balancing with WAF
- **Azure Key Vault integration**: Secure certificate management
- **WAF policies**: Web application firewall protection
- **Azure Monitor**: Comprehensive monitoring and logging

### DigitalOcean (DOKS)

#### Prerequisites Setup
```bash
# Create DOKS cluster
doctl kubernetes cluster create reverse-tender-cluster \
    --region nyc1 \
    --version 1.28.2-do.0 \
    --node-pool "name=worker-pool;size=s-2vcpu-2gb;count=3"

# Upload SSL certificates
doctl compute certificate create \
    --name api-cert \
    --private-key-path api.key \
    --leaf-certificate-path api.crt
```

#### Configuration
```bash
# Set environment variables
export DO_CERTIFICATE_ID="your-certificate-id"
export DO_LOAD_BALANCER_IP="your-lb-ip"

# Deploy Gateway API
./deployment/scripts/deploy-gateway-api.sh --provider digitalocean
```

#### Features
- **DigitalOcean Load Balancer**: Managed load balancing service
- **SSL termination**: Managed SSL certificates
- **Health checks**: Automatic health monitoring
- **Sticky sessions**: Session persistence for RPC calls

### Linode (LKE)

#### Prerequisites Setup
```bash
# Create LKE cluster
linode-cli lke cluster-create \
    --label reverse-tender-cluster \
    --region us-east \
    --k8s_version 1.28

# Install cert-manager for SSL certificates
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml
```

#### Configuration
```bash
# Set environment variables
export LINODE_REGION="us-east"
export ACME_EMAIL="admin@reversetender.com"

# Deploy Gateway API
./deployment/scripts/deploy-gateway-api.sh --provider linode
```

#### Features
- **NodeBalancer**: Managed load balancing service
- **Let's Encrypt integration**: Automatic SSL certificate management
- **Health checks**: Configurable health monitoring
- **Regional deployment**: Single region optimization

### OpenStack

#### Prerequisites Setup
```bash
# Source OpenStack credentials
source openstack-rc.sh

# Create load balancer
openstack loadbalancer create \
    --name reverse-tender-lb \
    --vip-subnet-id private-subnet

# Create Barbican certificate container
openstack secret store \
    --name api-cert \
    --payload-content-type "application/octet-stream" \
    --payload-content-encoding base64 \
    --payload "$(base64 -w 0 api.crt)"
```

#### Configuration
```bash
# Set environment variables
export OPENSTACK_LB_ID="your-lb-id"
export OPENSTACK_SUBNET_ID="your-subnet-id"
export BARBICAN_CONTAINER_REF="your-container-ref"

# Deploy Gateway API
./deployment/scripts/deploy-gateway-api.sh --provider openstack
```

#### Features
- **Octavia Load Balancer**: OpenStack LBaaS v2
- **Barbican integration**: Secure certificate management
- **Neutron networking**: Advanced networking features
- **Heat template support**: Infrastructure as Code

## Configuration Management

### Environment Variables

Each cloud provider requires specific environment variables. Create a `.env` file:

```bash
# Common variables
NAMESPACE=reverse-tender
ENVIRONMENT=production

# GCP specific
GOOGLE_PROJECT_ID=your-project-id
STATIC_IP_NAME=reverse-tender-global-ip

# Azure specific
AZURE_SUBSCRIPTION_ID=your-subscription-id
AZURE_RESOURCE_GROUP=reverse-tender-rg
AZURE_KEYVAULT_URI=https://your-keyvault.vault.azure.net/

# DigitalOcean specific
DO_CERTIFICATE_ID=your-certificate-id
DO_LOAD_BALANCER_IP=your-lb-ip

# Linode specific
LINODE_REGION=us-east
ACME_EMAIL=admin@reversetender.com

# OpenStack specific
OPENSTACK_LB_ID=your-lb-id
OPENSTACK_SUBNET_ID=your-subnet-id
BARBICAN_CONTAINER_REF=your-container-ref
```

### Customization

#### Hostnames
Update hostnames in the Gateway configuration:

```yaml
# deployment/{provider}/gateway-api/gateway.yaml
listeners:
  - name: https-rpc-api
    hostname: "api.yourdomain.com"  # Update this
```

#### SSL Certificates
Configure SSL certificates for each provider:

**GCP**: Use Google-managed certificates
**Azure**: Store certificates in Azure Key Vault
**DigitalOcean**: Upload certificates to DigitalOcean
**Linode**: Use cert-manager with Let's Encrypt
**OpenStack**: Store certificates in Barbican

#### Load Balancer Settings
Adjust load balancer configurations based on your requirements:

```yaml
# Example: Increase session timeout for WebSocket
annotations:
  service.beta.kubernetes.io/do-loadbalancer-sticky-sessions-cookie-ttl: "7200"
```

## Monitoring and Troubleshooting

### Health Checks

Verify Gateway API deployment:

```bash
# Check Gateway status
kubectl get gateway -n reverse-tender -o wide

# Check HTTPRoute status
kubectl get httproute -n reverse-tender -o wide

# Check GatewayClass status
kubectl get gatewayclass -o wide

# Describe Gateway for detailed status
kubectl describe gateway reverse-tender-{provider}-gateway -n reverse-tender
```

### Common Issues

#### Gateway Not Ready
```bash
# Check Gateway events
kubectl describe gateway reverse-tender-{provider}-gateway -n reverse-tender

# Check controller logs
kubectl logs -n gateway-system -l app=gateway-controller

# Verify GatewayClass
kubectl describe gatewayclass reverse-tender-{provider}-gateway-class
```

#### SSL Certificate Issues
```bash
# For cert-manager (Linode)
kubectl describe certificate -n reverse-tender
kubectl logs -n cert-manager -l app=cert-manager

# For cloud provider managed certificates
# Check cloud provider console for certificate status
```

#### Load Balancer Issues
```bash
# Check Service status
kubectl get service -n reverse-tender -o wide

# Check Service events
kubectl describe service reverse-tender-{provider}-lb-service -n reverse-tender

# Check cloud provider load balancer status in console
```

### Logging

Enable verbose logging for troubleshooting:

```bash
# Deploy with verbose logging
./deployment/scripts/deploy-gateway-api.sh --provider gcp --verbose

# Check Gateway API controller logs
kubectl logs -n gateway-system -l app=gateway-controller --tail=100 -f
```

## Migration from Ingress

### Assessment

Before migrating from Ingress to Gateway API:

1. **Inventory existing Ingress resources**
2. **Identify custom annotations and configurations**
3. **Plan for traffic routing changes**
4. **Prepare rollback strategy**

### Migration Steps

1. **Deploy Gateway API alongside existing Ingress**
2. **Test Gateway API with subset of traffic**
3. **Gradually migrate traffic to Gateway API**
4. **Remove old Ingress resources**

### Example Migration

```bash
# 1. Deploy Gateway API (without removing Ingress)
./deployment/scripts/deploy-gateway-api.sh --provider gcp

# 2. Test Gateway API endpoints
curl -H "Host: api.reversetender.com" http://gateway-ip/api/v1/health

# 3. Update DNS to point to Gateway API load balancer
# 4. Monitor traffic and performance

# 5. Remove old Ingress resources (after validation)
kubectl delete ingress old-ingress-name -n reverse-tender
```

## Best Practices

### Security

1. **Use HTTPS everywhere**: Redirect HTTP to HTTPS
2. **Implement proper RBAC**: Limit Gateway API resource access
3. **Regular certificate rotation**: Automate certificate management
4. **Network policies**: Restrict pod-to-pod communication
5. **Security scanning**: Regular vulnerability assessments

### Performance

1. **Connection pooling**: Configure appropriate connection limits
2. **Caching**: Enable CDN/caching where appropriate
3. **Health checks**: Optimize health check intervals
4. **Resource limits**: Set appropriate CPU/memory limits
5. **Monitoring**: Implement comprehensive monitoring

### Reliability

1. **Multi-region deployment**: For high availability
2. **Backup and recovery**: Regular configuration backups
3. **Disaster recovery**: Cross-cloud failover strategies
4. **Testing**: Regular load and failover testing
5. **Documentation**: Keep deployment docs updated

### Cost Optimization

1. **Right-sizing**: Choose appropriate load balancer sizes
2. **Reserved instances**: Use reserved capacity where available
3. **Monitoring**: Track costs and usage patterns
4. **Cleanup**: Remove unused resources regularly
5. **Automation**: Automate scaling and resource management

## Support and Resources

### Documentation
- [Kubernetes Gateway API](https://gateway-api.sigs.k8s.io/)
- [Cloud Provider Documentation](#cloud-provider-specific-guides)
- [Troubleshooting Guide](#monitoring-and-troubleshooting)

### Community
- [Gateway API Slack](https://kubernetes.slack.com/channels/sig-network-gateway-api)
- [GitHub Issues](https://github.com/kubernetes-sigs/gateway-api/issues)
- [Stack Overflow](https://stackoverflow.com/questions/tagged/kubernetes-gateway-api)

### Professional Support
- Cloud provider support channels
- Kubernetes support providers
- Professional services consultants

