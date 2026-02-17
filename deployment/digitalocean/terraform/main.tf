# DigitalOcean Terraform Configuration for Reverse Tender Platform
# This file orchestrates the deployment of microservices on DigitalOcean using DOKS

terraform {
  required_version = ">= 1.6"
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.34"
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

# Configure the DigitalOcean Provider
provider "digitalocean" {
  token = var.digitalocean_token
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

# DigitalOcean Project for organization
resource "digitalocean_project" "main" {
  name        = "${local.project_name}-${local.environment}"
  description = "Reverse Tender Platform - ${local.environment} environment"
  purpose     = "Web Application"
  environment = title(local.environment)
}

# VPC for network isolation
resource "digitalocean_vpc" "main" {
  name     = "vpc-${local.project_name}-${local.environment}"
  region   = local.region
  ip_range = var.vpc_ip_range
}

# DOKS Module
module "doks" {
  source = "./modules/doks"
  
  cluster_name       = "${local.project_name}-${local.environment}"
  region            = local.region
  kubernetes_version = var.kubernetes_version
  tags              = local.common_tags
  
  # Node pool configuration
  node_pool = {
    name       = "worker-pool"
    size       = var.node_size
    node_count = var.node_count
    min_nodes  = var.min_nodes
    max_nodes  = var.max_nodes
    auto_scale = var.enable_autoscaling
    tags       = local.common_tags
  }
  
  # VPC configuration
  vpc_uuid = digitalocean_vpc.main.id
  
  services = local.services
}

# Container Registry
resource "digitalocean_container_registry" "main" {
  name                   = "${replace(local.project_name, "-", "")}${local.environment}"
  subscription_tier_slug = var.registry_subscription_tier
  region                 = local.region
}

# Database Module
module "database" {
  source = "./modules/database"
  
  cluster_name = "db-${local.project_name}-${local.environment}"
  region      = local.region
  engine      = "pg"
  version     = var.postgresql_version
  size        = var.database_size
  node_count  = var.database_node_count
  
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
  private_network_uuid = digitalocean_vpc.main.id
  
  # Backup configuration
  backup_restore = {
    database_name     = var.environment == "prod" ? "auth_service" : null
    backup_created_at = var.environment == "prod" ? var.backup_restore_time : null
  }
  
  tags = local.common_tags
}

# Redis Database for caching
module "redis" {
  source = "./modules/database"
  
  cluster_name = "redis-${local.project_name}-${local.environment}"
  region      = local.region
  engine      = "redis"
  version     = var.redis_version
  size        = var.redis_size
  node_count  = 1
  
  # VPC configuration
  private_network_uuid = digitalocean_vpc.main.id
  
  tags = local.common_tags
}

# Spaces Object Storage
module "spaces" {
  source = "./modules/spaces"
  
  bucket_name = "${local.project_name}-${local.environment}-storage"
  region     = local.region
  
  # CDN configuration
  enable_cdn = var.enable_cdn
  cdn_ttl    = var.cdn_ttl
  
  # CORS configuration
  cors_rules = [
    {
      allowed_headers = ["*"]
      allowed_methods = ["GET", "PUT", "POST", "DELETE", "HEAD"]
      allowed_origins = ["*"]
      max_age_seconds = 3000
    }
  ]
  
  # Lifecycle configuration
  lifecycle_rules = var.spaces_lifecycle_rules
}

# Load Balancer
resource "digitalocean_loadbalancer" "main" {
  name   = "lb-${local.project_name}-${local.environment}"
  region = local.region
  vpc_uuid = digitalocean_vpc.main.id
  
  size_unit = var.load_balancer_size_unit
  
  forwarding_rule {
    entry_protocol  = "http"
    entry_port      = 80
    target_protocol = "http"
    target_port     = 80
    
    certificate_name = ""
  }
  
  forwarding_rule {
    entry_protocol  = "https"
    entry_port      = 443
    target_protocol = "http"
    target_port     = 80
    
    certificate_name = var.enable_ssl ? digitalocean_certificate.main[0].name : ""
  }
  
  healthcheck {
    protocol                 = "http"
    port                    = 80
    path                    = "/health"
    check_interval_seconds  = 10
    response_timeout_seconds = 5
    unhealthy_threshold     = 3
    healthy_threshold       = 2
  }
  
  # Sticky sessions
  sticky_sessions {
    type               = var.sticky_sessions_type
    cookie_name        = var.sticky_sessions_cookie_name
    cookie_ttl_seconds = var.sticky_sessions_cookie_ttl
  }
  
  # Redirect HTTP to HTTPS
  redirect_http_to_https = var.enable_ssl
  
  # Enable PROXY protocol
  enable_proxy_protocol = true
  
  # Droplet tag for auto-discovery
  droplet_tag = "doks:worker"
}

# SSL Certificate (if enabled)
resource "digitalocean_certificate" "main" {
  count = var.enable_ssl ? 1 : 0
  
  name    = "cert-${local.project_name}-${local.environment}"
  type    = var.certificate_type
  domains = var.certificate_domains
  
  # For Let's Encrypt certificates
  lifecycle {
    create_before_destroy = true
  }
}

# Firewall for security
resource "digitalocean_firewall" "main" {
  name = "fw-${local.project_name}-${local.environment}"
  
  droplet_ids = module.doks.node_droplet_ids
  
  # Inbound rules
  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = var.allowed_ssh_ips
  }
  
  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }
  
  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }
  
  inbound_rule {
    protocol         = "tcp"
    port_range       = "6443"
    source_addresses = var.allowed_k8s_ips
  }
  
  inbound_rule {
    protocol         = "icmp"
    source_addresses = ["0.0.0.0/0", "::/0"]
  }
  
  # Outbound rules
  outbound_rule {
    protocol              = "tcp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
  
  outbound_rule {
    protocol              = "udp"
    port_range            = "1-65535"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
  
  outbound_rule {
    protocol              = "icmp"
    destination_addresses = ["0.0.0.0/0", "::/0"]
  }
}

# Monitoring and Alerting
module "monitoring" {
  source = "./modules/monitoring"
  
  cluster_name = "${local.project_name}-${local.environment}"
  region      = local.region
  
  # DOKS cluster information
  doks_cluster_id = module.doks.cluster_id
  
  # Database information
  postgresql_cluster_id = module.database.cluster_id
  redis_cluster_id     = module.redis.cluster_id
  
  # Load balancer information
  load_balancer_id = digitalocean_loadbalancer.main.id
  
  # Notification settings
  notification_email = var.notification_email
  slack_webhook_url  = var.slack_webhook_url
  
  tags = local.common_tags
}

# Domain and DNS (optional)
resource "digitalocean_domain" "main" {
  count = var.domain_name != "" ? 1 : 0
  name  = var.domain_name
}

resource "digitalocean_record" "main" {
  count  = var.domain_name != "" ? 1 : 0
  domain = digitalocean_domain.main[0].name
  type   = "A"
  name   = var.environment == "prod" ? "@" : var.environment
  value  = digitalocean_loadbalancer.main.ip
  ttl    = 300
}

resource "digitalocean_record" "wildcard" {
  count  = var.domain_name != "" ? 1 : 0
  domain = digitalocean_domain.main[0].name
  type   = "A"
  name   = var.environment == "prod" ? "*" : "*.${var.environment}"
  value  = digitalocean_loadbalancer.main.ip
  ttl    = 300
}

# Volume for persistent storage (if needed)
resource "digitalocean_volume" "main" {
  count                   = var.enable_volume ? 1 : 0
  region                  = local.region
  name                    = "vol-${local.project_name}-${local.environment}"
  size                    = var.volume_size
  initial_filesystem_type = var.volume_filesystem_type
  description            = "Persistent storage for ${local.project_name} ${local.environment}"
  tags                   = local.common_tags
}

# Snapshot for backup (if enabled)
resource "digitalocean_volume_snapshot" "main" {
  count     = var.enable_volume && var.enable_snapshots ? 1 : 0
  name      = "snap-${local.project_name}-${local.environment}-${formatdate("YYYY-MM-DD", timestamp())}"
  volume_id = digitalocean_volume.main[0].id
  tags      = local.common_tags
}

# Reserved IP for static IP (optional)
resource "digitalocean_reserved_ip" "main" {
  count  = var.enable_reserved_ip ? 1 : 0
  region = local.region
  type   = "assign"
  droplet = module.doks.node_droplet_ids[0]
}

# Assign resources to project
resource "digitalocean_project_resources" "main" {
  project = digitalocean_project.main.id
  
  resources = concat(
    [
      digitalocean_vpc.main.urn,
      digitalocean_container_registry.main.urn,
      digitalocean_loadbalancer.main.urn,
      module.doks.cluster_urn,
      module.database.cluster_urn,
      module.redis.cluster_urn
    ],
    var.enable_volume ? [digitalocean_volume.main[0].urn] : [],
    var.enable_reserved_ip ? [digitalocean_reserved_ip.main[0].urn] : [],
    var.domain_name != "" ? [digitalocean_domain.main[0].urn] : []
  )
}
