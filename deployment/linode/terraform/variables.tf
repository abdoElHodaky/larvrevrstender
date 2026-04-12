# Linode Terraform Variables for Multi-Cloud Integration

# Core Configuration
variable "linode_token" {
  description = "Linode API token"
  type        = string
  sensitive   = true
}

variable "linode_root_password" {
  description = "Root password for Linode instances"
  type        = string
  sensitive   = true
}

variable "region" {
  description = "Linode region"
  type        = string
  default     = "us-east"
}

variable "environment" {
  description = "Environment name (e.g., dev, staging, prod)"
  type        = string
  default     = "prod"
}

variable "project_name" {
  description = "Name of the project"
  type        = string
  default     = "reverse-tender"
}

# Multi-cloud integration variables
variable "enable_blue_green_deployment" {
  description = "Enable blue-green deployment with Helm"
  type        = bool
  default     = true
}

variable "domain_name" {
  description = "Domain name for the application"
  type        = string
  default     = "reversetender.com"
}

# Object Storage Configuration
variable "object_storage_access_key" {
  description = "Linode Object Storage access key"
  type        = string
  sensitive   = true
}

variable "object_storage_secret_key" {
  description = "Linode Object Storage secret key"
  type        = string
  sensitive   = true
}

# Redis Configuration (External)
variable "redis_host" {
  description = "Redis host (external service)"
  type        = string
  default     = "redis-cluster.reversetender.com"
}

variable "redis_port" {
  description = "Redis port"
  type        = string
  default     = "6379"
}

variable "redis_password" {
  description = "Redis password"
  type        = string
  sensitive   = true
  default     = ""
}

# Storage Configuration
variable "enable_persistent_storage" {
  description = "Enable persistent block storage volumes"
  type        = bool
  default     = true
}

variable "persistent_storage_size" {
  description = "Size of persistent storage volume in GB"
  type        = number
  default     = 20
  
  validation {
    condition     = var.persistent_storage_size >= 10 && var.persistent_storage_size <= 10240
    error_message = "Persistent storage size must be between 10 GB and 10240 GB."
  }
}

# Backup Configuration
variable "backup_retention_days" {
  description = "Number of days to retain backups"
  type        = number
  default     = 30
  
  validation {
    condition     = var.backup_retention_days >= 7 && var.backup_retention_days <= 365
    error_message = "Backup retention days must be between 7 and 365."
  }
}

variable "enable_database_backups" {
  description = "Enable automated database backups"
  type        = bool
  default     = true
}
