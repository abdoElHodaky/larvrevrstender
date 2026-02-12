<div style="max-width: 38.2rem; line-height: 1.618; font-family: 'Inter', 'Segoe UI', 'Roboto', sans-serif;">

# <span style="font-size: 42px; font-weight: 700; line-height: 1.618;">🏗️ Terraform Modules</span>

<p style="font-size: 16px; line-height: 1.618; margin-bottom: 2rem;">Terraform modules for deploying the <strong>Reverse Tender Platform</strong> across multiple cloud providers with Gateway API support and infrastructure as code automation.</p>

## <span style="font-size: 26px; font-weight: 600; line-height: 1.618;">🎯 Infrastructure Strategy Overview</span>

### <span style="font-size: 20px; font-weight: 600; line-height: 1.618;">62% Major Concepts</span>

- **🌐 Multi-Cloud Support**: DigitalOcean and Linode infrastructure with cloud-agnostic Gateway API
- **🏗️ Modular Architecture**: Kubernetes applications, monitoring stack, and common resources modules
- **🚪 Gateway API Integration**: Cloud-agnostic ingress solution with load balancers and VPC networking

<details style="border-left: 3px solid #4ECDC4; padding-left: 1rem; margin: 1rem 0;">
<summary style="font-weight: 600; cursor: pointer;">🏗️ Complete Infrastructure Modules</summary>

### Module Structure

```
modules/
├── digitalocean/     # DigitalOcean infrastructure module
├── linode/          # Linode infrastructure module  
├── gateway-api/     # Gateway API module (cloud-agnostic)
├── common/          # Common resources module
├── kubernetes/      # Kubernetes applications module
└── monitoring/      # Monitoring stack module
```

### Supported Cloud Providers

| Provider | Status | Resources |
|----------|--------|-----------|
| **DigitalOcean** | ✅ Complete | DOKS, Load Balancers, Spaces, VPC, Firewall |
| **Linode** | ✅ Complete | LKE, NodeBalancers, Object Storage, VPC, Firewall |

### Cloud Provider Selection

Set the `cloud_provider` variable to switch between providers:

```hcl
# For DigitalOcean
cloud_provider = "digitalocean"

# For Linode  
cloud_provider = "linode"
```

### Gateway API Module

The Gateway API module provides a cloud-agnostic ingress solution that works with both DigitalOcean and Linode.

### Features

- **Multi-Cloud Compatibility**: Works with both DigitalOcean and Linode load balancers
- **SSL/TLS Termination**: Automatic HTTPS with Let's Encrypt integration
- **Rate Limiting**: Built-in request rate limiting
- **CORS Support**: Configurable Cross-Origin Resource Sharing
- **Health Checks**: Automatic service health monitoring
- **High Availability**: Multi-replica gateway deployment
- **Security Policies**: Advanced security configurations
- **Monitoring**: Built-in metrics and logging

### Gateway API Resources

| Resource | Purpose |
|----------|---------|
| `GatewayClass` | Defines the gateway controller |
| `Gateway` | Main gateway instance with listeners |
| `HTTPRoute` | Routes for each microservice |
| `ReferenceGrant` | Cross-namespace access permissions |
| `BackendTLSPolicy` | Secure backend communication |

### Service Routes

The Gateway API automatically configures routes for all services:

| Service | Path | Port |
|---------|------|------|
| API Gateway | `/api` | 8000 |
| Auth Service | `/auth` | 8001 |
| User Service | `/users` | 8003 |
| Bidding Service | `/bidding` | 8002 |
| Order Service | `/orders` | 8004 |
| Notification Service | `/notifications` | 8005 |
| Payment Service | `/payments` | 8006 |
| Analytics Service | `/analytics` | 8007 |
| VIN OCR Service | `/vin-ocr` | 8008 |

## 🏗️ DigitalOcean Module

### Resources Created

- **DOKS Cluster**: Managed Kubernetes cluster with auto-scaling
- **VPC**: Private network for resource isolation
- **Load Balancer**: HTTP/HTTPS load balancing with health checks
- **Spaces**: Object storage with CDN integration
- **Container Registry**: Private Docker image registry
- **Firewall**: Network security rules
- **SSL Certificate**: Let's Encrypt SSL certificates
- **Database**: Managed MySQL/PostgreSQL cluster
- **Redis**: Managed Redis cluster
- **Monitoring**: Built-in alerts and monitoring

### Key Features

- Auto-scaling node pools
- Automatic SSL certificate management
- CDN integration for production
- Managed database backups
- Network security with firewall rules
- Container registry integration

