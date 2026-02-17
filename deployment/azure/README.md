# Azure Deployment Infrastructure

This directory contains Azure-specific deployment configurations for the Reverse Tender Platform microservices.

## Architecture Overview

The Azure deployment supports multiple deployment strategies:

### Option 1: Azure Kubernetes Service (AKS)
- Managed Kubernetes cluster for container orchestration
- Azure Container Registry (ACR) for container images
- Azure Database for PostgreSQL for managed databases
- Azure Cache for Redis for caching layer

### Option 2: Azure Container Apps
- Serverless container platform with auto-scaling
- Built-in service discovery and load balancing
- Integrated with Azure Monitor and Application Insights

## Directory Structure

```
azure/
├── terraform/           # Infrastructure as Code
│   ├── modules/        # Reusable Terraform modules
│   │   ├── aks/       # Azure Kubernetes Service
│   │   ├── container-apps/  # Azure Container Apps
│   │   ├── database/  # Azure Database services
│   │   ├── networking/ # Virtual networks and security
│   │   └── monitoring/ # Azure Monitor and insights
│   ├── environments/  # Environment-specific configs
│   │   ├── dev/
│   │   ├── staging/
│   │   └── prod/
│   ├── main.tf
│   ├── variables.tf
│   └── outputs.tf
├── kubernetes/         # Kubernetes manifests for AKS
│   ├── base/          # Base configurations
│   └── overlays/      # Environment-specific overlays
├── container-apps/     # Azure Container Apps configurations
├── scripts/           # Deployment and management scripts
│   ├── deploy.ps1     # PowerShell deployment script
│   ├── deploy.sh      # Bash deployment script
│   └── validate.sh    # Validation and testing
├── configs/           # Configuration templates
├── monitoring/        # Azure-specific monitoring
└── docs/             # Azure deployment documentation
```

## Prerequisites

- Azure CLI installed and configured
- Terraform >= 1.6
- kubectl (for AKS deployments)
- PowerShell (for Windows-based deployments)

## Quick Start

1. Configure Azure credentials:
   ```bash
   az login
   az account set --subscription "your-subscription-id"
   ```

2. Initialize Terraform:
   ```bash
   cd terraform/environments/dev
   terraform init
   ```

3. Deploy infrastructure:
   ```bash
   terraform plan
   terraform apply
   ```

## Supported Services

All 11 microservices are supported:
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

## Cost Optimization

- Use Azure Reserved Instances for predictable workloads
- Implement auto-scaling to optimize resource usage
- Use Azure Cost Management for monitoring and alerts
- Consider Azure Spot Instances for non-critical workloads
