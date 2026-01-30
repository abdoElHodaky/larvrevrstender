# Multi-Cloud Deployment Guide - Reverse Tender Platform

## Overview

This guide covers deploying the Reverse Tender Platform on both **DigitalOcean** and **Linode** cloud providers using Terraform and Kubernetes.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    Multi-Cloud Architecture                      │
├─────────────────────────────┬───────────────────────────────────┤
│        DigitalOcean         │            Linode                 │
├─────────────────────────────┼───────────────────────────────────┤
│  ┌─────────────────────┐   │   ┌─────────────────────┐         │
│  │   DOKS Cluster      │   │   │   LKE Cluster       │         │
│  │   (Kubernetes)      │   │   │   (Kubernetes)      │         │
│  └─────────────────────┘   │   └─────────────────────┘         │
│  ┌─────────────────────┐   │   ┌─────────────────────┐         │
│  │  Managed MySQL      │   │   │  Managed MySQL      │         │
│  │  (Database)         │   │   │  (Database)         │         │
│  └─────────────────────┘   │   └─────────────────────┘         │
│  ┌─────────────────────┐   │   ┌─────────────────────┐         │
│  │  Managed Redis      │   │   │  In-Cluster Redis   │         │
│  │  (Cache/Queue)      │   │   │  (Cache/Queue)      │         │
│  └─────────────────────┘   │   └─────────────────────┘         │
│  ┌─────────────────────┐   │   ┌─────────────────────┐         │
│  │   Load Balancer     │   │   │   NodeBalancer      │         │
│  │   (Traffic Dist.)   │   │   │   (Traffic Dist.)   │         │
│  └─────────────────────┘   │   └─────────────────────┘         │
└─────────────────────────────┴───────────────────────────────────┘
```

## Prerequisites

### Required Tools
- **Terraform** >= 1.0
- **kubectl** >= 1.28
- **Docker** >= 20.10
- **doctl** (DigitalOcean CLI) - for DigitalOcean deployment
- **linode-cli** (Linode CLI) - for Linode deployment

### Required Accounts & API Tokens
- DigitalOcean account with API token
- Linode account with API token
- Domain name (optional, for custom domains)

### Environment Variables
```bash
# DigitalOcean
export DO_TOKEN="your_digitalocean_token"

# Linode
export LINODE_TOKEN="your_linode_token"

# Database
export DB_PASSWORD="secure_database_password"

# Payment Services
export STRIPE_KEY="your_stripe_publishable_key"
export STRIPE_SECRET="your_stripe_secret_key"

# Real-time Services
export PUSHER_APP_KEY="your_pusher_app_key"
export PUSHER_APP_SECRET="your_pusher_app_secret"
```

## Cloud Provider Comparison

| Feature | DigitalOcean | Linode |
|---------|--------------|--------|
| **Kubernetes** | DOKS (Managed) | LKE (Managed) |
| **Database** | Managed MySQL/Redis | Managed MySQL |
| **Load Balancer** | Integrated LB | NodeBalancer |
| **Regions** | 15+ regions | 11+ regions |
| **Pricing** | Competitive | Often lower |
| **Ease of Use** | Very Simple | Simple |
| **Performance** | Good | Excellent |

## Deployment Instructions

### Option 1: DigitalOcean Deployment

#### 1. Setup Environment
```bash
# Set required environment variables
export DO_TOKEN="your_digitalocean_token"
export DB_PASSWORD="secure_password"
export STRIPE_KEY="your_stripe_key"
export STRIPE_SECRET="your_stripe_secret"
export PUSHER_APP_KEY="your_pusher_key"
export PUSHER_APP_SECRET="your_pusher_secret"

# Optional: Custom configuration
export ENVIRONMENT="production"
export REGION="fra1"  # Frankfurt
export NODE_COUNT="3"
export DOMAIN_NAME="reverse-tender.com"
```

#### 2. Run Deployment Script
```bash
chmod +x deployment/scripts/deploy-digitalocean.sh
./deployment/scripts/deploy-digitalocean.sh
```

#### 3. Verify Deployment
```bash
# Check cluster status
kubectl get nodes

# Check service status
kubectl get pods -n reverse-tender

# Get load balancer IP
kubectl get service nginx-gateway -n reverse-tender
```

### Option 2: Linode Deployment

#### 1. Setup Environment
```bash
# Set required environment variables
export LINODE_TOKEN="your_linode_token"
export DB_PASSWORD="secure_password"
export STRIPE_KEY="your_stripe_key"
export STRIPE_SECRET="your_stripe_secret"
export PUSHER_APP_KEY="your_pusher_key"
export PUSHER_APP_SECRET="your_pusher_secret"

