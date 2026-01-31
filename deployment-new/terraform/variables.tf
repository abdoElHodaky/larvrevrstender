# Variables for Reverse Tender Platform Terraform Configuration

# General Configuration
variable "environment" {
  description = "Environment name (development, staging, production)"
  type        = string
  validation {
    condition     = contains(["development", "staging", "production"], var.environment)
    error_message = "Environment must be one of: development, staging, production."
  }
}

variable "cloud_provider" {
  description = "Cloud provider (digitalocean, linode)"
  type        = string
  validation {
    condition     = contains(["digitalocean", "linode"], var.cloud_provider)
    error_message = "Cloud provider must be one of: digitalocean, linode."
  }
}

variable "region" {
  description = "Cloud provider region"
  type        = string
  default     = "fra1"
}

# Kubernetes Configuration
variable "kubernetes_version" {
  description = "Kubernetes version"
  type        = string
  default     = "1.29"
}

variable "kubernetes_node_count" {
  description = "Number of Kubernetes worker nodes"
  type        = number
  default     = 3
}

variable "kubernetes_node_type" {
  description = "Kubernetes node instance type"
  type        = string
  default     = "s-4vcpu-8gb"
}

variable "kubernetes_min_nodes" {
  description = "Minimum number of nodes for auto-scaling"
  type        = number
  default     = 2
}

variable "kubernetes_max_nodes" {
  description = "Maximum number of nodes for auto-scaling"
  type        = number
  default     = 10
}

# Database Configuration
variable "database_engine" {
  description = "Database engine"
  type        = string
  default     = "mysql"
}

variable "database_version" {
  description = "Database version"
  type        = string
  default     = "8.0"
}

variable "database_instance_class" {
  description = "Database instance class"
  type        = string
  default     = "db-s-2vcpu-4gb"
}

variable "database_storage_size" {
  description = "Database storage size in GB"
  type        = number
  default     = 100
}

variable "database_backup_enabled" {
  description = "Enable database backups"
  type        = bool
  default     = true
}

# Redis Configuration
variable "redis_version" {
  description = "Redis version"
  type        = string
  default     = "7.0"
}

variable "redis_instance_class" {
  description = "Redis instance class"
  type        = string
  default     = "db-s-1vcpu-1gb"
}

variable "redis_node_count" {
  description = "Number of Redis nodes"
  type        = number
  default     = 1
}

# Network Configuration
variable "vpc_cidr" {
  description = "VPC CIDR block"
  type        = string
  default     = "10.10.0.0/16"
}

variable "private_subnets" {
  description = "Private subnet CIDR blocks"
  type        = list(string)
  default     = ["10.10.1.0/24", "10.10.2.0/24"]
}

variable "public_subnets" {
  description = "Public subnet CIDR blocks"
  type        = list(string)
  default     = ["10.10.101.0/24", "10.10.102.0/24"]
}

variable "enable_nat_gateway" {
  description = "Enable NAT gateway for private subnets"
  type        = bool
  default     = true
}

# Load Balancer Configuration
variable "load_balancer_algorithm" {
  description = "Load balancer algorithm"
  type        = string
  default     = "round_robin"
}

variable "health_check_path" {
  description = "Health check path"
  type        = string
  default     = "/health"
}

variable "health_check_interval" {
  description = "Health check interval in seconds"
  type        = number
  default     = 30
}

variable "health_check_timeout" {
  description = "Health check timeout in seconds"
  type        = number
  default     = 5
}

variable "health_check_retries" {
  description = "Health check retries"
  type        = number
  default     = 3
}

# Storage Configuration
variable "storage_bucket_name" {
  description = "Storage bucket name"
  type        = string
  default     = ""
}

variable "storage_region" {
  description = "Storage region"
  type        = string
  default     = ""
}

variable "storage_versioning" {
  description = "Enable storage versioning"
  type        = bool
  default     = true
}

variable "storage_encryption" {
  description = "Enable storage encryption"
  type        = bool
  default     = true
}

# Security Configuration
variable "allowed_ips" {
  description = "Allowed IP addresses for access"
  type        = list(string)
  default     = ["0.0.0.0/0"]
}

variable "ssl_certificate" {
  description = "SSL certificate configuration"
  type        = string
  default     = "lets_encrypt"
}

variable "firewall_enabled" {
  description = "Enable firewall"
  type        = bool
  default     = true
}

# Application Service Replicas
variable "api_gateway_replicas" {
  description = "Number of API Gateway replicas"
  type        = number
  default     = 2
}

variable "auth_service_replicas" {
  description = "Number of Auth Service replicas"
  type        = number
  default     = 2
}

variable "bidding_service_replicas" {
  description = "Number of Bidding Service replicas"
  type        = number
  default     = 3
}

variable "user_service_replicas" {
  description = "Number of User Service replicas"
  type        = number
  default     = 2
}

variable "order_service_replicas" {
  description = "Number of Order Service replicas"
  type        = number
  default     = 2
}

variable "notification_service_replicas" {
  description = "Number of Notification Service replicas"
  type        = number
  default     = 2
}

variable "payment_service_replicas" {
  description = "Number of Payment Service replicas"
  type        = number
  default     = 2
}

variable "analytics_service_replicas" {
  description = "Number of Analytics Service replicas"
  type        = number
  default     = 1
}

variable "vin_ocr_service_replicas" {
  description = "Number of VIN OCR Service replicas"
  type        = number
  default     = 2
}

# Monitoring Configuration
variable "monitoring_enabled" {
  description = "Enable monitoring stack"
  type        = bool
  default     = true
}

variable "alerting_enabled" {
  description = "Enable alerting"
  type        = bool
  default     = true
}

variable "prometheus_retention_days" {
  description = "Prometheus data retention in days"
  type        = number
  default     = 30
}

variable "prometheus_storage_size" {
  description = "Prometheus storage size in GB"
  type        = number
  default     = 50
}

variable "grafana_admin_password" {
  description = "Grafana admin password"
  type        = string
  sensitive   = true
}

variable "grafana_admin_user" {
  description = "Grafana admin username"
  type        = string
  default     = "admin"
}

variable "slack_webhook_url" {
  description = "Slack webhook URL for alerts"
  type        = string
  default     = ""
  sensitive   = true
}

variable "alert_email" {
  description = "Email address for alerts"
  type        = string
  default     = ""
}

# Application Secrets
variable "jwt_secret" {
  description = "JWT secret key"
  type        = string
  sensitive   = true
}

variable "app_key" {
  description = "Application encryption key"
  type        = string
  sensitive   = true
}

variable "twilio_sid" {
  description = "Twilio SID"
  type        = string
  default     = ""
  sensitive   = true
}

variable "twilio_token" {
  description = "Twilio token"
  type        = string
  default     = ""
  sensitive   = true
}

variable "sendgrid_api_key" {
  description = "SendGrid API key"
  type        = string
  default     = ""
  sensitive   = true
}

# Cloud Provider Tokens
variable "digitalocean_token" {
  description = "DigitalOcean API token"
  type        = string
  default     = ""
  sensitive   = true
}

variable "linode_token" {
  description = "Linode API token"
  type        = string
  default     = ""
  sensitive   = true
}

