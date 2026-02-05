# Linode Infrastructure Module for Reverse Tender Platform

terraform {
  required_providers {
    linode = {
      source  = "linode/linode"
      version = "~> 2.21"
    }
  }
}

# Configure Linode provider
provider "linode" {
  token = var.linode_token
}

# Local variables
locals {
  cluster_name = "${var.project_name}-${var.environment}"
  vpc_name     = "${var.project_name}-${var.environment}-vpc"
  
  tags = merge(var.tags, {
    Provider = "linode"
  })
}

# VPC for network isolation
resource "linode_vpc" "main" {
  label  = local.vpc_name
  region = var.region
  
  # Convert CIDR to individual subnets for Linode VPC
  dynamic "subnet" {
    for_each = var.network_config.private_subnets
    content {
      label = "${local.vpc_name}-private-${subnet.key + 1}"
      ipv4  = subnet.value
    }
  }
  
  dynamic "subnet" {
    for_each = var.network_config.public_subnets
    content {
      label = "${local.vpc_name}-public-${subnet.key + 1}"
      ipv4  = subnet.value
    }
  }
}

# Kubernetes cluster (LKE)
resource "linode_lke_cluster" "main" {
  label       = local.cluster_name
  k8s_version = var.kubernetes_config.version
  region      = var.region
  
  tags = [for k, v in local.tags : "${k}:${v}"]
  
  pool {
    type  = var.kubernetes_config.node_type
    count = var.kubernetes_config.node_count
    
    autoscaler {
      enabled = true
      min     = var.kubernetes_config.min_nodes
      max     = var.kubernetes_config.max_nodes
    }
  }
  
  # Control plane ACL
  control_plane {
    acl {
      enabled = true
      
      # Allow access from specified IPs
      dynamic "addresses" {
        for_each = var.security_config.allowed_ips
        content {
          ipv4 = [addresses.value]
        }
      }
    }
  }
}

# NodeBalancer (Load Balancer)
resource "linode_nodebalancer" "main" {
  label  = "${local.cluster_name}-lb"
  region = var.region
  
  tags = [for k, v in local.tags : "${k}:${v}"]
}

# NodeBalancer Config for HTTPS
resource "linode_nodebalancer_config" "https" {
  nodebalancer_id = linode_nodebalancer.main.id
  port            = 443
  protocol        = "https"
  algorithm       = var.load_balancer_config.algorithm
  stickiness      = "none"
  
  ssl_cert = linode_domain_record.ssl_cert.name
  ssl_key  = var.ssl_private_key
  
  check          = "http"
  check_path     = var.load_balancer_config.health_check_path
  check_interval = var.load_balancer_config.health_check_interval
  check_timeout  = var.load_balancer_config.health_check_timeout
  check_attempts = var.load_balancer_config.health_check_retries
}

# NodeBalancer Config for HTTP
resource "linode_nodebalancer_config" "http" {
  nodebalancer_id = linode_nodebalancer.main.id
  port            = 80
  protocol        = "http"
  algorithm       = var.load_balancer_config.algorithm
  stickiness      = "none"
  
  check          = "http"
  check_path     = var.load_balancer_config.health_check_path
  check_interval = var.load_balancer_config.health_check_interval
  check_timeout  = var.load_balancer_config.health_check_timeout
  check_attempts = var.load_balancer_config.health_check_retries
}

# Object Storage Bucket
resource "linode_object_storage_bucket" "main" {
  label  = var.storage_config.bucket_name != "" ? var.storage_config.bucket_name : "${var.project_name}-${var.environment}-storage"
  region = var.storage_config.region != "" ? var.storage_config.region : var.region
  
  versioning = var.storage_config.versioning
  
  cors_rule {
    allowed_headers = ["*"]
    allowed_methods = ["GET", "PUT", "POST", "DELETE", "HEAD"]
    allowed_origins = ["*"]
    max_age_seconds = 3000
  }
}

# Object Storage Keys
resource "linode_object_storage_key" "main" {
  label = "${local.cluster_name}-storage-key"
  
  bucket_access {
    bucket_name = linode_object_storage_bucket.main.label
    region      = linode_object_storage_bucket.main.region
    permissions = "read_write"
  }
}