# Optional: Custom configuration
export ENVIRONMENT="production"
export REGION="eu-west"  # Europe
export NODE_COUNT="3"
export DOMAIN_NAME="reverse-tender.com"
```

#### 2. Run Deployment Script
```bash
chmod +x deployment/scripts/deploy-linode.sh
./deployment/scripts/deploy-linode.sh
```

#### 3. Verify Deployment
```bash
# Check cluster status
kubectl get nodes

# Check service status
kubectl get pods -n reverse-tender

# Get load balancer IP
kubectl get service nginx-gateway -n reverse-tender
```

## Manual Deployment (Advanced)

### 1. Infrastructure with Terraform

#### Initialize Terraform
```bash
cd deployment/terraform
terraform init
```

#### Create Configuration File
```bash
# For DigitalOcean
cat > terraform.tfvars << EOF
cloud_provider = "digitalocean"
environment = "production"
region = "fra1"
node_count = 3
domain_name = "reverse-tender.com"
do_token = "$DO_TOKEN"
EOF

# For Linode
cat > terraform.tfvars << EOF
cloud_provider = "linode"
environment = "production"
region = "eu-west"
node_count = 3
domain_name = "reverse-tender.com"
linode_token = "$LINODE_TOKEN"
EOF
```

#### Deploy Infrastructure
```bash
terraform plan -var-file="terraform.tfvars"
terraform apply -var-file="terraform.tfvars"
```

### 2. Kubernetes Deployment

#### Configure kubectl
```bash
# DigitalOcean
doctl kubernetes cluster kubeconfig save <cluster-name>

# Linode
linode-cli lke kubeconfig-view <cluster-id> --text --no-headers | base64 -d > ~/.kube/config
```

#### Deploy Services
```bash
cd ../kubernetes

# Create namespace and configs
kubectl apply -f namespace.yaml
kubectl apply -f configmap.yaml

# Create secrets
kubectl create secret generic database-secret \
  --from-literal=host="$DB_HOST" \
  --from-literal=username="root" \
  --from-literal=password="$DB_PASSWORD" \
  --namespace=reverse-tender

# Deploy services
kubectl apply -f deployments.yaml
kubectl apply -f services.yaml
kubectl apply -f nginx-gateway.yaml
```

## Service Configuration

### Microservices Deployed
1. **Auth Service** (2 replicas) - Authentication & authorization
2. **User Service** (2 replicas) - User profile management
3. **Bidding Service** (3 replicas) - Real-time bidding
4. **Order Service** (2 replicas) - Order management
5. **Payment Service** (2 replicas) - Payment processing
6. **Analytics Service** (1 replica) - Analytics & reporting
7. **VIN OCR Service** (2 replicas) - VIN extraction

### Resource Allocation
| Service | CPU Request | Memory Request | CPU Limit | Memory Limit |
|---------|-------------|----------------|-----------|--------------|
| Auth | 250m | 256Mi | 500m | 512Mi |
| User | 250m | 256Mi | 500m | 512Mi |
| Bidding | 500m | 512Mi | 1000m | 1Gi |
| Order | 250m | 256Mi | 500m | 512Mi |
| Payment | 250m | 256Mi | 500m | 512Mi |
| Analytics | 250m | 512Mi | 500m | 1Gi |
| VIN OCR | 500m | 1Gi | 1000m | 2Gi |

## Monitoring & Health Checks

### Health Check Endpoints
- **Individual Services**: `http://<service-ip>:8000/health`
- **API Gateway**: `http://<load-balancer-ip>/health`
- **Simple Check**: `http://<service-ip>:8000/up`

### Monitoring Commands
```bash
# Check all pods
kubectl get pods -n reverse-tender

# Check service logs
kubectl logs -f deployment/auth-service -n reverse-tender

# Check resource usage
kubectl top pods -n reverse-tender

# Check service endpoints
kubectl get endpoints -n reverse-tender
```

## Scaling

### Horizontal Pod Autoscaling
```bash
# Enable HPA for a service
kubectl autoscale deployment auth-service \
  --cpu-percent=70 \
  --min=2 \
  --max=10 \
  -n reverse-tender

# Check HPA status
kubectl get hpa -n reverse-tender
```

### Manual Scaling
```bash
# Scale a specific service
kubectl scale deployment auth-service --replicas=5 -n reverse-tender

# Scale cluster nodes (via cloud provider)
# DigitalOcean: Use doctl or web console
# Linode: Use linode-cli or web console
```

## Security

