# OpenStack Deployment Infrastructure

This directory contains OpenStack-specific deployment configurations for the Reverse Tender Platform microservices.

## Architecture Overview

The OpenStack deployment supports multiple deployment strategies:

### Option 1: Heat Orchestration Templates
- Complete infrastructure orchestration using Heat
- Nova compute instances with auto-scaling
- Neutron networking with security groups
- Cinder block storage for persistence

### Option 2: Kubernetes on OpenStack
- Kubernetes cluster deployed on OpenStack VMs
- OpenStack Magnum for managed Kubernetes (if available)
- Container orchestration with persistent storage

### Option 3: Direct Container Deployment
- Docker containers on OpenStack VMs
- Docker Swarm for orchestration
- Load balancing with Octavia

## Directory Structure

```
openstack/
├── heat/              # Heat orchestration templates
│   ├── microservices-stack.yaml  # Main stack template
│   ├── database-stack.yaml       # Database services
│   ├── networking-stack.yaml     # Network infrastructure
│   ├── storage-stack.yaml        # Storage configuration
│   └── nested/                   # Nested templates
│       ├── service-template.yaml
│       └── database-template.yaml
├── terraform/         # Alternative IaC approach
│   ├── modules/
│   │   ├── compute/   # Nova instances
│   │   ├── networking/ # Neutron networks
│   │   ├── storage/   # Cinder volumes
│   │   └── database/  # Trove or containerized
│   ├── environments/
│   │   ├── dev/
│   │   ├── staging/
│   │   └── prod/
│   ├── main.tf
│   ├── variables.tf
│   └── outputs.tf
├── kubernetes/        # Kubernetes manifests
│   ├── base/
│   └── overlays/
├── scripts/           # Deployment scripts
│   ├── deploy.sh
│   ├── validate.sh
│   └── configure-magnum.sh
├── configs/           # Configuration templates
├── monitoring/        # OpenStack-specific monitoring
└── docs/             # OpenStack deployment documentation
```

## Prerequisites

- OpenStack CLI (python-openstackclient) installed
- Heat client (python-heatclient) for Heat deployments
- Terraform >= 1.6 (for Terraform approach)
- kubectl (for Kubernetes deployments)
- Access to OpenStack cloud with required quotas

## OpenStack Services Required

- **Nova**: Compute instances
- **Neutron**: Networking and security groups
- **Cinder**: Block storage for persistent data
- **Heat**: Orchestration (for Heat template approach)
- **Octavia**: Load balancing (recommended)
- **Trove**: Database as a Service (optional)
- **Swift**: Object storage for backups (optional)
- **Barbican**: Secrets management (optional)

## Quick Start

### Using Heat Templates

1. Configure OpenStack credentials:
   ```bash
   source openrc.sh
   ```

2. Deploy the stack:
   ```bash
   openstack stack create -t heat/microservices-stack.yaml \
     --parameter-file heat/environments/dev.yaml \
     reverse-tender-dev
   ```

### Using Terraform

1. Configure OpenStack credentials:
   ```bash
   export OS_AUTH_URL="https://your-openstack:5000/v3"
   export OS_PROJECT_NAME="your-project"
   export OS_USERNAME="your-username"
   export OS_PASSWORD="your-password"
   ```

2. Initialize and deploy:
   ```bash
   cd terraform/environments/dev
   terraform init
   terraform plan
   terraform apply
   ```

## Deployment Strategies

### 1. VM-Based Deployment
- Deploy services directly on Nova instances
- Use cloud-init for service configuration
- Implement load balancing with Octavia

### 2. Kubernetes Deployment
- Create Kubernetes cluster on OpenStack VMs
- Use existing Kubernetes manifests
- Leverage OpenStack Cinder for persistent volumes

### 3. Container-First Deployment
- Deploy Docker containers on OpenStack VMs
- Use Docker Swarm for orchestration
- Implement service discovery and load balancing

## Database Options

### Option 1: OpenStack Trove
- Managed database service
- Automated backups and scaling
- High availability configurations

### Option 2: Containerized Databases
- PostgreSQL containers on dedicated VMs
- Manual backup and scaling management
- More control over configuration

### Option 3: VM-Based Databases
- Traditional PostgreSQL installation on VMs
- Full control over database configuration
- Manual management required

## Networking Configuration

- **Management Network**: Internal service communication
- **Public Network**: External access via floating IPs
- **Database Network**: Isolated database communication
- **Security Groups**: Service-specific firewall rules

## Storage Strategy

- **Root Volumes**: OS and application code
- **Data Volumes**: Database and persistent data
- **Swift Storage**: Object storage for uploads and backups
- **Backup Strategy**: Automated snapshots and Swift backups

## Monitoring and Logging

- **Ceilometer**: Resource usage monitoring
- **Prometheus**: Application metrics (deployed as containers)
- **Grafana**: Visualization dashboards
- **ELK Stack**: Centralized logging
- **Jaeger**: Distributed tracing

## Cost Optimization

- Use appropriate instance flavors for workloads
- Implement auto-scaling based on metrics
- Use spot instances for non-critical workloads
- Regular cleanup of unused resources
- Monitor resource usage with Ceilometer