# Domain for SSL certificate
resource "linode_domain" "main" {
  domain    = var.domain_name
  soa_email = "admin@${var.domain_name}"
  type      = "master"
  
  tags = [for k, v in local.tags : "${k}:${v}"]
}

# Domain record for SSL
resource "linode_domain_record" "ssl_cert" {
  domain_id   = linode_domain.main.id
  name        = var.domain_name
  record_type = "A"
  target      = linode_nodebalancer.main.ipv4
  ttl_sec     = 300
}

# Wildcard domain record
resource "linode_domain_record" "wildcard" {
  domain_id   = linode_domain.main.id
  name        = "*"
  record_type = "A"
  target      = linode_nodebalancer.main.ipv4
  ttl_sec     = 300
}

# Firewall
resource "linode_firewall" "main" {
  label = "${local.cluster_name}-firewall"
  
  tags = [for k, v in local.tags : "${k}:${v}"]
  
  # HTTP/HTTPS access
  inbound {
    label    = "allow-http"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "80"
    ipv4     = var.security_config.allowed_ips
  }
  
  inbound {
    label    = "allow-https"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "443"
    ipv4     = var.security_config.allowed_ips
  }
  
  # SSH access (restricted to private networks)
  inbound {
    label    = "allow-ssh"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "22"
    ipv4     = ["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]
  }
  
  # Kubernetes API
  inbound {
    label    = "allow-k8s-api"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "6443"
    ipv4     = var.security_config.allowed_ips
  }
  
  # Allow all outbound
  outbound {
    label    = "allow-all-outbound"
    action   = "ACCEPT"
    protocol = "TCP"
    ports    = "1-65535"
    ipv4     = ["0.0.0.0/0"]
  }
  
  outbound {
    label    = "allow-all-outbound-udp"
    action   = "ACCEPT"
    protocol = "UDP"
    ports    = "1-65535"
    ipv4     = ["0.0.0.0/0"]
  }
  
  outbound {
    label    = "allow-all-outbound-icmp"
    action   = "ACCEPT"
    protocol = "ICMP"
    ipv4     = ["0.0.0.0/0"]
  }
  
  # Apply firewall to LKE nodes
  linodes = [for node in linode_lke_cluster.main.pool[0].nodes : node.instance_id]
}

# Database cluster (if using managed database)
resource "linode_database_mysql" "main" {
  count = var.use_managed_database ? 1 : 0
  
  label           = "${local.cluster_name}-db"
  engine_id       = "mysql/8.0.30"
  region          = var.region
  type            = var.database_size
  cluster_size    = var.database_node_count
  encrypted       = true
  ssl_connection  = true
  
  allow_list = var.security_config.allowed_ips
  
  updates {
    day_of_week   = "sunday"
    duration      = 1
    frequency     = "weekly"
    hour_of_day   = 4
  }
  
  backup {
    enabled = true
    time    = "04:00"
  }
}

# Redis cluster (if using managed Redis) - Note: Linode doesn't have managed Redis, so we'll use a Linode instance
resource "linode_instance" "redis" {
  count = var.use_managed_redis ? 1 : 0
  
  label           = "${local.cluster_name}-redis"
  image           = "linode/ubuntu22.04"
  region          = var.region
  type            = var.redis_size
  authorized_keys = [var.ssh_public_key]
  root_pass       = var.redis_root_password
  
  tags = [for k, v in local.tags : "${k}:${v}"]
  
  # Install and configure Redis
  stackscript_id = linode_stackscript.redis.id
}

# StackScript for Redis installation
resource "linode_stackscript" "redis" {
  label       = "${local.cluster_name}-redis-setup"
  description = "Install and configure Redis server"
  script = base64encode(templatefile("${path.module}/scripts/redis-setup.sh", {
    redis_password = var.redis_password
  }))
  images = ["linode/ubuntu22.04"]
}

# Monitoring alerts (using Linode's monitoring)
resource "linode_instance_config" "monitoring" {
  count       = var.monitoring_enabled ? length(linode_lke_cluster.main.pool[0].nodes) : 0
  linode_id   = linode_lke_cluster.main.pool[0].nodes[count.index].instance_id
  label       = "monitoring-config"
  
  # Enable Linode's monitoring service
  helpers {
    updatedb_disabled = false
    distro            = true
    modules_dep       = true
    network           = true
  }
}
