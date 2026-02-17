# Linode Terraform Configuration for Reverse Tender Platform
# This file orchestrates the deployment of microservices on Linode using LKE

terraform {
  required_version = ">= 1.6"
  required_providers {
    linode = {
      source  = "linode/linode"
      version = "~> 2.21"
    }
    kubernetes = {
      source  = "hashicorp/kubernetes"
      version = "~> 2.24"
    }
    helm = {
      source  = "hashicorp/helm"
      version = "~> 2.12"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }
}

# Configure the Linode Provider
provider "linode" {
  token = var.linode_token
}

# Local variables for common configuration
locals {
  project_name = "reverse-tender"
  environment  = var.environment
  region       = var.region
  
  common_tags = [
    "${local.project_name}:${local.environment}",
    "managed-by:terraform",
    "project:${local.project_name}",
    "environment:${local.environment}"
  ]
  
  # Service configuration matching existing setup
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
    auction_service  = { port = 8009, replicas = var.auction_service_replicas }
    gateway_service  = { port = 8010, replicas = var.gateway_service_replicas }
  }
}

# VPC for network isolation
resource "linode_vpc" "main" {
  label       = "vpc-${local.project_name}-${local.environment}"
  region      = local.region
  description = "VPC for ${local.project_name} ${local.environment} environment"
}

# VPC Subnets
resource "linode_vpc_subnet" "lke_subnet" {
  vpc_id = linode_vpc.main.id
  label  = "lke-subnet-${local.project_name}-${local.environment}"
  ipv4   = var.lke_subnet_cidr
}

resource "linode_vpc_subnet" "database_subnet" {
  vpc_id = linode_vpc.main.id
  label  = "db-subnet-${local.project_name}-${local.environment}"
  ipv4   = var.database_subnet_cidr
}

# LKE Module
module "lke" {
  source = "./modules/lke"
  
  cluster_name    = "${local.project_name}-${local.environment}"
  region         = local.region
  kubernetes_version = var.kubernetes_version
  tags           = local.common_tags
  
  # Node pools configuration
  node_pools = [
    {
      type  = var.node_type
      count = var.node_count
    }
  ]
  
  # VPC configuration
  vpc_id    = linode_vpc.main.id
  subnet_id = linode_vpc_subnet.lke_subnet.id
  
  services = local.services
}

# Database Module
module "database" {
  source = "./modules/database"
  
  cluster_label   = "db-${local.project_name}-${local.environment}"
  region         = local.region
  engine         = "postgresql"
  engine_version = var.postgresql_version
  type           = var.database_type
  node_count     = var.database_node_count
  
  # Database names for each service
  databases = [
    "auth_service",
    "user_service", 
    "order_service",
    "payment_service",
    "auction_service",
    "bidding_service",
    "notification_service",
    "analytics_service",
    "vin_ocr_service"
  ]
  
  # VPC configuration
  vpc_id    = linode_vpc.main.id
  subnet_id = linode_vpc_subnet.database_subnet.id
  
  # Backup configuration
  backup_enabled = var.environment == "prod" ? true : false
  
  tags = local.common_tags
}

# Redis Database for caching
module "redis" {
  source = "./modules/database"
  
  cluster_label   = "redis-${local.project_name}-${local.environment}"
  region         = local.region
  engine         = "redis"
  engine_version = var.redis_version
  type           = var.redis_type
  node_count     = 1
  
  # VPC configuration
  vpc_id    = linode_vpc.main.id
  subnet_id = linode_vpc_subnet.database_subnet.id
  
  # Backup configuration
  backup_enabled = var.environment == "prod" ? true : false
  
  tags = local.common_tags
}

# Object Storage Module
module "storage" {
  source = "./modules/storage"
  
  bucket_name = "${local.project_name}-${local.environment}-storage"
  region     = local.region
  
  # Versioning and lifecycle
  versioning_enabled = var.environment == "prod" ? true : false
  lifecycle_rules    = var.storage_lifecycle_rules
  
  tags = local.common_tags
}

# NodeBalancer for load balancing
resource "linode_nodebalancer" "main" {
  label  = "nb-${local.project_name}-${local.environment}"
  region = local.region
  tags   = local.common_tags
}

# NodeBalancer Config for HTTP
resource "linode_nodebalancer_config" "http" {
  nodebalancer_id = linode_nodebalancer.main.id
  port            = 80
  protocol        = "http"
  algorithm       = "roundrobin"
  stickiness      = "none"
  
  check          = "http"
  check_path     = "/health"
  check_attempts = 3
  check_timeout  = 10
  check_interval = 15
}

