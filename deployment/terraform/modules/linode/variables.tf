# Variables for Linode Module

variable "project_name" {
  description = "Project name"
  type        = string
}

variable "environment" {
  description = "Environment name"
  type        = string
}

variable "region" {
  description = "Linode region"
  type        = string
}

variable "tags" {
  description = "Resource tags"
  type        = map(string)
  default     = {}
}

variable "linode_token" {
  description = "Linode API token"
  type        = string
  sensitive   = true
}

variable "kubernetes_config" {
  description = "Kubernetes configuration"
  type = object({
    version    = string
    node_count = number
    node_type  = string
    min_nodes  = number
    max_nodes  = number
  })
}

variable "network_config" {
  description = "Network configuration"
  type = object({
    vpc_cidr           = string
    private_subnets    = list(string)
    public_subnets     = list(string)
    enable_nat_gateway = bool
  })
}

variable "load_balancer_config" {
  description = "Load balancer configuration"
  type = object({
    algorithm              = string
    health_check_path      = string
    health_check_interval  = number
    health_check_timeout   = number
    health_check_retries   = number
  })
}

variable "storage_config" {
  description = "Storage configuration"
  type = object({
    bucket_name = string
    region      = string
    versioning  = bool
    encryption  = bool
  })
}

variable "security_config" {
  description = "Security configuration"
  type = object({
    allowed_ips        = list(string)
    ssl_certificate    = string
    firewall_enabled   = bool
  })
}

variable "domain_name" {
  description = "Domain name for SSL certificate"
  type        = string
  default     = "reversetender.com"
}

variable "use_managed_database" {
  description = "Use Linode managed database"
  type        = bool
  default     = true
}

variable "database_size" {
  description = "Database instance size"
  type        = string
  default     = "g6-nanode-1"
}

variable "database_node_count" {
  description = "Number of database nodes"
  type        = number
  default     = 1
}

variable "backup_database_name" {
  description = "Database name for backup restore"
  type        = string
  default     = ""
}

variable "backup_created_at" {
  description = "Backup creation timestamp for restore"
  type        = string
  default     = ""
}

variable "use_managed_redis" {
  description = "Use Linode managed Redis (implemented as Linode instance)"
  type        = bool
  default     = true
}

variable "redis_size" {
  description = "Redis instance size"
  type        = string
  default     = "g6-nanode-1"
}

variable "redis_password" {
  description = "Redis password"
  type        = string
  default     = ""
  sensitive   = true
}

variable "redis_root_password" {
  description = "Redis instance root password"
  type        = string
  sensitive   = true
}

variable "ssh_public_key" {
  description = "SSH public key for Redis instance"
  type        = string
  default     = ""
}

variable "ssl_private_key" {
  description = "SSL private key for NodeBalancer"
  type        = string
  sensitive   = true
  default     = ""
}

variable "monitoring_enabled" {
  description = "Enable monitoring alerts"
  type        = bool
  default     = true
}

variable "alert_email" {
  description = "Email for alerts"
  type        = string
  default     = ""
}

variable "slack_channel" {
  description = "Slack channel for alerts"
  type        = string
  default     = "#alerts"
}

variable "slack_webhook_url" {
  description = "Slack webhook URL"
  type        = string
  default     = ""
  sensitive   = true
}

variable "common_outputs" {
  description = "Outputs from common module"
  type        = any
  default     = {}
}
