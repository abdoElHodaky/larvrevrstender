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

# Laravel Octane Configuration
variable "octane_server" {
  description = "Octane server type (frankenphp, swoole, roadrunner)"
  type        = string
  default     = "frankenphp"
  validation {
    condition     = contains(["frankenphp", "swoole", "roadrunner"], var.octane_server)
    error_message = "Octane server must be one of: frankenphp, swoole, roadrunner."
  }
}

variable "octane_workers_per_service" {
  description = "Default number of Octane workers per service"
  type        = map(number)
  default = {
    development = 2
    staging     = 3
    production  = 4
  }
}

variable "octane_task_workers_per_service" {
  description = "Default number of Octane task workers per service"
  type        = map(number)
  default = {
    development = 2
    staging     = 4
    production  = 6
  }
}

variable "octane_max_requests" {
  description = "Maximum requests per Octane worker before restart"
  type        = map(number)
  default = {
    development = 100
    staging     = 250
    production  = 500
  }
}

variable "octane_memory_limit" {
  description = "Memory limit per Octane worker"
  type        = map(string)
  default = {
    development = "256M"
    staging     = "384M"
    production  = "512M"
  }
}

variable "frankenphp_num_threads" {
  description = "Number of FrankenPHP threads per environment"
  type        = map(number)
  default = {
    development = 2
    staging     = 3
    production  = 4
  }
}

variable "php_opcache_memory" {
  description = "PHP OPcache memory consumption in MB per environment"
  type        = map(number)
  default = {
    development = 128
    staging     = 192
    production  = 256
  }
}

variable "php_opcache_max_files" {
  description = "Maximum number of files in OPcache per environment"
  type        = map(number)
  default = {
    development = 10000
    staging     = 15000
    production  = 20000
  }
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

# Gateway API Configuration
variable "gateway_api_enabled" {
  description = "Enable Gateway API deployment"
  type        = bool
  default     = true
}

variable "gateway_namespace" {
  description = "Kubernetes namespace for Gateway API resources"
  type        = string
  default     = "gateway-system"
}

variable "app_namespace" {
  description = "Kubernetes namespace for application services"
  type        = string
  default     = "default"
}

variable "gateway_api_version" {
  description = "Gateway API version"
  type        = string
  default     = "v1.0.0"
}

variable "gateway_controller_name" {
  description = "Gateway controller name"
  type        = string
  default     = "gateway.networking.k8s.io/gateway-controller"
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
  default     = "reversetender.com"
}

# SSL Configuration
variable "ssl_enabled" {
  description = "Enable SSL/TLS termination"
  type        = bool
  default     = true
}

variable "ssl_certificate_name" {
  description = "Name of the SSL certificate secret"
  type        = string
  default     = "gateway-tls-cert"
}

# CORS Configuration
variable "cors_enabled" {
  description = "Enable CORS"
  type        = bool
  default     = true
}

variable "cors_allowed_origins" {
  description = "Allowed CORS origins"
  type        = list(string)
  default     = ["*"]
}

# Rate Limiting Configuration
variable "rate_limiting_enabled" {
  description = "Enable rate limiting"
  type        = bool
  default     = true
}

variable "rate_limit_requests" {
  description = "Number of requests allowed per window"
  type        = number
  default     = 100
}

variable "rate_limit_window" {
  description = "Rate limit window duration"
  type        = string
  default     = "1m"
}

# Backend TLS Configuration
variable "backend_tls_enabled" {
  description = "Enable TLS for backend communication"
  type        = bool
  default     = false
}

variable "backend_ca_certificate_name" {
  description = "Name of the backend CA certificate ConfigMap"
  type        = string
  default     = "backend-ca-cert"
}

variable "backend_hostname" {
  description = "Backend hostname for TLS verification"
  type        = string
  default     = ""
}

# Gateway High Availability Configuration
variable "high_availability_enabled" {
  description = "Enable high availability mode for Gateway API"
  type        = bool
  default     = true
}

variable "gateway_replicas" {
  description = "Number of gateway replicas"
  type        = number
  default     = 2
}

# Gateway Security Configuration
variable "security_policies_enabled" {
  description = "Enable security policies"
  type        = bool
  default     = true
}

# Gateway Logging Configuration
variable "access_logging_enabled" {
  description = "Enable access logging"
  type        = bool
  default     = true
}

variable "log_level" {
  description = "Log level for Gateway API"
  type        = string
  default     = "info"
  validation {
    condition     = contains(["debug", "info", "warn", "error"], var.log_level)
    error_message = "Log level must be one of: debug, info, warn, error."
  }
}

# Additional Linode-specific variables
variable "redis_password" {
  description = "Redis password for Linode Redis instance"
  type        = string
  default     = ""
  sensitive   = true
}

variable "redis_root_password" {
  description = "Redis instance root password for Linode"
  type        = string
  default     = ""
  sensitive   = true
}

variable "ssh_public_key" {
  description = "SSH public key for Linode instances"
  type        = string
  default     = ""
}

variable "ssl_private_key" {
  description = "SSL private key for Linode NodeBalancer"
  type        = string
  default     = ""
  sensitive   = true
}