## 🔧 Linode Module

### Resources Created

- **LKE Cluster**: Linode Kubernetes Engine with auto-scaling
- **VPC**: Virtual Private Cloud for network isolation
- **NodeBalancer**: Load balancing with health checks
- **Object Storage**: S3-compatible object storage
- **Firewall**: Cloud firewall for security
- **Domain Management**: DNS records for SSL
- **Database**: Managed MySQL cluster
- **Redis Instance**: Self-managed Redis on Linode instance
- **Monitoring**: Instance monitoring and alerts

### Key Features

- LKE cluster with control plane ACL
- NodeBalancer with SSL termination
- Object storage with CORS configuration
- Self-managed Redis with automated setup
- VPC with custom subnets
- Firewall rules for security

### Linode-Specific Considerations

- **Redis**: Linode doesn't offer managed Redis, so we deploy Redis on a Linode instance with automated configuration
- **Container Registry**: Uses external registry (Docker Hub) as Linode doesn't have a native container registry
- **CDN**: Uses object storage endpoint directly as Linode doesn't have native CDN

## 🔄 Module Interface Compatibility

Both cloud provider modules maintain identical interfaces to ensure seamless switching:

### Required Inputs
- `project_name`
- `environment` 
- `region`
- `kubernetes_config`
- `network_config`
- `load_balancer_config`
- `storage_config`
- `security_config`

### Standard Outputs
- `kubernetes_endpoint`
- `kubernetes_token`
- `kubernetes_ca_certificate`
- `load_balancer_ip`
- `storage_bucket`
- `storage_region`
- `database_endpoint`
- `redis_endpoint`

## 🚀 Usage Examples

### Basic Deployment

```hcl
module "infrastructure" {
  source = "./modules"
  
  # Basic configuration
  project_name   = "reverse-tender"
  environment    = "production"
  cloud_provider = "digitalocean"  # or "linode"
  region         = "fra1"
  
  # Kubernetes configuration
  kubernetes_config = {
    version    = "1.29"
    node_count = 3
    node_type  = "s-4vcpu-8gb"
    min_nodes  = 2
    max_nodes  = 10
  }
  
  # Gateway API configuration
  gateway_api_enabled = true
  domain_name        = "reversetender.com"
  ssl_enabled        = true
  
  # Provider tokens
  digitalocean_token = var.digitalocean_token
  linode_token      = var.linode_token
}
```

### Advanced Configuration

```hcl
module "infrastructure" {
  source = "./modules"
  
  # Multi-cloud configuration
  cloud_provider = "linode"
  
  # Advanced Gateway API settings
  gateway_api_enabled       = true
  rate_limiting_enabled     = true
  rate_limit_requests       = 1000
  cors_enabled             = true
  cors_allowed_origins     = ["https://app.reversetender.com"]
  backend_tls_enabled      = true
  high_availability_enabled = true
  gateway_replicas         = 3
  
  # Security configuration
  security_policies_enabled = true
  allowed_ips               = ["10.0.0.0/8", "172.16.0.0/12"]
  
  # Monitoring
  monitoring_enabled     = true
  access_logging_enabled = true
  log_level             = "info"
}
```

## 🔧 Configuration Variables

### Core Variables

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `cloud_provider` | string | - | Cloud provider (digitalocean/linode) |
| `project_name` | string | - | Project name |
| `environment` | string | - | Environment (development/staging/production) |
| `region` | string | "fra1" | Cloud provider region |

### Gateway API Variables

| Variable | Type | Default | Description |
|----------|------|---------|-------------|
| `gateway_api_enabled` | bool | true | Enable Gateway API |
| `domain_name` | string | "reversetender.com" | Domain name |
| `ssl_enabled` | bool | true | Enable SSL/TLS |
| `rate_limiting_enabled` | bool | true | Enable rate limiting |
| `cors_enabled` | bool | true | Enable CORS |
| `high_availability_enabled` | bool | true | Enable HA mode |

### Provider-Specific Variables

| Variable | Type | Required For | Description |
|----------|------|--------------|-------------|
| `digitalocean_token` | string | DigitalOcean | DO API token |
| `linode_token` | string | Linode | Linode API token |
| `redis_password` | string | Linode | Redis password |
| `ssh_public_key` | string | Linode | SSH key for instances |

## 📊 Outputs

### Gateway API Outputs