### Network Security
- Services communicate via internal Kubernetes network
- External access only through Nginx gateway
- Database and Redis not exposed externally

### Secrets Management
- Database credentials stored in Kubernetes secrets
- API keys and tokens encrypted at rest
- Service-to-service authentication via internal tokens

### SSL/TLS
```bash
# Install cert-manager for automatic SSL
kubectl apply -f https://github.com/cert-manager/cert-manager/releases/download/v1.13.0/cert-manager.yaml

# Create ClusterIssuer for Let's Encrypt
kubectl apply -f - << EOF
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@reverse-tender.com
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF
```

## Backup & Disaster Recovery

### Database Backups
- **DigitalOcean**: Automatic daily backups enabled
- **Linode**: Manual backup configuration required

### Application Data
```bash
# Backup Kubernetes configurations
kubectl get all -n reverse-tender -o yaml > backup-$(date +%Y%m%d).yaml

# Backup persistent volumes
kubectl get pv,pvc -n reverse-tender -o yaml > pv-backup-$(date +%Y%m%d).yaml
```

## Cost Optimization

### DigitalOcean Estimated Costs (Monthly)
- **DOKS Cluster**: $36 (3 nodes × $12)
- **MySQL Database**: $60 (2-node cluster)
- **Redis Database**: $30 (1-node cluster)
- **Load Balancer**: $12
- **Total**: ~$138/month

### Linode Estimated Costs (Monthly)
- **LKE Cluster**: $30 (3 nodes × $10)
- **MySQL Database**: $40 (managed instance)
- **NodeBalancer**: $10
- **Total**: ~$80/month

## Troubleshooting

### Common Issues

#### 1. Pod Startup Issues
```bash
# Check pod status
kubectl describe pod <pod-name> -n reverse-tender

# Check logs
kubectl logs <pod-name> -n reverse-tender

# Check events
kubectl get events -n reverse-tender --sort-by='.lastTimestamp'
```

#### 2. Service Communication Issues
```bash
# Test service connectivity
kubectl exec -it <pod-name> -n reverse-tender -- curl http://auth-service:8000/health

# Check service endpoints
kubectl get endpoints -n reverse-tender

# Check DNS resolution
kubectl exec -it <pod-name> -n reverse-tender -- nslookup auth-service
```

#### 3. Database Connection Issues
```bash
# Check database secret
kubectl get secret database-secret -n reverse-tender -o yaml

# Test database connectivity
kubectl exec -it <pod-name> -n reverse-tender -- nc -zv <db-host> 3306
```

### Performance Optimization

#### 1. Resource Tuning
```bash
# Monitor resource usage
kubectl top pods -n reverse-tender

# Adjust resource limits
kubectl patch deployment auth-service -n reverse-tender -p '{"spec":{"template":{"spec":{"containers":[{"name":"auth-service","resources":{"limits":{"memory":"1Gi"}}}]}}}}'
```

#### 2. Database Optimization
- Enable query caching
- Optimize database indexes
- Use read replicas for analytics

## Migration Between Providers

### From DigitalOcean to Linode
1. **Backup Data**: Export databases and configurations
2. **Deploy Infrastructure**: Run Linode deployment script
3. **Migrate Data**: Import databases and update DNS
4. **Test Services**: Verify all services are working
5. **Switch Traffic**: Update DNS to point to new load balancer
6. **Cleanup**: Destroy DigitalOcean resources

### From Linode to DigitalOcean
1. **Backup Data**: Export databases and configurations
2. **Deploy Infrastructure**: Run DigitalOcean deployment script
3. **Migrate Data**: Import databases and update DNS
4. **Test Services**: Verify all services are working
5. **Switch Traffic**: Update DNS to point to new load balancer
6. **Cleanup**: Destroy Linode resources

## Support & Maintenance

### Regular Maintenance Tasks
- **Weekly**: Check service health and resource usage
- **Monthly**: Review and optimize costs
- **Quarterly**: Update Kubernetes and service versions
- **Annually**: Review security configurations and certificates

### Monitoring Setup
```bash
# Install Prometheus and Grafana
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm install monitoring prometheus-community/kube-prometheus-stack -n reverse-tender-monitoring
```

## Conclusion

This multi-cloud deployment setup provides:
- **High Availability**: Multiple replicas and health checks
- **Scalability**: Auto-scaling and load balancing
- **Cost Efficiency**: Choose the most cost-effective provider
- **Flexibility**: Easy migration between providers
- **Security**: Network isolation and secrets management
- **Monitoring**: Comprehensive health checks and logging

The platform is now ready for production deployment on either DigitalOcean or Linode, with the ability to migrate between providers as needed.

