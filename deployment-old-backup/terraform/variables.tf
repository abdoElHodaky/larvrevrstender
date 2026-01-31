# Terraform Variables for Multi-Cloud Deployment

variable "do_token" {
  description = "DigitalOcean API Token"
  type        = string
  sensitive   = true
  default     = ""
}

variable "linode_token" {
  description = "Linode API Token"
  type        = string
  sensitive   = true
  default     = ""
}

variable "environment" {
  description = "Environment name"
  type        = string
  default     = "production"
  validation {
    condition     = contains(["dev", "staging", "production"], var.environment)
    error_message = "Environment must be dev, staging, or production."
  }
}

variable "cloud_provider" {
  description = "Cloud provider to use"
  type        = string
  default     = "digitalocean"
  validation {
    condition     = contains(["digitalocean", "linode"], var.cloud_provider)
    error_message = "Cloud provider must be digitalocean or linode."
  }
}

variable "region_mapping" {
  description = "Region mapping between cloud providers"
  type = map(object({
    digitalocean = string
    linode       = string
  }))
  default = {
    "europe" = {
      digitalocean = "fra1"
      linode       = "eu-west"
    }
    "us-east" = {
      digitalocean = "nyc1"
      linode       = "us-east"
    }
    "us-west" = {
      digitalocean = "sfo3"
      linode       = "us-west"
    }
    "asia" = {
      digitalocean = "sgp1"
      linode       = "ap-south"
    }
  }
}

variable "node_size_mapping" {
  description = "Node size mapping between cloud providers"
  type = map(object({
    digitalocean = string
    linode       = string
  }))
  default = {
    "small" = {
      digitalocean = "s-2vcpu-4gb"
      linode       = "g6-standard-2"
    }
    "medium" = {
      digitalocean = "s-4vcpu-8gb"
      linode       = "g6-standard-4"
    }
    "large" = {
      digitalocean = "s-8vcpu-16gb"
      linode       = "g6-standard-8"
    }
  }
}

variable "database_size_mapping" {
  description = "Database size mapping between cloud providers"
  type = map(object({
    digitalocean = string
    linode       = string
  }))
  default = {
    "small" = {
      digitalocean = "db-s-2vcpu-4gb"
      linode       = "g6-nanode-1"
    }
    "medium" = {
      digitalocean = "db-s-4vcpu-8gb"
      linode       = "g6-standard-2"
    }
    "large" = {
      digitalocean = "db-s-8vcpu-16gb"
      linode       = "g6-standard-4"
    }
  }
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
  default     = "reverse-tender.com"
}

variable "ssl_email" {
  description = "Email for SSL certificate generation"
  type        = string
  default     = "admin@reverse-tender.com"
}

variable "backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 30
}

variable "monitoring_enabled" {
  description = "Enable monitoring and alerting"
  type        = bool
  default     = true
}

variable "auto_scaling_enabled" {
  description = "Enable auto-scaling for the cluster"
  type        = bool
  default     = true
}

variable "min_nodes" {
  description = "Minimum number of nodes for auto-scaling"
  type        = number
  default     = 2
}

variable "max_nodes" {
  description = "Maximum number of nodes for auto-scaling"
  type        = number
  default     = 10
}

variable "database_backup_enabled" {
  description = "Enable automated database backups"
  type        = bool
  default     = true
}

variable "redis_cluster_enabled" {
  description = "Enable Redis clustering"
  type        = bool
  default     = false
}

variable "load_balancer_algorithm" {
  description = "Load balancer algorithm"
  type        = string
  default     = "round_robin"
  validation {
    condition     = contains(["round_robin", "least_connections", "ip_hash"], var.load_balancer_algorithm)
    error_message = "Load balancer algorithm must be round_robin, least_connections, or ip_hash."
  }
}

