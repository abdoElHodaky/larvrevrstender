# Multi-Cloud Terraform Configuration for Reverse Tender Platform
# Supports DigitalOcean and Linode deployments

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

# Variables
variable "cloud_provider" {
  description = "Cloud provider to use (digitalocean or linode)"
  type        = string
  default     = "digitalocean"
  validation {
    condition     = contains(["digitalocean", "linode"], var.cloud_provider)
    error_message = "Cloud provider must be either 'digitalocean' or 'linode'."
  }
}

variable "environment" {
  description = "Environment name (dev, staging, production)"
  type        = string
  default     = "production"
}

variable "region" {
  description = "Cloud provider region"
  type        = string
  default     = "fra1" # Frankfurt for DO, eu-west for Linode
}

variable "node_count" {
  description = "Number of worker nodes"
  type        = number
  default     = 3
}

variable "node_size" {
  description = "Size of worker nodes"
  type        = string
  default     = "s-2vcpu-4gb" # DO size, will be mapped for Linode
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
  default     = "reverse-tender.com"
}

# DigitalOcean Provider
provider "digitalocean" {
  count = var.cloud_provider == "digitalocean" ? 1 : 0
  token = var.do_token
}

# Linode Provider
provider "linode" {
  count = var.cloud_provider == "linode" ? 1 : 0
  token = var.linode_token
}

# DigitalOcean Kubernetes Cluster
resource "digitalocean_kubernetes_cluster" "reverse_tender" {
  count   = var.cloud_provider == "digitalocean" ? 1 : 0
  name    = "reverse-tender-${var.environment}"
  region  = var.region
  version = "1.28.2-do.0"

  node_pool {
    name       = "worker-pool"
    size       = var.node_size
    node_count = var.node_count
    auto_scale = true
    min_nodes  = 2
    max_nodes  = 10
    tags       = ["reverse-tender", var.environment]
  }

  tags = ["reverse-tender", var.environment, "kubernetes"]
}

# DigitalOcean Database Cluster
resource "digitalocean_database_cluster" "mysql" {
  count      = var.cloud_provider == "digitalocean" ? 1 : 0
  name       = "reverse-tender-mysql-${var.environment}"
  engine     = "mysql"
  version    = "8"
  size       = "db-s-2vcpu-4gb"
  region     = var.region
  node_count = 2

  tags = ["reverse-tender", var.environment, "database"]
}

# DigitalOcean Redis Cluster
resource "digitalocean_database_cluster" "redis" {
  count      = var.cloud_provider == "digitalocean" ? 1 : 0
  name       = "reverse-tender-redis-${var.environment}"
  engine     = "redis"
  version    = "7"
  size       = "db-s-1vcpu-2gb"
  region     = var.region
  node_count = 1

  tags = ["reverse-tender", var.environment, "cache"]
}

# DigitalOcean Load Balancer
resource "digitalocean_loadbalancer" "reverse_tender" {
  count  = var.cloud_provider == "digitalocean" ? 1 : 0
  name   = "reverse-tender-lb-${var.environment}"
  region = var.region

  forwarding_rule {
    entry_protocol  = "http"
    entry_port      = 80
    target_protocol = "http"
    target_port     = 80
  }

  forwarding_rule {
    entry_protocol  = "https"
    entry_port      = 443
    target_protocol = "http"
    target_port     = 80
    tls_passthrough = false
  }

  healthcheck {
    protocol               = "http"
    port                   = 80
    path                   = "/health"
    check_interval_seconds = 10
    response_timeout_seconds = 5
    unhealthy_threshold    = 3
    healthy_threshold      = 2
  }

  tags = ["reverse-tender", var.environment, "load-balancer"]
}

# Linode Kubernetes Cluster (LKE)
resource "linode_lke_cluster" "reverse_tender" {
  count       = var.cloud_provider == "linode" ? 1 : 0
  label       = "reverse-tender-${var.environment}"
  k8s_version = "1.28"
  region      = var.region
  tags        = ["reverse-tender", var.environment]

  pool {
    type  = "g6-standard-2" # 2 vCPU, 4GB RAM
    count = var.node_count
    autoscaler {
      min = 2
      max = 10
    }
  }
}

# Linode MySQL Database
resource "linode_database_mysql" "reverse_tender" {
  count          = var.cloud_provider == "linode" ? 1 : 0
  label          = "reverse-tender-mysql-${var.environment}"
  engine_id      = "mysql/8.0.30"
  region         = var.region
  type           = "g6-nanode-1"
  cluster_size   = 2
  encrypted      = true
  ssl_connection = true

  allow_list = ["0.0.0.0/0"] # Restrict this in production

  tags = ["reverse-tender", var.environment, "database"]
}

# Kubernetes Provider Configuration
provider "kubernetes" {
  host  = var.cloud_provider == "digitalocean" ? digitalocean_kubernetes_cluster.reverse_tender[0].endpoint : linode_lke_cluster.reverse_tender[0].api_endpoints[0]
  token = var.cloud_provider == "digitalocean" ? digitalocean_kubernetes_cluster.reverse_tender[0].kube_config[0].token : base64decode(linode_lke_cluster.reverse_tender[0].kubeconfig)
  cluster_ca_certificate = var.cloud_provider == "digitalocean" ? base64decode(digitalocean_kubernetes_cluster.reverse_tender[0].kube_config[0].cluster_ca_certificate) : base64decode(linode_lke_cluster.reverse_tender[0].kubeconfig)
}

# Helm Provider Configuration
provider "helm" {
  kubernetes {
    host  = var.cloud_provider == "digitalocean" ? digitalocean_kubernetes_cluster.reverse_tender[0].endpoint : linode_lke_cluster.reverse_tender[0].api_endpoints[0]
    token = var.cloud_provider == "digitalocean" ? digitalocean_kubernetes_cluster.reverse_tender[0].kube_config[0].token : base64decode(linode_lke_cluster.reverse_tender[0].kubeconfig)
    cluster_ca_certificate = var.cloud_provider == "digitalocean" ? base64decode(digitalocean_kubernetes_cluster.reverse_tender[0].kube_config[0].cluster_ca_certificate) : base64decode(linode_lke_cluster.reverse_tender[0].kubeconfig)
  }
}

# Outputs
output "cluster_endpoint" {
  value = var.cloud_provider == "digitalocean" ? digitalocean_kubernetes_cluster.reverse_tender[0].endpoint : linode_lke_cluster.reverse_tender[0].api_endpoints[0]
}

output "database_host" {
  value = var.cloud_provider == "digitalocean" ? digitalocean_database_cluster.mysql[0].host : linode_database_mysql.reverse_tender[0].host
}

output "redis_host" {
  value = var.cloud_provider == "digitalocean" ? digitalocean_database_cluster.redis[0].host : "redis-service.default.svc.cluster.local"
}

output "load_balancer_ip" {
  value = var.cloud_provider == "digitalocean" ? digitalocean_loadbalancer.reverse_tender[0].ip : "Check Linode NodeBalancer"
}

