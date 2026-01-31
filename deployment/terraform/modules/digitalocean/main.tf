# DigitalOcean Infrastructure Module for Reverse Tender Platform

terraform {
  required_providers {
    digitalocean = {
      source  = "digitalocean/digitalocean"
      version = "~> 2.0"
    }
  }
}

# Configure DigitalOcean provider
provider "digitalocean" {
  token = var.digitalocean_token
}

# Local variables
locals {
  cluster_name = "${var.project_name}-${var.environment}"
  vpc_name     = "${var.project_name}-${var.environment}-vpc"
  
  tags = merge(var.tags, {
    Provider = "digitalocean"
  })
}

# VPC for network isolation
resource "digitalocean_vpc" "main" {
  name     = local.vpc_name
  region   = var.region
  ip_range = var.network_config.vpc_cidr
}

# Kubernetes cluster
resource "digitalocean_kubernetes_cluster" "main" {
  name     = local.cluster_name
  region   = var.region
  version  = var.kubernetes_config.version
  vpc_uuid = digitalocean_vpc.main.id
  
  tags = [for k, v in local.tags : "${k}:${v}"]
  
  node_pool {
    name       = "${local.cluster_name}-worker-pool"
    size       = var.kubernetes_config.node_type
    node_count = var.kubernetes_config.node_count
    tags       = [for k, v in local.tags : "${k}:${v}"]
    
    auto_scale = true
    min_nodes  = var.kubernetes_config.min_nodes
    max_nodes  = var.kubernetes_config.max_nodes
  }
  
  maintenance_policy {
    start_time = "04:00"
    day        = "sunday"
  }
}

# Container Registry
resource "digitalocean_container_registry" "main" {
  name                   = "${var.project_name}-registry"
  subscription_tier_slug = var.environment == "production" ? "professional" : "basic"
  region                 = var.region
}

# Load Balancer
resource "digitalocean_loadbalancer" "main" {
  name     = "${local.cluster_name}-lb"
  region   = var.region
  vpc_uuid = digitalocean_vpc.main.id
  
  size_unit = var.environment == "production" ? 2 : 1
  
  forwarding_rule {
    entry_protocol  = "https"
    entry_port      = 443
    target_protocol = "http"
    target_port     = 80
    certificate_name = digitalocean_certificate.main.name
  }
  
  forwarding_rule {
    entry_protocol  = "http"
    entry_port      = 80
    target_protocol = "http"
    target_port     = 80
  }
  
  healthcheck {
    protocol               = "http"
    port                   = 80
    path                   = var.load_balancer_config.health_check_path
    check_interval_seconds = var.load_balancer_config.health_check_interval
    response_timeout_seconds = var.load_balancer_config.health_check_timeout
    unhealthy_threshold    = var.load_balancer_config.health_check_retries
    healthy_threshold      = 2
  }
  
  algorithm                = var.load_balancer_config.algorithm
  enable_proxy_protocol    = true
  enable_backend_keepalive = true
  
  droplet_tag = "${var.project_name}-${var.environment}-worker"
}

# SSL Certificate
resource "digitalocean_certificate" "main" {
  name    = "${local.cluster_name}-cert"
  type    = "lets_encrypt"
  domains = [var.domain_name, "*.${var.domain_name}"]
  
  lifecycle {
    create_before_destroy = true
  }
}

# Spaces (Object Storage)
resource "digitalocean_spaces_bucket" "main" {
  name   = var.storage_config.bucket_name != "" ? var.storage_config.bucket_name : "${var.project_name}-${var.environment}-storage"
  region = var.storage_config.region != "" ? var.storage_config.region : var.region
  
  versioning {
    enabled = var.storage_config.versioning
  }
  
  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET", "PUT", "POST", "DELETE", "HEAD"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }
}

# CDN for Spaces
resource "digitalocean_cdn" "main" {
  count  = var.environment == "production" ? 1 : 0
  origin = digitalocean_spaces_bucket.main.bucket_domain_name
  
  custom_domain = "cdn.${var.domain_name}"
  certificate_name = digitalocean_certificate.main.name
  
  ttl = 3600
}