# NodeBalancer Config for HTTPS
resource "linode_nodebalancer_config" "https" {
  count           = var.enable_ssl ? 1 : 0
  nodebalancer_id = linode_nodebalancer.main.id
  port            = 443
  protocol        = "https"
  algorithm       = "roundrobin"
  stickiness      = "none"
  
  ssl_cert = var.ssl_certificate
  ssl_key  = var.ssl_private_key
  
  check          = "http"
  check_path     = "/health"
  check_attempts = 3
  check_timeout  = 10
  check_interval = 15
}

# Firewall for security
resource "linode_firewall" "main" {
  label = "fw-${local.project_name}-${local.environment}"
  tags  = local.common_tags

  # Inbound rules
  inbound {
    label    = "allow-http"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "80"
    ipv4     = ["0.0.0.0/0"]
    ipv6     = ["::/0"]
  }

  inbound {
    label    = "allow-https"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "443"
    ipv4     = ["0.0.0.0/0"]
    ipv6     = ["::/0"]
  }

  inbound {
    label    = "allow-ssh"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "22"
    ipv4     = var.allowed_ssh_ips
  }

  inbound {
    label    = "allow-kubernetes-api"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "6443"
    ipv4     = var.allowed_k8s_ips
  }

  # Outbound rules
  outbound {
    label    = "allow-all-outbound"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "1-65535"
    ipv4     = ["0.0.0.0/0"]
    ipv6     = ["::/0"]
  }

  outbound {
    label    = "allow-all-udp-outbound"
    action   = "ACCEPT"
    protocol = "UDP"
    ports    = "1-65535"
    ipv4     = ["0.0.0.0/0"]
    ipv6     = ["::/0"]
  }

  # Apply to LKE cluster nodes
  linodes = module.lke.node_instance_ids
}

# Monitoring and Alerting
module "monitoring" {
  source = "./modules/monitoring"
  
  cluster_name = "${local.project_name}-${local.environment}"
  region      = local.region
  
  # LKE cluster information
  lke_cluster_id = module.lke.cluster_id
  
  # Database information
  postgresql_cluster_id = module.database.cluster_id
  redis_cluster_id     = module.redis.cluster_id
  
  # Notification settings
  notification_email = var.notification_email
  slack_webhook_url  = var.slack_webhook_url
  
  tags = local.common_tags
}

# Domain and DNS (optional)
resource "linode_domain" "main" {
  count       = var.domain_name != "" ? 1 : 0
  domain      = var.domain_name
  type        = "master"
  description = "Domain for ${local.project_name} ${local.environment}"
  tags        = local.common_tags
}

resource "linode_domain_record" "main" {
  count     = var.domain_name != "" ? 1 : 0
  domain_id = linode_domain.main[0].id
  name      = var.environment == "prod" ? "" : var.environment
  record_type = "A"
  target    = linode_nodebalancer.main.ipv4
  ttl_sec   = 300
}

# Wildcard record for subdomains
resource "linode_domain_record" "wildcard" {
  count     = var.domain_name != "" ? 1 : 0
  domain_id = linode_domain.main[0].id
  name      = var.environment == "prod" ? "*" : "*.${var.environment}"
  record_type = "A"
  target    = linode_nodebalancer.main.ipv4
  ttl_sec   = 300
}

# Backup configuration
resource "linode_volume" "backup" {
  count  = var.enable_backup_volume ? 1 : 0
  label  = "backup-${local.project_name}-${local.environment}"
  region = local.region
  size   = var.backup_volume_size
  tags   = local.common_tags
}

# Longview for system monitoring
resource "linode_longview" "main" {
  count = var.enable_longview ? 1 : 0
  label = "longview-${local.project_name}-${local.environment}"
}

# StackScript for node initialization (if needed)
resource "linode_stackscript" "node_init" {
  count       = var.custom_node_init ? 1 : 0
  label       = "node-init-${local.project_name}-${local.environment}"
  description = "Node initialization script for ${local.project_name}"
  script      = file("${path.module}/scripts/node-init.sh")
  images      = ["linode/ubuntu22.04"]
  is_public   = false
}

# Instance for bastion host (optional)
resource "linode_instance" "bastion" {
  count  = var.enable_bastion ? 1 : 0
  label  = "bastion-${local.project_name}-${local.environment}"
  image  = "linode/ubuntu22.04"
  region = local.region
  type   = var.bastion_type
  
  authorized_keys = var.ssh_public_keys
  root_pass      = var.root_password
  
  tags = local.common_tags
  
  # Attach to VPC
  interface {
    purpose      = "vpc"
    vpc_id       = linode_vpc.main.id
    subnet_id    = linode_vpc_subnet.lke_subnet.id
    ipv4 {
      vpc = "10.0.1.10"
    }
  }
  
  interface {
    purpose = "public"
  }
}