```hcl
# Gateway endpoints
gateway_endpoint = "https://reversetender.com"

# Service endpoints
service_endpoints = {
  api_gateway      = "https://reversetender.com/api"
  auth_service     = "https://reversetender.com/auth"
  user_service     = "https://reversetender.com/users"
  # ... other services
}

# Gateway configuration
gateway_config = {
  ssl_enabled           = true
  rate_limiting_enabled = true
  cors_enabled         = true
  monitoring_enabled   = true
}
```

### Infrastructure Outputs

```hcl
# Kubernetes access
kubernetes_endpoint = "https://k8s-cluster-endpoint"
load_balancer_ip   = "203.0.113.1"

# Storage
storage_bucket   = "reverse-tender-production-storage"
storage_endpoint = "https://fra1.digitaloceanspaces.com"

# Database
database_endpoint = "db-cluster.fra1.db.ondigitalocean.com"
redis_endpoint   = "redis-cluster.fra1.db.ondigitalocean.com"
```

## 🔍 Monitoring and Observability

### Built-in Monitoring

- **Gateway Metrics**: Request rates, response times, error rates
- **Infrastructure Metrics**: CPU, memory, disk usage
- **Application Metrics**: Service-specific metrics
- **Security Metrics**: Rate limiting, blocked requests

### Logging

- **Access Logs**: HTTP request/response logging
- **Application Logs**: Service-specific logging
- **Infrastructure Logs**: Kubernetes and cloud provider logs
- **Security Logs**: Firewall and security policy logs

## 🛡️ Security Features

### Network Security

- **VPC Isolation**: Private networks for each environment
- **Firewall Rules**: Restrictive ingress/egress rules
- **SSL/TLS**: End-to-end encryption
- **Private Subnets**: Database and Redis in private networks

### Application Security

- **Rate Limiting**: Request throttling
- **CORS Policies**: Cross-origin request control
- **Security Headers**: HTTP security headers
- **IP Whitelisting**: Source IP restrictions

### Access Control

- **RBAC**: Kubernetes role-based access control
- **Service Mesh**: mTLS between services
- **Secrets Management**: Encrypted secret storage
- **Certificate Management**: Automatic SSL renewal

## 🚀 Deployment Guide

### Prerequisites

1. **Terraform**: Version >= 1.6
2. **Cloud Provider Account**: DigitalOcean or Linode
3. **API Tokens**: Provider-specific API tokens
4. **Domain**: Registered domain for SSL certificates
5. **kubectl**: For Kubernetes management

### Deployment Steps

1. **Clone Repository**
   ```bash
   git clone <repository-url>
   cd deployment/terraform
   ```

2. **Configure Variables**
   ```bash
   cp terraform.tfvars.example terraform.tfvars
   # Edit terraform.tfvars with your configuration
   ```

3. **Initialize Terraform**
   ```bash
   terraform init
   ```

4. **Plan Deployment**
   ```bash
   terraform plan
   ```

5. **Deploy Infrastructure**
   ```bash
   terraform apply
   ```

6. **Configure kubectl**
   ```bash
   # For DigitalOcean
   doctl kubernetes cluster kubeconfig save <cluster-name>
   
   # For Linode
   linode-cli lke kubeconfig-view <cluster-id> --text --no-headers | base64 -d > ~/.kube/config
   ```

### Environment-Specific Deployments

```bash
# Development
terraform workspace new development
terraform apply -var="environment=development"

# Staging  
terraform workspace new staging
terraform apply -var="environment=staging"

# Production
terraform workspace new production
terraform apply -var="environment=production"
```

## 🔄 Migration Between Providers

To migrate from one cloud provider to another:

1. **Backup Data**
   ```bash
   # Backup databases and storage
   kubectl exec -it <db-pod> -- mysqldump --all-databases > backup.sql
   ```

2. **Update Configuration**
   ```hcl
   # Change cloud provider
   cloud_provider = "linode"  # from "digitalocean"
   ```

3. **Plan Migration**
   ```bash
   terraform plan -var="cloud_provider=linode"
   ```

4. **Apply Changes**
   ```bash
   terraform apply -var="cloud_provider=linode"
   ```

5. **Restore Data**
   ```bash
   # Restore to new infrastructure
   kubectl exec -it <new-db-pod> -- mysql < backup.sql
   ```

## 📚 Additional Resources

- [Gateway API Documentation](https://gateway-api.sigs.k8s.io/)
- [DigitalOcean Kubernetes](https://docs.digitalocean.com/products/kubernetes/)
- [Linode Kubernetes Engine](https://www.linode.com/products/kubernetes/)
- [Terraform Best Practices](https://www.terraform.io/docs/cloud/guides/recommended-practices/index.html)

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.