# Firewall
resource "digitalocean_firewall" "main" {
  name = "${local.cluster_name}-firewall"
  
  tags = [for k, v in local.tags : "${k}:${v}"]
  
  # HTTP/HTTPS access
  inbound_rule {
    protocol         = "tcp"
    port_range       = "80"
    source_addresses = var.security_config.allowed_ips
  }
  
  inbound_rule {
    protocol         = "tcp"
    port_range       = "443"
    source_addresses = var.security_config.allowed_ips
  }
  
  # SSH access (restricted)
  inbound_rule {
    protocol         = "tcp"
    port_range       = "22"
    source_addresses = ["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]
  }
  
  # Kubernetes API
  inbound_rule {
    protocol         = "tcp"
    port_range       = "6443"
    source_addresses = var.security_config.allowed_ips
  }
  
  # Allow all outbound
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

# Reserved IP for Load Balancer
resource "digitalocean_reserved_ip" "main" {
  region = var.region
  type   = "assign"
  droplet = digitalocean_loadbalancer.main.id
}

# Project for organization
resource "digitalocean_project" "main" {
  name        = "${var.project_name}-${var.environment}"
  description = "Reverse Tender Platform - ${var.environment} environment"
  purpose     = "Web Application"
  environment = var.environment
  
  resources = [
    digitalocean_kubernetes_cluster.main.urn,
    digitalocean_loadbalancer.main.urn,
    digitalocean_spaces_bucket.main.urn,
    digitalocean_vpc.main.urn,
    digitalocean_reserved_ip.main.urn
  ]
}

# Database cluster (if using managed database)
resource "digitalocean_database_cluster" "main" {
  count = var.use_managed_database ? 1 : 0
  
  name       = "${local.cluster_name}-db"
  engine     = "mysql"
  version    = "8"
  size       = var.database_size
  region     = var.region
  node_count = var.database_node_count
  
  private_network_uuid = digitalocean_vpc.main.id
  
  maintenance_window {
    day  = "sunday"
    hour = "04:00:00"
  }
  
  backup_restore {
    database_name = var.backup_database_name
    backup_created_at = var.backup_created_at
  }
  
  tags = [for k, v in local.tags : "${k}:${v}"]
}

# Redis cluster (if using managed Redis)
resource "digitalocean_database_cluster" "redis" {
  count = var.use_managed_redis ? 1 : 0
  
  name       = "${local.cluster_name}-redis"
  engine     = "redis"
  version    = "7"
  size       = var.redis_size
  region     = var.region
  node_count = 1
  
  private_network_uuid = digitalocean_vpc.main.id
  
  maintenance_window {
    day  = "sunday"
    hour = "05:00:00"
  }
  
  tags = [for k, v in local.tags : "${k}:${v}"]
}

# Monitoring (if enabled)
resource "digitalocean_monitor_alert" "high_cpu" {
  count = var.monitoring_enabled ? 1 : 0
  
  alerts {
    email = [var.alert_email]
    slack {
      channel = var.slack_channel
      url     = var.slack_webhook_url
    }
  }
  
  window      = "5m"
  type        = "v1/insights/droplet/cpu"
  compare     = "GreaterThan"
  value       = 80
  enabled     = true
  entities    = [digitalocean_kubernetes_cluster.main.id]
  description = "High CPU usage alert for ${local.cluster_name}"
}

resource "digitalocean_monitor_alert" "high_memory" {
  count = var.monitoring_enabled ? 1 : 0
  
  alerts {
    email = [var.alert_email]
    slack {
      channel = var.slack_channel
      url     = var.slack_webhook_url
    }
  }
  
  window      = "5m"
  type        = "v1/insights/droplet/memory_utilization_percent"
  compare     = "GreaterThan"
  value       = 85
  enabled     = true
  entities    = [digitalocean_kubernetes_cluster.main.id]
  description = "High memory usage alert for ${local.cluster_name}"
}

