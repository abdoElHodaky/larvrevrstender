# OpenStack Terraform Variables for Multi-Cloud Integration

# Core OpenStack Configuration
variable "os_username" {
  description = "OpenStack username"
  type        = string
  sensitive   = true
}

variable "os_password" {
  description = "OpenStack password"
  type        = string
  sensitive   = true
}

variable "os_auth_url" {
  description = "OpenStack authentication URL"
  type        = string
}

variable "os_tenant_id" {
  description = "OpenStack tenant ID"
  type        = string
}

variable "os_tenant_name" {
  description = "OpenStack tenant name"
  type        = string
}

variable "os_project_id" {
  description = "OpenStack project ID"
  type        = string
}

variable "os_project_name" {
  description = "OpenStack project name"
  type        = string
}

variable "os_user_domain_name" {
  description = "OpenStack user domain name"
  type        = string
  default     = "Default"
}

variable "os_project_domain_name" {
  description = "OpenStack project domain name"
  type        = string
  default     = "Default"
}

variable "os_region_name" {
  description = "OpenStack region name"
  type        = string
  default     = "RegionOne"
}

# Project Configuration
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

# Network Configuration
variable "floating_network_id" {
  description = "ID of the floating network for load balancer"
  type        = string
}

# Database Configuration (External)
variable "database_host" {
  description = "Database host (external service)"
  type        = string
  default     = "postgres.openstack.local"
}

variable "database_port" {
  description = "Database port"
  type        = string
  default     = "5432"
}

variable "database_name" {
  description = "Database name"
  type        = string
  default     = "reversetender"
}

variable "database_username" {
  description = "Database username"
  type        = string
  sensitive   = true
}

variable "database_password" {
  description = "Database password"
  type        = string
  sensitive   = true
}

# Redis Configuration (External)
variable "redis_host" {
  description = "Redis host (external service)"
  type        = string
  default     = "redis.openstack.local"
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
  description = "Enable persistent Cinder block storage volumes"
  type        = bool
  default     = true
}

variable "persistent_storage_size" {
  description = "Size of persistent storage volume in GB"
  type        = number
  default     = 20
  
  validation {
    condition     = var.persistent_storage_size >= 1 && var.persistent_storage_size <= 1024
    error_message = "Persistent storage size must be between 1 GB and 1024 GB."
  }
}

variable "cinder_volume_type" {
  description = "Cinder volume type"
  type        = string
  default     = "standard"
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

# Heat Stack Configuration
variable "enable_heat_stack" {
  description = "Enable Heat stack for infrastructure orchestration"
  type        = bool
  default     = true
}

variable "heat_stack_name" {
  description = "Name of the Heat stack"
  type        = string
  default     = "reversetender-stack"
}

variable "heat_flavor" {
  description = "Flavor for Heat stack instances"
  type        = string
  default     = "m1.medium"
}

variable "heat_image" {
  description = "Image for Heat stack instances"
  type        = string
  default     = "ubuntu-20.04"
}

variable "heat_key_name" {
  description = "Key pair name for Heat stack instances"
  type        = string
  default     = "reversetender-key"
}

variable "heat_network" {
  description = "Network for Heat stack instances"
  type        = string
  default     = "private-network"
}

variable "heat_security_group" {
  description = "Security group for Heat stack instances"
  type        = string
  default     = "reversetender-sg"
}
