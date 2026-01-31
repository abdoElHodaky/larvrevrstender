# Main Terraform Configuration for Reverse Tender Platform
# This file orchestrates the deployment across different cloud providers

terraform {
  required_version = ">= 1.0"
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
    linode = {
      source  = "linode/linode"
      version = "~> 2.0"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.0"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.0"
    }
  }
}

# Local variables for common configuration
locals {
  project_name = "reverse-tender"
  environment  = var.environment
  region       = var.region
  
  common_tags = {
    Project     = local.project_name
    Environment = local.environment
    ManagedBy   = "terraform"
    CreatedAt   = timestamp()
  }
  
  # Service configuration
  services = {
    api_gateway      = { port = 8000, replicas = var.api_gateway_replicas }
    auth_service     = { port = 8001, replicas = var.auth_service_replicas }
    bidding_service  = { port = 8002, replicas = var.bidding_service_replicas }
    user_service     = { port = 8003, replicas = var.user_service_replicas }
    order_service    = { port = 8004, replicas = var.order_service_replicas }
    notification_service = { port = 8005, replicas = var.notification_service_replicas }
    payment_service  = { port = 8006, replicas = var.payment_service_replicas }
    analytics_service = { port = 8007, replicas = var.analytics_service_replicas }
    vin_ocr_service  = { port = 8008, replicas = var.vin_ocr_service_replicas }
  }
}

# Common resources module
module "common" {
  source = "./modules/common"
  
  project_name = local.project_name
  environment  = local.environment
  tags         = local.common_tags
  
  # Database configuration
  database_config = {
    engine         = var.database_engine
    version        = var.database_version
    instance_class = var.database_instance_class
    storage_size   = var.database_storage_size
    backup_enabled = var.database_backup_enabled
  }
  
  # Redis configuration
  redis_config = {
    version        = var.redis_version
    instance_class = var.redis_instance_class
    node_count     = var.redis_node_count
  }
  
  # Monitoring configuration
  monitoring_enabled = var.monitoring_enabled
  alerting_enabled   = var.alerting_enabled
}

# Cloud provider specific resources
module "cloud_provider" {
  source = "./modules/${var.cloud_provider}"
  
  project_name = local.project_name
  environment  = local.environment
  region       = local.region
  tags         = local.common_tags
  
  # Kubernetes configuration
  kubernetes_config = {
    version    = var.kubernetes_version
    node_count = var.kubernetes_node_count
    node_type  = var.kubernetes_node_type
    min_nodes  = var.kubernetes_min_nodes
    max_nodes  = var.kubernetes_max_nodes
  }
  
  # Network configuration
  network_config = {
    vpc_cidr           = var.vpc_cidr
    private_subnets    = var.private_subnets
    public_subnets     = var.public_subnets
    enable_nat_gateway = var.enable_nat_gateway
  }
  
  # Load balancer configuration
  load_balancer_config = {
    algorithm              = var.load_balancer_algorithm
    health_check_path      = var.health_check_path
    health_check_interval  = var.health_check_interval
    health_check_timeout   = var.health_check_timeout
    health_check_retries   = var.health_check_retries
  }
  
  # Storage configuration
  storage_config = {
    bucket_name = var.storage_bucket_name
    region      = var.storage_region
    versioning  = var.storage_versioning
    encryption  = var.storage_encryption
  }
  
  # Security configuration
  security_config = {
    allowed_ips        = var.allowed_ips
    ssl_certificate    = var.ssl_certificate
    firewall_enabled   = var.firewall_enabled
  }
  
  # Pass common module outputs
  common_outputs = module.common
}

# Kubernetes provider configuration
provider "kubernetes" {
  host                   = module.cloud_provider.kubernetes_endpoint
  token                  = module.cloud_provider.kubernetes_token
  cluster_ca_certificate = base64decode(module.cloud_provider.kubernetes_ca_certificate)
}

# Helm provider configuration
provider "helm" {
  kubernetes {
    host                   = module.cloud_provider.kubernetes_endpoint
    token                  = module.cloud_provider.kubernetes_token
    cluster_ca_certificate = base64decode(module.cloud_provider.kubernetes_ca_certificate)
  }
}

# Deploy applications to Kubernetes
module "kubernetes_apps" {
  source = "./modules/kubernetes"
  
  project_name = local.project_name
  environment  = local.environment
  services     = local.services
  
  # Database connection
  database_host     = module.common.database_endpoint
  database_name     = module.common.database_name
  database_username = module.common.database_username
  database_password = module.common.database_password
  
  # Redis connection
  redis_host     = module.common.redis_endpoint
  redis_password = module.common.redis_password
  
  # Load balancer
  load_balancer_ip = module.cloud_provider.load_balancer_ip
  
  # Storage
  storage_bucket = module.cloud_provider.storage_bucket
  storage_region = module.cloud_provider.storage_region
  
  # Secrets
  jwt_secret           = var.jwt_secret
  app_key             = var.app_key
  twilio_sid          = var.twilio_sid
  twilio_token        = var.twilio_token
  sendgrid_api_key    = var.sendgrid_api_key
  
  depends_on = [
    module.common,
    module.cloud_provider
  ]
}

# Monitoring stack
module "monitoring" {
  source = "./modules/monitoring"
  count  = var.monitoring_enabled ? 1 : 0
  
  project_name = local.project_name
  environment  = local.environment
  
  # Prometheus configuration
  prometheus_config = {
    retention_days = var.prometheus_retention_days
    storage_size   = var.prometheus_storage_size
  }
  
  # Grafana configuration
  grafana_config = {
    admin_password = var.grafana_admin_password
    admin_user     = var.grafana_admin_user
  }
  
  # Alert manager configuration
  alertmanager_config = {
    slack_webhook = var.slack_webhook_url
    email_to      = var.alert_email
  }
  
  depends_on = [module.kubernetes_apps]
}

